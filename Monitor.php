<?php
class Monitor {
    private $pdo;

    public function __construct(Database $database) {
        $this->pdo = $database->getConnection();
    }

    public function getDatabaseSize() {
        $stmt = $this->pdo->query("
            SELECT table_schema AS db, 
                   ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
            GROUP BY table_schema;
        ");
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getActiveConnections() {
        $stmt = $this->pdo->query("SHOW PROCESSLIST");
        return $stmt->rowCount();
    }

    public function estimateSaturationTime() {
        $size = $this->getDatabaseSize()['size_mb'] ?? 0;
        $connections = $this->getActiveConnections();

        if ($size > 500 || $connections > 100) {
            return "⚠️ Posible saturación pronto (espacio/conexiones altas)";
        } else {
            return "✅ Base de datos estable";
        }
    }
}
