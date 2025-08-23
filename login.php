<?php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $host = $_POST['host'];
    $db   = $_POST['database'];
    $user = $_POST['user'];
    $pass = $_POST['password'];

    try {
        $pdo = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        echo "✅ Conexión exitosa a $db en $host con el usuario $user.";
        // Aquí podrías redirigir al "dashboard" o cargar datos de la BD
    } catch (PDOException $e) {
        echo "❌ Error de conexión: " . $e->getMessage();
    }
}
?>

