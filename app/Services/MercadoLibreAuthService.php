<?php


class MercadoLibreAuthService {
    private $connection;
    private $db;

    public function __construct($db = null) {
        $this->db = $db;
        if ($db !== null) {
            $this->connection = new ConnectionModel($db);
        } else {
            $this->connection = new ConnectionModel();
        }
    }

    private function resolveAppId() {
        try {
            if ($this->db) {
                return (new AppSettingModel($this->db))->get('ml_app_id') ?: '';
            }
        } catch (Exception $e) {}
        return '';
    }

    private function resolveClientSecret() {
        try {
            if ($this->db) {
                $encrypted = (new AppSettingModel($this->db))->get('ml_client_secret');
                if ($encrypted) return self::decrypt($encrypted);
            }
        } catch (Exception $e) {}
        return '';
    }

    public static function encrypt($plaintext) {
        $key = hex2bin(ENCRYPTION_KEY);
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length(ENCRYPTION_CIPHER));
        $cipher = openssl_encrypt($plaintext, ENCRYPTION_CIPHER, $key, OPENSSL_RAW_DATA, $iv);
        return base64_encode($iv . $cipher);
    }

    public static function decrypt($encoded) {
        if (empty($encoded)) return '';
        $key = hex2bin(ENCRYPTION_KEY);
        $data = base64_decode($encoded);
        $ivLen = openssl_cipher_iv_length(ENCRYPTION_CIPHER);
        $iv = substr($data, 0, $ivLen);
        $cipher = substr($data, $ivLen);
        return openssl_decrypt($cipher, ENCRYPTION_CIPHER, $key, OPENSSL_RAW_DATA, $iv);
    }

    public function getTokenForStore($storeId, $userId) {
        $conn = $this->connection->findTokenDataById($storeId);
        if (!$conn) {
            error_log("[ML-AUTH] Store {$storeId}: token data no encontrado");
            return null;
        }

        if ($conn->token_expires_at && strtotime($conn->token_expires_at . ' UTC') < time() + 300) {
            $refreshToken = self::decrypt($conn->refresh_token);
            if ($refreshToken) {
                $result = $this->refreshAccessToken($refreshToken);
                if ($result['code'] === 200 && !empty($result['body']->access_token)) {
                    $newToken = $result['body']->access_token;
                    $newRefresh = $result['body']->refresh_token ?? $refreshToken;
                    $expiresAt = (new DateTime('now', new DateTimeZone('UTC')))->setTimestamp(time() + (int) ($result['body']->expires_in ?? 21600))->format('Y-m-d H:i:s');
                    $this->connection->updateTokens($storeId, self::encrypt($newToken), self::encrypt($newRefresh), $expiresAt);
                    error_log("[ML-AUTH] Store {$storeId}: token renovado OK, expira en " . ($result['body']->expires_in ?? 21600) . "s");
                    return $newToken;
                }
                error_log("[ML-AUTH] Store {$storeId}: refresh FALLÓ — HTTP " . ($result['code'] ?? '?') . " — " . ($result['body']->message ?? 'sin detalle'));
            } else {
                error_log("[ML-AUTH] Store {$storeId}: refresh_token vacío o inválido");
            }
            return null;
        }

        $token = self::decrypt($conn->access_token);
        return $token ?: null;
    }

    public function getActiveTokenForStore($storeId, $userId) {
        return $this->getTokenForStore($storeId, $userId);
    }

    public function exchangeCodeForToken($code) {
        return $this->callMlApi('/oauth/token', 'POST', [
            'grant_type' => 'authorization_code',
            'client_id' => $this->resolveAppId(),
            'client_secret' => $this->resolveClientSecret(),
            'code' => $code,
            'redirect_uri' => ML_REDIRECT_URI,
        ]);
    }

    public function refreshAccessToken($refreshToken) {
        return $this->callMlApi('/oauth/token', 'POST', [
            'grant_type' => 'refresh_token',
            'client_id' => $this->resolveAppId(),
            'client_secret' => $this->resolveClientSecret(),
            'refresh_token' => $refreshToken,
        ]);
    }

    public function tryRefreshToken($id, $userId) {
        $conn = $this->connection->findTokenData($id, $userId);
        if (!$conn || !$conn->refresh_token) return false;

        $refreshToken = self::decrypt($conn->refresh_token);
        if (!$refreshToken) return false;

        $result = $this->refreshAccessToken($refreshToken);
        if ($result['code'] !== 200 || empty($result['body']->access_token)) return false;

        $newToken = $result['body']->access_token;
        $newRefresh = $result['body']->refresh_token ?? $refreshToken;
        $expiresAt = (new DateTime('now', new DateTimeZone('UTC')))->setTimestamp(time() + (int) ($result['body']->expires_in ?? 21600))->format('Y-m-d H:i:s');

        $this->connection->updateTokens($id, self::encrypt($newToken), self::encrypt($newRefresh), $expiresAt);
        return true;
    }

    public function makeOauthState($userId) {
        $random = bin2hex(random_bytes(16));
        $ts = time();
        $payload = $userId . '.' . $ts . '.' . $random;
        $sig = hash_hmac('sha256', $payload, ENCRYPTION_KEY);
        return $payload . '.' . $sig;
    }

    public function verifyOauthState($state) {
        $parts = explode('.', $state);
        if (count($parts) !== 4) return false;
        [$userId, $ts, $random, $sig] = $parts;
        $payload = $userId . '.' . $ts . '.' . $random;
        $expected = hash_hmac('sha256', $payload, ENCRYPTION_KEY);
        if (!hash_equals($expected, $sig)) return false;
        if (time() - (int) $ts > 600) return false;
        return (int) $userId;
    }

    public function countryToSiteId($country) {
        $map = [
            'AR' => 'MLA', 'BO' => 'MBO', 'BR' => 'MLB',
            'CL' => 'MLC', 'CO' => 'MCO', 'CR' => 'MCR',
            'DO' => 'MLD', 'EC' => 'MEC', 'GT' => 'MLG',
            'HN' => 'MLH', 'MX' => 'MLM', 'NI' => 'MLI',
            'PA' => 'MPA', 'PE' => 'MPE', 'PY' => 'MPY',
            'SV' => 'MLS', 'UY' => 'MLU', 'VE' => 'MLV',
        ];
        return $map[strtoupper($country)] ?? 'MLC';
    }

    private function callMlApi($endpoint, $method = 'GET', $data = null) {
        return MlApiHelper::mlApi($endpoint, $method, $data);
    }
}
