<?php

/**
 * Sincroniza órdenes de Walmart hacia walmart_orders/walmart_order_lines.
 *
 * Primera integración de la Orders API de Walmart en este proyecto — no
 * probada aún contra una cuenta real (a diferencia de items/inventory).
 * Por eso cada campo se extrae de forma defensiva (con `??` y fallbacks)
 * y cualquier orden que no se logre parsear queda registrada en el log
 * con su JSON crudo completo, en vez de fallar silenciosamente o cortar
 * todo el sync por una orden con forma inesperada.
 */
class WalmartOrderSyncService {

    private $db;
    private $api;
    private $orderModel;

    const PAGE_SIZE = 100;

    public function __construct($db) {
        $this->db = $db;
        $this->api = new WalmartApiService($db);
        $this->orderModel = new WalmartOrderModel($db);
    }

    /**
     * Sincroniza las órdenes de los últimos $daysBack días para una tienda.
     * Retorna ['ok', 'synced' => n, 'failed' => n, 'error' => ?string].
     */
    public function syncStore($connectionId, $daysBack = 30) {
        $startDate = (new DateTime("-{$daysBack} days"))->format('Y-m-d\TH:i:s.000\Z');
        $endDate = (new DateTime())->format('Y-m-d\TH:i:s.000\Z');

        $synced = 0;
        $failed = 0;
        $cursor = null;
        $pages = 0;

        do {
            $page = $this->api->getOrdersPage($connectionId, $startDate, $endDate, self::PAGE_SIZE, $cursor);
            if ($page['code'] !== 200) {
                return ['ok' => false, 'synced' => $synced, 'failed' => $failed, 'error' => 'Walmart respondió HTTP ' . $page['code'] . ' al listar órdenes.'];
            }

            foreach ($page['orders'] as $order) {
                try {
                    $this->persistOrder($connectionId, $order);
                    $synced++;
                } catch (Exception $e) {
                    $failed++;
                    error_log('[WALMART-ORDERS] Error al procesar orden: ' . $e->getMessage() . ' | raw=' . json_encode($order));
                }
            }

            $cursor = $page['nextCursor'];
            $pages++;
        } while ($cursor && $pages < 200); // límite defensivo contra loops infinitos si el cursor no avanza

        return ['ok' => true, 'synced' => $synced, 'failed' => $failed, 'error' => null];
    }

    private function persistOrder($connectionId, $order) {
        $purchaseOrderId = $order->purchaseOrderId ?? null;
        if (!$purchaseOrderId) {
            throw new Exception('Orden sin purchaseOrderId, no se puede identificar.');
        }

        $customerOrderId = $order->customerOrderId ?? null;
        $orderDate = $this->parseDate($order->orderDate ?? null);

        $rawLines = $order->orderLines->orderLine ?? [];
        if (!is_array($rawLines)) $rawLines = [$rawLines]; // Walmart a veces devuelve objeto único en vez de array cuando hay 1 sola línea

        $lines = [];
        $totalAmount = 0;
        $currency = 'CLP';
        $status = null;

        foreach ($rawLines as $line) {
            $sku = $line->item->sku ?? null;
            $productName = $line->item->productName ?? null;
            $quantity = (int) ($line->orderLineQuantity->amount ?? 1);

            $unitPrice = $this->extractLinePrice($line);
            if ($unitPrice !== null) $currency = $this->extractLineCurrency($line) ?? $currency;

            $lineStatus = $this->extractLineStatus($line);
            if ($status === null) $status = $lineStatus;

            $lines[] = [
                'line_number' => (string) ($line->lineNumber ?? ''),
                'sku' => $sku,
                'product_name' => $productName,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'line_status' => $lineStatus,
            ];

            if ($unitPrice !== null) $totalAmount += $unitPrice * $quantity;
        }

        $rawJson = json_encode($order, JSON_UNESCAPED_UNICODE);
        $orderId = $this->orderModel->upsertOrder(
            $connectionId, $purchaseOrderId, $customerOrderId, $orderDate, $status, $totalAmount, $currency, $rawJson
        );

        if ($orderId) {
            $this->orderModel->replaceOrderLines($orderId, $lines);
        }
    }

    /** Walmart puede entregar la fecha en epoch millis o como string ISO — se prueban ambas formas. */
    private function parseDate($raw) {
        if ($raw === null) return null;
        if (is_numeric($raw)) {
            $seconds = ((int) $raw) > 9999999999 ? ((int) $raw) / 1000 : (int) $raw; // millis vs segundos
            return (new DateTime('@' . (int) $seconds))->format('Y-m-d H:i:s');
        }
        try {
            return (new DateTime((string) $raw))->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            return null;
        }
    }

    /** Busca el precio del ítem entre los distintos "charges" de la línea — el nombre exacto del cargo puede variar. */
    private function extractLinePrice($line) {
        $charges = $line->charges->charge ?? null;
        if ($charges === null) return null;
        if (!is_array($charges)) $charges = [$charges];

        foreach ($charges as $charge) {
            $type = strtoupper($charge->chargeType ?? '');
            $name = strtoupper($charge->chargeName ?? '');
            if ($type === 'PRODUCT' || strpos($name, 'ITEMPRICE') !== false || strpos($name, 'PRICE') !== false) {
                if (isset($charge->chargeAmount->amount)) return (float) $charge->chargeAmount->amount;
            }
        }
        // Fallback: primer cargo con monto, lo que sea
        foreach ($charges as $charge) {
            if (isset($charge->chargeAmount->amount)) return (float) $charge->chargeAmount->amount;
        }
        return null;
    }

    private function extractLineCurrency($line) {
        $charges = $line->charges->charge ?? null;
        if ($charges === null) return null;
        if (!is_array($charges)) $charges = [$charges];
        foreach ($charges as $charge) {
            if (isset($charge->chargeAmount->currency)) return $charge->chargeAmount->currency;
        }
        return null;
    }

    private function extractLineStatus($line) {
        $statuses = $line->orderLineStatuses->orderLineStatus ?? null;
        if ($statuses === null) return null;
        if (!is_array($statuses)) $statuses = [$statuses];
        return $statuses[0]->status ?? null;
    }
}
