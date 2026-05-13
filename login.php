<?php
// 1. Инициализируем сессию
session_start();

// Переменные для хранения ошибок и данных формы
$errors = [];
$formData = [
    'login' => '',
];

// Подключение к БД
require_once('connect_db.php');
require_once('auth_helper.php');
require_once('csrf.php');

// 1. Сначала пробуем авто-вход по токену
if (!isset($_SESSION['logged_in']) && autoLogin($link)) {
    header("Location: profile.php");
    exit;
}

// 2. ТОЛЬКО ПОТОМ — предзаполнение логина из куки
if (empty($formData['login']) && isset($_COOKIE['remember_login'])) {
    $formData['login'] = htmlspecialchars($_COOKIE['remember_login']);
}

if ($link->connect_error) {
    die("Ошибка подключения к БД: " . $link->connect_error);
}

// Обрабатываем форму, если она отправлена
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Сессия устарела. Обновите страницу и повторите попытку.';
    } else {
        // Получаем и очищаем данные
    $formData['login'] = trim(htmlspecialchars($_POST['login'] ?? ''));
    $password = $_POST['password'] ?? '';

    // Валидация данных
    if (empty($formData['login'])) {
        $errors[] = 'Логин обязателен для заполнения';
    }

    if (empty($password)) {
        $errors[] = 'Пароль обязателен для заполнения';
    }

    // Если ошибок нет, проверяем пользователя в БД
    if (empty($errors)) {
        // 1. Добавляем role и status в запрос
        $stmt = $link->prepare("SELECT user_id, username, password, role, status FROM user WHERE login = ?");
        $stmt->bind_param("s", $formData['login']);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        // 2. Проверяем статус ДО проверки пароля
        if ($user && ($user['status'] ?? '') === 'blocked') {
            $errors[] = 'Аккаунт заблокирован. Обратитесь в поддержку.';
        } elseif ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['logged_in'] = true;
            $_SESSION['role'] = $user['role'] ?? 'user';

            // Обработка "Запомнить меня" (твой код остаётся без изменений)
            if (!empty($_POST['remember-me'])) {
                setcookie('remember_login', $formData['login'], [
                    'expires' => time() + (30 * 86400),
                    'path' => '/',
                    'httponly' => false,
                    'secure' => cookie_secure_default(),
                    'samesite' => 'Strict'
                ]);
                $token = generateSecureToken();
                $tokenHash = hash('sha256', $token);
                $stmt = $link->prepare("UPDATE user SET remember_token = ?, token_expires = DATE_ADD(NOW(), INTERVAL 30 DAY) WHERE user_id = ?");
                $stmt->bind_param("si", $tokenHash, $user['user_id']);
                $stmt->execute();
                $stmt->close();
                setRememberCookie($token);
            } else {
                clearRememberCookie();
                setcookie('remember_login', '', [
                    'expires' => time() - 3600,
                    'path' => '/',
                    'secure' => cookie_secure_default(),
                    'samesite' => 'Strict',
                ]);
            }
            header("Location: profile.php");
            exit;
        } else {
            $errors[] = 'Неверный логин или пароль';
        }
    }
    }
}

$link->close();
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Security-Policy"
        content="default-src 'self'; script-src 'self' https://cdnjs.cloudflare.com; style-src 'self' https://fonts.googleapis.com https://cdnjs.cloudflare.com; font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; connect-src 'self';">
    <meta http-equiv="X-Frame-Options" content="DENY">

    <link rel="dns-prefetch" href="//fonts.googleapis.com">
    <link rel="dns-prefetch" href="//cdnjs.cloudflare.com">
    <link rel="preload" href="../css/styles_login.css" as="style">
    <title>Вход на SportNutrition</title>
    <link rel="stylesheet" href="../css/styles_login.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
    <link rel="shortcut icon" type="image/x-icon" href="favicon/favicon.ico">
    <link rel="icon" type="image/png" sizes="16x16" href="favicon/favicon-16x16.png">
    <link rel="icon" type="image/png" sizes="32x32" href="favicon/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="192x192" href="favicon/android-chrome-192x192.png">
    <link rel="icon" type="image/png" sizes="512x512" href="favicon/android-chrome-512x512.png">
    <link rel="apple-touch-icon" sizes="180x180" href="favicon/apple-touch-icon.png">
    <link rel="manifest" href="favicon/site.webmanifest">
</head>

<body>
    <header class="logo-header">
        <a href="index.php" class="logo">SportNutrition</a>
    </header>

    <section class="login-form">
        <a href="index.php" class="close-button" aria-label="Вернуться на главную страницу">×</a>

        <h2>Вход</h2>

        <!-- Вывод сообщений об ошибках -->
        <?php if (!empty($errors)): ?>
            <div class="error-message" id="login-error" role="alert" aria-live="assertive">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li>
                            <?= htmlspecialchars($error) ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form action="" method="post" autocomplete="on" role="form" aria-label="Форма входа">
            <?php csrf_field(); ?>
            <div class="input-wrapper">
                <label for="login" class="hidden-label">
                    <span class="sr-only">Логин (обязательно)</span>
                    <span aria-hidden="true">Логин (обязательно)</span>
                </label>
                <input type="text" id="login" name="login" placeholder="Введите логин" required autocomplete="login"
                    value="<?= htmlspecialchars($formData['login']) ?>">
                <i class="fa fa-user input-icon"></i>
            </div>

            <div class="input-wrapper">
                <label for="password" class="hidden-label">
                    <span class="sr-only">Пароль (обязательно)</span>
                    <span aria-hidden="true">Пароль (обязательно)</span>
                </label>
                <input type="password" id="password" name="password" placeholder="Введите пароль" required
                    autocomplete="current-password">
                <i class="fa fa-lock input-icon"></i>
            </div>

            <div class="remember-forgotten">
                <label>
                    <input type="checkbox" id="remember-me" name="remember-me" autocomplete="off">
                    <label for="remember-me">Запомнить меня</label>
                </label>
                <a href="index.php#contacts" class="forgot-password-link">Забыли пароль?</a>
            </div>

            <button type="submit" class="login-button">Войти</button>
        </form>

        <p class="registration-hint">
            У вас нет учётной записи?<br>
            <a href="reg.php" class="register-link">Регистрация</a>
        </p>
    </section>
</body>

</html>