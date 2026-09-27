<?php

/**
 * Vínculo manual entre productos de distintas tiendas que son el mismo
 * producto físico pero no comparten SKU (a veces ni título). Curado a mano
 * por un admin desde /productcentral — sin matching automático/difuso.
 * Ver Docs/database/MIGRATION-2026-07-30-PRODUCT-CROSS-LINKS.sql.
 */
class ProductCrossLinkModel extends Model {

    /**
     * Misma fila "representante" por family_key que usa
     * ProductsCentralModel::getProducts() — el vínculo siempre apunta a la
     * fila que efectivamente se muestra en el Catálogo Central.
     */
    private const REPRESENTATIVE_SUBQUERY = "
        SELECT * FROM (
            SELECT t.*, ROW_NUMBER() OVER (
                PARTITION BY t.ml_connection_id, t.family_key
                ORDER BY (t.parent_ml_item_id IS NULL) DESC,
                         (t.sku_pack_type IS NULL) DESC,
                         (t.sku IS NULL OR t.sku = '') ASC,
                         t.id ASC
            ) AS rn
            FROM products_central t
        ) ranked WHERE rn = 1
    ";

    public function search($query, $excludeConnectionId = null, $limit = 20) {
        $limit = max(1, min(50, (int) $limit));
        $params = [];
        $where = 'WHERE 1=1';
        if ($query !== '') {
            $where .= ' AND (r.title LIKE ? OR r.sku LIKE ?)';
            $params[] = '%' . $query . '%';
            $params[] = '%' . $query . '%';
        }
        if ($excludeConnectionId) {
            $where .= ' AND r.ml_connection_id != ?';
            $params[] = (int) $excludeConnectionId;
        }

        return $this->rows(
            'SELECT r.ml_connection_id, r.family_key, r.title, r.sku, r.price, r.thumbnail,
                    c.store_name, c.ml_nickname
             FROM (' . self::REPRESENTATIVE_SUBQUERY . ') r
             LEFT JOIN ml_connections c ON c.id = r.ml_connection_id
             ' . $where . '
             ORDER BY r.title ASC
             LIMIT ' . $limit,
            $params
        );
    }

    public function findLinkForFamily($connectionId, $familyKey) {
        return $this->row(
            'SELECT link_id FROM product_cross_link_members WHERE ml_connection_id = ? AND family_key = ?',
            [(int) $connectionId, $familyKey]
        );
    }

    /**
     * $members = [['connection_id' => int, 'family_key' => string], ...],
     * al menos 2, de tiendas distintas, ninguno ya vinculado (sin merge
     * automático de vínculos existentes en esta primera versión).
     */
    public function createLink($createdBy, array $members) {
        if (count($members) < 2) {
            return ['ok' => false, 'error' => 'Se necesitan al menos 2 productos de tiendas distintas para vincular.'];
        }

        $connectionIds = array_unique(array_column($members, 'connection_id'));
        if (count($connectionIds) < 2) {
            return ['ok' => false, 'error' => 'Los productos deben ser de tiendas distintas.'];
        }

        foreach ($members as $m) {
            if ($this->findLinkForFamily($m['connection_id'], $m['family_key'])) {
                return ['ok' => false, 'error' => 'Uno de los productos ya tiene un vínculo. Quítalo primero.'];
            }
        }

        try {
            $this->db->beginTransaction();
            $linkId = $this->insertGetId(
                'INSERT INTO product_cross_links (created_by, created_at) VALUES (?, NOW())',
                [(int) $createdBy]
            );
            foreach ($members as $m) {
                $this->execute(
                    'INSERT INTO product_cross_link_members (link_id, ml_connection_id, family_key, added_by, added_at)
                     VALUES (?, ?, ?, ?, NOW())',
                    [$linkId, (int) $m['connection_id'], $m['family_key'], (int) $createdBy]
                );
            }
            $this->db->commit();
            return ['ok' => true, 'link_id' => $linkId];
        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log('[PRODUCT_CROSS_LINK] Error al crear vínculo: ' . $e->getMessage());
            return ['ok' => false, 'error' => 'Error al crear el vínculo.'];
        }
    }

