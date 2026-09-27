<?php

class MarketplaceFactory {
    public static function make($provider, $db = null): MarketplaceInterface {
        $dbInstance = $db ?: new Database();
        switch (strtolower($provider)) {
            case 'mercadolibre':
                return new MercadoLibreAdapter($dbInstance);
            case 'cencosud':
                return new CencosudAdapter($dbInstance);
            default:
                throw new InvalidArgumentException("Proveedor de Marketplace no soportado: $provider");
        }
    }
}
