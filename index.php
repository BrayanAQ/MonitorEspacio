<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar sesión</title>
    <style>
        body {
            margin: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #00758f 0%, #f29111 70%);
            color: #ffffff;
            line-height: 1.6;
            position: relative;
            overflow: hidden;
        }

        .language-selector-top {
            position: absolute;
            top: 20px;
            right: 20px;
            z-index: 1000;
        }

        .language-selector-top select {
            padding: 8px 12px;
            border: 1px solid rgba(255,255,255,0.3);
            border-radius: 6px;
            background: rgba(255,255,255,0.9);
            color: #333;
            font-size: 14px;
            cursor: pointer;
            backdrop-filter: blur(10px);
        }

        .login-container {
            background: #00758f;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.2);
            width: 320px;
            text-align: center;
        }

        .login-title {
            margin: 10px 0;
            font-size: 22px;
        }

        .login-subtitle {
            font-size: 14px;
            color: #ffffff;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 15px;
            text-align: left;
        }

        .form-label {
            font-size: 14px;
            margin-bottom: 5px;
            display: block;
        }

        .input-with-icon {
            position: relative;
        }

        .form-input {
            width: 85%;
            padding: 10px 35px 10px 10px;
            border: 1px solid #ccc;
            border-radius: 6px;
            outline: none;
        }

        .input-icon {
            position: absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
        }

        .login-button {
            width: 100%;
            padding: 12px;
            background: #f29111;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            cursor: pointer;
        }

        .login-footer {
            margin-top: 15px;
        }

        .login-footer a {
            font-size: 14px;
            color: #ffffff;
            text-decoration: none;
        }

        .login-footer a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>

<!-- Selector de idioma en la esquina superior derecha -->
<div class="language-selector-top">
    <select id="languageSelect" onchange="changeLanguage(this.value)">
        <option value="es" selected>Español</option>
        <option value="en">English</option>
    </select>
</div>

<div class="login-container">
    <div class="login-header">
        <h2 class="login-title">Bienvenido</h2>
        <p class="login-subtitle">Por favor, ingrese sus credenciales</p>
    </div>

    <!-- ✅ Envío directo al login.php -->
    <form class="login-form" method="POST" action="login.php">
        <div class="form-group">
            <label class="form-label" for="user">Usuario</label>
            <div class="input-with-icon">
                <input
                        type="text"
                        id="user"
                        name="user"
                        class="form-input"
                        placeholder="Ingrese su usuario"
                        required
                        autocomplete="username"
                >
                <span class="input-icon">👤</span>
            </div>
        </div>

        <div class="form-group">
            <label class="form-label" for="password">Contraseña</label>
            <div class="input-with-icon">
                <input
                        type="password"
                        id="password"
                        name="password"
                        class="form-input"
                        placeholder="Ingrese su contraseña"
                        required
                        autocomplete="current-password"
                >
                <span class="input-icon">🔒</span>
            </div>
        </div>

        <div class="form-group">
            <label class="form-label" for="host">Host</label>
            <div class="input-with-icon">
                <input
                        type="text"
                        id="host"
                        name="host"
                        class="form-input"
                        placeholder="localhost"
                        required
                >
                <span class="input-icon">🖥️</span>
            </div>
        </div>

        <div class="form-group">
            <label class="form-label" for="database">Base de datos</label>
            <div class="input-with-icon">
                <input
                        type="text"
                        id="database"
                        name="database"
                        class="form-input"
                        placeholder="test"
                        required
                >
                <span class="input-icon">🛢️</span>
            </div>
        </div>

        <div class="remember-section">
            <div class="checkbox-container">
                <input type="checkbox" id="remember" name="remember" class="checkbox-input">
                <label for="remember" class="checkbox-label">Recordar sesión</label>
            </div>
        </div>

        <button type="submit" class="login-button">
            Iniciar sesión
        </button>
    </form>

    <div class="login-footer">
        <a href="#" class="forgot-password">¿Olvidaste tu contraseña?</a>
    </div>
</div>

<script>
    function changeLanguage(lang) {
        alert("Cambio de idioma a: " + lang);
    }

    // ✅ Auto-focus al campo de usuario
    window.addEventListener('load', function() {
        document.getElementById('user').focus();
    });
</script>
</body>
</html>
