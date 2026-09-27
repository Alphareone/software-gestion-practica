<?php

/**
 * Agrega datos ya existentes en el sistema (ml_products_cache,
 * walmart_products_cache, auditorias, auditorias_walmart) en reportes
 * listos para mostrar en pantalla o exportar a Excel.
 *
 * No incluye ventas: no existe ninguna fuente de datos de órdenes/ventas
 * en el sistema todavía (ver conversación de diseño). Cuando esa
 * integración exista, este servicio es el lugar natural para sumarla.
 */
class ReportService {

    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    // ── Inventario ──

    /** Resumen por tienda, ambos canales: total, con stock, sin stock, precio promedio, último sync. */
    public function inventorySummary() {
        $rows = [];

        $stmt = $this->db->query(
            "SELECT c.id, c.store_name, c.ml_nickname,
                    COUNT(p.id) as total,
                    SUM(CASE WHEN p.available_quantity > 0 THEN 1 ELSE 0 END) as with_stock,
                    SUM(CASE WHEN p.available_quantity = 0 OR p.available_quantity IS NULL THEN 1 ELSE 0 END) as without_stock,
                    AVG(p.price) as avg_price,
                    MAX(p.synced_at) as last_sync
             FROM ml_connections c
             LEFT JOIN ml_products_cache p ON p.ml_connection_id = c.id
             WHERE c.is_active = 1
             GROUP BY c.id, c.store_name, c.ml_nickname"
        );
        $stmt->execute();
        foreach ($stmt->fetchAll() as $r) {
            $rows[] = [
                'channel' => 'ML', 'connection_id' => (int) $r->id,
                'store_name' => $r->store_name ?: $r->ml_nickname ?: 'Sin nombre',
                'total' => (int) $r->total, 'with_stock' => (int) $r->with_stock, 'without_stock' => (int) $r->without_stock,
                'avg_price' => round((float) $r->avg_price, 0), 'last_sync' => $r->last_sync,
            ];
        }

        $stmt = $this->db->query(
            "SELECT c.id, c.store_name,
                    COUNT(p.id) as total,
                    SUM(CASE WHEN p.available_quantity > 0 THEN 1 ELSE 0 END) as with_stock,
                    SUM(CASE WHEN p.available_quantity = 0 OR p.available_quantity IS NULL THEN 1 ELSE 0 END) as without_stock,
                    AVG(p.price) as avg_price,
                    MAX(p.synced_at) as last_sync
             FROM walmart_connections c
             LEFT JOIN walmart_products_cache p ON p.walmart_connection_id = c.id
             WHERE c.is_active = 1
             GROUP BY c.id, c.store_name"
        );
        $stmt->execute();
        foreach ($stmt->fetchAll() as $r) {
            $rows[] = [
                'channel' => 'Walmart', 'connection_id' => (int) $r->id,
                'store_name' => $r->store_name ?: 'Sin nombre',
                'total' => (int) $r->total, 'with_stock' => (int) $r->with_stock, 'without_stock' => (int) $r->without_stock,
                'avg_price' => round((float) $r->avg_price, 0), 'last_sync' => $r->last_sync,
            ];
        }

        return $rows;
    }

    /** Detalle plano de inventario para exportar (ambos canales o uno filtrado). */
    public function inventoryDetail($channel = 'all') {
        $rows = [];

        if ($channel === 'all' || $channel === 'ML') {
            $stmt = $this->db->query(
                "SELECT c.store_name, p.sku, p.title, p.price, p.available_quantity, p.status, p.synced_at
                 FROM ml_products_cache p
                 JOIN ml_connections c ON c.id = p.ml_connection_id
                 WHERE c.is_active = 1 ORDER BY c.store_name, p.sku"
            );
            $stmt->execute();
            foreach ($stmt->fetchAll() as $r) {
                $rows[] = ['channel' => 'ML', 'store_name' => $r->store_name, 'sku' => $r->sku, 'title' => $r->title, 'price' => $r->price, 'stock' => $r->available_quantity, 'status' => $r->status, 'synced_at' => $r->synced_at];
            }
        }

        if ($channel === 'all' || $channel === 'Walmart') {
            $stmt = $this->db->query(
                "SELECT c.store_name, p.sku, p.title, p.price, p.available_quantity, p.published_status as status, p.synced_at
                 FROM walmart_products_cache p
                 JOIN walmart_connections c ON c.id = p.walmart_connection_id
                 WHERE c.is_active = 1 ORDER BY c.store_name, p.sku"
            );
            $stmt->execute();
            foreach ($stmt->fetchAll() as $r) {
                $rows[] = ['channel' => 'Walmart', 'store_name' => $r->store_name, 'sku' => $r->sku, 'title' => $r->title, 'price' => $r->price, 'stock' => $r->available_quantity, 'status' => $r->status, 'synced_at' => $r->synced_at];
            }
        }

        return $rows;
    }

