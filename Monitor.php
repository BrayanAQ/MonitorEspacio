<?php
class Monitor {
    private $pdo;
    private $database;

    public function __construct(Database $database) {
        $this->database = $database;
        $this->pdo = $database->getConnection();
    }

    public function getDatabaseSize() {
        try {
            // Try to get database name from Database instance first
            $dbName = $this->database->getDatabaseName();

            // Fallback to session if method doesn't exist
            if (!$dbName && isset($_SESSION['db'])) {
                $dbName = $_SESSION['db'];
            }

            if (!$dbName) {
                // Last resort: query current database
                $stmt = $this->pdo->query("SELECT DATABASE() as current_db");
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $dbName = $result['current_db'];
            }

            if (!$dbName) {
                error_log("No database name found in getDatabaseSize");
                return ['db' => 'Error', 'size_mb' => 0];
            }

            $query = "
                SELECT table_schema AS db, 
                       ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb
                FROM information_schema.tables
                WHERE table_schema = :database_name
                GROUP BY table_schema
            ";

            $stmt = $this->pdo->prepare($query);
            $stmt->bindParam(':database_name', $dbName, PDO::PARAM_STR);
            $stmt->execute();

            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            error_log("Database size query result: " . print_r($result, true));

            return $result ?: ['db' => $dbName, 'size_mb' => 0];
        } catch (Exception $e) {
            error_log("Error in getDatabaseSize: " . $e->getMessage());
            return ['db' => 'Error', 'size_mb' => 0];
        }
    }

