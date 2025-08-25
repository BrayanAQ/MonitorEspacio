<?php
class Database {
    private $pdo;

    public function __construct($host, $db, $user, $pass) {
        try {
            $this->pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            die("❌ Error de conexión: " . $e->getMessage());
        }
    }

    public function getConnection() {
        return $this->pdo;
    }
}
