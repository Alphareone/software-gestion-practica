<?php

class ActivityLogModel extends Model {

    public function insert($userId, $action, $description, $ipAddress = null) {
        return $this->insertGetId(
            'INSERT INTO activity_logs (user_id, action, description, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())',
            [$userId, $action, $description, $ipAddress]
        );
    }

    public function findAll($page = 1, $perPage = 50, $search = '', $actionFilter = '', $userId = null) {
        $where = '1=1';
        $params = [];

        if ($search) {
            $where .= ' AND (u.username LIKE ? OR a.description LIKE ?';
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
            if (is_numeric($search)) {
                $where .= ' OR a.id = ?';
                $params[] = (int) $search;
            }
            $where .= ')';
        }

        if ($actionFilter) {
            $where .= ' AND a.action = ?';
            $params[] = $actionFilter;
        }

        if ($userId !== null) {
            $where .= ' AND a.user_id = ?';
            $params[] = (int) $userId;
        }

        $total = (int) $this->value(
            "SELECT COUNT(*) FROM activity_logs a LEFT JOIN users u ON a.user_id = u.id WHERE $where",
            $params
        );

        $offset = ($page - 1) * $perPage;
        $items = $this->rows(
            "SELECT a.*, u.username 
             FROM activity_logs a 
             LEFT JOIN users u ON a.user_id = u.id 
             WHERE $where 
             ORDER BY a.created_at DESC 
             LIMIT $perPage OFFSET $offset",
            $params
        );

        return ['total' => $total, 'items' => $items, 'page' => $page, 'per_page' => $perPage];
    }

    public function getDistinctUsers() {
        return $this->rows(
            'SELECT DISTINCT a.user_id, u.username
             FROM activity_logs a
             INNER JOIN users u ON a.user_id = u.id
             WHERE a.user_id IS NOT NULL
             ORDER BY u.username ASC'
        );
    }

    public function getDistinctActions() {
        return $this->rows('SELECT DISTINCT action FROM activity_logs ORDER BY action');
    }

    public function purgeOld($months) {
        return $this->execute(
            'DELETE FROM activity_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? MONTH)',
            [$months]
        );
    }

    public function countRecent($userId) {
        return (int) $this->value(
            'SELECT COUNT(*) FROM activity_logs WHERE user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)',
            [$userId]
        );
    }

    public function getTodayCount() {
        return (int) $this->value(
            "SELECT COUNT(*) FROM activity_logs WHERE DATE(created_at) = CURDATE()"
        );
    }

    public function getDateRange() {
        return $this->row(
            "SELECT MIN(created_at) AS first_date, MAX(created_at) AS last_date FROM activity_logs"
        );
    }

    public function getTopAction() {
        return $this->row(
            "SELECT action, COUNT(*) AS cnt FROM activity_logs GROUP BY action ORDER BY cnt DESC LIMIT 1"
        );
    }
}
