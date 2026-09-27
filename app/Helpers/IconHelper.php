<?php

function icon(string $name, array $opts = []): string
{
    $size = $opts['size'] ?? 18;
    $class = $opts['class'] ?? '';
    $stroke = $opts['stroke'] ?? 2;
    $id = $opts['id'] ?? '';

    $attrs = ' width="' . (int)$size . '" height="' . (int)$size . '"';
    $attrs .= ' viewBox="0 0 24 24" fill="none" stroke="currentColor"';
    $attrs .= ' stroke-width="' . (int)$stroke . '" stroke-linecap="round" stroke-linejoin="round"';
    if ($id) {
        $attrs .= ' id="' . htmlspecialchars($id, ENT_QUOTES) . '"';
    }
    if ($class) {
        $attrs .= ' class="' . htmlspecialchars($class, ENT_QUOTES) . '"';
    }


    $href = URLROOT . '/assets/icons/sprite.svg#icon-' . htmlspecialchars($name, ENT_QUOTES);

    return '<svg' . $attrs . '><use href="' . $href . '"/></svg>';
}