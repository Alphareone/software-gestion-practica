<?php

/**
 * Traduce el "product_type" de Walmart (viene en inglés desde la
 * taxonomía de Walmart, ej. "Surgical Gowns") a español para mostrar en
 * pantalla. Usa una tabla editable (walmart_product_type_es) para poder
 * agregar traducciones nuevas sin desplegar código, con un diccionario
 * mínimo embebido como respaldo si la tabla aún no existe o está vacía.
 */
class WalmartProductTypeService {

    private static $fallback = [
        'Hospital Gowns' => 'Batas de hospital',
        'Surgical Gowns' => 'Batas quirúrgicas',
        'Clothing Protectors & Adult Bibs' => 'Protectores de ropa y baberos para adultos',
    ];

    private $db;
    private $map = null;

    public function __construct($db) {
        $this->db = $db;
    }

    private function loadMap() {
        if ($this->map !== null) return $this->map;
        $this->map = [];
        try {
            $stmt = $this->db->query('SELECT product_type_en, product_type_es FROM walmart_product_type_es');
            $stmt->execute();
            foreach ($stmt->fetchAll() as $row) {
                $this->map[$row->product_type_en] = $row->product_type_es;
            }
        } catch (Exception $e) {
            // Tabla no disponible todavía (migración no aplicada) — se usa solo el respaldo embebido.
        }
        return $this->map;
    }

    /** Traduce un product_type; si no hay traducción registrada, retorna el original en inglés. */
    public function translate($productTypeEn) {
        if (empty($productTypeEn)) return $productTypeEn;
        $map = $this->loadMap();
        return $map[$productTypeEn] ?? self::$fallback[$productTypeEn] ?? $productTypeEn;
    }

    /** Traduce en lote un arreglo de filas que tengan la propiedad product_type, devolviendo una nueva propiedad product_type_es. */
    public function translateRows(array $rows) {
        foreach ($rows as $row) {
            $row->product_type_es = $this->translate($row->product_type ?? null);
        }
        return $rows;
    }
}
