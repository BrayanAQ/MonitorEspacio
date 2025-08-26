<?php
class Database {
    private $pdo;
    private $dbName;
    private $host;
    private $user;
    private $connectionOptions;

    public function __construct($host, $db, $user, $pass, $options = []) {
        $this->host = $host;
        $this->dbName = $db;
        $this->user = $user;

        // Validar parámetros de entrada
        $this->validateConnectionParams($host, $db, $user, $pass);

        // Configuración por defecto mejorada
        $this->connectionOptions = array_merge([
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_PERSISTENT => false,
            PDO::ATTR_TIMEOUT => 10,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
            PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false
        ], $options);

        try {
            $this->connect($host, $db, $user, $pass);
        } catch (PDOException $e) {
            error_log("Database PDO Error: " . $e->getMessage());
            throw new Exception("Connection failed: Unable to connect to database server");
        } catch (Exception $e) {
            error_log("Database General Error: " . $e->getMessage());
            throw new Exception("Database error: " . $e->getMessage());
        }
    }

    /**
     * Establece la conexión a la base de datos
     */
    private function connect($host, $db, $user, $pass) {
        // Construir DSN con opciones de seguridad
        $dsn = $this->buildDSN($host, $db);

        // Crear conexión PDO
        $this->pdo = new PDO($dsn, $user, $pass, $this->connectionOptions);

        // Configuraciones adicionales post-conexión
        $this->configureConnection();

        // Verificar que la base de datos esté correctamente seleccionada
        $this->verifyDatabaseSelection($db);
    }

    /**
     * Construye el DSN de conexión
     */
    private function buildDSN($host, $db) {
        // Validar y limpiar el host
        $host = $this->sanitizeHost($host);

        $dsn = "mysql:host={$host};dbname=" . $this->sanitizeDatabaseName($db) . ";charset=utf8mb4";

        // Agregar opciones adicionales al DSN
        $dsn .= ";connect_timeout=10";

        return $dsn;
    }

    /**
     * Configura la conexión después de establecerla
     */
    private function configureConnection() {
        // Configurar zona horaria
        $this->pdo->exec("SET time_zone = '+00:00'");

        // Configurar modo SQL más estricto
        $this->pdo->exec("SET sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO'");

        // Configurar encoding
        $this->pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");

        // Configurar timeouts de sesión
        $this->pdo->exec("SET SESSION wait_timeout = 300");
        $this->pdo->exec("SET SESSION interactive_timeout = 300");
    }

    /**
     * Verifica que la base de datos esté correctamente seleccionada
     */
    private function verifyDatabaseSelection($expectedDb) {
        try {
            $stmt = $this->pdo->prepare("SELECT DATABASE() as current_db");
            $stmt->execute();
            $result = $stmt->fetch();

            $currentDb = $result['current_db'] ?? null;

            if (empty($currentDb)) {
                throw new Exception("No database selected after connection");
            }

            if ($currentDb !== $expectedDb) {
                throw new Exception("Database mismatch. Expected: {$expectedDb}, Got: {$currentDb}");
            }

            // Verificar que tenemos permisos básicos
            $this->verifyPermissions();

        } catch (PDOException $e) {
            throw new Exception("Failed to verify database selection: " . $e->getMessage());
        }
    }

    /**
     * Verifica permisos básicos en la base de datos
     */
    private function verifyPermissions() {
        try {
            // Intentar una consulta básica para verificar permisos de lectura
            $stmt = $this->pdo->prepare("SHOW TABLES LIMIT 1");
            $stmt->execute();

            // Verificar acceso a information_schema
            $stmt = $this->pdo->prepare("SELECT COUNT(*) as count FROM information_schema.tables WHERE table_schema = ?");
            $stmt->execute([$this->dbName]);
            $result = $stmt->fetch();

            if ($result['count'] === null) {
                throw new Exception("Cannot access information_schema for database: " . $this->dbName);
            }

        } catch (PDOException $e) {
            throw new Exception("Insufficient database permissions: " . $e->getMessage());
        }
    }

