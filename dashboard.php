<?php
session_start();
require_once 'Lenguage/Language.php';
require_once 'Database.php';
require_once 'Monitor.php';

// Check authentication
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header("Location: index.php");
    exit;
}

// Initialize language system
$lang = new Language();

// Handle language change
if (isset($_GET['lang'])) {
    $lang->setLanguage($_GET['lang']);
    // Redirect to clean URL
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

// Initialize database connection and monitoring
try {
    $database = new Database($_SESSION['host'], $_SESSION['db'], $_SESSION['user'], $_SESSION['pass']);
    $monitor = new Monitor($database);

    // Get monitoring data
    $dbSize = $monitor->getDatabaseSize();
    $connections = $monitor->getActiveConnections();
    $status = $monitor->estimateSaturationTime();
    $tableStats = $monitor->getTableStatistics();
    $serverInfo = $monitor->getServerInfo();
    $diskSpace = $monitor->getDiskSpaceInfo();
    $performanceMetrics = $monitor->getPerformanceMetrics();
    $storagePrediction = $monitor->getStoragePrediction(); // Nueva línea

    // Debug información
    error_log("Debug - Table Stats Count: " . count($tableStats));
    error_log("Debug - Performance Metrics: " . print_r($performanceMetrics, true));

} catch (Exception $e) {
    $error = $e->getMessage();
    error_log("Dashboard Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="<?= $lang->getHtmlLangCode() ?>" dir="<?= $lang->isRTL() ? 'rtl' : 'ltr' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $lang->get('dashboard_title') ?> - <?= $_SESSION['db'] ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            position: relative;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, #00758f 0%, #f29111 70%);
            opacity: 0.9;
            z-index: -1;
        }

        .floating-shapes {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: -2;
            overflow: hidden;
        }

        .shape {
            position: absolute;
            opacity: 0.05;
            animation: float 20s infinite linear;
        }

        .shape:nth-child(1) {
            top: 20%;
            left: 20%;
            width: 60px;
            height: 60px;
            background: white;
            border-radius: 50%;
            animation-delay: 0s;
        }

        .shape:nth-child(2) {
            top: 60%;
            left: 80%;
            width: 80px;
            height: 80px;
            background: rgba(255,255,255,0.3);
            border-radius: 50%;
            animation-delay: 5s;
        }

        .shape:nth-child(3) {
            top: 40%;
            left: 10%;
            width: 40px;
            height: 40px;
            background: rgba(255,255,255,0.2);
            transform: rotate(45deg);
            animation-delay: 10s;
        }

        @keyframes float {
            0% { transform: translateY(0px) rotate(0deg); }
            33% { transform: translateY(-20px) rotate(120deg); }
            66% { transform: translateY(10px) rotate(240deg); }
            100% { transform: translateY(0px) rotate(360deg); }
        }

        .header {
            background: rgba(255,255,255,0.1);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255,255,255,0.2);
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: white;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .header-title {
            font-size: 1.5rem;
            font-weight: 600;
        }

        .db-indicator {
            background: rgba(242, 145, 17, 0.2);
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 0.85rem;
            border: 1px solid rgba(242, 145, 17, 0.5);
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .language-selector {
            position: relative;
        }

        .language-btn {
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.3);
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
        }

        .language-btn:hover {
            background: rgba(255,255,255,0.2);
        }

        .language-dropdown {
            position: absolute;
            top: 100%;
            right: 0;
            background: rgba(255,255,255,0.95);
            border-radius: 12px;
            min-width: 150px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.2);
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
            margin-top: 5px;
            z-index: 1000;
        }

        .language-selector:hover .language-dropdown {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .language-option {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 15px;
            color: #333;
            text-decoration: none;
            transition: background-color 0.2s ease;
            border-radius: 8px;
            margin: 4px;
        }

        .language-option:hover {
            background: rgba(0,117,143,0.1);
        }

        .logout-btn {
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.3);
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .logout-btn:hover {
            background: rgba(255,100,100,0.2);
            border-color: rgba(255,100,100,0.5);
        }

        .main-container {
            padding: 30px;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .card {
            background: rgba(255,255,255,0.95);
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
            border: 1px solid rgba(255,255,255,0.2);
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.15);
        }

        .card-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
        }

        .card-icon {
            width: 45px;
            height: 45px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            color: white;
        }

        .card-icon.primary {
            background: linear-gradient(135deg, #00758f, #0a5d6b);
        }

        .card-icon.success {
            background: linear-gradient(135deg, #28a745, #20c997);
        }

        .card-icon.warning {
            background: linear-gradient(135deg, #ffc107, #f29111);
        }

        .card-icon.danger {
            background: linear-gradient(135deg, #dc3545, #c82333);
        }

        .card-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #333;
        }

        .card-value {
            font-size: 2rem;
            font-weight: 700;
            color: #00758f;
            margin-bottom: 8px;
        }

        .card-subtitle {
            color: #666;
            font-size: 0.9rem;
        }

        .chart-container {
            position: relative;
            height: 300px;
            margin-top: 20px;
        }

        .progress-bar {
            background: #e9ecef;
            border-radius: 10px;
            height: 12px;
            overflow: hidden;
            margin: 15px 0;
        }

        .progress-fill {
            height: 100%;
            border-radius: 10px;
            transition: width 0.8s ease;
        }

        .progress-fill.success {
            background: linear-gradient(90deg, #28a745, #20c997);
        }

        .progress-fill.warning {
            background: linear-gradient(90deg, #ffc107, #f29111);
        }

        .progress-fill.danger {
            background: linear-gradient(90deg, #dc3545, #c82333);
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }

        .info-item {
            background: rgba(0,117,143,0.05);
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #00758f;
        }

        .info-label {
            font-size: 0.85rem;
            color: #666;
            margin-bottom: 5px;
            font-weight: 500;
        }

        .info-value {
            font-size: 1.1rem;
            font-weight: 600;
            color: #333;
        }

        .status-indicator {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .status-indicator.healthy {
            background: rgba(40,167,69,0.1);
            color: #28a745;
        }

        .status-indicator.warning {
            background: rgba(255,193,7,0.1);
            color: #ffc107;
        }

        .status-indicator.critical {
            background: rgba(220,53,69,0.1);
            color: #dc3545;
        }

        .table-stats {
            overflow-x: auto;
        }

        .stats-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        .stats-table th,
        .stats-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }

        .stats-table th {
            background: rgba(0,117,143,0.05);
            font-weight: 600;
            color: #333;
        }

        .stats-table tr:hover {
            background: rgba(0,117,143,0.02);
        }

        .refresh-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #00758f, #f29111);
            color: white;
            border: none;
            border-radius: 50%;
            font-size: 1.5rem;
            cursor: pointer;
            box-shadow: 0 10px 30px rgba(0,117,143,0.3);
            transition: all 0.3s ease;
            z-index: 1000;
        }

        .refresh-btn:hover {
            transform: scale(1.1) rotate(180deg);
            box-shadow: 0 15px 40px rgba(0,117,143,0.4);
        }

        .error-message {
            background: rgba(220,53,69,0.1);
            border: 1px solid rgba(220,53,69,0.3);
            color: #dc3545;
            padding: 20px;
            border-radius: 12px;
            margin: 20px 0;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        @media (max-width: 768px) {
            .header {
                padding: 15px 20px;
                flex-direction: column;
                gap: 15px;
            }

            .main-container {
                padding: 20px 15px;
            }

            .dashboard-grid {
                grid-template-columns: 1fr;
            }

            .card {
                padding: 20px;
            }

            .refresh-btn {
                bottom: 20px;
                right: 20px;
                width: 50px;
                height: 50px;
                font-size: 1.2rem;
            }


        }
        .language-dropdown {
            z-index: 9999; /* Cambiar de 1000 a 9999 */
        }
        .language-selector {
            position: relative;
            z-index: 9998; /* Añadir esta línea */
        }
        .header-right {
            position: relative; /* Añadir esta línea */
            z-index: 9997; /* Añadir esta línea */
        }
        .header {
            position: relative; /* Cambiar si no está */
            z-index: 9996; /* Añadir esta línea */
        }
        .storage-card {
            position: relative;
        }

        .storage-tooltip {
            position: absolute;
            top: -10px;
            left: 50%;
            transform: translateX(-50%) translateY(-100%);
            background: rgba(0, 0, 0, 0.9);
            color: white;
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 0.85rem;
            white-space: nowrap;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
            z-index: 1000;
            pointer-events: none;
        }

        .storage-tooltip::after {
            content: '';
            position: absolute;
            top: 100%;
            left: 50%;
            transform: translateX(-50%);
            border: 6px solid transparent;
            border-top-color: rgba(0, 0, 0, 0.9);
        }

        .storage-card:hover .storage-tooltip {
            opacity: 1;
            visibility: visible;
            transform: translateX(-50%) translateY(-100%) translateY(-8px);
        }

        .no-data-message {
            text-align: center;
            padding: 20px;
            color: #666;
            font-style: italic;
        }

        .metric-item {
            background: rgba(0,117,143,0.05);
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 10px;
            border-left: 3px solid #00758f;
        }

        .metric-label {
            font-size: 0.85rem;
            color: #666;
            margin-bottom: 4px;
        }

        .metric-value {
            font-size: 1.2rem;
            font-weight: 600;
            color: #333;
        }
        .storage-card {
            position: relative;
        }

        .storage-tooltip {
            position: absolute;
            top: -10px;
            left: 50%;
            transform: translateX(-50%) translateY(-100%);
            background: rgba(0, 0, 0, 0.9);
            color: white;
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 0.85rem;
            white-space: nowrap;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
            z-index: 1000;
            pointer-events: none;
        }

        .storage-tooltip::after {
            content: '';
            position: absolute;
            top: 100%;
            left: 50%;
            transform: translateX(-50%);
            border: 6px solid transparent;
            border-top-color: rgba(0, 0, 0, 0.9);
        }

        .storage-card:hover .storage-tooltip {
            opacity: 1;
            visibility: visible;
            transform: translateX(-50%) translateY(-100%) translateY(-8px);
        }

        .no-data-message {
            text-align: center;
            padding: 20px;
            color: #666;
            font-style: italic;
        }

        .metric-item {
            background: rgba(0,117,143,0.05);
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 10px;
            border-left: 3px solid #00758f;
        }

        .metric-label {
            font-size: 0.85rem;
            color: #666;
            margin-bottom: 4px;
        }

        .metric-value {
            font-size: 1.2rem;
            font-weight: 600;
            color: #333;
        }
    </style>
</head>
<body>
<!-- Animated Background -->
<div class="floating-shapes">
    <div class="shape"></div>
    <div class="shape"></div>
    <div class="shape"></div>
</div>

<!-- Header -->
<header class="header">
    <div class="header-left">
        <h1 class="header-title">
            <i class="fas fa-chart-line"></i>
            <?= $lang->get('dashboard_title') ?>
        </h1>
        <div class="db-indicator">
            <i class="fas fa-database"></i>
            <?= htmlspecialchars($_SESSION['db']) ?>@<?= htmlspecialchars($_SESSION['host']) ?>
        </div>
    </div>
    <div class="header-right">
        <!-- Language Selector -->
        <div class="language-selector">
            <button class="language-btn" type="button">
                <?= $lang->getLanguageInfo()['flag'] ?>
                <?= $lang->getLanguageInfo()['name'] ?>
                <i class="fas fa-chevron-down"></i>
            </button>
            <div class="language-dropdown">
                <?php foreach ($lang->getSupportedLanguages() as $langCode): ?>
                    <?php $langInfo = $lang->getLanguageInfo($langCode); ?>
                    <a href="?lang=<?= $langCode ?>" class="language-option">
                        <span><?= $langInfo['flag'] ?></span>
                        <span><?= $langInfo['name'] ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <a href="logout.php" class="logout-btn">
            <i class="fas fa-sign-out-alt"></i>
            <?= $lang->get('logout') ?>
        </a>
    </div>
</header>

<main class="main-container">
    <?php if (isset($error)): ?>
        <div class="error-message">
            <i class="fas fa-exclamation-triangle"></i>
            <?= $lang->get('connection_error') ?>: <?= htmlspecialchars($error) ?>
        </div>
    <?php else: ?>

        <!-- Dashboard Grid -->
        <div class="dashboard-grid">
            <!-- Database Size Card con Tooltip Mejorado -->
            <div class="card storage-card">
                <div class="card-header">
                    <div class="card-icon primary">
                        <i class="fas fa-database"></i>
                    </div>
                    <div>
                        <div class="card-title"><?= $lang->get('database_size') ?></div>
                    </div>
                </div>
                <div class="card-value"><?= number_format($dbSize['size_mb'] ?? 0, 2) ?> MB</div>
                <div class="card-subtitle"><?= $lang->get('total_size_description') ?></div>

                <?php
                $sizePercent = min(($dbSize['size_mb'] ?? 0) / 1000 * 100, 100);
                $sizeClass = $sizePercent > 80 ? 'danger' : ($sizePercent > 60 ? 'warning' : 'success');
                ?>
                <div class="progress-bar">
                    <div class="progress-fill <?= $sizeClass ?>" style="width: <?= $sizePercent ?>%"></div>
                </div>
                <div class="card-subtitle"><?= number_format($sizePercent, 1) ?>% <?= $lang->get('of_estimated_capacity') ?></div>
            </div>

            <!-- Active Connections Card -->
            <div class="card">
                <div class="card-header">
                    <div class="card-icon success">
                        <i class="fas fa-users"></i>
                    </div>
                    <div>
                        <div class="card-title"><?= $lang->get('active_connections') ?></div>
                    </div>
                </div>
                <div class="card-value"><?= $connections ?></div>
                <div class="card-subtitle"><?= $lang->get('current_connections') ?></div>

                <?php
                $connPercent = min($connections / 50 * 100, 100);
                $connClass = $connPercent > 80 ? 'danger' : ($connPercent > 60 ? 'warning' : 'success');
                ?>
                <div class="progress-bar">
                    <div class="progress-fill <?= $connClass ?>" style="width: <?= $connPercent ?>%"></div>
                </div>
            </div>

            <!-- Database Status Card -->
            <div class="card">
                <div class="card-header">
                    <div class="card-icon <?= strpos($status, '✅') !== false ? 'success' : 'warning' ?>">
                        <i class="fas fa-<?= strpos($status, '✅') !== false ? 'check-circle' : 'exclamation-triangle' ?>"></i>
                    </div>
                    <div>
                        <div class="card-title"><?= $lang->get('database_status') ?></div>
                    </div>
                </div>
                <div class="status-indicator <?= strpos($status, '✅') !== false ? 'healthy' : 'warning' ?>">
                    <?= htmlspecialchars($status) ?>
                </div>
                <div class="card-subtitle"><?= $lang->get('current_health_status') ?></div>
            </div>

            <!-- Server Information Card -->
            <div class="card">
                <div class="card-header">
                    <div class="card-icon primary">
                        <i class="fas fa-server"></i>
                    </div>
                    <div>
                        <div class="card-title"><?= $lang->get('server_information') ?></div>
                    </div>
                </div>
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label"><?= $lang->get('mysql_version') ?></div>
                        <div class="info-value"><?= $serverInfo['version'] ?? 'N/A' ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label"><?= $lang->get('server_uptime') ?></div>
                        <div class="info-value"><?= $serverInfo['uptime'] ?? 'N/A' ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label"><?= $lang->get('data_directory') ?></div>
                        <div class="info-value"><?= $serverInfo['datadir'] ?? 'N/A' ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label"><?= $lang->get('max_connections') ?></div>
                        <div class="info-value"><?= $serverInfo['max_connections'] ?? 'N/A' ?></div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Charts Section -->
        <div class="dashboard-grid">
            <!-- Storage Usage Chart con Predicción -->
            <div class="card storage-card">
                <div class="card-header">
                    <div class="card-icon warning">
                        <i class="fas fa-chart-area"></i>
                    </div>
                    <div>
                        <div class="card-title"><?= $lang->get('storage_usage') ?></div>
                        <div class="card-subtitle"><?= $lang->get('storage_breakdown') ?></div>
                    </div>
                </div>
                <div class="storage-tooltip">
                    <?php if (isset($storagePrediction) && $storagePrediction['days_until_full'] !== 'N/A'): ?>
                        <div><strong><?= $lang->get('storage_fill_estimate') ?>:</strong></div>
                        <div>• <?= $lang->get('days_remaining') ?>: ~<?= $storagePrediction['days_until_full'] ?> <?= $lang->get('days') ?></div>
                        <div>• <?= $lang->get('estimated_date') ?>: <?= $storagePrediction['estimated_full_date'] ?></div>
                        <div>• <?= $lang->get('daily_growth') ?>: <?= $storagePrediction['daily_growth_mb'] ?> <?= $lang->get('mb_per_day') ?></div>
                        <div>• <?= $lang->get('remaining_space') ?>: <?= $storagePrediction['remaining_space_mb'] ?> <?= $lang->get('mb') ?></div>
                    <?php else: ?>
                        <div><?= $lang->get('prediction_info_unavailable') ?></div>
                    <?php endif; ?>
                </div>
                <!-- Información de predicción que aparece/desaparece con hover -->
                <div class="storage-prediction-info" style="display: none;">
                    <?php if (isset($storagePrediction) && $storagePrediction['days_until_full'] !== 'N/A'): ?>
                        <div class="prediction-header">📊 <strong><?= $lang->get('storage_prediction') ?></strong></div>
                        <div class="prediction-details">
                            <div class="prediction-item">
                                <span class="prediction-label"><?= $lang->get('days_remaining') ?>:</span>
                                <span class="prediction-value">~<?= $storagePrediction['days_until_full'] ?> <?= $lang->get('days') ?></span>
                            </div>
                            <div class="prediction-item">
                                <span class="prediction-label"><?= $lang->get('estimated_date') ?>:</span>
                                <span class="prediction-value"><?= $storagePrediction['estimated_full_date'] ?></span>
                            </div>
                            <div class="prediction-item">
                                <span class="prediction-label"><?= $lang->get('daily_growth') ?>:</span>
                                <span class="prediction-value"><?= $storagePrediction['daily_growth_mb'] ?> <?= $lang->get('mb_per_day') ?></span>
                            </div>
                            <div class="prediction-item">
                                <span class="prediction-label"><?= $lang->get('remaining_space') ?>:</span>
                                <span class="prediction-value"><?= $storagePrediction['remaining_space_mb'] ?> <?= $lang->get('mb') ?></span>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="prediction-header">⚠️ <strong><?= $lang->get('prediction_unavailable') ?></strong></div>
                        <div class="card-subtitle"><?= $lang->get('storage_prediction_calculation_failed') ?></div>
                    <?php endif; ?>
                </div>

                <!-- Gráfico que se oculta con hover -->
                <div class="storage-chart-container">
                    <div class="chart-container">
                        <canvas id="storageChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Tables Statistics - MEJORADO -->
            <div class="card">
                <div class="card-header">
                    <div class="card-icon primary">
                        <i class="fas fa-table"></i>
                    </div>
                    <div>
                        <div class="card-title"><?= $lang->get('table_statistics') ?></div>
                        <div class="card-subtitle"><?= $lang->get('largest_tables') ?> (<?= count($tableStats) ?> <?= $lang->get('tables_count') ?>)</div>
                    </div>
                </div>
                <div class="table-stats">
                    <?php if (!empty($tableStats)): ?>
                        <table class="stats-table">
                            <thead>
                            <tr>
                                <th><?= $lang->get('table_name') ?></th>
                                <th><?= $lang->get('rows') ?></th>
                                <th><?= $lang->get('size_mb') ?></th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach (array_slice($tableStats, 0, 10) as $table): ?>
                                <tr>
                                    <td><?= htmlspecialchars($table['table_name']) ?></td>
                                    <td><?= number_format(intval($table['rows'])) ?></td>
                                    <td><?= number_format(floatval($table['size_mb']), 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="no-data-message">
                            <i class="fas fa-info-circle"></i>
                            <?= $lang->get('no_table_data_found') ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Performance Metrics - MEJORADO -->
        <?php if (isset($performanceMetrics) && !empty($performanceMetrics)): ?>
            <div class="dashboard-grid">
                <div class="card">
                    <div class="card-header">
                        <div class="card-icon success">
                            <i class="fas fa-tachometer-alt"></i>
                        </div>
                        <div>
                            <div class="card-title"><?= $lang->get('performance_metrics') ?></div>
                            <div class="card-subtitle"><?= $lang->get('real_time_server_metrics') ?></div>
                        </div>
                    </div>

                    <!-- Mostrar métricas en grid -->
                    <div class="info-grid">
                        <div class="metric-item">
                            <div class="metric-label"><?= $lang->get('slow_queries_label') ?></div>
                            <div class="metric-value"><?= number_format($performanceMetrics['slow_queries']) ?></div>
                        </div>
                        <div class="metric-item">
                            <div class="metric-label"><?= $lang->get('queries_per_second_label') ?></div>
                            <div class="metric-value"><?= $performanceMetrics['queries_per_second'] ?></div>
                        </div>
                        <div class="metric-item">
                            <div class="metric-label"><?= $lang->get('threads_connected_label') ?></div>
                            <div class="metric-value"><?= number_format($performanceMetrics['threads_connected']) ?></div>
                        </div>
                        <div class="metric-item">
                            <div class="metric-label"><?= $lang->get('threads_running_label') ?></div>
                            <div class="metric-value"><?= number_format($performanceMetrics['threads_running']) ?></div>
                        </div>
                        <div class="metric-item">
                            <div class="metric-label"><?= $lang->get('buffer_pool_hit_ratio_label') ?></div>
                            <div class="metric-value"><?= $performanceMetrics['buffer_pool_hit_ratio'] ?>%</div>
                        </div>
                        <div class="metric-item">
                            <div class="metric-label"><?= $lang->get('total_queries') ?></div>
                            <div class="metric-value"><?= number_format($performanceMetrics['questions']) ?></div>
                        </div>
                    </div>

                    <div class="chart-container">
                        <canvas id="performanceChart"></canvas>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="dashboard-grid">
                <div class="card">
                    <div class="card-header">
                        <div class="card-icon warning">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <div>
                            <div class="card-title"><?= $lang->get('performance_metrics') ?></div>
                        </div>
                    </div>
                    <div class="no-data-message">
                        <i class="fas fa-info-circle"></i>
                        <?= $lang->get('performance_metrics_error') ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

    <?php endif; ?>
</main>

<!-- Refresh Button -->
<button class="refresh-btn" onclick="window.location.reload();" title="<?= $lang->get('refresh_data') ?>">
    <i class="fas fa-sync-alt"></i>
</button>

<script>
    // Language translations for JavaScript
    const translations = <?= $lang->getJSTranslations() ?>;

    // Auto-refresh every 30 seconds
    setTimeout(() => {
        window.location.reload();
    }, 30000);

    <?php if (!isset($error)): ?>
    const storageCtx = document.getElementById('storageChart');
    if (storageCtx) {
        new Chart(storageCtx, {
            type: 'doughnut',
            data: {
                labels: ['<?= $lang->get('used_space') ?>', '<?= $lang->get('free_space') ?>'],
                datasets: [{
                    data: [<?= $dbSize['size_mb'] ?? 0 ?>, <?= max(1000 - ($dbSize['size_mb'] ?? 0), 0) ?>],
                    backgroundColor: ['#00758f', '#e9ecef'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }

    // Performance Chart - MEJORADO
    const performanceCtx = document.getElementById('performanceChart');
    if (performanceCtx) {
        new Chart(performanceCtx, {
            type: 'bar',
            data: {
                labels: ['<?= $lang->get('slow_queries_label') ?>', '<?= $lang->get('threads_connected_label') ?>', '<?= $lang->get('threads_running_label') ?>', '<?= $lang->get('hit_ratio_percent') ?>'],
                datasets: [{
                    label: '<?= $lang->get('values') ?>',
                    data: [
                        <?= $performanceMetrics['slow_queries'] ?? 0 ?>,
                        <?= $performanceMetrics['threads_connected'] ?? 0 ?>,
                        <?= $performanceMetrics['threads_running'] ?? 0 ?>,
                        <?= $performanceMetrics['buffer_pool_hit_ratio'] ?? 0 ?>
                    ],
                    backgroundColor: ['#dc3545', '#00758f', '#f29111', '#28a745'],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }
    <?php endif; ?>

    // Add smooth scrolling
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            document.querySelector(this.getAttribute('href')).scrollIntoView({
                behavior: 'smooth'
            });
        });
    });
    console.log('<?= $lang->get('debug_table_stats') ?>:', <?= json_encode($tableStats ?? []) ?>);
    console.log('<?= $lang->get('debug_performance_metrics') ?>:', <?= json_encode($performanceMetrics ?? []) ?>);

</script>
</body>
</html>