    public function addMember($linkId, $connectionId, $familyKey, $userId) {
        if ($this->findLinkForFamily($connectionId, $familyKey)) {
            return ['ok' => false, 'error' => 'Ese producto ya tiene un vínculo.'];
        }
        try {
            $this->execute(
                'INSERT INTO product_cross_link_members (link_id, ml_connection_id, family_key, added_by, added_at)
                 VALUES (?, ?, ?, ?, NOW())',
                [(int) $linkId, (int) $connectionId, $familyKey, (int) $userId]
            );
            return ['ok' => true];
        } catch (\Exception $e) {
            error_log('[PRODUCT_CROSS_LINK] Error al agregar miembro: ' . $e->getMessage());
            return ['ok' => false, 'error' => 'Error al agregar el producto al vínculo.'];
        }
    }

    /**
     * Si el vínculo queda con menos de 2 miembros tras quitar este, se
     * borra completo (un vínculo de 1 solo miembro no tiene sentido).
     */
    public function removeMember($linkId, $connectionId, $familyKey) {
        try {
            $this->execute(
                'DELETE FROM product_cross_link_members WHERE link_id = ? AND ml_connection_id = ? AND family_key = ?',
                [(int) $linkId, (int) $connectionId, $familyKey]
            );
            $remaining = (int) $this->value(
                'SELECT COUNT(*) FROM product_cross_link_members WHERE link_id = ?',
                [(int) $linkId]
            );
            if ($remaining < 2) {
                $this->execute('DELETE FROM product_cross_links WHERE id = ?', [(int) $linkId]);
            }
            return ['ok' => true];
        } catch (\Exception $e) {
            error_log('[PRODUCT_CROSS_LINK] Error al quitar miembro: ' . $e->getMessage());
            return ['ok' => false, 'error' => 'Error al quitar el vínculo.'];
        }
    }

    public function getLinkMembers($linkId) {
        return $this->rows(
            'SELECT r.ml_connection_id, r.family_key, r.title, r.sku, r.price, r.thumbnail,
                    c.store_name, c.ml_nickname
             FROM product_cross_link_members m
             JOIN (' . self::REPRESENTATIVE_SUBQUERY . ') r
                ON r.ml_connection_id = m.ml_connection_id AND r.family_key = m.family_key
             LEFT JOIN ml_connections c ON c.id = m.ml_connection_id
             WHERE m.link_id = ?
             ORDER BY r.title ASC',
            [(int) $linkId]
        );
    }

    /**
     * Lookup en bloque para anotar una página de getProducts() sin N+1
     * queries. $pairs = [['connection_id' => X, 'family_key' => Y], ...].
     * Devuelve un mapa "connectionId:familyKey" => ['link_id', 'member_count'].
     */
    public function getLinksForFamilies(array $pairs) {
        if (empty($pairs)) return [];

        $conditions = [];
        $params = [];
        foreach ($pairs as $p) {
            $conditions[] = '(ml_connection_id = ? AND family_key = ?)';
            $params[] = (int) $p['connection_id'];
            $params[] = $p['family_key'];
        }

        $rows = $this->rows(
            'SELECT m.ml_connection_id, m.family_key, m.link_id,
                    (SELECT COUNT(*) FROM product_cross_link_members m2 WHERE m2.link_id = m.link_id) AS member_count
             FROM product_cross_link_members m
             WHERE ' . implode(' OR ', $conditions),
            $params
        );

        $map = [];
        foreach ($rows as $row) {
            $key = $row->ml_connection_id . ':' . $row->family_key;
            $map[$key] = ['link_id' => (int) $row->link_id, 'member_count' => (int) $row->member_count];
        }
        return $map;
    }

