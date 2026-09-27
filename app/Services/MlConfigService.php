<?php

class MlConfigService {

    public static function getAppId($db) {
        $model = new AppSettingModel($db);
        return $model->get('ml_app_id') ?: '';
    }

    public static function getClientSecret($db) {
        $model = new AppSettingModel($db);
        $encrypted = $model->get('ml_client_secret');
        if ($encrypted) {
            try {
                return MercadoLibreAuthService::decrypt($encrypted);
            } catch (Exception $e) {
                error_log('[MlConfig] Error descifrando client_secret: ' . $e->getMessage());
            }
        }
        return '';
    }

    public static function hasCustomCredentials($db) {
        $model = new AppSettingModel($db);
        return (bool) $model->get('ml_app_id');
    }

    public static function saveCredentials($db, $appId, $clientSecret) {
        $model = new AppSettingModel($db);
        $model->set('ml_app_id', trim($appId));
        if (trim($clientSecret)) {
            $encrypted = MercadoLibreAuthService::encrypt(trim($clientSecret));
            $model->set('ml_client_secret', $encrypted);
        }
    }

    public static function clearCredentials($db) {
        $model = new AppSettingModel($db);
        $model->set('ml_app_id', '');
        $model->set('ml_client_secret', '');
    }

    public static function areCredentialsConfigured($db) {
        $appId = self::getAppId($db);
        $secret = self::getClientSecret($db);
        return !empty($appId) && !empty($secret);
    }
}
