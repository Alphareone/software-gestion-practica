<?php

/**
 * Cliente HTTP de bajo nivel para la API de Cencosud Chile Marketplace.
 *
 * Cada request lleva los headers obligatorios de la API de Cencosud.
 * Al igual que Walmart, con CENCOSUD_DRY_RUN = true no se llama a la API real,
 * se registran los llamados en logs con secretos enmascarados y se devuelven
 * respuestas simuladas (mockups) para pruebas locales seguras.
 */
class CencosudApiHelper {

    /**
     * Envía una petición HTTP mediante cURL.
     * Retorna ['code' => int, 'body' => object|null, 'raw' => string].
     */
    public static function request($baseUrl, $endpoint, $method = 'GET', $data = null, array $auth = [], $rateLimiter = null) {
        $method = strtoupper($method);

        // Controlar la tasa de peticiones mediante el Rate Limiter
        if ($rateLimiter) {
            $waitSeconds = $rateLimiter->checkAndWait();
            if ($waitSeconds > 0) {
                usleep((int) ($waitSeconds * 1000000)); // Esperar microsegundos
            }
        }

        // Si el modo simulación (Dry-Run) está activo, no contactar la API real
        if (defined('CENCOSUD_DRY_RUN') && CENCOSUD_DRY_RUN) {
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
            error_log("[CENCOSUD-API] cURL error en {$method} {$endpoint}: {$curlError}");
            return ['code' => 0, 'body' => null, 'raw' => ''];
        }

        return ['code' => $httpCode, 'body' => json_decode($response), 'raw' => $response];
    }

    /**
     * Generador de UUID para los correlation ID de las peticiones.
     */
    public static function uuid4() {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }

    /**
     * Construye las cabeceras HTTP de la API.
     *
     * La API de Cencosud usa Bearer en los dos pasos:
     *   1. Para pedir el token se envía la propia API Key como Bearer
     *      (POST /v1/auth/apiKey).
     *   2. Para el resto de los servicios se envía el Access Token (JWT).
     * No requiere las cabeceras CENCOSUD_* que usaba la versión anterior:
     * eran una suposición copiada de Walmart y la API real las ignora.
     */
    private static function buildHeaders(array $auth) {
        $headers = [
            'Accept: application/json',
            'Content-Type: application/json',
        ];

        // El token tiene prioridad; si aún no hay, se usa la API Key para obtenerlo.
        $bearer = !empty($auth['token']) ? $auth['token'] : ($auth['api_key'] ?? '');
        if ($bearer !== '') {
            $headers[] = 'Authorization: Bearer ' . $bearer;
        }

        return $headers;
    }

    // ── Modo dry-run: logs de auditoría y respuestas simuladas (Mockups) ──

    private static function dryRun($baseUrl, $endpoint, $method, $data, array $auth) {
        $maskedAuth = [
            'client_id' => $auth['client_id'] ?? '',
            'client_secret' => isset($auth['client_secret']) ? self::mask($auth['client_secret']) : '',
            'token' => isset($auth['token']) ? self::mask((string) $auth['token']) : null,
            'channel_type' => $auth['channel_type'] ?? null,
            'market' => $auth['market'] ?? 'cl',
        ];
        
        $payload = is_string($data) ? $data : json_encode($data);
        
        // Loguear la petición (para verificar que los controladores y servicios envían datos correctos)
        error_log("[CENCOSUD-DRYRUN] {$method} " . rtrim($baseUrl, '/') . $endpoint
            . ' auth=' . json_encode($maskedAuth)
            . ($payload !== 'null' && $payload !== null ? " body={$payload}" : ''));

        // Generar respuesta simulada adecuada al endpoint solicitado
        $body = self::simulatedBody($endpoint, $method, $data);
        $raw = json_encode($body);
        return ['code' => 200, 'body' => json_decode($raw), 'raw' => $raw];
    }

    /**
     * Enmascara credenciales para evitar volcados accidentales en archivos de log de auditoría.
     */
    private static function mask($value) {
        if ($value === '' || $value === null) return '';
        $len = strlen($value);
        return substr($value, 0, 4) . str_repeat('*', max(0, $len - 8)) . substr($value, -4);
    }

