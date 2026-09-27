<?php
class Model {
    protected $db;

    public function __construct($db = null) {
        if ($db !== null) {
            $this->db = $db;
        } else {
            $this->db = new Database();
        }
    }

    protected function query($sql) {
        return $this->db->query($sql);
    }

    protected function row($sql, $params = []) {
        $stmt = $this->query($sql);
        $stmt->execute($params);
        return $stmt->fetch();
    }

    protected function rows($sql, $params = []) {
        $stmt = $this->query($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    protected function value($sql, $params = []) {
        $stmt = $this->query($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ? current((array) $row) : null;
    }

    protected function execute($sql, $params = []) {
        $stmt = $this->query($sql);
        $stmt->execute($params);
        return $stmt;
    }

    protected function insertGetId($sql, $params = []) {
        $this->execute($sql, $params);
        return $this->db->lastInsertId();
    }
}
