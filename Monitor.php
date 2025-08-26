<?php
class Monitor {
    private $pdo;

    public function __construct(Database $database) {
        $this->pdo = $database->getConnection();
    }

    public function getDatabaseSize() {
        try {
            $stmt = $this->pdo->query("
                SELECT table_schema AS db, 
                       ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb
                FROM information_schema.tables
                WHERE table_schema = DATABASE()
                GROUP BY table_schema
            ");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ?: ['db' => 'Unknown', 'size_mb' => 0];
        } catch (Exception $e) {
            return ['db' => 'Error', 'size_mb' => 0];
        }
    }

    public function getActiveConnections() {
        try {
            $stmt = $this->pdo->query("SHOW PROCESSLIST");
            return $stmt->rowCount();
        } catch (Exception $e) {
            return 0;
        }
    }

    public function estimateSaturationTime() {
        try {
            $size = $this->getDatabaseSize()['size_mb'] ?? 0;
            $connections = $this->getActiveConnections();

            if ($size > 500 || $connections > 100) {
                return "⚠️ Possible saturation soon (high space/connections)";
            } else {
                return "✅ Database stable";
            }
        } catch (Exception $e) {
            return "❌ Unable to determine status";
        }
    }

    public function getTableStatistics() {
        try {
            $stmt = $this->pdo->query("
                SELECT 
                    table_name,
                    COALESCE(table_rows, 0) as rows,
                    ROUND(((data_length + index_length) / 1024 / 1024), 2) as size_mb
                FROM information_schema.tables
                WHERE table_schema = DATABASE()
                AND table_type = 'BASE TABLE'
                ORDER BY (data_length + index_length) DESC
                LIMIT 20
            ");
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Debug: Log results
            error_log("Table Statistics Query Results: " . print_r($results, true));

            return $results;
        } catch (Exception $e) {
            error_log("Error in getTableStatistics: " . $e->getMessage());
            return [];
        }
    }

    public function getServerInfo() {
        try {
            $info = [];

            // MySQL Version
            $stmt = $this->pdo->query("SELECT VERSION() as version");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $info['version'] = $result['version'] ?? 'Unknown';

            // Server uptime
            $stmt = $this->pdo->query("SHOW STATUS LIKE 'Uptime'");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $uptime_seconds = $result['Value'] ?? 0;
            $info['uptime'] = $this->formatUptime($uptime_seconds);

            // Data directory
            $stmt = $this->pdo->query("SHOW VARIABLES LIKE 'datadir'");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $info['datadir'] = $result['Value'] ?? 'Unknown';

            // Max connections
            $stmt = $this->pdo->query("SHOW VARIABLES LIKE 'max_connections'");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $info['max_connections'] = $result['Value'] ?? 'Unknown';

            return $info;
        } catch (Exception $e) {
            return [
                'version' => 'Error',
                'uptime' => 'Error',
                'datadir' => 'Error',
                'max_connections' => 'Error'
            ];
        }
    }

    public function getDiskSpaceInfo() {
        try {
            // Get data directory
            $stmt = $this->pdo->query("SHOW VARIABLES LIKE 'datadir'");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $datadir = $result['Value'] ?? '/var/lib/mysql/';

            if (function_exists('disk_free_space') && function_exists('disk_total_space')) {
                $free_bytes = disk_free_space($datadir);
                $total_bytes = disk_total_space($datadir);

                if ($free_bytes !== false && $total_bytes !== false) {
                    return [
                        'free_mb' => round($free_bytes / 1024 / 1024, 2),
                        'total_mb' => round($total_bytes / 1024 / 1024, 2),
                        'used_mb' => round(($total_bytes - $free_bytes) / 1024 / 1024, 2),
                        'usage_percent' => round((($total_bytes - $free_bytes) / $total_bytes) * 100, 2)
                    ];
                }
            }

            return [
                'free_mb' => 0,
                'total_mb' => 0,
                'used_mb' => 0,
                'usage_percent' => 0
            ];
        } catch (Exception $e) {
            return [
                'free_mb' => 0,
                'total_mb' => 0,
                'used_mb' => 0,
                'usage_percent' => 0
            ];
        }
    }

    public function getPerformanceMetrics() {
        try {
            $metrics = [];

            // Query performance metrics
            $performance_queries = [
                'slow_queries' => "SHOW STATUS LIKE 'Slow_queries'",
                'questions' => "SHOW STATUS LIKE 'Questions'",
                'uptime' => "SHOW STATUS LIKE 'Uptime'",
                'threads_connected' => "SHOW STATUS LIKE 'Threads_connected'",
                'threads_running' => "SHOW STATUS LIKE 'Threads_running'",
                'innodb_buffer_pool_reads' => "SHOW STATUS LIKE 'Innodb_buffer_pool_reads'",
                'innodb_buffer_pool_read_requests' => "SHOW STATUS LIKE 'Innodb_buffer_pool_read_requests'"
            ];

            foreach ($performance_queries as $key => $query) {
                try {
                    $stmt = $this->pdo->query($query);
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    $metrics[$key] = intval($result['Value'] ?? 0);
                } catch (Exception $e) {
                    $metrics[$key] = 0;
                }
            }

            // Calculate derived metrics
            if ($metrics['questions'] > 0 && $metrics['uptime'] > 0) {
                $metrics['queries_per_second'] = round($metrics['questions'] / $metrics['uptime'], 2);
            } else {
                $metrics['queries_per_second'] = 0;
            }

            // Buffer pool hit ratio
            if ($metrics['innodb_buffer_pool_read_requests'] > 0) {
                $hit_ratio = (($metrics['innodb_buffer_pool_read_requests'] - $metrics['innodb_buffer_pool_reads']) / $metrics['innodb_buffer_pool_read_requests']) * 100;
                $metrics['buffer_pool_hit_ratio'] = round($hit_ratio, 2);
            } else {
                $metrics['buffer_pool_hit_ratio'] = 0;
            }

            // Debug: Log metrics
            error_log("Performance Metrics: " . print_r($metrics, true));

            return $metrics;
        } catch (Exception $e) {
            error_log("Error in getPerformanceMetrics: " . $e->getMessage());
            return [
                'slow_queries' => 0,
                'questions' => 0,
                'uptime' => 0,
                'threads_connected' => 0,
                'threads_running' => 0,
                'queries_per_second' => 0,
                'buffer_pool_hit_ratio' => 0
            ];
        }
    }

    // Nueva función para calcular estimación de tiempo hasta llenarse
    public function getStoragePrediction() {
        try {
            $currentSize = $this->getDatabaseSize()['size_mb'] ?? 0;
            $maxCapacity = 1000; // MB - puedes ajustar según tu servidor

            // Obtener historial de crecimiento (simulado aquí, idealmente desde una tabla de log)
            $stmt = $this->pdo->query("
                SELECT 
                    COUNT(*) as total_records,
                    COALESCE(SUM(data_length + index_length), 0) as total_size
                FROM information_schema.tables 
                WHERE table_schema = DATABASE()
            ");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            // Estimación simple basada en el crecimiento promedio
            // En un entorno real, guardarías mediciones históricas
            $estimatedDailyGrowth = max($currentSize * 0.01, 0.1); // 1% diario mínimo 0.1 MB
            $remainingSpace = $maxCapacity - $currentSize;

            if ($remainingSpace <= 0) {
                return [
                    'days_until_full' => 0,
                    'estimated_full_date' => 'Base de datos llena',
                    'daily_growth_mb' => $estimatedDailyGrowth,
                    'remaining_space_mb' => 0
                ];
            }

            $daysUntilFull = round($remainingSpace / $estimatedDailyGrowth);
            $estimatedFullDate = date('Y-m-d', strtotime("+{$daysUntilFull} days"));

            return [
                'days_until_full' => $daysUntilFull,
                'estimated_full_date' => $estimatedFullDate,
                'daily_growth_mb' => round($estimatedDailyGrowth, 2),
                'remaining_space_mb' => round($remainingSpace, 2)
            ];
        } catch (Exception $e) {
            return [
                'days_until_full' => 'N/A',
                'estimated_full_date' => 'N/A',
                'daily_growth_mb' => 0,
                'remaining_space_mb' => 0
            ];
        }
    }

    private function formatUptime($seconds) {
        if ($seconds < 60) {
            return $seconds . ' seconds';
        } elseif ($seconds < 3600) {
            return floor($seconds / 60) . ' minutes';
        } elseif ($seconds < 86400) {
            return floor($seconds / 3600) . ' hours';
        } else {
            return floor($seconds / 86400) . ' days';
        }
    }

    public function getRealtimeStats() {
        return [
            'timestamp' => date('Y-m-d H:i:s'),
            'connections' => $this->getActiveConnections(),
            'database_size' => $this->getDatabaseSize(),
            'status' => $this->estimateSaturationTime(),
            'storage_prediction' => $this->getStoragePrediction()
        ];
    }
}