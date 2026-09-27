<?php

class NotificationService {
    private $db;
    private $notification;
    private $setting;

    public function __construct($db) {
        $this->db = $db;
        $this->notification = new NotificationModel($db);
        $this->setting = new AppSettingModel($db);
    }

    public function getNotifications($userId, $page = 1, $perPage = 20, $unreadOnly = false) {
        $result = $this->notification->findByUser($userId, $page, $perPage, $unreadOnly);
        $offset = ($page - 1) * $perPage;

        $notifications = [];
        foreach ($result['items'] as $r) {
            $notifications[] = [
                'id' => (int) $r->id,
                'type' => $r->type,
                'title' => $r->title,
                'message' => $r->message,
                'product_id' => $r->product_id ? (int) $r->product_id : null,
                'is_read' => (bool) $r->is_read,
                'created_at' => $r->created_at,
            ];
        }

        return [
            'notifications' => $notifications,
            'total' => $result['total'],
            'page' => $page,
            'has_more' => ($offset + $perPage) < $result['total'],
        ];
    }

    public function getUnreadCount($userId) {
        return $this->notification->countUnread($userId);
    }

    public function markRead($id, $userId) {
        $this->notification->markRead($id, $userId);
    }

    public function markAllRead($userId) {
        $this->notification->markAllRead($userId);
    }

    public function delete($id, $userId) {
        return $this->notification->deleteById($id, $userId);
    }

    public function deleteAllForUser($userId) {
        return $this->notification->deleteAllForUser($userId);
    }

    public function deleteAll() {
        return $this->notification->deleteAll();
    }

    public function purgeOld($days = 30) {
        return $this->notification->purgeOld($days);
    }

    public function createNotification($userId, $type, $title, $message, $productId = null, $storeId = null) {
        return $this->notification->create($userId, $type, $title, $message, $productId, $storeId);
    }

    public function checkAndNotifyLowStock($userId, $productId, $sku, $name, $stock, $storeId) {
        $threshold = $this->setting->getInt('low_stock_threshold', 5);
        if ($threshold < 1) $threshold = 1;
        if ($stock < 0 || $stock >= $threshold) return null;

        $title = $sku . ' — ' . $name;
        $msg = 'Stock: ' . (int) $stock . ' unidades';

        $existing = $this->notification->findExisting($userId, $productId, 'low_stock');
        if ($existing) {
            if ($existing->message !== $msg) {
                $this->notification->updateMessage($existing->id, $msg);
            }
            return 'updated';
        }
        $this->createNotification($userId, 'low_stock', $title, $msg, $productId, $storeId);
        return 'created';
    }

    public function notifyPriceBatchDone($userId, $storeId, $ok, $errors) {
        $title = $errors === 0 ? 'Lote de precios completado' : 'Lote de precios con errores';
        $msg = $errors === 0
            ? "{$ok} precios actualizados correctamente"
            : "{$ok} OK, {$errors} errores";
        $this->createNotification($userId, 'price_batch', $title, $msg, null, $storeId);
    }

    public function notifyImportWarning($userId, $storeId, $totalErrors) {
        $this->createNotification(
            $userId, 'import_warning', 'Advertencia en importación',
            "{$totalErrors} filas con errores de validación", null, $storeId
        );
    }

    public function notifySyncComplete($userId, $storeId, $processed, $total) {
        $this->createNotification(
            $userId, 'sync_complete', 'Sincronización completada',
            "{$processed} de {$total} productos procesados", null, $storeId
        );
    }

    public function notifyUserCreated($userId, $newUsername) {
        $this->createNotification(
            $userId, 'user_created', 'Usuario creado',
            "El usuario '{$newUsername}' fue creado exitosamente"
        );
    }

    public function notifyProductFallbackWarning($userId, $sku, $productTitle, array $usedFallbacks) {
        $titleNotif = "Inconsistencia en Ficha SKU: " . $sku;
        $msg = "El producto '{$productTitle}' se procesó con valores de respaldo automáticos debido a atributos faltantes en el Maestro: " . implode(', ', $usedFallbacks) . ". Favor completar la ficha técnica.";
        $this->createNotification($userId, 'product_fallback', $titleNotif, $msg);
    }

    public function notifyStockSyncProtection($userId, $sku, $mlQty, $cencoTargetStock) {
        $titleNotif = "Protección de Stock Crítico (SKU: {$sku})";
        $msg = "Stock detectado en Mercado Libre: {$mlQty} unidades. Por regla de seguridad (<= 5 unidades), se fijó automáticamente el stock de Cencosud/Paris.cl en {$cencoTargetStock} para evitar quiebres.";
        $this->createNotification($userId, 'stock_protection', $titleNotif, $msg);
    }
}

