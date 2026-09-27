<?php

class AppSettingModel extends Model {

    public function get($key, $default = null) {
        $row = $this->row('SELECT setting_value FROM app_settings WHERE setting_key = ?', [$key]);
        return $row ? $row->setting_value : $default;
    }

    public function getAll() {
        return $this->rows('SELECT setting_key, setting_value, description FROM app_settings ORDER BY id');
    }

    public function getAllAssoc() {
        $rows = $this->getAll();
        $result = [];
        foreach ($rows as $r) {
            $result[$r->setting_key] = $r;
        }
        return $result;
    }

    public function set($key, $value) {
        return $this->execute(
            'INSERT INTO app_settings (setting_key, setting_value, description)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
            [$key, $value, '']
        );
    }

    public function getInt($key, $default = 0) {
        return (int) $this->get($key, $default);
    }
}
