<?php

class MlApiHelper {

    public static function mlApi($endpoint, $method = 'GET', $data = null, $token = null, $rateLimiter = null) {
        if (strtoupper($method) === 'PUT') {
            return ['code' => 503, 'body' => json_decode(json_encode(['error' => 'Las modificaciones a MercadoLibre están deshabilitadas.'])), 'raw' => ''];
        }

        if ($rateLimiter) {
            $waitSeconds = $rateLimiter->checkAndWait();
            
            if ($waitSeconds > 0) {
                usleep($waitSeconds * 1000000);
            }
        }
        
        $url = 'https://api.mercadolibre.com' . $endpoint;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

        $headers = ['Accept: application/json'];
        if ($token) {
            $headers[] = 'Authorization: Bearer ' . $token;
        }
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data) {
                $payload = is_string($data) ? $data : http_build_query($data);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
                if (is_string($data)) {
                    $headers[] = 'Content-Type: application/json';
                } else {
                    $headers[] = 'Content-Type: application/x-www-form-urlencoded';
                }
            }
        } elseif ($method === 'PUT') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
                $headers[] = 'Content-Type: application/json';
            }
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $decoded = json_decode($response);
        return ['code' => $httpCode, 'body' => $decoded, 'raw' => $response];
    }

    public static function extractSku($item) {
        if (!empty($item->seller_custom_field)) {
            return trim($item->seller_custom_field);
        }
        if (!empty($item->attributes)) {
            $fallback = '';
            foreach ($item->attributes as $attr) {
                if (in_array($attr->id ?? '', ['SELLER_SKU', 'SELLER_SKU_', 'SELLER_SKU_OTH'])) {
                    $v = trim($attr->value_name ?? $attr->values[0]->name ?? '');
                    if ($v) return $v;
                }
                if (in_array($attr->id ?? '', ['SKU', 'MODEL'])) {
                    $v = trim($attr->value_name ?? $attr->values[0]->name ?? '');
                    if ($v && !$fallback) $fallback = $v;
                }
            }
            if ($fallback) return $fallback;
        }
        if (!empty($item->variations)) {
            foreach ($item->variations as $variation) {
                $attrs = $variation->attributes ?? $variation->attribute_combinations ?? [];
                $fallback = '';
                foreach ($attrs as $attr) {
                    if (in_array($attr->id ?? '', ['SELLER_SKU', 'SELLER_SKU_', 'SELLER_SKU_OTH'])) {
                        $v = trim($attr->value_name ?? $attr->name ?? '');
                        if ($v) return $v;
                    }
                    if (in_array($attr->id ?? '', ['SKU', 'MODEL'])) {
                        $v = trim($attr->value_name ?? $attr->name ?? '');
                        if ($v && !$fallback) $fallback = $v;
                    }
                }
                if ($fallback) return $fallback;
            }
        }
        return '';
    }

}
