<?php
require 'Database.php';
require 'Auth.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $host = $_POST['host'];
    $db   = $_POST['database'];
    $user = $_POST['user'];
    $pass = $_POST['password'];

    try {
        // Intentar conexión con la BD
        $database = new Database($host, $db, $user, $pass);
        $auth = new Auth();

        // Guardar sesión
        if ($auth->login($user, $pass, $host, $db)) {
            header("Location: dashboard.php");
            exit;
        } else {
            echo "❌ Usuario o contraseña incorrectos";
        }

    } catch (Exception $e) {
        echo "❌ Error de conexión: " . $e->getMessage();
    }
}
