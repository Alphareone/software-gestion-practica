<?php

/**
 * Trae descripciones de MercadoLibre (GET /items/{id}/description) para las
 * filas "ganadoras" de products_central (el set deduplicado que se muestra
 * en el Catálogo Central, ~31k de ~154k filas). No hay endpoint bulk para
 * descripciones, así que es una llamada por producto — se procesa en tandas
 * chicas (ver processNextChunk) para poder llamarse repetidamente desde un
 * botón admin sin bloquear la request. Ver Docs/database/PRODUCTS-CENTRAL-DESCRIPTIONS.md.
 */
class ProductDescriptionService {

    private $db;
    private $central;

    public function __construct($db) {
        $this->db = $db;
        $this->central = new ProductsCentralModel($db);
    }

    public function getStats() {
        return $this->central->getDescriptionStats();
    }

    /**
     * Procesa hasta $limit filas pendientes. Reutiliza el rate limiter de
     * MercadoLibreApiService (300/min, 15.000/hora, compartido con el sync
     * normal), así que cada llamada puede tardar si el presupuesto está
     * cerca del límite — por eso el tamaño de tanda es chico por defecto.
     */
    public function processNextChunk($limit = 20) {
        $candidates = $this->central->getNextDescriptionCandidates($limit);

        $result = ['processed' => 0, 'fetched' => 0, 'empty' => 0, 'errors' => 0];

        if (empty($candidates)) {
            return $result;
        }

        $api = new MercadoLibreApiService($this->db);
        $authService = new MercadoLibreAuthService($this->db);
        $tokens = [];
        $brokenConnections = [];

        foreach ($candidates as $row) {
            $connId = (int) $row->ml_connection_id;

            if (isset($brokenConnections[$connId])) {
                continue;
            }

            if (!isset($tokens[$connId])) {
                // getTokenForStore refresca automáticamente si el token está
                // por expirar (igual que MlSyncService); userId=0 porque esto
                // corre como tarea de mantenimiento, no en nombre de un usuario.
                $tokens[$connId] = $authService->getTokenForStore($connId, 0) ?: null;
            }
            $token = $tokens[$connId];

            if (!$token) {
                $brokenConnections[$connId] = true;
                error_log("[PRODUCT_DESCRIPTIONS] Conexión $connId: no se pudo obtener token válido, se omite por esta tanda.");
                continue;
            }

            $resp = $api->callWithRetry('/items/' . $row->ml_item_id . '/description', 'GET', null, $token);
            $result['processed']++;

            if ($resp['code'] === 200 && $resp['body']) {
                $text = trim((string) ($resp['body']->plain_text ?? $resp['body']->text ?? ''));
                $this->central->saveDescription($row->id, $text);
                if ($text !== '') {
                    $result['fetched']++;
                } else {
                    $result['empty']++;
                }
            } elseif ($resp['code'] === 404) {
                // Ítem sin descripción en ML: se guarda vacío para no reintentar.
                $this->central->saveDescription($row->id, '');
                $result['empty']++;
            } elseif ($resp['code'] === 401 || $resp['code'] === 403) {
                // Token inválido/expirado: no marcar la fila (se reintenta en
                // la próxima corrida cuando se repare la conexión), pero no
                // seguir gastando llamadas contra esta misma tienda ahora.
                $brokenConnections[$connId] = true;
                $result['errors']++;
                error_log("[PRODUCT_DESCRIPTIONS] Conexión $connId: HTTP {$resp['code']} al pedir descripción de {$row->ml_item_id}, token inválido.");
            } else {
                $result['errors']++;
                error_log("[PRODUCT_DESCRIPTIONS] Item {$row->ml_item_id} (conexión $connId): HTTP " . ($resp['code'] ?? '?') . ' al pedir descripción.');
            }
        }

        return $result;
    }
}