    /**
     * Simula las respuestas JSON del catálogo e inventarios de Cencosud.
     */
    private static function simulatedBody($endpoint, $method, $data) {
        // Simular obtención de Token
        if (strpos($endpoint, '/v3/token') === 0 && $method === 'POST') {
            return [
                'access_token' => 'cencosud-dryrun-token-' . bin2hex(random_bytes(8)),
                'token_type' => 'Bearer',
                'expires_in' => 900
            ];
        }

        // Simular listado de catálogo: Paginado
        if (strpos($endpoint, '/v3/items') === 0 && $method === 'GET') {
            $isSecondPage = strpos($endpoint, 'nextCursor=') !== false || strpos($endpoint, 'offset=') !== false;
            
            $makeItem = function ($sku, $name, $price, $color = 'Rojo', $size = 'M', $brand = 'Rhypolia') {
                return [
                    'sku' => $sku,
                    'sellerSku' => $sku,
                    'cencosud_product_id' => 'CSPID-' . $sku,
                    'upc' => '7809876' . rand(10000, 99999),
                    'gtin' => '007809876' . rand(10000, 99999),
                    'productName' => $name . " (dry-run)",
                    'brand' => $brand,
                    'category' => 'Vestuario',
                    'productType' => 'Ropa',
                    'price' => ['currency' => 'CLP', 'amount' => $price],
                    'publishedStatus' => 'PUBLISHED',
                    'lifecycleStatus' => 'ACTIVE',
                    'attributes' => [
                        ['name' => 'Marca', 'value' => $brand],
                        ['name' => 'Categoría', 'value' => 'Vestuario'],
                    ],
                    'variants' => [
                        [
                            'sellerSku' => $sku,
                            'attributes' => [
                                ['name' => 'Color Comercial', 'value' => $color],
                                ['name' => 'Talla', 'value' => $size],
                                ['name' => 'Género', 'value' => 'Mujer'],
                            ]
                        ]
                    ]
                ];
            };

            $itemsPage1 = [
                $makeItem('A-201', 'Polera Base A-201', 12990),
                $makeItem('A-201-ROJ-S', 'Polera Roja S A-201', 12990),
                $makeItem('PACK-A-201-ROJ-AZU-S', 'Pack Poleras A-201 S', 22990),
                $makeItem('TRIPACK-A-201-ROJ-AZU-VER-S', 'Tripack Poleras A-201 S', 32990),
                $makeItem('A-2010', 'Polera Diferente A-2010', 14990),
            ];

            $itemsPage2 = [
                $makeItem('A-2010-ROJ-S', 'Polera Diferente Roja S A-2010', 14990),
                $makeItem('CS-DRY-SKU-001', 'Producto Cencosud 1', 15990),
                $makeItem('CS-DRY-SKU-002', 'Producto Cencosud 2', 17990),
            ];

            if ($isSecondPage) {
                return ['ItemResponse' => $itemsPage2, 'totalItems' => 8];
            }
            return [
                'ItemResponse' => $itemsPage1,
                'totalItems' => 8,
                'nextCursor' => 'cencosud-dryrun-cursor-page2'
            ];
        }

        // Simular inventario paginado
        if (strpos($endpoint, '/v3/inventories') === 0 && $method === 'GET') {
            $inv = [];
            $allSkus = [
                'A-201', 'A-201-ROJ-S', 'PACK-A-201-ROJ-AZU-S', 'TRIPACK-A-201-ROJ-AZU-VER-S', 'A-2010',
                'A-2010-ROJ-S', 'CS-DRY-SKU-001', 'CS-DRY-SKU-002'
            ];
            foreach ($allSkus as $sku) {
                $qty = ($sku === 'A-201-ROJ-S') ? 3 : 15;
                $inv[] = [
                    'sku' => $sku,
                    'quantity' => ['unit' => 'EACH', 'amount' => $qty]
                ];
            }
            return ['elements' => ['inventories' => $inv], 'meta' => ['totalCount' => count($allSkus)]];
        }

        // Simular inventario individual
        if (strpos($endpoint, '/v3/inventory') === 0 && $method === 'GET') {
            parse_str((string) parse_url($endpoint, PHP_URL_QUERY), $q);
            return ['sku' => $q['sku'] ?? '', 'quantity' => ['unit' => 'EACH', 'amount' => 25]];
        }

        // Simular actualizaciones de stock o precio
        if ($method === 'PUT') {
            return ['status' => 'OK', 'message' => 'Simulado (dry-run): no se envió a Cencosud.'];
        }

        return ['status' => 'OK'];
    }
}
