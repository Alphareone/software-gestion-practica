<?php

/**
 * Wrapper de negocio sobre la API de Walmart Chile.
 * Resuelve conexión + token (WalmartAuthService), reintenta una vez en 401
 * (token expirado a mitad de proceso) y aplica backoff exponencial en 429/5xx.
 */
class WalmartApiService {

    private $db;
    private $auth;
    private $connection;
    private $rateLimiter;

    public function __construct($db) {
        $this->db = $db;
        $this->auth = new WalmartAuthService($db);
        $this->connection = new WalmartConnectionModel($db);
        $this->rateLimiter = new WalmartApiRateLimiter($db);
    }

    public function call($connectionId, $endpoint, $method = 'GET', $data = null) {
        $conn = $this->connection->findById($connectionId);
        if (!$conn) {
            return ['code' => 0, 'body' => null, 'raw' => 'Conexión no encontrada'];
        }

        $token = $this->auth->getToken($connectionId);
        if (!$token) {
            return ['code' => 401, 'body' => null, 'raw' => 'No se pudo obtener token'];
        }

        $result = $this->doRequest($conn, $endpoint, $method, $data, $token);

        // Token expirado/revocado a mitad de proceso: renovar y reintentar una vez
        if ($result['code'] === 401) {
            $token = $this->auth->getToken($connectionId, true);
            if ($token) {
                $result = $this->doRequest($conn, $endpoint, $method, $data, $token);
            }
        }

        return $result;
    }

    public function callWithRetry($connectionId, $endpoint, $method = 'GET', $data = null, $maxRetries = 3) {
        $delay = 1;
        for ($attempt = 0; $attempt <= $maxRetries; $attempt++) {
            $result = $this->call($connectionId, $endpoint, $method, $data);
            $retryable = $result['code'] === 429 || ($result['code'] >= 500 && $result['code'] < 600);
            if (!$retryable || $attempt === $maxRetries) {
                return $result;
            }
            sleep($delay);
            $delay *= 2;
        }
        return $result;
    }

    // ── Items ──

    /**
     * GET /v3/items paginado.
     *
     * Walmart CL no siempre devuelve `nextCursor` en el body (confirmado en
     * cuentas reales: solo trae `ItemResponse` + `totalItems`). Por eso el
     * "cursor" que maneja este método es doble uso:
     *   - Si Walmart devuelve un `nextCursor` real, se usa tal cual (algunas
     *     cuentas/versiones de la API sí lo soportan).
     *   - Si no, se cae a paginación por `offset` calculado a partir de
     *     `totalItems`, codificado como string numérico en el mismo campo
     *     'nextCursor' (así WalmartSyncService no necesita saber cuál de
     *     los dos modos está usando la cuenta).
     *
     * Retorna ['code', 'items' => array, 'nextCursor' => ?string, 'total' => int].
     */
    public function getItemsPage($connectionId, $limit = 200, $cursor = null) {
        $isOffsetCursor = $cursor !== null && $cursor !== '' && ctype_digit((string) $cursor);
        $offset = $isOffsetCursor ? (int) $cursor : 0;

        $endpoint = '/v3/items?limit=' . (int) $limit;
        if ($cursor !== null && $cursor !== '' && !$isOffsetCursor) {
            $endpoint .= '&nextCursor=' . urlencode($cursor);
        } elseif ($offset > 0) {
            $endpoint .= '&offset=' . $offset;
        }

        $result = $this->callWithRetry($connectionId, $endpoint);
        if ($result['code'] !== 200 || !$result['body']) {
            return ['code' => $result['code'], 'items' => [], 'nextCursor' => null, 'total' => 0, 'raw' => $result['raw'] ?? ''];
        }

        $body = $result['body'];
        $items = $body->ItemResponse ?? $body->itemResponse ?? [];
        $items = is_array($items) ? $items : [$items];
        $total = (int) ($body->totalItems ?? 0);

        $nextCursor = $body->nextCursor ?? null;
        if ($nextCursor === null) {
            $nextOffset = $offset + count($items);
            $nextCursor = ($total > 0 && $nextOffset < $total && count($items) > 0) ? (string) $nextOffset : null;
        }

        return [
            'code' => 200,
            'items' => $items,
            'nextCursor' => $nextCursor,
            'total' => $total,
        ];
    }

    public function getItem($connectionId, $sku) {
        return $this->callWithRetry($connectionId, '/v3/items/' . rawurlencode($sku));
    }

    // ── Inventario ──