    // ── Auditorías ──

    /** Resumen agregado por canal y tipo: cuántos batches, SKUs, tasa de coincidencia. */
    public function auditsSummary() {
        $rows = [];

        foreach ([['auditorias', 'ML'], ['auditorias_walmart', 'Walmart']] as [$table, $channel]) {
            $stmt = $this->db->query(
                "SELECT audit_type,
                        COUNT(DISTINCT batch_id) as total_batches,
                        COUNT(*) as total_skus,
                        SUM(CASE WHEN diff_amount = 0 THEN 1 ELSE 0 END) as matches,
                        SUM(CASE WHEN diff_amount != 0 AND diff_amount IS NOT NULL THEN 1 ELSE 0 END) as mismatches,
                        MAX(audit_date) as last_audit
                 FROM {$table}
                 GROUP BY audit_type"
            );
            $stmt->execute();
            foreach ($stmt->fetchAll() as $r) {
                $total = (int) $r->total_skus;
                $rows[] = [
                    'channel' => $channel, 'audit_type' => $r->audit_type,
                    'total_batches' => (int) $r->total_batches, 'total_skus' => $total,
                    'matches' => (int) $r->matches, 'mismatches' => (int) $r->mismatches,
                    'match_rate' => $total > 0 ? round(((int) $r->matches / $total) * 100, 1) : null,
                    'last_audit' => $r->last_audit,
                ];
            }
        }

        return $rows;
    }

    /** SKUs con diferencias recurrentes (aparecen con diff != 0 en más de un batch) — señal de un problema estructural, no un error puntual. */
    public function recurringMismatches($limit = 20) {
        $rows = [];

        foreach ([['auditorias', 'ML'], ['auditorias_walmart', 'Walmart']] as [$table, $channel]) {
            $safeLimit = (int) $limit;
            $stmt = $this->db->query(
                "SELECT sku, audit_type, COUNT(DISTINCT batch_id) as times_seen, MAX(audit_date) as last_seen
                 FROM {$table}
                 WHERE diff_amount != 0 AND diff_amount IS NOT NULL AND sku IS NOT NULL AND sku != ''
                 GROUP BY sku, audit_type
                 HAVING times_seen > 1
                 ORDER BY times_seen DESC, last_seen DESC
                 LIMIT {$safeLimit}"
            );
            $stmt->execute();
            foreach ($stmt->fetchAll() as $r) {
                $rows[] = ['channel' => $channel, 'sku' => $r->sku, 'audit_type' => $r->audit_type, 'times_seen' => (int) $r->times_seen, 'last_seen' => $r->last_seen];
            }
        }

        usort($rows, function ($a, $b) { return $b['times_seen'] <=> $a['times_seen']; });
        return array_slice($rows, 0, $limit);
    }

    // ── Ventas (Walmart) ──
    // Nota: solo Walmart por ahora — confirmado con el líder técnico que
    // las ventas se obtienen desde su API. Si más adelante se agrega el
    // mismo dato para ML, esta es la capa donde se sumarían ambos canales,
    // igual que ya se hace en inventoryDetail()/priceSnapshot().

    public function salesSummary($dateFrom, $dateTo) {
        $connections = (new WalmartConnectionModel($this->db))->findActive();
        $ids = array_map(function ($c) { return (int) $c->id; }, $connections);

        $orderModel = new WalmartOrderModel($this->db);
        return [
            'by_store' => $orderModel->getSalesSummary($ids, $dateFrom, $dateTo),
            'top_products' => $orderModel->getTopProducts($ids, $dateFrom, $dateTo, 20),
            'daily' => $orderModel->getDailySales($ids, $dateFrom, $dateTo),
        ];
    }

    public function salesSyncStatus() {
        $connections = (new WalmartConnectionModel($this->db))->findActive();
        $orderModel = new WalmartOrderModel($this->db);
        $result = [];
        foreach ($connections as $c) {
            $status = $orderModel->getTotalOrders((int) $c->id);
            $result[] = ['store_name' => $c->store_name, 'connection_id' => (int) $c->id] + $status;
        }
        return $result;
    }

    // ── Precios ──

    /** Snapshot de precios actuales, ambos canales — mismo dataset que inventoryDetail pero pensado para comparar precio entre canales. */
    public function priceSnapshot() {
        return $this->inventoryDetail('all');
    }
}
