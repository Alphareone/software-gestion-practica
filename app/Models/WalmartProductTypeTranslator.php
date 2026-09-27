<?php

/**
 * Walmart entrega el "product_type" (categoría) siempre en inglés, tomado
 * directamente de su taxonomía interna. Como quien usa este software no
 * necesariamente maneja inglés, se traduce a español para mostrar.
 *
 * Se traduce solo lo que efectivamente se ha visto en el catálogo real;
 * cualquier valor nuevo que Walmart use y no esté aquí simplemente se
 * muestra tal cual llega (en inglés) en vez de romper la pantalla — mejor
 * eso que ocultar información. Agregar una traducción nueva es solo
 * sumar una línea a este arreglo.
 */
class WalmartProductTypeTranslator {

    private static $map = [
        'Hospital Gowns' => 'Batas hospitalarias',
        'Surgical Gowns' => 'Batas quirúrgicas',
        'Clothing Protectors & Adult Bibs' => 'Protectores de ropa y baberos para adultos',
        'Scrub Tops' => 'Chaquetas de uniforme clínico',
        'Scrub Pants' => 'Pantalones de uniforme clínico',
        'Scrub Sets' => 'Conjuntos de uniforme clínico',
        'Lab Coats' => 'Cotonas / delantales de laboratorio',
        'Face Masks' => 'Mascarillas',
        'Disposable Gowns' => 'Batas desechables',
        'Shoe Covers' => 'Cubrecalzado',
        'Head Covers' => 'Cubrecabezas / gorros clínicos',
    ];

    public static function translate($productType) {
        if ($productType === null || $productType === '') return '-';
        return self::$map[$productType] ?? $productType;
    }
}
