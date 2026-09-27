<?php

/**
 * Cifrado simétrico compartido (AES-256-CBC con ENCRYPTION_KEY).
 * Mismo formato que MercadoLibreAuthService::encrypt/decrypt:
 * base64(iv . cipher) — los valores cifrados son intercambiables entre ambos.
 */
class CryptoHelper {

    public static function encrypt($plaintext) {
        $key = hex2bin(ENCRYPTION_KEY);
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length(ENCRYPTION_CIPHER));
        $cipher = openssl_encrypt($plaintext, ENCRYPTION_CIPHER, $key, OPENSSL_RAW_DATA, $iv);
        return base64_encode($iv . $cipher);
    }

    public static function decrypt($encoded) {
        if (empty($encoded)) return '';
        $key = hex2bin(ENCRYPTION_KEY);
        $data = base64_decode($encoded);
        $ivLen = openssl_cipher_iv_length(ENCRYPTION_CIPHER);
        $iv = substr($data, 0, $ivLen);
        $cipher = substr($data, $ivLen);
        return openssl_decrypt($cipher, ENCRYPTION_CIPHER, $key, OPENSSL_RAW_DATA, $iv);
    }
}
