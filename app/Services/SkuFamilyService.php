<?php

/**
 * Agrupa SKUs que representan el mismo producto base bajo distintas
 * presentaciones: individual, pack (2 colores) y tripack (3 colores).
 *
 * Ejemplo real del negocio:
 *   A-201                       -> código base del producto
 *   A-201-ROJ-S                 -> individual (color + talla)
 *   PACK-A-201-ROJ-AZU-S        -> pack de 2 colores
 *   TRIPACK-A-201-ROJ-AZU-VER-S -> tripack de 3 colores
 *
 * Resolución en dos niveles:
 *  1. Manual (tabla sku_family_map, source='manual') — siempre gana.
 *  2. Automática por patrón — quita el prefijo PACK-/TRIPACK-, luego
 *     despega desde el final: 1 token de talla + N tokens de color
 *     (1 para individual, 2 para pack, 3 para tripack) usando el
 *     diccionario de sku_color_codes. Lo que sobra es el SKU base.
 *
 * Si el patrón no logra resolver un SKU (color no está en el diccionario,
 * estructura atípica, etc.) NO se adivina: se deja sin agrupar para que
 * quede visible como pendiente de mapeo manual, en vez de agrupar mal.
 */
class SkuFamilyService {

    const SIZES = ['XXS', 'XS', 'S', 'M', 'L', 'XL', 'XXL', 'XXXL'];

    private $db;
    private $colorAbbrs = null;

    public function __construct($db) {
        $this->db = $db;
    }

    private function loadColorAbbrs() {
        if ($this->colorAbbrs !== null) return $this->colorAbbrs;
        $this->colorAbbrs = [];
        try {
            $stmt = $this->db->query('SELECT abbr FROM sku_color_codes');
            $stmt->execute();
            foreach ($stmt->fetchAll() as $row) {
                $this->colorAbbrs[strtoupper($row->abbr)] = true;
            }
        } catch (Exception $e) {
            // Tabla no existe todavía (migración no aplicada) — sin diccionario, todo queda sin auto-resolver.
        }
        return $this->colorAbbrs;
    }

    /**
     * Intenta resolver el SKU base y tipo de variante puramente por patrón.
     * Retorna ['base_sku' => string, 'variant_type' => string] o null si no pudo.
     */
    public function resolveByPattern($sku) {
        $sku = trim($sku);
        $variantType = 'individual';
        $colorsNeeded = 1;

        if (stripos($sku, 'TRIPACK-') === 0) {
            $sku = substr($sku, 8);
            $variantType = 'tripack';
            $colorsNeeded = 3;
        } elseif (stripos($sku, 'PACK-') === 0) {
            $sku = substr($sku, 5);
            $variantType = 'pack';
            $colorsNeeded = 2;
        }

        $tokens = explode('-', $sku);
        if (count($tokens) <= $colorsNeeded) {
            return null; // no quedan tokens suficientes para tener base + talla + colores
        }

        $lastToken = strtoupper(end($tokens));
        if (!in_array($lastToken, self::SIZES, true)) {
            return null; // el patrón asume que siempre termina en talla; si no, no adivinamos
        }
        array_pop($tokens);

        $colorAbbrs = $this->loadColorAbbrs();
        for ($i = 0; $i < $colorsNeeded; $i++) {
            $candidate = strtoupper(end($tokens));
            if (!isset($colorAbbrs[$candidate])) {
                return null; // color no reconocido en el diccionario -> no se adivina, queda pendiente
            }
            array_pop($tokens);
        }

        if (empty($tokens)) {
            return null;
        }

        return ['base_sku' => implode('-', $tokens), 'variant_type' => $variantType];
    }

    /**
     * Deriva el "código corto" de un SKU real: quita el color (si lo
     * reconoce en el diccionario) pero conserva marca, producto,
     * variación y talla. Pensado para cruzar contra archivos externos
     * que identifican el producto sin especificar el color explícito
     * (ej. "300-1-L" en vez de "RIP-300-1-AM-L"), algo válido en este
     * catálogo porque el número de variación ya identifica un color
     * específico de forma única dentro de cada producto.
     *
     * Retorna ['with_prefix' => 'RIP-300-1-L', 'no_prefix' => '300-1-L']
     * o null si no pudo identificar talla+color de forma confiable.
     */
    public function resolveShortcode($sku) {
        $sku = trim($sku);
        $tokens = explode('-', $sku);
        if (count($tokens) < 3) return null;

        $lastToken = strtoupper(end($tokens));
        if (!in_array($lastToken, self::SIZES, true)) return null;

        $colorToken = strtoupper($tokens[count($tokens) - 2]);
        $colorAbbrs = $this->loadColorAbbrs();
        if (!isset($colorAbbrs[$colorToken])) return null;

        $withoutColor = $tokens;
        array_splice($withoutColor, count($withoutColor) - 2, 1); // quita el color, deja el resto + talla
        $withPrefix = implode('-', $withoutColor);
        $noPrefix = implode('-', array_slice($withoutColor, 1)); // quita también el primer token (marca)

        if ($noPrefix === '') return null;

        return ['with_prefix' => $withPrefix, 'no_prefix' => $noPrefix];
    }