    /**
     * Un representante por (título, tienda) que todavía no tiene vínculo,
     * quedándose con el family_key menor si una misma tienda tuviera más de
     * un representante con ese título exacto (caso raro, ver
     * ProductsCentralModel::getFamilyMembers()/duplicados dentro de una
     * tienda — fuera de alcance de esta función).
     */
    private function perStoreUnlinkedSubquery() {
        return '
            SELECT r.title, r.ml_connection_id, MIN(r.family_key) AS family_key
            FROM (' . self::REPRESENTATIVE_SUBQUERY . ') r
            LEFT JOIN product_cross_link_members m
                ON m.ml_connection_id = r.ml_connection_id AND m.family_key = r.family_key
            WHERE m.link_id IS NULL
            GROUP BY r.title, r.ml_connection_id
        ';
    }

    /**
     * Análogo a perStoreUnlinkedSubquery() pero agrupando por SKU en vez de
     * título — para detectar el mismo producto físico publicado con SKUs
     * iguales pero títulos que difieren levemente entre tiendas. Excluye
     * SKU de pack/tripack (ya cubiertos vía family_key) y filas sin SKU.
     */
    private function skuPerStoreUnlinkedSubquery() {
        return '
            SELECT r.sku, r.ml_connection_id, MIN(r.family_key) AS family_key
            FROM (' . self::REPRESENTATIVE_SUBQUERY . ') r
            LEFT JOIN product_cross_link_members m
                ON m.ml_connection_id = r.ml_connection_id AND m.family_key = r.family_key
            WHERE m.link_id IS NULL AND r.sku IS NOT NULL AND r.sku != \'\' AND r.sku_pack_type IS NULL
            GROUP BY r.sku, r.ml_connection_id
        ';
    }

    /**
     * Query combinada (título + SKU) de grupos candidatos a sugerencia,
     * ya filtrados por "ignorados" (product_cross_link_dismissed) y por
     * mínimo de 2 tiendas. El grupo por SKU además exige que ese SKU no
     * aparezca con más de 2 títulos distintos entre tiendas — algunos
     * vendedores usan el campo SKU como texto genérico/placeholder
     * (ej. "GENÉRICO", "PRUEBA"), y sin este filtro esos SKU generarían
     * falsos positivos masivos.
     */
    private function suggestionGroupsSubquery() {
        return '
            SELECT \'title\' AS match_type, per_store.title AS match_value, COUNT(*) AS store_count
            FROM (' . $this->perStoreUnlinkedSubquery() . ') per_store
            LEFT JOIN product_cross_link_dismissed d
                ON d.match_type = \'title\' AND d.match_value = per_store.title
            WHERE d.match_value IS NULL
            GROUP BY per_store.title
            HAVING store_count >= 2

            UNION ALL

            SELECT \'sku\' AS match_type, sps.sku AS match_value, COUNT(*) AS store_count
            FROM (' . $this->skuPerStoreUnlinkedSubquery() . ') sps
            JOIN (' . self::REPRESENTATIVE_SUBQUERY . ') r3
                ON r3.ml_connection_id = sps.ml_connection_id AND r3.family_key = sps.family_key
            LEFT JOIN product_cross_link_dismissed d
                ON d.match_type = \'sku\' AND d.match_value = sps.sku
            WHERE d.match_value IS NULL
            GROUP BY sps.sku
            HAVING store_count >= 2 AND COUNT(DISTINCT r3.title) <= 2
        ';
    }

    /**
     * Cantidad de grupos candidatos a vincular (por título exacto o por
     * SKU compartido) en 2+ tiendas distintas, sin vínculo previo y sin
     * haber sido ignorados (ver dismissSuggestion()).
     */
    public function countSuggestions() {
        return (int) $this->value(
            'SELECT COUNT(*) FROM (' . $this->suggestionGroupsSubquery() . ') g'
        );
    }

