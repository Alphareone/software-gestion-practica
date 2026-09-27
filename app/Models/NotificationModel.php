<?php

class NotificationModel extends Model {

    public function findByUser($userId, $page = 1, $perPage = 20, $unreadOnly = false) {
        $offset = ($page - 1) * $perPage;
        $where = 'n.user_id = ?';
        $params = [$userId];

        if ($unreadOnly) {
            $where .= ' AND n.is_read = 0';
        }

        return [
            'total' => (int) $this->value("SELECT COUNT(*) as cnt FROM notifications n WHERE $where", $params),
            'items' => $this->rows(
                "SELECT n.id, n.type, n.title, n.message, n.product_id, n.is_read, n.created_at
                 FROM notifications n WHERE $where
                 ORDER BY n.created_at DESC LIMIT $perPage OFFSET $offset",
                $params
            ),
        ];
    }

    public function countUnread($userId) {
        return (int) $this->value('SELECT COUNT(*) as cnt FROM notifications WHERE user_id = ? AND is_read = 0', [$userId]);
    }

    public function markRead($id, $userId) {
        return $this->execute('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?', [$id, $userId]);
    }

    public function markAllRead($userId) {
        return $this->execute('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0', [$userId]);
    }

    public function deleteById($id, $userId) {
        return $this->execute('DELETE FROM notifications WHERE id = ? AND user_id = ?', [$id, $userId]);
    }

    public function deleteAllForUser($userId) {
        return $this->execute('DELETE FROM notifications WHERE user_id = ?', [$userId]);
    }

    public function deleteAll() {
        return $this->execute('TRUNCATE TABLE notifications');
    }

    public function purgeOld($days = 30) {
        $stmt = $this->query('SELECT COUNT(*) as cnt FROM notifications WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)');
        $stmt->execute([$days]);
        $count = (int) $stmt->fetch()->cnt;
        $this->execute('DELETE FROM notifications WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)', [$days]);
        return $count;
    }

    public function findExisting($userId, $productId, $type) {
        return $this->row(
            'SELECT id, message FROM notifications WHERE user_id = ? AND product_id = ? AND type = ? AND is_read = 0',
            [$userId, $productId, $type]
        );
    }

    public function create($userId, $type, $title, $message, $productId, $storeId) {
        return $this->insertGetId(
            'INSERT INTO notifications (user_id, type, title, message, product_id, ml_connection_id) VALUES (?, ?, ?, ?, ?, ?)',
            [$userId, $type, $title, $message, $productId, $storeId]
        );
    }

    public function updateMessage($id, $message) {
        return $this->execute('UPDATE notifications SET message = ?, created_at = NOW() WHERE id = ?', [$message, $id]);
    }
}
