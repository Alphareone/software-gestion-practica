<?php

/**
 * Exporta el catálogo cacheado de una tienda Walmart a Excel — precios o stock.
 *
 * A diferencia de WalmartAuditService (que COMPARA un archivo subido contra
 * la caché), esto simplemente VUELCA lo que ya está en walmart_products_cache,
 * sin necesidad de que el usuario suba nada.
 *
 * Cada archivo se sobrescribe en cada llamada (no se versiona) — el objetivo
 * es tener siempre "la última foto" disponible, sea generada a mano con el
 * botón o automáticamente al terminar un sync.
 */
class WalmartExportService {

    private $db;
    private $cacheModel;

    public function __construct($db) {
        $this->db = $db;
        $this->cacheModel = new WalmartProductsCacheModel($db);
    }

    private function storageDir() {
        $dir = dirname(__DIR__, 2) . '/storage/walmart-exports';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        return $dir;
    }

    public function filePath($connectionId, $type) {
        return $this->storageDir() . '/walmart_' . $type . '_' . (int) $connectionId . '.xlsx';
    }

    /**
     * Genera (o regenera) el Excel de precios o stock para una tienda.
     * Retorna la ruta absoluta del archivo escrito.
     */
    public function generate($connectionId, $type) {
        if (!in_array($type, ['price', 'stock'], true)) {
            throw new InvalidArgumentException('Tipo de export inválido.');
        }

        $rows = $this->cacheModel->getAllForAudit((int) $connectionId);

        require_once __DIR__ . '/../Helpers/XlsxWriter.php';
        $xlsx = new XlsxWriter();

        if ($type === 'price') {
            $xlsx->setHeaders(['SKU', 'WPID', 'Título', 'Precio', 'Moneda', 'Estado', 'Actualizado']);
            foreach ($rows as $r) {
                $xlsx->addRow([$r->sku, $r->wpid, $r->title ?? '', $r->price, $r->currency ?? 'CLP', $r->published_status, $r->synced_at]);
            }
        } else {
            $xlsx->setHeaders(['SKU', 'WPID', 'Título', 'Stock', 'Estado', 'Actualizado']);
            foreach ($rows as $r) {
                $xlsx->addRow([$r->sku, $r->wpid, $r->title ?? '', $r->available_quantity, $r->published_status, $r->synced_at]);
            }
        }

        $path = $this->filePath($connectionId, $type);
        $xlsx->output($path);
        return $path;
    }

    /** Genera ambos archivos (precio y stock) de una tienda — usado tras cada sync. */
    public function generateBoth($connectionId) {
        $results = [];
        foreach (['price', 'stock'] as $type) {
            try {
                $results[$type] = $this->generate($connectionId, $type);
            } catch (Exception $e) {
                error_log('[WALMART-EXPORT] Error generando ' . $type . ' para conexión ' . $connectionId . ': ' . $e->getMessage());
                $results[$type] = null;
            }
        }
        return $results;
    }
}
