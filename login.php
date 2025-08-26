<?php
session_start();
require 'Database.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $host = $_POST['host'];
    $db   = $_POST['database'];
    $user = $_POST['user'];
    $pass = $_POST['password'];

    try {
        // Test database connection with provided credentials
        $database = new Database($host, $db, $user, $pass);
        $pdo = $database->getConnection();

        // If we reach here, connection is successful
        // Save connection details in session
        $_SESSION['logged_in'] = true;
        $_SESSION['username'] = $user;
        $_SESSION['host'] = $host;
        $_SESSION['db'] = $db;
        $_SESSION['user'] = $user;
        $_SESSION['pass'] = $pass;

        // Optional: You can still check if the user exists in usuarios table
        try {
            $stmt = $pdo->prepare("SELECT username, rol, nombre_completo FROM usuarios WHERE username = ?");
            $stmt->execute([$user]);
            $userRecord = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($userRecord) {
                $_SESSION['rol'] = $userRecord['rol'];
                $_SESSION['nombre_completo'] = $userRecord['nombre_completo'];
            }
        } catch (Exception $e) {
            // If usuarios table doesn't exist or query fails, continue with basic info
            $_SESSION['rol'] = 'Database User';
            $_SESSION['nombre_completo'] = $user;
        }

        header("Location: dashboard.php");
        exit;

    } catch (Exception $e) {
        $_SESSION['error'] = "Error de conexión: " . $e->getMessage();
        header("Location: index.php");
        exit;
    }
}
?>