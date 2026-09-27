<?php

class WalmartOrderModel extends Model {

    /** Inserta o actualiza una orden (idempotente por purchase_order_id + tienda). */
    public function upsertOrder($connectionId, $purchaseOrderId, $customerOrderId, $orderDate, $status, $totalAmount, $currency, $rawJson) {
        $this->query(
            'INSERT INTO walmart_orders
                (walmart_connection_id, purchase_order_id, customer_order_id, order_date, order_status, total_amount, currency, raw_data)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                customer_order_id = VALUES(customer_order_id),
                order_date = VALUES(order_date),
                order_status = VALUES(order_status),
                total_amount = VALUES(total_amount),
                currency = VALUES(currency),
                raw_data = VALUES(raw_data)'
        )->execute([$connectionId, $purchaseOrderId, $customerOrderId, $orderDate, $status, $totalAmount, $currency, $rawJson]);

        $row = $this->row('SELECT id FROM walmart_orders WHERE walmart_connection_id = ? AND purchase_order_id = ?', [$connectionId, $purchaseOrderId]);
        return $row ? (int) $row->id : null;
    }

    /** Reemplaza las líneas de una orden (se borran y se re-insertan, más simple que hacer diff). */
    public function replaceOrderLines($walmartOrderId, array $lines) {
        $this->query('DELETE FROM walmart_order_lines WHERE walmart_order_id = ?')->execute([$walmartOrderId]);

        foreach ($lines as $line) {
            $this->query(
                'INSERT INTO walmart_order_lines (walmart_order_id, line_number, sku, product_name, quantity, unit_price, line_status)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $walmartOrderId, $line['line_number'], $line['sku'], $line['product_name'],
                $line['quantity'], $line['unit_price'], $line['line_status'],
            ]);
        }
    }

    /** Resumen de ventas por tienda + rango de fechas — para el reporte. */
    public function getSalesSummary($connectionIds, $dateFrom, $dateTo) {
        if (empty($connectionIds)) return [];
        $placeholders = implode(',', array_fill(0, count($connectionIds), '?'));

        $stmt = $this->query(
            "SELECT c.store_name,
                    COUNT(DISTINCT o.id) as total_orders,
                    SUM(ol.quantity) as total_units,
                    SUM(ol.quantity * ol.unit_price) as total_revenue
             FROM walmart_orders o
             JOIN walmart_connections c ON c.id = o.walmart_connection_id
             LEFT JOIN walmart_order_lines ol ON ol.walmart_order_id = o.id
             WHERE o.walmart_connection_id IN ({$placeholders})
               AND o.order_date BETWEEN ? AND ?
             GROUP BY c.store_name
             ORDER BY total_revenue DESC"
        );
        $stmt->execute(array_merge($connectionIds, [$dateFrom, $dateTo]));
        return $stmt->fetchAll();
    }

    /** Productos más vendidos en el rango — para el reporte. */
    public function getTopProducts($connectionIds, $dateFrom, $dateTo, $limit = 20) {
        if (empty($connectionIds)) return [];
        $placeholders = implode(',', array_fill(0, count($connectionIds), '?'));
        $safeLimit = (int) $limit;

        $stmt = $this->query(
            "SELECT ol.sku, ol.product_name,
                    SUM(ol.quantity) as total_units,
                    SUM(ol.quantity * ol.unit_price) as total_revenue
             FROM walmart_order_lines ol
             JOIN walmart_orders o ON o.id = ol.walmart_order_id
             WHERE o.walmart_connection_id IN ({$placeholders})
               AND o.order_date BETWEEN ? AND ?
             GROUP BY ol.sku, ol.product_name
             ORDER BY total_revenue DESC
             LIMIT {$safeLimit}"
        );
        $stmt->execute(array_merge($connectionIds, [$dateFrom, $dateTo]));
        return $stmt->fetchAll();
    }

    /** Ventas por día — para graficar tendencia. */
    public function getDailySales($connectionIds, $dateFrom, $dateTo) {
        if (empty($connectionIds)) return [];
        $placeholders = implode(',', array_fill(0, count($connectionIds), '?'));

        $stmt = $this->query(
            "SELECT DATE(o.order_date) as day,
                    SUM(ol.quantity * ol.unit_price) as total_revenue,
                    COUNT(DISTINCT o.id) as total_orders
             FROM walmart_orders o
             LEFT JOIN walmart_order_lines ol ON ol.walmart_order_id = o.id
             WHERE o.walmart_connection_id IN ({$placeholders})
               AND o.order_date BETWEEN ? AND ?
             GROUP BY DATE(o.order_date)
             ORDER BY day ASC"
        );
        $stmt->execute(array_merge($connectionIds, [$dateFrom, $dateTo]));
        return $stmt->fetchAll();
    }

    public function getTotalOrders($connectionId) {
        $row = $this->row('SELECT COUNT(*) as cnt, MAX(order_date) as last_order FROM walmart_orders WHERE walmart_connection_id = ?', [$connectionId]);
        return $row ? ['count' => (int) $row->cnt, 'last_order' => $row->last_order] : ['count' => 0, 'last_order' => null];
    }
}
