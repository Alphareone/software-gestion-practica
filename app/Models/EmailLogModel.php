<?php

class EmailLogModel extends Model {

    public function log($recipientEmail, $recipientUser, $template, $subject, $status, $userId = null, $senderId = null, $metadata = null) {
        return $this->insertGetId(
            'INSERT INTO email_logs (recipient_email, recipient_user, template, subject, status, user_id, sender_id, metadata, sent_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())',
            [$recipientEmail, $recipientUser, $template, $subject, $status, $userId, $senderId, $metadata]
        );
    }

    public function findAll($page = 1, $perPage = 50, $search = '', $templateFilter = '') {
        $where = '1=1';
        $params = [];

        if ($search) {
            $where .= ' AND (recipient_email LIKE ? OR recipient_user LIKE ?)';
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
        }

        if ($templateFilter) {
            $where .= ' AND template = ?';
            $params[] = $templateFilter;
        }

        $total = (int) $this->value(
            "SELECT COUNT(*) FROM email_logs WHERE $where",
            $params
        );

        $offset = ($page - 1) * $perPage;
        $items = $this->rows(
            "SELECT * FROM email_logs WHERE $where ORDER BY sent_at DESC LIMIT $perPage OFFSET $offset",
            $params
        );

        return ['total' => $total, 'items' => $items, 'page' => $page, 'per_page' => $perPage];
    }

    public function getDistinctTemplates() {
        return $this->rows('SELECT DISTINCT template FROM email_logs ORDER BY template');
    }

    public function getTodayCount() {
        return (int) $this->value("SELECT COUNT(*) FROM email_logs WHERE DATE(sent_at) = CURDATE()");
    }

    public function purgeAll() {
        $stmt = $this->query('SELECT COUNT(*) as cnt FROM email_logs');
        $stmt->execute();
        $count = (int) $stmt->fetch()->cnt;
        $this->execute('TRUNCATE TABLE email_logs');
        return $count;
    }
}