    public function getTableStatistics() {
        try {
            // Method 1: Try to get database name from Database class
            $dbName = null;
            if (method_exists($this->database, 'getDatabaseName')) {
                $dbName = $this->database->getDatabaseName();
            }

            // Method 2: Fallback to session
            if (empty($dbName) && isset($_SESSION['db'])) {
                $dbName = $_SESSION['db'];
            }

            // Method 3: Query current database as last resort
            if (empty($dbName)) {
                $stmt = $this->pdo->query("SELECT DATABASE() as current_db");
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $dbName = $result['current_db'];
            }

            if (empty($dbName)) {
                error_log("No database name found in getTableStatistics");
                return [];
            }

            error_log("DEBUG: Using database name: " . $dbName);

            // Test if we can see the tables first
            $testQuery = "SHOW TABLES FROM `" . $dbName . "`";
            $testStmt = $this->pdo->query($testQuery);
            $tableList = $testStmt->fetchAll(PDO::FETCH_COLUMN);
            error_log("DEBUG: Tables found with SHOW TABLES: " . count($tableList));
            error_log("DEBUG: Table names: " . implode(', ', $tableList));

            // If no tables found with SHOW TABLES, return empty
            if (empty($tableList)) {
                error_log("No tables found with SHOW TABLES command");
                return [];
            }

            // Now try the information_schema query
            $query = "
            SELECT 
                table_name,
                COALESCE(table_rows, 0) as rows,
                ROUND(COALESCE((data_length + index_length), 0) / 1024 / 1024, 2) as size_mb
            FROM information_schema.tables
            WHERE table_schema = ?
            AND table_type = 'BASE TABLE'
            ORDER BY (data_length + index_length) DESC
        ";

            $stmt = $this->pdo->prepare($query);
            $stmt->execute([$dbName]);

            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            error_log("DEBUG: Information_schema results count: " . count($results));

            // If information_schema fails, fall back to manual method
            if (empty($results) && !empty($tableList)) {
                error_log("Information_schema failed, using fallback method");
                $results = [];

                foreach ($tableList as $tableName) {
                    try {
                        // Get row count
                        $rowStmt = $this->pdo->prepare("SELECT COUNT(*) as count FROM `" . $tableName . "`");
                        $rowStmt->execute();
                        $rowCount = $rowStmt->fetch(PDO::FETCH_ASSOC)['count'];

                        // Estimate size (this is rough, but better than nothing)
                        $sizeStmt = $this->pdo->prepare("
                        SELECT 
                            ROUND(COALESCE((data_length + index_length), 0) / 1024 / 1024, 2) as size_mb
                        FROM information_schema.tables 
                        WHERE table_schema = ? AND table_name = ?
                    ");
                        $sizeStmt->execute([$dbName, $tableName]);
                        $sizeResult = $sizeStmt->fetch(PDO::FETCH_ASSOC);
                        $sizeMb = $sizeResult['size_mb'] ?? 0;

                        $results[] = [
                            'table_name' => $tableName,
                            'rows' => $rowCount,
                            'size_mb' => $sizeMb
                        ];
                    } catch (Exception $e) {
                        error_log("Error getting stats for table $tableName: " . $e->getMessage());
                        // Still add the table with basic info
                        $results[] = [
                            'table_name' => $tableName,
                            'rows' => 0,
                            'size_mb' => 0
                        ];
                    }
                }

                // Sort by size descending
                usort($results, function($a, $b) {
                    return $b['size_mb'] <=> $a['size_mb'];
                });
            }

            error_log("DEBUG: Final results count: " . count($results));
            return $results;

        } catch (Exception $e) {
            error_log("Error in getTableStatistics: " . $e->getMessage());
            error_log("Error trace: " . $e->getTraceAsString());
            return [];
        }
    }

    public function testDatabaseAccess() {
        try {
            $dbName = $_SESSION['db'] ?? null;
            if (!$dbName) {
                return ['status' => 'error', 'message' => 'No database name'];
            }

            // Test basic table listing
            $stmt = $this->pdo->query("SHOW TABLES FROM `$dbName`");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

            // Test information_schema access
            $stmt2 = $this->pdo->prepare("
            SELECT COUNT(*) as count 
            FROM information_schema.tables 
            WHERE table_schema = ?
        ");
            $stmt2->execute([$dbName]);
            $schemaCount = $stmt2->fetch(PDO::FETCH_ASSOC)['count'];

            return [
                'status' => 'success',
                'show_tables_count' => count($tables),
                'schema_count' => $schemaCount,
                'tables' => $tables
            ];

        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
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

            // Force status refresh for more accurate data
            $this->pdo->query("FLUSH STATUS");

            // Core performance queries with error handling
            $performance_queries = [
                'slow_queries' => "SHOW GLOBAL STATUS LIKE 'Slow_queries'",
                'questions' => "SHOW GLOBAL STATUS LIKE 'Questions'",
                'uptime' => "SHOW GLOBAL STATUS LIKE 'Uptime'",
                'threads_connected' => "SHOW GLOBAL STATUS LIKE 'Threads_connected'",
                'threads_running' => "SHOW GLOBAL STATUS LIKE 'Threads_running'",
                'threads_created' => "SHOW GLOBAL STATUS LIKE 'Threads_created'",
                'connections' => "SHOW GLOBAL STATUS LIKE 'Connections'",
                'aborted_connects' => "SHOW GLOBAL STATUS LIKE 'Aborted_connects'",
                'bytes_sent' => "SHOW GLOBAL STATUS LIKE 'Bytes_sent'",
                'bytes_received' => "SHOW GLOBAL STATUS LIKE 'Bytes_received'",
                'table_open_cache_hits' => "SHOW GLOBAL STATUS LIKE 'Table_open_cache_hits'",
                'table_open_cache_misses' => "SHOW GLOBAL STATUS LIKE 'Table_open_cache_misses'",
                'innodb_buffer_pool_reads' => "SHOW GLOBAL STATUS LIKE 'Innodb_buffer_pool_reads'",
                'innodb_buffer_pool_read_requests' => "SHOW GLOBAL STATUS LIKE 'Innodb_buffer_pool_read_requests'",
                'innodb_rows_read' => "SHOW GLOBAL STATUS LIKE 'Innodb_rows_read'",
                'innodb_rows_inserted' => "SHOW GLOBAL STATUS LIKE 'Innodb_rows_inserted'",
                'innodb_rows_updated' => "SHOW GLOBAL STATUS LIKE 'Innodb_rows_updated'",
                'innodb_rows_deleted' => "SHOW GLOBAL STATUS LIKE 'Innodb_rows_deleted'",
                'com_select' => "SHOW GLOBAL STATUS LIKE 'Com_select'",
                'com_insert' => "SHOW GLOBAL STATUS LIKE 'Com_insert'",
                'com_update' => "SHOW GLOBAL STATUS LIKE 'Com_update'",
                'com_delete' => "SHOW GLOBAL STATUS LIKE 'Com_delete'",
                'key_reads' => "SHOW GLOBAL STATUS LIKE 'Key_reads'",
                'key_read_requests' => "SHOW GLOBAL STATUS LIKE 'Key_read_requests'",
                'sort_merge_passes' => "SHOW GLOBAL STATUS LIKE 'Sort_merge_passes'",
                'sort_range' => "SHOW GLOBAL STATUS LIKE 'Sort_range'",
                'sort_scan' => "SHOW GLOBAL STATUS LIKE 'Sort_scan'",
                'created_tmp_disk_tables' => "SHOW GLOBAL STATUS LIKE 'Created_tmp_disk_tables'",
                'created_tmp_tables' => "SHOW GLOBAL STATUS LIKE 'Created_tmp_tables'"
            ];

            // Get all metrics
            foreach ($performance_queries as $key => $query) {
                try {
                    $stmt = $this->pdo->query($query);
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    $metrics[$key] = intval($result['Value'] ?? 0);
                } catch (Exception $e) {
                    $metrics[$key] = 0;
                    error_log("Error getting metric $key: " . $e->getMessage());
                }
            }

            // Get configuration variables for calculations
            $config_queries = [
                'max_connections' => "SHOW VARIABLES LIKE 'max_connections'",
                'table_open_cache' => "SHOW VARIABLES LIKE 'table_open_cache'",
                'slow_query_log' => "SHOW VARIABLES LIKE 'slow_query_log'",
                'long_query_time' => "SHOW VARIABLES LIKE 'long_query_time'"
            ];

            foreach ($config_queries as $key => $query) {
                try {
                    $stmt = $this->pdo->query($query);
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    $metrics['config_' . $key] = $result['Value'] ?? 'Unknown';
                } catch (Exception $e) {
                    $metrics['config_' . $key] = 'Unknown';
                }
            }

            // Calculate derived metrics
            if ($metrics['uptime'] > 0) {
                $metrics['queries_per_second'] = round($metrics['questions'] / $metrics['uptime'], 2);
                $metrics['connections_per_second'] = round($metrics['connections'] / $metrics['uptime'], 2);
                $metrics['avg_queries_per_connection'] = $metrics['connections'] > 0 ?
                    round($metrics['questions'] / $metrics['connections'], 2) : 0;
            } else {
                $metrics['queries_per_second'] = 0;
                $metrics['connections_per_second'] = 0;
                $metrics['avg_queries_per_connection'] = 0;
            }

            // Buffer pool hit ratio
            if ($metrics['innodb_buffer_pool_read_requests'] > 0) {
                $hit_ratio = (($metrics['innodb_buffer_pool_read_requests'] - $metrics['innodb_buffer_pool_reads']) /
                        $metrics['innodb_buffer_pool_read_requests']) * 100;
                $metrics['buffer_pool_hit_ratio'] = round($hit_ratio, 2);
            } else {
                $metrics['buffer_pool_hit_ratio'] = 0;
            }

            // Key cache hit ratio
            if ($metrics['key_read_requests'] > 0) {
                $key_hit_ratio = (($metrics['key_read_requests'] - $metrics['key_reads']) /
                        $metrics['key_read_requests']) * 100;
                $metrics['key_cache_hit_ratio'] = round($key_hit_ratio, 2);
            } else {
                $metrics['key_cache_hit_ratio'] = 0;
            }

            // Table cache hit ratio
            $total_table_opens = $metrics['table_open_cache_hits'] + $metrics['table_open_cache_misses'];
            if ($total_table_opens > 0) {
                $table_hit_ratio = ($metrics['table_open_cache_hits'] / $total_table_opens) * 100;
                $metrics['table_cache_hit_ratio'] = round($table_hit_ratio, 2);
            } else {
                $metrics['table_cache_hit_ratio'] = 0;
            }

            // Connection usage percentage
            $max_conn = intval($metrics['config_max_connections']);
            if ($max_conn > 0) {
                $metrics['connection_usage_percent'] = round(($metrics['threads_connected'] / $max_conn) * 100, 2);
            } else {
                $metrics['connection_usage_percent'] = 0;
            }

            // Aborted connection percentage
            if ($metrics['connections'] > 0) {
                $metrics['aborted_connection_percent'] = round(($metrics['aborted_connects'] / $metrics['connections']) * 100, 2);
            } else {
                $metrics['aborted_connection_percent'] = 0;
            }

            // Temporary table efficiency
            if ($metrics['created_tmp_tables'] > 0) {
                $metrics['tmp_disk_table_percent'] = round(($metrics['created_tmp_disk_tables'] / $metrics['created_tmp_tables']) * 100, 2);
            } else {
                $metrics['tmp_disk_table_percent'] = 0;
            }

            // Query type breakdown
            $metrics['total_operations'] = $metrics['com_select'] + $metrics['com_insert'] +
                $metrics['com_update'] + $metrics['com_delete'];

            if ($metrics['total_operations'] > 0) {
                $metrics['select_percent'] = round(($metrics['com_select'] / $metrics['total_operations']) * 100, 2);
                $metrics['insert_percent'] = round(($metrics['com_insert'] / $metrics['total_operations']) * 100, 2);
                $metrics['update_percent'] = round(($metrics['com_update'] / $metrics['total_operations']) * 100, 2);
                $metrics['delete_percent'] = round(($metrics['com_delete'] / $metrics['total_operations']) * 100, 2);
            } else {
                $metrics['select_percent'] = 0;
                $metrics['insert_percent'] = 0;
                $metrics['update_percent'] = 0;
                $metrics['delete_percent'] = 0;
            }

            // Network throughput (MB)
            $metrics['bytes_sent_mb'] = round($metrics['bytes_sent'] / 1024 / 1024, 2);
            $metrics['bytes_received_mb'] = round($metrics['bytes_received'] / 1024 / 1024, 2);

            // Performance health indicators
            $metrics['health_indicators'] = $this->calculateHealthIndicators($metrics);

            error_log("Performance metrics calculated successfully with " . count($metrics) . " metrics");
            return $metrics;

        } catch (Exception $e) {
            error_log("Error in getPerformanceMetrics: " . $e->getMessage());
            return [];
        }
    }

    private function calculateHealthIndicators($metrics) {
        $indicators = [];

        // Slow query indicator
        if ($metrics['uptime'] > 3600) { // Only if server has been up for more than 1 hour
            $slow_query_rate = $metrics['slow_queries'] / ($metrics['uptime'] / 3600); // per hour
            $indicators['slow_queries'] = [
                'status' => $slow_query_rate > 10 ? 'critical' : ($slow_query_rate > 1 ? 'warning' : 'healthy'),
                'rate' => round($slow_query_rate, 2),
                'message' => $slow_query_rate > 10 ? 'High slow query rate' :
                    ($slow_query_rate > 1 ? 'Moderate slow query rate' : 'Low slow query rate')
            ];
        } else {
            $indicators['slow_queries'] = [
                'status' => 'unknown',
                'rate' => 0,
                'message' => 'Server uptime too short for analysis'
            ];
        }

        // Connection usage indicator
        $conn_usage = $metrics['connection_usage_percent'];
        $indicators['connections'] = [
            'status' => $conn_usage > 80 ? 'critical' : ($conn_usage > 60 ? 'warning' : 'healthy'),
            'usage' => $conn_usage,
            'message' => $conn_usage > 80 ? 'Connection limit nearly reached' :
                ($conn_usage > 60 ? 'High connection usage' : 'Normal connection usage')
        ];

        // Buffer pool indicator
        $buffer_hit = $metrics['buffer_pool_hit_ratio'];
        $indicators['buffer_pool'] = [
            'status' => $buffer_hit < 90 ? 'critical' : ($buffer_hit < 95 ? 'warning' : 'healthy'),
            'ratio' => $buffer_hit,
            'message' => $buffer_hit < 90 ? 'Low buffer pool hit ratio' :
                ($buffer_hit < 95 ? 'Moderate buffer pool performance' : 'Good buffer pool performance')
        ];

        // Temporary table indicator
        $tmp_disk = $metrics['tmp_disk_table_percent'];
        $indicators['tmp_tables'] = [
            'status' => $tmp_disk > 25 ? 'critical' : ($tmp_disk > 10 ? 'warning' : 'healthy'),
            'percent' => $tmp_disk,
            'message' => $tmp_disk > 25 ? 'Too many temp tables on disk' :
                ($tmp_disk > 10 ? 'Some temp tables on disk' : 'Good temp table performance')
        ];

        return $indicators;
    }

    // New method to get real-time process list - FIXED NULL HANDLING
    public function getActiveProcesses() {
        try {
            $stmt = $this->pdo->query("SHOW FULL PROCESSLIST");
            $processes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $processInfo = [
                'total' => count($processes),
                'sleeping' => 0,
                'running' => 0,
                'locked' => 0,
                'other' => 0,
                'longest_query' => 0,
                'processes' => []
            ];

            foreach ($processes as $process) {
                $state = strtolower($process['Command']);

                switch ($state) {
                    case 'sleep':
                        $processInfo['sleeping']++;
                        break;
                    case 'query':
                        $processInfo['running']++;
                        break;
                    case 'locked':
                        $processInfo['locked']++;
                        break;
                    default:
                        $processInfo['other']++;
                }

                if ($process['Time'] > $processInfo['longest_query']) {
                    $processInfo['longest_query'] = $process['Time'];
                }

                // Store top 10 longest running processes
                if (count($processInfo['processes']) < 10) {
                    $processInfo['processes'][] = [
                        'id' => $process['Id'],
                        'user' => $process['User'],
                        'host' => $process['Host'],
                        'db' => $process['db'] ?? '', // FIX: Handle null database
                        'command' => $process['Command'],
                        'time' => $process['Time'],
                        'info' => $process['Info'] ? substr($process['Info'], 0, 100) : '' // FIX: Handle null query
                    ];
                }
            }

            // Sort processes by time descending
            usort($processInfo['processes'], function($a, $b) {
                return $b['time'] - $a['time'];
            });

            return $processInfo;

        } catch (Exception $e) {
            error_log("Error getting active processes: " . $e->getMessage());
            return ['total' => 0, 'sleeping' => 0, 'running' => 0, 'locked' => 0, 'other' => 0, 'longest_query' => 0, 'processes' => []];
        }
    }

    // Nueva función para calcular estimación de tiempo hasta llenarse
    public function getStoragePrediction() {
        try {
            $currentSize = $this->getDatabaseSize()['size_mb'] ?? 0;
            $maxCapacity = 1000; // MB - puedes ajustar según tu servidor

            // Obtener historial de crecimiento (simulado aquí, idealmente desde una tabla de log)
            $dbName = $this->database->getDatabaseName();
            if (!$dbName && isset($_SESSION['db'])) {
                $dbName = $_SESSION['db'];
            }

            $stmt = $this->pdo->prepare("
                SELECT 
                    COUNT(*) as total_records,
                    COALESCE(SUM(data_length + index_length), 0) as total_size
                FROM information_schema.tables 
                WHERE table_schema = :database_name
            ");
            $stmt->bindParam(':database_name', $dbName, PDO::PARAM_STR);
            $stmt->execute();
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
?>