    /**
     * GET /v3/inventories paginado. Retorna ['code', 'inventories' => array, 'nextCursor' => ?string].
     * Cada elemento: {sku, quantity: {unit, amount}}.
     */
    public function getInventoriesPage($connectionId, $limit = 50, $cursor = null) {
        $endpoint = '/v3/inventories?limit=' . (int) $limit;
        if ($cursor !== null && $cursor !== '') {
            $endpoint .= '&nextCursor=' . urlencode($cursor);
        }
        $result = $this->callWithRetry($connectionId, $endpoint);
        if ($result['code'] !== 200 || !$result['body']) {
            return ['code' => $result['code'], 'inventories' => [], 'nextCursor' => null];
        }

        $body = $result['body'];
        $inventories = $body->elements->inventories ?? $body->inventories ?? [];
        $nextCursor = $body->meta->nextCursor ?? $body->nextCursor ?? null;
        return [
            'code' => 200,
            'inventories' => is_array($inventories) ? $inventories : [$inventories],
            'nextCursor' => $nextCursor,
        ];
    }

    public function getInventory($connectionId, $sku) {
        return $this->callWithRetry($connectionId, '/v3/inventory?sku=' . rawurlencode($sku));
    }

    // ── Órdenes ──

    /**
     * GET /v3/orders paginado, filtrado por fecha de creación.
     *
     * IMPORTANTE: a diferencia de items/inventory (ya probados contra una
     * cuenta real de Walmart CL), este endpoint todavía NO se ha probado
     * contra una cuenta real. La forma documentada por Walmart es algo como:
     *   { list: { meta: { totalCount, limit, nextCursor }, elements: { order: [ {...} ] } } }
     * pero ya hemos visto en este mismo proyecto (getItem, getInventory) que
     * las cuentas reales de Walmart CL a veces devuelven una forma distinta
     * a la documentada. Por eso este método prueba varias formas conocidas
     * y registra en el log la forma cruda si ninguna calza, en vez de fallar
     * en silencio — mismo enfoque que ya usamos para arreglar "Verificar en vivo".
     *
     * Retorna ['code', 'orders' => array, 'nextCursor' => ?string].
     */
    public function getOrdersPage($connectionId, $createdStartDate, $createdEndDate = null, $limit = 100, $cursor = null) {
        $endpoint = '/v3/orders?limit=' . (int) $limit
            . '&createdStartDate=' . urlencode($createdStartDate);
        if ($createdEndDate) {
            $endpoint .= '&createdEndDate=' . urlencode($createdEndDate);
        }
        if ($cursor !== null && $cursor !== '') {
            $endpoint .= '&nextCursor=' . urlencode($cursor);
        }

        $result = $this->callWithRetry($connectionId, $endpoint);
        if ($result['code'] !== 200 || !$result['body']) {
            return ['code' => $result['code'], 'orders' => [], 'nextCursor' => null];
        }

        $body = $result['body'];

        // Forma documentada: list.elements.order (puede venir objeto único si hay 1 sola orden)
        $orders = $body->list->elements->order ?? null;
        $nextCursor = $body->list->meta->nextCursor ?? null;

        // Fallbacks por si la cuenta real devuelve algo distinto
        if ($orders === null) {
            $orders = $body->elements->order ?? $body->order ?? $body->orders ?? null;
        }
        if ($nextCursor === null) {
            $nextCursor = $body->meta->nextCursor ?? $body->nextCursor ?? null;
        }

        if ($orders === null) {
            error_log('[WALMART-ORDERS] Forma de respuesta no reconocida: ' . json_encode($body));
            return ['code' => 200, 'orders' => [], 'nextCursor' => null];
        }

        return [
            'code' => 200,
            'orders' => is_array($orders) ? $orders : [$orders],
            'nextCursor' => $nextCursor,
        ];
    }

    // ── Escrituras (habilitadas para Walmart, a diferencia de ML) ──

    /** PUT /v3/price — actualiza el precio de un SKU. */
    public function updatePrice($connectionId, $sku, $amount, $currency = 'CLP') {
        $payload = json_encode([
            'sku' => $sku,
            'pricing' => [[
                'currentPriceType' => 'BASE',
                'currentPrice' => [
                    'currency' => $currency,
                    'amount' => $amount,
                ],
            ]],
        ], JSON_UNESCAPED_UNICODE);
        return $this->callWithRetry($connectionId, '/v3/price', 'PUT', $payload, 2);
    }

    /** PUT /v3/inventory?sku= — actualiza el stock de un SKU. */
    public function updateInventory($connectionId, $sku, $quantity) {
        $payload = json_encode([
            'sku' => $sku,
            'quantity' => [
                'unit' => 'EACH',
                'amount' => (int) $quantity,
            ],
        ], JSON_UNESCAPED_UNICODE);
        return $this->callWithRetry($connectionId, '/v3/inventory?sku=' . rawurlencode($sku), 'PUT', $payload, 2);
    }

    private function doRequest($conn, $endpoint, $method, $data, $token) {
        return WalmartApiHelper::request(
            $conn->api_base_url ?: WALMART_API_BASE_URL,
            $endpoint,
            $method,
            $data,
            $this->auth->buildAuth($conn, $token),
            $this->rateLimiter
        );
    }
}
