<?php

/**
 * Cliente HTTP de bajo nivel para la API de Walmart Chile Marketplace.
 * Docs: https://developer.walmart.com/cl-marketplace
 *
 * Cada request lleva los headers WM_* obligatorios. La autenticación es
 * OAuth 2.0 client_credentials: Authorization Basic + WM_SEC.ACCESS_TOKEN
 * (token de ~15 min obtenido de POST /v3/token).
 *
 * Con WALMART_DRY_RUN=1 no se llama a la API real: se loguea el request
 * (con secretos enmascarados) y se devuelven respuestas simuladas, lo que
 * permite probar el pipeline completo sin credenciales.
 */
class WalmartApiHelper {

    /**
     * $auth = [
     *   'client_id'     => string,
     *   'client_secret' => string,           // en claro (ya descifrado)
     *   'token'         => ?string,          // WM_SEC.ACCESS_TOKEN; null para POST /v3/token
     *   'channel_type'  => ?string,          // WM_CONSUMER.CHANNEL.TYPE
     *   'market'        => string,           // 'cl'
     * ]
     * Retorna ['code' => int, 'body' => object|null, 'raw' => string].
     */
    public static function request($baseUrl, $endpoint, $method = 'GET', $data = null, array $auth = [], $rateLimiter = null) {
        $method = strtoupper($method);

        if ($rateLimiter) {
            $waitSeconds = $rateLimiter->checkAndWait();
            if ($waitSeconds > 0) {
                usleep((int) ($waitSeconds * 1000000));
            }
        }

        if (defined('WALMART_DRY_RUN') && WALMART_DRY_RUN) {
            return self::dryRun($baseUrl, $endpoint, $method, $data, $auth);
        }

        $url = rtrim($baseUrl, '/') . $endpoint;
        $headers = self::buildHeaders($auth);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data !== null) {
                $payload = is_string($data) ? $data : http_build_query($data);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
                $headers[] = is_string($data)
                    ? 'Content-Type: application/json'
                    : 'Content-Type: application/x-www-form-urlencoded';
            }
        } elseif ($method === 'PUT') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            if ($data !== null) {
                $payload = is_string($data) ? $data : json_encode($data);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
                $headers[] = 'Content-Type: application/json';
            }
        } elseif ($method !== 'GET') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            error_log("[WALMART-API] cURL error en {$method} {$endpoint}: {$curlError}");
            return ['code' => 0, 'body' => null, 'raw' => ''];
        }

        return ['code' => $httpCode, 'body' => json_decode($response), 'raw' => $response];
    }

    public static function uuid4() {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }

    private static function buildHeaders(array $auth) {
        $headers = ['Accept: application/json'];
        if (!empty($auth['client_id'])) {
            $headers[] = 'Authorization: Basic ' . base64_encode($auth['client_id'] . ':' . ($auth['client_secret'] ?? ''));
        }
        if (!empty($auth['token'])) {
            $headers[] = 'WM_SEC.ACCESS_TOKEN: ' . $auth['token'];
        }
        $headers[] = 'WM_SVC.NAME: Walmart Marketplace';
        $headers[] = 'WM_MARKET: ' . ($auth['market'] ?? 'cl');
        $headers[] = 'WM_QOS.CORRELATION_ID: ' . self::uuid4();
        if (!empty($auth['channel_type'])) {
            $headers[] = 'WM_CONSUMER.CHANNEL.TYPE: ' . $auth['channel_type'];
        }
        return $headers;
    }

    // ── Modo dry-run: log + respuestas simuladas ──

    private static function dryRun($baseUrl, $endpoint, $method, $data, array $auth) {
        $maskedAuth = [
            'client_id' => $auth['client_id'] ?? '',
            'client_secret' => isset($auth['client_secret']) ? self::mask($auth['client_secret']) : '',
            'token' => isset($auth['token']) ? self::mask((string) $auth['token']) : null,
            'channel_type' => $auth['channel_type'] ?? null,
            'market' => $auth['market'] ?? 'cl',
        ];
        $payload = is_string($data) ? $data : json_encode($data);
        error_log("[WALMART-DRYRUN] {$method} " . rtrim($baseUrl, '/') . $endpoint
            . ' auth=' . json_encode($maskedAuth)
            . ($payload !== 'null' && $payload !== null ? " body={$payload}" : ''));

        $body = self::simulatedBody($endpoint, $method, $data);
        $raw = json_encode($body);
        return ['code' => 200, 'body' => json_decode($raw), 'raw' => $raw];
    }

    private static function mask($value) {
        if ($value === '' || $value === null) return '';
        $len = strlen($value);
        return substr($value, 0, 4) . str_repeat('*', max(0, $len - 8)) . substr($value, -4);
    }

    private static function simulatedBody($endpoint, $method, $data) {
        // Token
        if (strpos($endpoint, '/v3/token') === 0 && $method === 'POST') {
            return ['access_token' => 'dryrun-token-' . bin2hex(random_bytes(8)), 'token_type' => 'Bearer', 'expires_in' => 900];
        }

        // Listado de items: 2 páginas simuladas (cursor presente => página 2)
        if (strpos($endpoint, '/v3/items') === 0 && $method === 'GET') {
            $isSecondPage = strpos($endpoint, 'nextCursor=') !== false;
            $makeItem = function ($i) {
                return [
                    'sku' => 'DRY-SKU-' . str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                    'wpid' => 'WPID' . (1000 + $i),
                    'upc' => '78012345678' . $i,
                    'gtin' => '0078012345678' . $i,
                    'productName' => "Producto de prueba {$i} (dry-run)",
                    'productType' => 'Ropa',
                    'price' => ['currency' => 'CLP', 'amount' => 9990 + $i * 1000],
                    'publishedStatus' => $i % 4 === 0 ? 'UNPUBLISHED' : 'PUBLISHED',
                    'lifecycleStatus' => 'ACTIVE',
                ];
            };
            if ($isSecondPage) {
                return ['ItemResponse' => [$makeItem(4), $makeItem(5)], 'totalItems' => 5];
            }
            return ['ItemResponse' => [$makeItem(1), $makeItem(2), $makeItem(3)], 'totalItems' => 5, 'nextCursor' => 'dryrun-cursor-page2'];
        }

        // Inventario paginado
        if (strpos($endpoint, '/v3/inventories') === 0 && $method === 'GET') {
            $inv = [];
            for ($i = 1; $i <= 5; $i++) {
                $inv[] = ['sku' => 'DRY-SKU-' . str_pad((string) $i, 3, '0', STR_PAD_LEFT), 'quantity' => ['unit' => 'EACH', 'amount' => $i === 2 ? 2 : 50]];
            }
            return ['elements' => ['inventories' => $inv], 'meta' => ['totalCount' => 5]];
        }

        // Inventario individual
        if (strpos($endpoint, '/v3/inventory') === 0 && $method === 'GET') {
            parse_str((string) parse_url($endpoint, PHP_URL_QUERY), $q);
            return ['sku' => $q['sku'] ?? '', 'quantity' => ['unit' => 'EACH', 'amount' => 50]];
        }

        // Escrituras (precio / inventario)
        if ($method === 'PUT') {
            return ['status' => 'OK', 'message' => 'Simulado (dry-run): no se envió a Walmart.'];
        }

        return ['status' => 'OK'];
    }
}
