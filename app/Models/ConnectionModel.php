<?php

class ConnectionModel extends Model {

    public function findById($id, $userId = null) {
        if ($userId) {
            return $this->row('SELECT * FROM ml_connections WHERE id = ? AND user_id = ?', [$id, $userId]);
        }
        return $this->row('SELECT * FROM ml_connections WHERE id = ?', [$id]);
    }

    public function findByMlUserId($mlUserId, $userId) {
        return $this->row('SELECT id FROM ml_connections WHERE ml_user_id = ? AND user_id = ?', [$mlUserId, $userId]);
    }

    public function findActiveByUser($userId) {
        return $this->rows(
            'SELECT id, store_name, ml_user_id, ml_email, ml_nickname, country, config_app_id, token_expires_at, is_active, created_at FROM ml_connections WHERE user_id = ? AND is_active = 1 ORDER BY created_at DESC',
            [$userId]
        );
    }

    public function findAll() {
        return $this->rows(
            'SELECT id, store_name, ml_user_id, ml_email, ml_nickname, country, config_app_id, token_expires_at, is_active, created_at FROM ml_connections ORDER BY created_at DESC'
        );
    }

    public function findAllForUser($userId) {
        return $this->rows(
            'SELECT id, store_name, ml_user_id, ml_nickname, country, config_app_id, token_expires_at, is_active, created_at FROM ml_connections WHERE user_id = ? ORDER BY created_at DESC',
            [$userId]
        );
    }

    public function findStoreName($storeId, $userId) {
        $row = $this->row('SELECT store_name, ml_nickname FROM ml_connections WHERE id = ? AND user_id = ?', [$storeId, $userId]);
        return $row ? ($row->store_name ?: $row->ml_nickname) : '';
    }

    public function getMlUserId($storeId, $userId) {
        return $this->row('SELECT ml_user_id FROM ml_connections WHERE id = ? AND user_id = ?', [$storeId, $userId]);
    }

    public function findTokenData($storeId, $userId) {
        return $this->row(
            'SELECT access_token, refresh_token, token_expires_at FROM ml_connections WHERE id = ? AND user_id = ?',
            [$storeId, $userId]
        );
    }

    public function findTokenDataById($storeId) {
        return $this->row(
            'SELECT access_token, refresh_token, token_expires_at FROM ml_connections WHERE id = ?',
            [$storeId]
        );
    }

    public function findActiveStore($storeId, $userId) {
        return $this->row(
            'SELECT id, ml_user_id, token_expires_at, is_active FROM ml_connections WHERE id = ? AND user_id = ? AND is_active = 1',
            [$storeId, $userId]
        );
    }

    public function updateTokens($id, $accessToken, $refreshToken, $expiresAt) {
        return $this->execute(
            'UPDATE ml_connections SET access_token = ?, refresh_token = ?, token_expires_at = ? WHERE id = ?',
            [$accessToken, $refreshToken, $expiresAt, $id]
        );
    }

    public function create($userId, $storeName, $mlUserId, $mlEmail, $mlNickname, $country, $accessToken, $refreshToken, $expiresAt, $configAppId = '') {
        return $this->insertGetId(
            'INSERT INTO ml_connections (user_id, store_name, ml_user_id, ml_email, ml_nickname, country, config_app_id, access_token, refresh_token, token_expires_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$userId, $storeName, $mlUserId, $mlEmail, $mlNickname, $country, $configAppId, $accessToken, $refreshToken, $expiresAt]
        );
    }

    public function update($id, $storeName, $mlEmail, $mlNickname, $country, $accessToken, $refreshToken, $expiresAt, $configAppId = '') {
        return $this->execute(
            'UPDATE ml_connections SET store_name = ?, ml_email = ?, ml_nickname = ?, country = ?, config_app_id = ?, access_token = ?, refresh_token = ?, token_expires_at = ?, is_active = 1, updated_at = NOW() WHERE id = ?',
            [$mlNickname, $mlEmail, $mlNickname, $country, $configAppId, $accessToken, $refreshToken, $expiresAt, $id]
        );
    }
}
