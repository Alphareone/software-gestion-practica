<?php

class ColumnMapper {

    public static function defaultTemplateSynonyms(): array {
        return [
            'sku'            => ['sku', 'codigo', 'cod', 'id', 'referencia', 'código', 'ref', 'article', 'articulo', 'cód', 'modelo', 'item code', 'codigo_producto', 'codigo de producto', 'cod producto', 'codigo art', 'cod articulo'],
            'name'           => ['nombre', 'name', 'producto', 'titulo', 'title', 'descripcion', 'product', 'prod', 'denominacion', 'articulo', 'descripcion del articulo', 'título'],
            'description'    => ['descripcion', 'description', 'detalle', 'descripción', 'texto', 'comentarios', 'observaciones', 'caracteristicas', 'especificaciones'],
            'short_desc'     => ['descripcion_corta', 'short_description', 'resumen', 'extracto', 'descripción corta'],
            'price'          => ['precio', 'price', 'precio_normal', 'valor', 'costo', 'precio venta', 'pvp', 'precio lista', 'precio unitario', 'precio de venta', 'neto', 'precio costo', 'precio final', 'precio 1', 'mercado libre final redondeo', 'ml final redondeo', 'precio ml', 'precio mercado libre', 'final redondeo'],
            'stock'          => ['stock', 'inventario', 'inventory', 'cantidad', 'qty', 'quantity', 'inventario final', 'existencia', 'disponible', 'saldo', 'inventario actual', 'unidades'],
            'category'       => ['categoria', 'category', 'categorias', 'categories', 'departamento', 'cat', 'linea', 'línea', 'familia', 'subfamilia', 'rubro', 'seccion', 'grupo', 'linea de producto', 'tipo de producto'],
            'brand'          => ['marca', 'brand', 'marcas', 'marca producto', 'proveedor', 'fabricante', 'make'],
            'gender'         => ['genero', 'gender', 'sexo', 'publico', 'target', 'para'],
            'color'          => ['color', 'colour', 'colors', 'tono', 'colores'],
            'size'           => ['talla', 'size', 'talle', 'medida', 'medidas', 'tamaño', 'tamano', 'volumen', 'unidad medida', 'tallas'],
            'ean'            => ['ean', 'gtin', 'upc', 'isbn', 'codigo_universal', 'codigo_barra', 'barcode', 'código universal', 'codigo de barras', 'codigo barras'],
            'images'         => ['imagen', 'image', 'imagenes', 'images', 'foto', 'fotos', 'url_imagen', 'photo', 'img', 'url imagen'],
            'weight'         => ['peso', 'weight', 'peso_kg', 'kilogramos', 'kg'],
            'length'         => ['largo', 'length', 'longitud', 'profundidad'],
            'width'          => ['ancho', 'width', 'anchura'],
            'height'         => ['alto', 'height', 'altura'],
            'type'           => ['tipo', 'type', 'tipo_producto', 'clase'],
        ];
    }

    public static function normalizeHeader(string $header): string {
        $header = trim($header);
        $header = str_replace(['á','é','í','ó','ú','ñ','ü','Á','É','Í','Ó','Ú','Ñ','Ü'],
                              ['a','e','i','o','u','n','u','A','E','I','O','U','N','U'], $header);
        $header = preg_replace('/[^a-z0-9_ ]/i', '', $header);
        $header = mb_strtolower(trim($header));
        $header = preg_replace('/\s+/', ' ', $header);
        return $header;
    }

    public static function headerContains(string $header, string $keyword): bool {
        $h = self::normalizeHeader($header);
        $k = self::normalizeHeader($keyword);
        return strpos($h, $k) !== false || strpos($k, $h) !== false;
    }

    public static function buildMapFromRow(array $headers): array {
        $map = [];
        foreach ($headers as $idx => $header) {
            if ($header === null || trim((string)$header) === '') continue;
            $key = self::normalizeHeader((string)$header);
            $map[$key] = $idx;
        }
        return $map;
    }

    public static function generateEan13(): string {
        $d12 = [random_int(1, 9)];
        for ($i = 0; $i < 11; $i++) {
            $d12[] = random_int(0, 9);
        }
        $s = 0;
        for ($i = 0; $i < 12; $i++) {
            $s += ($i % 2 === 0) ? $d12[$i] : 3 * $d12[$i];
        }
        $cd = (10 - ($s % 10)) % 10;
        return implode('', $d12) . $cd;
    }

    public static function generateEan13Massive(int $n, bool $unique = false): array {
        if (!$unique) {
            $res = [];
            for ($i = 0; $i < $n; $i++) {
                $res[] = self::generateEan13();
            }
            return $res;
        }
        $res = [];
        $seen = [];
        while (count($res) < $n) {
            $code = self::generateEan13();
            if (!isset($seen[$code])) {
                $seen[$code] = true;
                $res[] = $code;
            }
        }
        return $res;
    }

    public static function detectHeaderRow(array $rows, int $maxSearch = 20): int {
        $synonyms = self::defaultTemplateSynonyms();
        $allKeys = [];
        foreach ($synonyms as $field => $aliases) {
            $allKeys = array_merge($allKeys, $aliases);
        }
        $allKeys = array_unique($allKeys);

        $bestRow = 0;
        $bestScore = 0;

        $limit = min($maxSearch, count($rows));
        for ($r = 0; $r < $limit; $r++) {
            if (!isset($rows[$r]) || !is_array($rows[$r])) continue;
            $score = 0;
            foreach ($rows[$r] as $cell) {
                if ($cell === null || trim((string)$cell) === '') continue;
                $normalized = self::normalizeHeader((string)$cell);
                foreach ($allKeys as $key) {
                    $nk = self::normalizeHeader($key);
                    if ($normalized === $nk || strpos($normalized, $nk) !== false) {
                        $score++;
                        break;
                    }
                }
            }
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestRow = $r;
            }
        }

        return $bestRow;
    }
}
