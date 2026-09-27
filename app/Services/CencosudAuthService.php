<?php

/**
 * Autenticación contra la API de Cencosud Chile (OAuth 2.0 client_credentials).
 *
 * Cada conexión guarda su Client ID/Secret (cifrado) y el token de acceso
 * se solicita bajo demanda a POST /v3/token y se almacena cacheado
 * y cifrado en cencosud_connections (access_token + token_expires_at).
 */
class CencosudAuthService {

    /** Margen en segundos antes de la expiración para renovar el token. */
    private const RENEW_MARGIN = 60;

    private $db;
    private $connection;

    public function __construct($db) {
        $this->db = $db;
        $this->connection = new CencosudConnectionModel($db);
    }

    /**
     * Devuelve un token válido para la conexión, renovándolo si está por
     * vencer (o si $forceNew, por ejemplo, tras recibir un error 401).
     * Retorna null si falla.
     */
    public function getToken($connectionId, $forceNew = false) {
        $conn = $this->connection->findById($connectionId);
        if (!$conn || (isset($conn->is_active) && (int)$conn->is_active !== 1)) {
            error_log("[CENCOSUD-AUTH] Conexión {$connectionId}: no encontrada o desactivada en BD");
            return null;
        }

        // Si no se fuerza token nuevo y el token cacheado es válido, usarlo
        if (!$forceNew && $conn->access_token && $conn->token_expires_at
            && strtotime($conn->token_expires_at . ' UTC') > time() + self::RENEW_MARGIN) {
            $token = CryptoHelper::decrypt($conn->access_token);
            if ($token) return $token;
        }

        // Solicitar un nuevo token a la API
        $result = $this->requestToken($conn);
        $body = $result['body'] ?? null;
        $token = null;

        if (is_object($body)) {
            if (!empty($body->accessToken) && is_string($body->accessToken)) {
                $token = trim($body->accessToken);
            } elseif (!empty($body->access_token) && is_string($body->access_token)) {
                $token = trim($body->access_token);
            }
        }

        // Validación estricta: Rechazar respuestas sin token o con código distinto a HTTP 200
        if ($result['code'] !== 200 || empty($token)) {
            error_log("[CENCOSUD-AUTH] Conexión {$connectionId}: obtención de token FALLÓ — HTTP " . ($result['code'] ?? '?')
                . ' — ' . substr((string) ($result['raw'] ?? ''), 0, 300));
            return null;
        }

        $expiresIn = (int) ($body->expiresIn ?? ($body->expires_in ?? 14400));
        if ($expiresIn < 60) $expiresIn = 14400; // Valor seguro por defecto (4 horas Cencosud)
        
        // Guardar la expiración en UTC restándole un pequeño margen de seguridad
        $expiresAt = (new DateTime('now', new DateTimeZone('UTC')))
            ->setTimestamp(time() + max(60, $expiresIn - 30))
            ->format('Y-m-d H:i:s');
            
        $this->connection->updateToken($connectionId, CryptoHelper::encrypt($token), $expiresAt);
        return $token;
    }

    /**
     * Valida credenciales solicitando un token real (o simulado en dry-run),
     * sin persistir nada en la base de datos.
     * Retorna ['ok' => bool, 'error' => ?string].
     */
    public function testCredentials($apiKey, $baseUrl = null) {
        $apiKey = trim((string) $apiKey);
        if ($apiKey === '') {
            return ['ok' => false, 'error' => 'La API Key es obligatoria.'];
        }

        $result = self::pedirToken($baseUrl ?: CENCOSUD_API_BASE_URL, $apiKey);
        $body = $result['body'] ?? null;
        $token = is_object($body) && !empty($body->accessToken) ? $body->accessToken : null;

        if ($result['code'] === 200 && !empty($token)) {
            // El payload identifica al vendedor: se devuelve para mostrarlo al conectar.
            $payload = $body->jwtPayload ?? null;
            return [
                'ok' => true,
                'error' => null,
                'seller_id' => $payload->seller_id ?? null,
                'seller_name' => $payload->seller_name ?? null,
            ];
        }
        if (in_array($result['code'], [400, 401, 403], true)) {
            return ['ok' => false, 'error' => 'API Key rechazada por Cencosud (HTTP ' . $result['code'] . ').'];
        }
        if ($result['code'] === 0) {
            return ['ok' => false, 'error' => 'No se pudo contactar la API de Cencosud (error de red).'];
        }
        return ['ok' => false, 'error' => 'Respuesta inesperada de Cencosud (HTTP ' . $result['code'] . ').'];
    }

    /**
     * Datos de autenticación estructurados para CencosudApiHelper::request().
     *
     * La API Key se guarda cifrada en la columna client_secret de la conexión.
     */
    public function buildAuth($conn, $token = null) {
        return [
            'api_key' => CryptoHelper::decrypt($conn->client_secret),
            'token'   => $token,
        ];
    }

    /**
     * Canjea la API Key por un Access Token.
     *
     * POST /v1/auth/apiKey con la API Key en «Authorization: Bearer».
     * Devuelve accessToken y expiresIn (4 horas). La documentación pide
     * explícitamente no llamarlo en cada petición; por eso getToken() reutiliza
     * el token cacheado mientras siga vigente.
     */
    private static function pedirToken($baseUrl, $apiKey) {
        return CencosudApiHelper::request(
            $baseUrl,
            '/v1/auth/apiKey',
            'POST',
            null,
            ['api_key' => $apiKey]
        );
    }

    private function requestToken($conn) {
        return self::pedirToken(
            $conn->api_base_url ?: CENCOSUD_API_BASE_URL,
            CryptoHelper::decrypt($conn->client_secret)
        );
    }
}
