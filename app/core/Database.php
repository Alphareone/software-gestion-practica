<?php
class Database {
    private $host = DB_HOST;
    private $port = DB_PORT;
    private $user = DB_USER;
    private $pass = DB_PASS;
    private $dbname = DB_NAME;

    private static $sharedPdo = null;

    private $dbh; // Database Handler
    private $error;

    public function __construct() {
        $dsn = 'mysql:host=' . $this->host . ';port=' . $this->port . ';dbname=' . $this->dbname . ';charset=utf8mb4';
        $options = [
            PDO::ATTR_PERSISTENT => false,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ
        ];

        try {
            if (self::$sharedPdo === null) {
                self::$sharedPdo = new PDO($dsn, $this->user, $this->pass, $options);
                // Forzar timezone UTC para consistencia con PHP
                self::$sharedPdo->exec("SET time_zone = '+00:00'");
            }
            $this->dbh = self::$sharedPdo;
        } catch (PDOException $e) {
            $this->error = $e->getMessage();
            error_log("Error de conexión a la base de datos: " . $this->error);
            die("Error de conexión a la base de datos. Por favor, inténtalo de nuevo más tarde.");
        }
    }

    // Método para preparar la consulta
    public function query($sql) {
        return $this->dbh->prepare($sql);
    }

    public function lastInsertId() {
        return $this->dbh->lastInsertId();
    }

    public function beginTransaction() {
        return $this->dbh->beginTransaction();
    }

    public function commit() {
        return $this->dbh->commit();
    }

    public function rollBack() {
        return $this->dbh->rollBack();
    }
}