    /**
     * Valida los parámetros de conexión
     */
    private function validateConnectionParams($host, $db, $user, $pass) {
        if (empty($host)) {
            throw new InvalidArgumentException("Host cannot be empty");
        }

        if (empty($db)) {
            throw new InvalidArgumentException("Database name cannot be empty");
        }

        if (empty($user)) {
            throw new InvalidArgumentException("Username cannot be empty");
        }

        // Validar formato del nombre de base de datos
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $db)) {
            throw new InvalidArgumentException("Invalid database name format: " . $db);
        }

        // Validar longitud
        if (strlen($db) > 64) {
            throw new InvalidArgumentException("Database name too long: " . $db);
        }

        if (strlen($user) > 32) {
            throw new InvalidArgumentException("Username too long: " . $user);
        }
    }

    /**
     * Sanitiza el nombre del host
     */
    private function sanitizeHost($host) {
        // Eliminar espacios y caracteres peligrosos
        $host = trim($host);

        // Validar formato básico (IP, localhost, o nombre de dominio)
        if (!filter_var($host, FILTER_VALIDATE_IP) &&
            !preg_match('/^[a-zA-Z0-9.-]+$/', $host)) {
            throw new InvalidArgumentException("Invalid host format: " . $host);
        }

        return $host;
    }

    /**
     * Sanitiza el nombre de la base de datos
     */
    private function sanitizeDatabaseName($dbName) {
        return preg_replace('/[^a-zA-Z0-9_]/', '', $dbName);
    }

    /**
     * Obtiene la conexión PDO
     */
    public function getConnection() {
        if (!$this->pdo) {
            throw new RuntimeException("Database connection not established");
        }

        // Verificar que la conexión esté activa
        try {
            $this->pdo->query("SELECT 1");
        } catch (PDOException $e) {
            throw new RuntimeException("Database connection lost: " . $e->getMessage());
        }

        return $this->pdo;
    }

    /**
     * Obtiene el nombre de la base de datos
     */
    public function getDatabaseName() {
        return $this->dbName;
    }

    /**
     * Obtiene información de la conexión
     */
    public function getConnectionInfo() {
        return [
            'host' => $this->host,
            'database' => $this->dbName,
            'user' => $this->user,
            'connected' => $this->isConnected()
        ];
    }

    /**
     * Verifica si la conexión está activa
     */
    public function isConnected() {
        try {
            if (!$this->pdo) {
                return false;
            }

            $stmt = $this->pdo->prepare("SELECT 1");
            $stmt->execute();
            return true;

        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Reconecta a la base de datos si es necesario
     */
    public function reconnect() {
        try {
            if (!$this->isConnected()) {
                // Aquí necesitarías almacenar la contraseña de forma segura
                // o implementar un mecanismo de reconexión diferente
                throw new RuntimeException("Cannot reconnect: password not stored for security reasons");
            }
        } catch (Exception $e) {
            throw new RuntimeException("Reconnection failed: " . $e->getMessage());
        }
    }

    /**
     * Cierra la conexión
     */
    public function close() {
        $this->pdo = null;
    }

    /**
     * Inicia una transacción
     */
    public function beginTransaction() {
        if (!$this->pdo->inTransaction()) {
            return $this->pdo->beginTransaction();
        }
        return false;
    }

    /**
     * Confirma una transacción
     */
    public function commit() {
        if ($this->pdo->inTransaction()) {
            return $this->pdo->commit();
        }
        return false;
    }

    /**
     * Deshace una transacción
     */
    public function rollback() {
        if ($this->pdo->inTransaction()) {
            return $this->pdo->rollback();
        }
        return false;
    }

    /**
     * Obtiene estadísticas de la conexión
     */
    public function getConnectionStats() {
        try {
            $stmt = $this->pdo->prepare("SHOW STATUS WHERE Variable_name IN ('Connections', 'Uptime', 'Threads_connected')");
            $stmt->execute();
            $stats = [];

            while ($row = $stmt->fetch()) {
                $stats[strtolower($row['Variable_name'])] = $row['Value'];
            }

            return $stats;

        } catch (PDOException $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Destructor - limpia la conexión
     */
    public function __destruct() {
        $this->close();
    }
}