<?php
session_start();
require_once 'Lenguage/Language.php';

// Initialize language system
$lang = new Language();

// Handle language change
if (isset($_GET['lang'])) {
    $lang->setLanguage($_GET['lang']);
    // Redirect to clean URL
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}
?>
<!DOCTYPE html>
<html lang="<?= $lang->getHtmlLangCode() ?>" dir="<?= $lang->isRTL() ? 'rtl' : 'ltr' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $lang->get('page_title') ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            position: relative;
            overflow: hidden;
        }

        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, #00758f 0%, #f29111 70%);
            opacity: 0.9;
            z-index: -1;
        }

        .animated-bg {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: -2;
        }

        .floating-shapes {
            position: absolute;
            width: 100%;
            height: 100%;
            overflow: hidden;
            z-index: -1;
        }

        .shape {
            position: absolute;
            opacity: 0.1;
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

        .language-selector-top {
            position: absolute;
            top: 20px;
            right: 20px;
            z-index: 1000;
        }

        .language-dropdown {
            position: relative;
            display: inline-block;
        }

        .language-btn {
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.3);
            color: white;
            padding: 8px 15px;
            border-radius: 25px;
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
            transform: translateY(-2px);
        }

        .language-dropdown-content {
            position: absolute;
            right: 0;
            top: 100%;
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
        }

        .language-dropdown:hover .language-dropdown-content {
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

        .language-option.active {
            background: rgba(0,117,143,0.15);
            font-weight: 600;
        }

        .main-container {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
        }

        .login-wrapper {
            background: rgba(255,255,255,0.95);
            border-radius: 20px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.1);
            overflow: hidden;
            width: 100%;
            max-width: 900px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            min-height: 500px;
            backdrop-filter: blur(10px);
        }

        .welcome-panel {
            background: linear-gradient(135deg, #00758f 0%, #0a5d6b 100%);
            color: white;
            padding: 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            position: relative;
        }

        .welcome-panel::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.05'%3E%3Ccircle cx='30' cy='30' r='2'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E") repeat;
        }

        .welcome-content {
            position: relative;
            z-index: 2;
        }

        .welcome-title {
            font-size: 2.5rem;
            font-weight: 300;
            margin-bottom: 15px;
            opacity: 0;
            animation: fadeInUp 1s ease forwards 0.2s;
        }

        .welcome-subtitle {
            font-size: 1.1rem;
            opacity: 0.9;
            line-height: 1.6;
            margin-bottom: 30px;
            opacity: 0;
            animation: fadeInUp 1s ease forwards 0.4s;
        }

        .welcome-features {
            list-style: none;
            opacity: 0;
            animation: fadeInUp 1s ease forwards 0.6s;
        }

        .welcome-features li {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
            font-size: 0.95rem;
        }

        .welcome-features i {
            color: #f29111;
            width: 20px;
        }

        .login-panel {
            padding: 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .login-title {
            font-size: 2rem;
            color: #333;
            margin-bottom: 8px;
            font-weight: 600;
        }

        .login-subtitle {
            color: #666;
            font-size: 0.95rem;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
            font-size: 0.9rem;
        }

        .input-wrapper {
            position: relative;
        }

        .form-input {
            width: 100%;
            padding: 12px 45px 12px 15px;
            border: 2px solid #e1e5e9;
            border-radius: 10px;
            font-size: 1rem;
            outline: none;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }

        .form-input:focus {
            border-color: #00758f;
            background: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,117,143,0.1);
        }

        .input-icon {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
            font-size: 1.1rem;
            transition: color 0.3s ease;
        }

        .form-input:focus + .input-icon {
            color: #00758f;
        }

        .remember-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .checkbox-wrapper {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .checkbox-input {
            width: 18px;
            height: 18px;
            accent-color: #00758f;
        }

        .checkbox-label {
            font-size: 0.9rem;
            color: #666;
        }

        .forgot-link {
            color: #00758f;
            text-decoration: none;
            font-size: 0.9rem;
            transition: color 0.3s ease;
        }

        .forgot-link:hover {
            color: #f29111;
        }

        .login-button {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #00758f 0%, #f29111 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .login-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,117,143,0.3);
        }

        .login-button:active {
            transform: translateY(0);
        }

        .divider {
            text-align: center;
            margin: 25px 0;
            position: relative;
            color: #999;
            font-size: 0.9rem;
        }

        .divider::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: #e1e5e9;
            z-index: 1;
        }

        .divider span {
            background: white;
            padding: 0 15px;
            position: relative;
            z-index: 2;
        }

        .social-login {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }

        .social-btn {
            flex: 1;
            padding: 12px;
            border: 2px solid #e1e5e9;
            border-radius: 10px;
            background: white;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-size: 0.9rem;
            color: #666;
        }

        .social-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .social-btn.google:hover {
            border-color: #db4437;
            color: #db4437;
        }

        .social-btn.microsoft:hover {
            border-color: #0078d4;
            color: #0078d4;
        }

        .error-message {
            background: #fee;
            border: 1px solid #fcc;
            color: #c66;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .success-message {
            background: #efe;
            border: 1px solid #cfc;
            color: #6c6;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .login-wrapper {
                grid-template-columns: 1fr;
                max-width: 400px;
            }

            .welcome-panel {
                display: none;
            }

            .login-panel {
                padding: 30px 20px;
            }

            .welcome-title {
                font-size: 2rem;
            }

            .language-selector-top {
                top: 10px;
                right: 10px;
            }
        }

        /* RTL Support */
        [dir="rtl"] .input-icon {
            right: auto;
            left: 15px;
        }

        [dir="rtl"] .form-input {
            padding: 12px 15px 12px 45px;
        }

        [dir="rtl"] .language-dropdown-content {
            right: auto;
            left: 0;
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

<!-- Language Selector -->
<div class="language-selector-top">
    <div class="language-dropdown">
        <button class="language-btn" type="button">
            <?= $lang->getLanguageInfo()['flag'] ?>
            <?= $lang->getLanguageInfo()['name'] ?>
            <i class="fas fa-chevron-down"></i>
        </button>
        <div class="language-dropdown-content">
            <?php foreach ($lang->getSupportedLanguages() as $langCode): ?>
                <?php $langInfo = $lang->getLanguageInfo($langCode); ?>
                <a href="?lang=<?= $langCode ?>"
                   class="language-option <?= $langCode === $lang->getCurrentLanguage() ? 'active' : '' ?>">
                    <span><?= $langInfo['flag'] ?></span>
                    <span><?= $langInfo['name'] ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="main-container">
    <div class="login-wrapper">
        <!-- Welcome Panel -->
        <div class="welcome-panel">
            <div class="welcome-content">
                <h1 class="welcome-title"><?= $lang->get('welcome_title') ?></h1>
                <p class="welcome-subtitle"><?= $lang->get('welcome_subtitle') ?></p>

                <ul class="welcome-features">
                    <li>
                        <i class="fas fa-shield-alt"></i>
                        <?= $lang->get('feature_secure') ?>
                    </li>
                    <li>
                        <i class="fas fa-bolt"></i>
                        <?= $lang->get('feature_fast') ?>
                    </li>
                    <li>
                        <i class="fas fa-globe"></i>
                        <?= $lang->get('feature_multilingual') ?>
                    </li>
                    <li>
                        <i class="fas fa-mobile-alt"></i>
                        <?= $lang->get('feature_responsive') ?>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Login Panel -->
        <div class="login-panel">
            <div class="login-header">
                <h2 class="login-title"><?= $lang->get('login_title') ?></h2>
                <p class="login-subtitle"><?= $lang->get('login_subtitle') ?></p>
            </div>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?= htmlspecialchars($_SESSION['error']) ?>
                    <?php unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i>
                    <?= htmlspecialchars($_SESSION['success']) ?>
                    <?php unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>

            <form class="login-form" method="POST" action="login.php" autocomplete="on">
                <div class="form-group">
                    <label class="form-label" for="user"><?= $lang->get('username') ?></label>
                    <div class="input-wrapper">
                        <input
                                type="text"
                                id="user"
                                name="user"
                                class="form-input"
                                placeholder="<?= $lang->get('username_placeholder') ?>"
                                required
                                autocomplete="username"
                                value="<?= isset($_POST['user']) ? htmlspecialchars($_POST['user']) : '' ?>"
                        >
                        <i class="fas fa-user input-icon"></i>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="password"><?= $lang->get('password') ?></label>
                    <div class="input-wrapper">
                        <input
                                type="password"
                                id="password"
                                name="password"
                                class="form-input"
                                placeholder="<?= $lang->get('password_placeholder') ?>"
                                required
                                autocomplete="current-password"
                        >
                        <i class="fas fa-lock input-icon"></i>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="host"><?= $lang->get('host') ?></label>
                    <div class="input-wrapper">
                        <input
                                type="text"
                                id="host"
                                name="host"
                                class="form-input"
                                placeholder="localhost"
                                required
                                value="<?= isset($_POST['host']) ? htmlspecialchars($_POST['host']) : 'localhost' ?>"
                        >
                        <i class="fas fa-server input-icon"></i>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="database"><?= $lang->get('database') ?></label>
                    <div class="input-wrapper">
                        <input
                                type="text"
                                id="database"
                                name="database"
                                class="form-input"
                                placeholder="<?= $lang->get('database_placeholder') ?>"
                                required
                                value="<?= isset($_POST['database']) ? htmlspecialchars($_POST['database']) : '' ?>"
                        >
                        <i class="fas fa-database input-icon"></i>
                    </div>
                </div>

                <div class="remember-section">
                    <div class="checkbox-wrapper">
                        <input type="checkbox" id="remember" name="remember" class="checkbox-input"
                                <?= isset($_POST['remember']) ? 'checked' : '' ?>>
                        <label for="remember" class="checkbox-label"><?= $lang->get('remember_me') ?></label>
                    </div>
                    <a href="#" class="forgot-link"><?= $lang->get('forgot_password') ?></a>
                </div>

                <button type="submit" class="login-button">
                    <i class="fas fa-sign-in-alt" style="margin-right: 8px;"></i>
                    <?= $lang->get('login_button') ?>
                </button>
            </form>

        </div>
    </div>
</div>

<script>
    // Language translations for JavaScript
    const translations = <?= $lang->getJSTranslations() ?>;

    // Auto-focus on username field
    document.addEventListener('DOMContentLoaded', function() {
        const userField = document.getElementById('user');
        if (userField) {
            userField.focus();
        }
    });

    // Form validation
    document.querySelector('.login-form').addEventListener('submit', function(e) {
        const requiredFields = ['user', 'password', 'host', 'database'];
        const emptyFields = [];

        requiredFields.forEach(field => {
            const input = document.getElementById(field);
            if (!input.value.trim()) {
                emptyFields.push(input.previousElementSibling.textContent);
                input.style.borderColor = '#dc3545';
            } else {
                input.style.borderColor = '#e1e5e9';
            }
        });

        if (emptyFields.length > 0) {
            e.preventDefault();
            alert('<?= $lang->get('required_fields') ?>: ' + emptyFields.join(', '));
        }
    });

    // Real-time field validation
    document.querySelectorAll('.form-input').forEach(input => {
        input.addEventListener('blur', function() {
            if (this.hasAttribute('required') && !this.value.trim()) {
                this.style.borderColor = '#dc3545';
            } else {
                this.style.borderColor = '#e1e5e9';
            }
        });
    });


</script>
</body>
</html>