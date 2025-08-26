<?php
session_start();
require_once 'Lenguage/Language.php';
require_once 'Database.php';
require_once 'Monitor.php';
error_log("DEBUG: Using database = " . $_SESSION['db']);

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
    $storagePrediction = $monitor->getStoragePrediction();

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
        /* [CSS remains the same - keeping it unchanged for brevity] */
        .performance-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin: 25px 0;
        }

        .metric-card {
            background: rgba(255,255,255,0.98);
            border-radius: 12px;
            padding: 20px;
            border-left: 4px solid #00758f;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            position: relative;
            overflow: hidden;
            min-height: 120px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .metric-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
        }

        .metric-card.warning {
            border-left-color: #ffc107;
        }

        .metric-card.critical {
            border-left-color: #dc3545;
        }

        .metric-card.healthy {
            border-left-color: #28a745;
        }

        .metric-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }

        .metric-title {
            font-size: 0.95rem;
            color: #555;
            font-weight: 600;
            margin: 0;
            flex: 1;
        }

        .metric-status {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }

        .metric-status.healthy {
            background: rgba(40,167,69,0.15);
            color: #28a745;
            border: 1px solid rgba(40,167,69,0.2);
        }

        .metric-status.warning {
            background: rgba(255,193,7,0.15);
            color: #f39c12;
            border: 1px solid rgba(255,193,7,0.2);
        }

        .metric-status.critical {
            background: rgba(220,53,69,0.15);
            color: #dc3545;
            border: 1px solid rgba(220,53,69,0.2);
        }

        .metric-value {
            font-size: 2.2rem;
            font-weight: 700;
            color: #333;
            margin: 8px 0;
            line-height: 1.2;
        }

        .metric-subtitle {
            font-size: 0.85rem;
            color: #666;
            margin: 0;
        }

        /* Health Summary Improvements */
        .health-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 15px;
            margin: 25px 0;
            padding: 20px;
            background: rgba(255,255,255,0.05);
            border-radius: 12px;
            backdrop-filter: blur(10px);
        }

        .health-item {
            background: rgba(255,255,255,0.98);
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            border-top: 4px solid;
            transition: transform 0.3s ease;
        }

        .health-item:hover {
            transform: translateY(-2px);
        }

        .health-item.healthy {
            border-color: #28a745;
        }

        .health-item.warning {
            border-color: #ffc107;
        }

        .health-item.critical {
            border-color: #dc3545;
        }

        .health-icon {
            font-size: 1.8rem;
            margin-bottom: 10px;
            display: block;
        }

        .health-title {
            font-size: 0.95rem;
            font-weight: 600;
            margin-bottom: 6px;
            color: #333;
            text-transform: capitalize;
        }

        .health-message {
            font-size: 0.8rem;
            color: #666;
            line-height: 1.4;
        }

        /* Chart Grid Improvements */
        .chart-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(450px, 1fr));
            gap: 25px;
            margin: 30px 0;
        }

        .chart-card {
            background: rgba(255,255,255,0.98);
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }

        .chart-card:hover {
            transform: translateY(-3px);
        }

        .chart-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .chart-title i {
            color: #00758f;
            font-size: 1.1rem;
        }

        .chart-container {
            position: relative;
            height: 320px;
            margin-top: 10px;
        }

        /* Processes Table Improvements */
        .processes-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            font-size: 0.88rem;
        }

        .processes-table th,
        .processes-table td {
            padding: 12px 10px;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }

        .processes-table th {
            background: rgba(0,117,143,0.08);
            font-weight: 600;
            color: #333;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .processes-table tr:hover {
            background: rgba(0,117,143,0.03);
        }

        .processes-table td:last-child {
            max-width: 250px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        /* Performance Metrics Container */
        .performance-metrics-container {
            background: rgba(255,255,255,0.02);
            border-radius: 20px;
            padding: 30px;
            margin: 20px 0;
            backdrop-filter: blur(15px);
            border: 1px solid rgba(255,255,255,0.1);
        }

        /* Responsive Design */
        @media (max-width: 1200px) {
            .performance-grid {
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                gap: 15px;
            }

            .chart-grid {
                grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            }
        }

        @media (max-width: 768px) {
            .performance-grid {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 12px;
            }

            .metric-card {
                padding: 15px;
                min-height: 100px;
            }

            .metric-value {
                font-size: 1.8rem;
            }

            .chart-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .chart-container {
                height: 250px;
            }

            .health-summary {
                grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
                gap: 12px;
                padding: 15px;
            }

            .health-item {
                padding: 15px;
            }
        }

        /* Additional styles for storage prediction */
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

        /* Common styles */
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
            position: relative;
            z-index: 9997;
        }

        .language-selector {
            position: relative;
            z-index: 9998;
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
            z-index: 9999;
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

        .no-data-message {
            text-align: center;
            padding: 20px;
            color: #666;
            font-style: italic;
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
            <!-- Database Size Card -->
            <div class="card storage-card">
                <div class="card-header">
                    <div class="card-icon primary">
                        <i class="fas fa-database"></i>
                    </div>
                    <div>
                        <div class="card-title"><?= $lang->get('database_size') ?></div>
                    </div>
                </div>
                <div class="card-value"><?= number_format($dbSize['size_mb'] ?? 0, 2) ?> <?= $lang->get('mb') ?></div>
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
                        <div class="info-value"><?= $serverInfo['version'] ?? $lang->get('not_available') ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label"><?= $lang->get('server_uptime') ?></div>
                        <div class="info-value"><?= $serverInfo['uptime'] ?? $lang->get('not_available') ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label"><?= $lang->get('data_directory') ?></div>
                        <div class="info-value"><?= $serverInfo['datadir'] ?? $lang->get('not_available') ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label"><?= $lang->get('max_connections') ?></div>
                        <div class="info-value"><?= $serverInfo['max_connections'] ?? $lang->get('not_available') ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="dashboard-grid">
            <!-- Storage Usage Chart -->
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
                <div class="chart-container">
                    <canvas id="storageChart"></canvas>
                </div>
                <?php if (isset($storagePrediction) && $storagePrediction['days_until_full'] !== 'N/A'): ?>
                    <div class="storage-tooltip">
                        <div><strong><?= $lang->get('storage_prediction') ?>:</strong></div>
                        <div>• <?= $lang->get('days_remaining') ?>: ~<?= $storagePrediction['days_until_full'] ?> <?= $lang->get('days') ?></div>
                        <div>• <?= $lang->get('estimated_date') ?>: <?= $storagePrediction['estimated_full_date'] ?></div>
                        <div>• <?= $lang->get('daily_growth') ?>: <?= $storagePrediction['daily_growth_mb'] ?> <?= $lang->get('mb_per_day') ?></div>
                        <div>• <?= $lang->get('remaining_space') ?>: <?= $storagePrediction['remaining_space_mb'] ?> <?= $lang->get('mb') ?></div>
                    </div>
                <?php else: ?>
                    <div class="storage-tooltip">
                        <div><?= $lang->get('prediction_info_unavailable') ?></div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Tables Statistics -->
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
                            <?php foreach ($tableStats as $table): ?>
                                <?php if (!empty($table['table_name']) && $table['size_mb'] > 0): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($table['table_name']) ?></td>
                                        <td><?= number_format(intval($table['rows'])) ?></td>
                                        <td><?= number_format(floatval($table['size_mb']), 2) ?></td>
                                    </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="no-data-message">
                            <i class="fas fa-info-circle"></i>
                            <?= $lang->get('no_table_data_found') ?>
                            <div style="margin-top: 10px; font-size: 12px;">
                                <?= $lang->get('database') ?>: <?= htmlspecialchars($_SESSION['db']) ?> |
                                <?= $lang->get('tables_found') ?>: <?= count($tableStats) ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if (isset($performanceMetrics) && !empty($performanceMetrics)): ?>
            <div class="dashboard-grid">
                <div class="card" style="grid-column: 1 / -1;">
                    <div class="card-header">
                        <div class="card-icon success">
                            <i class="fas fa-tachometer-alt"></i>
                        </div>
                        <div>
                            <div class="card-title"><?= $lang->get('performance_metrics') ?></div>
                            <div class="card-subtitle"><?= $lang->get('real_time_server_analysis') ?></div>
                        </div>
                    </div>

                    <!-- Health Summary -->
                    <?php if (isset($performanceMetrics['health_indicators'])): ?>
                        <div class="health-summary">
                            <?php foreach ($performanceMetrics['health_indicators'] as $indicator => $data): ?>
                                <div class="health-item <?= $data['status'] ?>">
                                    <div class="health-icon">
                                        <?php
                                        switch($data['status']) {
                                            case 'healthy': echo '✅'; break;
                                            case 'warning': echo '⚠️'; break;
                                            case 'critical': echo '🔴'; break;
                                            default: echo '❓'; break;
                                        }
                                        ?>
                                    </div>
                                    <div class="health-title"><?= $lang->get('health_indicator_' . $indicator) ?></div>
                                    <div class="health-message"><?= $data['message'] ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Key Metrics Grid -->
                    <div class="performance-grid">
                        <div class="metric-card">
                            <div class="metric-header">
                                <span class="metric-title"><?= $lang->get('queries_per_second') ?></span>
                                <span class="metric-status <?= $performanceMetrics['queries_per_second'] > 100 ? 'warning' : 'healthy' ?>">
                                    <?= $performanceMetrics['queries_per_second'] > 100 ? $lang->get('status_high') : $lang->get('status_ok') ?>
                                </span>
                            </div>
                            <div class="metric-value"><?= $performanceMetrics['queries_per_second'] ?></div>
                            <div class="metric-subtitle"><?= $lang->get('average_query_rate') ?></div>
                        </div>

                        <div class="metric-card">
                            <div class="metric-header">
                                <span class="metric-title"><?= $lang->get('buffer_pool_hit_ratio') ?></span>
                                <span class="metric-status <?= $performanceMetrics['buffer_pool_hit_ratio'] < 90 ? 'critical' : ($performanceMetrics['buffer_pool_hit_ratio'] < 95 ? 'warning' : 'healthy') ?>">
                                    <?= $performanceMetrics['buffer_pool_hit_ratio'] < 90 ? $lang->get('status_low') : ($performanceMetrics['buffer_pool_hit_ratio'] < 95 ? $lang->get('status_fair') : $lang->get('status_good')) ?>
                                </span>
                            </div>
                            <div class="metric-value"><?= $performanceMetrics['buffer_pool_hit_ratio'] ?>%</div>
                            <div class="metric-subtitle"><?= $lang->get('memory_efficiency') ?></div>
                        </div>

                        <div class="metric-card">
                            <div class="metric-header">
                                <span class="metric-title"><?= $lang->get('connection_usage') ?></span>
                                <span class="metric-status <?= $performanceMetrics['connection_usage_percent'] > 80 ? 'critical' : ($performanceMetrics['connection_usage_percent'] > 60 ? 'warning' : 'healthy') ?>">
                                    <?= $performanceMetrics['connection_usage_percent'] > 80 ? $lang->get('status_high') : ($performanceMetrics['connection_usage_percent'] > 60 ? $lang->get('status_fair') : $lang->get('status_ok')) ?>
                                </span>
                            </div>
                            <div class="metric-value"><?= $performanceMetrics['connection_usage_percent'] ?>%</div>
                            <div class="metric-subtitle"><?= $performanceMetrics['threads_connected'] ?> / <?= $performanceMetrics['config_max_connections'] ?> <?= $lang->get('connections') ?></div>
                        </div>

                        <div class="metric-card">
                            <div class="metric-header">
                                <span class="metric-title"><?= $lang->get('slow_queries') ?></span>
                                <span class="metric-status <?= $performanceMetrics['slow_queries'] > 10 ? 'critical' : ($performanceMetrics['slow_queries'] > 0 ? 'warning' : 'healthy') ?>">
                                    <?= $performanceMetrics['slow_queries'] > 10 ? $lang->get('status_high') : ($performanceMetrics['slow_queries'] > 0 ? $lang->get('status_some') : $lang->get('status_none')) ?>
                                </span>
                            </div>
                            <div class="metric-value"><?= number_format($performanceMetrics['slow_queries']) ?></div>
                            <div class="metric-subtitle"><?= $lang->get('total_slow_queries') ?></div>
                        </div>

                        <div class="metric-card">
                            <div class="metric-header">
                                <span class="metric-title"><?= $lang->get('key_cache_hit_ratio') ?></span>
                                <span class="metric-status <?= $performanceMetrics['key_cache_hit_ratio'] < 95 ? 'warning' : 'healthy' ?>">
                                    <?= $performanceMetrics['key_cache_hit_ratio'] < 95 ? $lang->get('status_fair') : $lang->get('status_good') ?>
                                </span>
                            </div>
                            <div class="metric-value"><?= $performanceMetrics['key_cache_hit_ratio'] ?>%</div>
                            <div class="metric-subtitle"><?= $lang->get('index_cache_efficiency') ?></div>
                        </div>

                        <div class="metric-card">
                            <div class="metric-header">
                                <span class="metric-title"><?= $lang->get('temp_tables_on_disk') ?></span>
                                <span class="metric-status <?= $performanceMetrics['tmp_disk_table_percent'] > 25 ? 'critical' : ($performanceMetrics['tmp_disk_table_percent'] > 10 ? 'warning' : 'healthy') ?>">
                                    <?= $performanceMetrics['tmp_disk_table_percent'] > 25 ? $lang->get('status_high') : ($performanceMetrics['tmp_disk_table_percent'] > 10 ? $lang->get('status_some') : $lang->get('status_low')) ?>
                                </span>
                            </div>
                            <div class="metric-value"><?= $performanceMetrics['tmp_disk_table_percent'] ?>%</div>
                            <div class="metric-subtitle"><?= $lang->get('memory_vs_disk_usage') ?></div>
                        </div>

                        <div class="metric-card">
                            <div class="metric-header">
                                <span class="metric-title"><?= $lang->get('network_traffic') ?></span>
                                <span class="metric-status healthy"><?= $lang->get('status_ok') ?></span>
                            </div>
                            <div class="metric-value"><?= $performanceMetrics['bytes_sent_mb'] + $performanceMetrics['bytes_received_mb'] ?></div>
                            <div class="metric-subtitle"><?= $lang->get('total_mb_transferred') ?></div>
                        </div>

                        <div class="metric-card">
                            <div class="metric-header">
                                <span class="metric-title"><?= $lang->get('running_threads') ?></span>
                                <span class="metric-status <?= $performanceMetrics['threads_running'] > 10 ? 'warning' : 'healthy' ?>">
                                    <?= $performanceMetrics['threads_running'] > 10 ? $lang->get('status_high') : $lang->get('status_ok') ?>
                                </span>
                            </div>
                            <div class="metric-value"><?= $performanceMetrics['threads_running'] ?></div>
                            <div class="metric-subtitle"><?= $lang->get('active_query_threads') ?></div>
                        </div>
                    </div>

                    <!-- Charts Section -->
                    <div class="chart-grid">
                        <!-- Query Types Distribution -->
                        <div class="chart-card">
                            <div class="chart-title">
                                <i class="fas fa-chart-pie"></i>
                                <?= $lang->get('query_types_distribution') ?>
                            </div>
                            <div class="chart-container">
                                <canvas id="queryTypesChart"></canvas>
                            </div>
                        </div>

                        <!-- Performance Trends -->
                        <div class="chart-card">
                            <div class="chart-title">
                                <i class="fas fa-chart-line"></i>
                                <?= $lang->get('hit_ratios_comparison') ?>
                            </div>
                            <div class="chart-container">
                                <canvas id="hitRatiosChart"></canvas>
                            </div>
                        </div>

                        <!-- Connection Analysis -->
                        <div class="chart-card">
                            <div class="chart-title">
                                <i class="fas fa-chart-bar"></i>
                                <?= $lang->get('connection_thread_analysis') ?>
                            </div>
                            <div class="chart-container">
                                <canvas id="connectionChart"></canvas>
                            </div>
                        </div>
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

    // Query Types Chart
    const queryTypesCtx = document.getElementById('queryTypesChart');
    if (queryTypesCtx) {
        new Chart(queryTypesCtx, {
            type: 'doughnut',
            data: {
                labels: ['SELECT', 'INSERT', 'UPDATE', 'DELETE'],
                datasets: [{
                    data: [
                        <?= $performanceMetrics['select_percent'] ?? 0 ?>,
                        <?= $performanceMetrics['insert_percent'] ?? 0 ?>,
                        <?= $performanceMetrics['update_percent'] ?? 0 ?>,
                        <?= $performanceMetrics['delete_percent'] ?? 0 ?>
                    ],
                    backgroundColor: ['#00758f', '#28a745', '#ffc107', '#dc3545'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.label + ': ' + context.parsed + '%';
                            }
                        }
                    }
                }
            }
        });
    }

    // Hit Ratios Comparison Chart
    const hitRatiosCtx = document.getElementById('hitRatiosChart');
    if (hitRatiosCtx) {
        new Chart(hitRatiosCtx, {
            type: 'bar',
            data: {
                labels: ['<?= $lang->get('buffer_pool') ?>', '<?= $lang->get('key_cache') ?>', '<?= $lang->get('table_cache') ?>'],
                datasets: [{
                    label: '<?= $lang->get('hit_ratio_percent') ?>',
                    data: [
                        <?= $performanceMetrics['buffer_pool_hit_ratio'] ?? 0 ?>,
                        <?= $performanceMetrics['key_cache_hit_ratio'] ?? 0 ?>,
                        <?= $performanceMetrics['table_cache_hit_ratio'] ?? 0 ?>
                    ],
                    backgroundColor: function(context) {
                        const value = context.parsed.y;
                        if (value >= 95) return '#28a745';
                        if (value >= 90) return '#ffc107';
                        return '#dc3545';
                    },
                    borderRadius: 5,
                    borderSkipped: false
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
                        beginAtZero: true,
                        max: 100,
                        ticks: {
                            callback: function(value) {
                                return value + '%';
                            }
                        }
                    }
                }
            }
        });
    }

    // Connection & Thread Analysis Chart
    const connectionCtx = document.getElementById('connectionChart');
    if (connectionCtx) {
        new Chart(connectionCtx, {
            type: 'bar',
            data: {
                labels: ['<?= $lang->get('connected') ?>', '<?= $lang->get('running') ?>', '<?= $lang->get('created') ?>', '<?= $lang->get('max_allowed') ?>'],
                datasets: [{
                    label: '<?= $lang->get('connections_and_threads') ?>',
                    data: [
                        <?= $performanceMetrics['threads_connected'] ?? 0 ?>,
                        <?= $performanceMetrics['threads_running'] ?? 0 ?>,
                        <?= $performanceMetrics['threads_created'] ?? 0 ?>,
                        <?= intval($performanceMetrics['config_max_connections'] ?? 0) ?>
                    ],
                    backgroundColor: ['#00758f', '#f29111', '#6f42c1', '#6c757d'],
                    borderRadius: 5,
                    borderSkipped: false
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

    // Real-time updates every 30 seconds
    setInterval(function() {
        const refreshBtn = document.querySelector('.refresh-btn');
        if (refreshBtn) {
            refreshBtn.style.transform = 'scale(1.1) rotate(180deg)';
            setTimeout(() => {
                refreshBtn.style.transform = '';
                window.location.reload();
            }, 1000);
        }
    }, 30000);

    // Add smooth animations on load
    document.addEventListener('DOMContentLoaded', function() {
        const metricCards = document.querySelectorAll('.metric-card');
        metricCards.forEach((card, index) => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            setTimeout(() => {
                card.style.transition = 'all 0.5s ease';
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, index * 100);
        });
    });

    console.log('<?= $lang->get('debug_table_stats') ?>:', <?= json_encode($tableStats ?? []) ?>);
    console.log('<?= $lang->get('debug_performance_metrics') ?>:', <?= json_encode($performanceMetrics ?? []) ?>);
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
</script>
</body>
</html>