<?php
require 'Database.php';
require 'Auth.php';
require 'Monitor.php';

session_start();
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header("Location: index.html");
    exit;
}

$database = new Database($_SESSION['host'], $_SESSION['db'], $_SESSION['user'], $_SESSION['pass']);
$monitor = new Monitor($database);

$dbSize = $monitor->getDatabaseSize();
$connections = $monitor->getActiveConnections();
$status = $monitor->estimateSaturationTime();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Dashboard - Monitoreo</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        .card { background: #f2f2f2; padding: 15px; border-radius: 8px; margin-bottom: 15px; }
    </style>
</head>
<body>
<h1>📊 Monitoreo de Base de Datos</h1>
<div class="card"><strong>📦 Tamaño de la BD:</strong> <?= $dbSize['size_mb'] ?? 0 ?> MB</div>
<div class="card"><strong>👥 Conexiones activas:</strong> <?= $connections ?></div>
<div class="card"><strong>⏳ Estado:</strong> <?= $status ?></div>
<a href="logout.php">Cerrar sesión</a>
</body>
</html>

