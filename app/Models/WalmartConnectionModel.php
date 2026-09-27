<?php

class WalmartConnectionModel extends Model {

    public function findById($id) {
        return $this->row('SELECT * FROM walmart_connections WHERE id = ?', [$id]);
    }

    public function findAll() {
        return $this->rows(
            'SELECT id, user_id, store_name, client_id, channel_type, api_base_url, market, token_expires_at, is_active, created_at FROM walmart_connections ORDER BY created_at DESC'
        );
    }

    public function findActive() {
        return $this->rows(
            'SELECT id, user_id, store_name, client_id, channel_type, api_base_url, market, token_expires_at, is_active, created_at FROM walmart_connections WHERE is_active = 1 ORDER BY created_at DESC'
        );
    }

    public function findStoreName($id) {
        $row = $this->row('SELECT store_name FROM walmart_connections WHERE id = ?', [$id]);
        return $row ? $row->store_name : '';
    }

    public function findByClientId($clientId) {
        return $this->row('SELECT id FROM walmart_connections WHERE client_id = ?', [$clientId]);
    }

    public function create($userId, $storeName, $clientId, $encryptedSecret, $channelType, $apiBaseUrl, $market = 'cl') {
        return $this->insertGetId(
            'INSERT INTO walmart_connections (user_id, store_name, client_id, client_secret, channel_type, api_base_url, market) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$userId, $storeName, $clientId, $encryptedSecret, $channelType, $apiBaseUrl, $market]
        );
    }

    public function updateCredentials($id, $clientId, $encryptedSecret, $channelType, $apiBaseUrl) {
        return $this->execute(
            'UPDATE walmart_connections SET client_id = ?, client_secret = ?, channel_type = ?, api_base_url = ?, access_token = NULL, token_expires_at = NULL, is_active = 1 WHERE id = ?',
            [$clientId, $encryptedSecret, $channelType, $apiBaseUrl, $id]
        );
    }

    public function updateToken($id, $encryptedToken, $expiresAt) {
        return $this->execute(
            'UPDATE walmart_connections SET access_token = ?, token_expires_at = ? WHERE id = ?',
            [$encryptedToken, $expiresAt, $id]
        );
    }

    public function rename($id, $storeName) {
        return $this->execute('UPDATE walmart_connections SET store_name = ? WHERE id = ?', [$storeName, $id]);
    }

    public function setActive($id, $active) {
        return $this->execute('UPDATE walmart_connections SET is_active = ? WHERE id = ?', [$active ? 1 : 0, $id]);
    }

    public function delete($id) {
        return $this->execute('DELETE FROM walmart_connections WHERE id = ?', [$id]);
    }
}
