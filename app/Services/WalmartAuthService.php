<?php

/**
 * Autenticación contra la API de Walmart Chile (OAuth 2.0 client_credentials).
 *
 * A diferencia de MercadoLibre no hay flujo de autorización con redirect ni
 * refresh_token: cada conexión guarda su Client ID/Secret (cifrado) y el
 * token de ~15 minutos se pide on-demand a POST /v3/token y se cachea
 * cifrado en walmart_connections (access_token + token_expires_at).
 */
class WalmartAuthService {

    /** Margen en segundos antes de la expiración para renovar el token. */
    private const RENEW_MARGIN = 60;

    private $db;
    private $connection;

    public function __construct($db) {
        $this->db = $db;
        $this->connection = new WalmartConnectionModel($db);
    }

    /**
     * Devuelve un token válido para la conexión, renovándolo si está por
     * vencer (o si $forceNew, p. ej. tras un 401). Null si falla.
     */
    public function getToken($connectionId, $forceNew = false) {
        $conn = $this->connection->findById($connectionId);
        if (!$conn) {
            error_log("[WALMART-AUTH] Conexión {$connectionId}: no encontrada");
            return null;
        }

        if (!$forceNew && $conn->access_token && $conn->token_expires_at
            && strtotime($conn->token_expires_at . ' UTC') > time() + self::RENEW_MARGIN) {
            $token = CryptoHelper::decrypt($conn->access_token);
            if ($token) return $token;
        }

        $result = $this->requestToken($conn);
        if ($result['code'] !== 200 || empty($result['body']->access_token)) {
            error_log("[WALMART-AUTH] Conexión {$connectionId}: token FALLÓ — HTTP " . ($result['code'] ?? '?')
                . ' — ' . substr((string) ($result['raw'] ?? ''), 0, 300));
            return null;
        }

        $token = $result['body']->access_token;
        $expiresIn = (int) ($result['body']->expires_in ?? 900);
        $expiresAt = (new DateTime('now', new DateTimeZone('UTC')))
            ->setTimestamp(time() + max(60, $expiresIn - 30))
            ->format('Y-m-d H:i:s');
        $this->connection->updateToken($connectionId, CryptoHelper::encrypt($token), $expiresAt);
        return $token;
    }

    /**
     * Valida credenciales pidiendo un token real (o simulado en dry-run),
     * sin persistir nada. Para el botón "Probar conexión" y el alta.
     * Retorna ['ok' => bool, 'error' => ?string].
     */
    public function testCredentials($clientId, $clientSecret, $baseUrl = null, $channelType = null) {
        $clientId = trim((string) $clientId);
        $clientSecret = trim((string) $clientSecret);
        if ($clientId === '' || $clientSecret === '') {
            return ['ok' => false, 'error' => 'Client ID y Client Secret son obligatorios.'];
        }

        $result = WalmartApiHelper::request(
            $baseUrl ?: WALMART_API_BASE_URL,
            '/v3/token',
            'POST',
            ['grant_type' => 'client_credentials'],
            ['client_id' => $clientId, 'client_secret' => $clientSecret, 'channel_type' => $channelType, 'market' => 'cl']
        );

        if ($result['code'] === 200 && !empty($result['body']->access_token)) {
            return ['ok' => true, 'error' => null];
        }
        if ($result['code'] === 401 || $result['code'] === 400) {
            return ['ok' => false, 'error' => 'Credenciales rechazadas por Walmart (HTTP ' . $result['code'] . '). Verifica Client ID y Secret.'];
        }
        if ($result['code'] === 0) {
            return ['ok' => false, 'error' => 'No se pudo contactar la API de Walmart (error de red).'];
        }
        return ['ok' => false, 'error' => 'Respuesta inesperada de Walmart (HTTP ' . $result['code'] . ').'];
    }

    /** Datos de autenticación listos para WalmartApiHelper::request(). */
    public function buildAuth($conn, $token = null) {
        return [
            'client_id' => $conn->client_id,
            'client_secret' => CryptoHelper::decrypt($conn->client_secret),
            'token' => $token,
            'channel_type' => $conn->channel_type ?: null,
            'market' => $conn->market ?: 'cl',
        ];
    }

    private function requestToken($conn) {
        return WalmartApiHelper::request(
            $conn->api_base_url ?: WALMART_API_BASE_URL,
            '/v3/token',
            'POST',
            ['grant_type' => 'client_credentials'],
            $this->buildAuth($conn)
        );
    }
}