    /**
     * Página de sugerencias de vínculo: grupos de productos que comparten
     * título exacto o SKU en 2+ tiendas distintas, ninguno vinculado
     * todavía. Cada grupo trae sus miembros (uno por tienda) listos para
     * pasarle directo a createLink() si el admin aprueba el grupo completo.
     */
    public function getSuggestions($limit = 20, $offset = 0) {
        $limit = max(1, min(50, (int) $limit));
        $offset = max(0, (int) $offset);

        $groups = $this->rows(
            'SELECT * FROM (' . $this->suggestionGroupsSubquery() . ') g
             ORDER BY store_count DESC, match_value ASC
             LIMIT ' . $limit . ' OFFSET ' . $offset
        );

        if (empty($groups)) return [];

        $titleValues = [];
        $skuValues = [];
        foreach ($groups as $g) {
            if ($g->match_type === 'title') {
                $titleValues[] = $g->match_value;
            } else {
                $skuValues[] = $g->match_value;
            }
        }

        $detailsByGroup = [];

        if (!empty($titleValues)) {
            $placeholders = implode(',', array_fill(0, count($titleValues), '?'));
            $rows = $this->rows(
                'SELECT per_store.title AS match_value, per_store.ml_connection_id, per_store.family_key,
                        r2.title, r2.sku, r2.price, r2.thumbnail, c.store_name, c.ml_nickname
                 FROM (' . $this->perStoreUnlinkedSubquery() . ') per_store
                 JOIN (' . self::REPRESENTATIVE_SUBQUERY . ') r2
                    ON r2.ml_connection_id = per_store.ml_connection_id AND r2.family_key = per_store.family_key
                 LEFT JOIN ml_connections c ON c.id = per_store.ml_connection_id
                 WHERE per_store.title IN (' . $placeholders . ')
                 ORDER BY per_store.title ASC',
                $titleValues
            );
            foreach ($rows as $d) {
                $detailsByGroup['title:' . $d->match_value][] = $d;
            }
        }

        if (!empty($skuValues)) {
            $placeholders = implode(',', array_fill(0, count($skuValues), '?'));
            $rows = $this->rows(
                'SELECT sps.sku AS match_value, sps.ml_connection_id, sps.family_key,
                        r2.title, r2.sku, r2.price, r2.thumbnail, c.store_name, c.ml_nickname
                 FROM (' . $this->skuPerStoreUnlinkedSubquery() . ') sps
                 JOIN (' . self::REPRESENTATIVE_SUBQUERY . ') r2
                    ON r2.ml_connection_id = sps.ml_connection_id AND r2.family_key = sps.family_key
                 LEFT JOIN ml_connections c ON c.id = sps.ml_connection_id
                 WHERE sps.sku IN (' . $placeholders . ')
                 ORDER BY sps.sku ASC',
                $skuValues
            );
            foreach ($rows as $d) {
                $detailsByGroup['sku:' . $d->match_value][] = $d;
            }
        }

        $result = [];
        foreach ($groups as $g) {
            $key = $g->match_type . ':' . $g->match_value;
            $members = [];
            foreach ($detailsByGroup[$key] ?? [] as $d) {
                $members[] = [
                    'connection_id' => (int) $d->ml_connection_id,
                    'family_key' => $d->family_key,
                    'title' => $d->title,
                    'sku' => $d->sku,
                    'price' => (float) $d->price,
                    'thumbnail' => $d->thumbnail,
                    'store_name' => $d->store_name ?: ($d->ml_nickname ?: '—'),
                ];
            }
            $result[] = [
                'match_type' => $g->match_type,
                'match_value' => $g->match_value,
                'store_count' => (int) $g->store_count,
                'members' => $members,
            ];
        }
        return $result;
    }

    public function dismissSuggestion($matchType, $matchValue, $userId) {
        $this->execute(
            'INSERT INTO product_cross_link_dismissed (match_type, match_value, dismissed_by, dismissed_at) VALUES (?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE dismissed_by = VALUES(dismissed_by), dismissed_at = NOW()',
            [$matchType, $matchValue, (int) $userId]
        );
        return ['ok' => true];
    }
}