    /**
     * Construye un índice código-corto -> fila de caché para una lista de
     * productos. Si dos SKUs distintos derivan al mismo código corto,
     * se marca como ambiguo (false) en vez de quedarse con el primero que
     * encontró — mejor no adivinar que aplicar un match incorrecto.
     */
    public function buildShortcodeIndex(array $cacheRows) {
        $index = []; // key => row | false (false = ambiguo, más de un SKU cae en el mismo código corto)

        foreach ($cacheRows as $row) {
            $resolved = $this->resolveShortcode($row->sku);
            if ($resolved === null) continue;

            foreach ([$resolved['with_prefix'], $resolved['no_prefix']] as $key) {
                $key = strtoupper($key);
                if (!array_key_exists($key, $index)) {
                    $index[$key] = $row;
                } elseif ($index[$key] !== false && $index[$key]->sku !== $row->sku) {
                    $index[$key] = false; // dos SKUs distintos compiten por el mismo código corto -> ambiguo
                }
            }
        }

        return $index;
    }

    /** Retorna la fila de caché para un código corto, o 'ambiguous' si compite con otro SKU, o null si no hay match. */
    public function lookupShortcode($code, array $index) {
        $key = strtoupper(trim($code));
        if (!array_key_exists($key, $index)) return null;
        return $index[$key] === false ? 'ambiguous' : $index[$key];
    }

    /** Guarda (o actualiza, si no es manual) la resolución de un SKU. */
    public function upsert($sku, $baseSku, $variantType, $source = 'auto') {
        $stmt = $this->db->query('SELECT source FROM sku_family_map WHERE sku = ?');
        $stmt->execute([$sku]);
        $existing = $stmt->fetch();

        if ($existing && $existing->source === 'manual' && $source !== 'manual') {
            return; // nunca pisar una corrección manual con una resolución automática
        }

        $this->db->query(
            'INSERT INTO sku_family_map (sku, base_sku, variant_type, source)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE base_sku = VALUES(base_sku), variant_type = VALUES(variant_type), source = VALUES(source)'
        )->execute([$sku, $baseSku, $variantType, $source]);
    }

    /**
     * Reconstruye el índice automático a partir de todos los SKUs conocidos
     * en products_master + walmart_products_cache + ml_products_cache.
     * Correr después de sincronizar catálogos o crear productos nuevos.
     * Retorna ['resolved' => n, 'unresolved' => n].
     */
    public function rebuildIndex() {
        $skus = [];

        foreach ([
            'SELECT DISTINCT sku FROM products_master',
            'SELECT DISTINCT sku FROM walmart_products_cache',
            'SELECT DISTINCT sku FROM ml_products_cache',
        ] as $sql) {
            try {
                $stmt = $this->db->query($sql);
                $stmt->execute();
                foreach ($stmt->fetchAll() as $row) {
                    if (!empty($row->sku)) $skus[$row->sku] = true;
                }
            } catch (Exception $e) {
                // tabla no disponible en este entorno, se ignora
            }
        }

        $resolved = 0;
        $unresolved = 0;
        foreach (array_keys($skus) as $sku) {
            $r = $this->resolveByPattern($sku);
            if ($r === null) {
                $unresolved++;
                continue;
            }
            $this->upsert($sku, $r['base_sku'], $r['variant_type'], 'auto');
            $resolved++;
        }

        return ['resolved' => $resolved, 'unresolved' => $unresolved, 'total' => count($skus)];
    }

    /** Dado cualquier SKU de la familia (base o variante), retorna todos los miembros conocidos. */
    public function getFamily($sku) {
        $stmt = $this->db->query('SELECT base_sku FROM sku_family_map WHERE sku = ?');
        $stmt->execute([$sku]);
        $row = $stmt->fetch();

        // Si el SKU dado es directamente el base (o no está mapeado como variante),
        // se prueba también como base_sku directo.
        $baseSku = $row ? $row->base_sku : $sku;

        $stmt = $this->db->query('SELECT sku, variant_type, source FROM sku_family_map WHERE base_sku = ? ORDER BY variant_type, sku');
        $stmt->execute([$baseSku]);
        $members = $stmt->fetchAll();

        return ['base_sku' => $baseSku, 'members' => $members];
    }
}
