<?php
// Инициализируем сессию для хранения сообщений и CSRF
session_start();

$appDebug = getenv('APP_DEBUG');
if ($appDebug === '1' || strtolower((string) $appDebug) === 'true') {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
}

require_once __DIR__ . '/csrf.php';

// Переменные для хранения данных и ошибок
$errors = [];
$formData = [
    'username' => '',
    'login' => '',
    'email' => ''
];

// Подключение к БД
require_once('connect_db.php');

if ($link->connect_error) {
    die("Ошибка подключения к БД: " . $link->connect_error);
}

// Обрабатываем форму, если она отправлена
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Сессия устарела. Обновите страницу и повторите попытку.';
    } else {
        // Получаем и очищаем данные
    $formData['username'] = trim(htmlspecialchars($_POST['username'] ?? ''));
    $formData['login'] = trim(htmlspecialchars($_POST['login'] ?? ''));
    $formData['email'] = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Валидация данных
    if (empty($formData['username'])) {
        $errors[] = 'Имя пользователя обязательно для заполнения';
    }

    if (empty($formData['login'])) {
        $errors[] = 'Логин обязателен для заполнения';
    }

    if (!$formData['email']) {
        $errors[] = 'Введите корректный email';
    }

    if (strlen($password) < 6) {
        $errors[] = 'Пароль должен содержать минимум 6 символов';
    }

    // Проверяем совпадение паролей
    if ($password !== $confirm_password) {
        $errors[] = 'Пароли не совпадают. Пожалуйста, введите одинаковый пароль в обоих полях.';
    }

    // Проверка согласия с условиями
    if (!isset($_POST['terms'])) {
        $errors[] = 'Необходимо согласиться с условиями использования и политикой конфиденциальности';
    }

    // Если ошибок нет, сохраняем пользователя
    if (empty($errors)) {
        // Хэшируем пароль
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Подготовленный запрос для вставки данных
        $stmt = $link->prepare(
            "INSERT INTO user (username, login, email, password) VALUES (?, ?, ?, ?)"
        );
        $stmt->bind_param(
            "ssss",
            $formData['username'],
            $formData['login'],
            $formData['email'],
            $hashed_password
        );

        if ($stmt->execute()) {
            $_SESSION['user_id'] = $link->insert_id;
            $_SESSION['username'] = $formData['username'];
            $_SESSION['logged_in'] = true;
            $_SESSION['role'] = 'user'; // По умолчанию роль user
            $stmt->close();
            header("Location: profile.php");
            exit;
        } else {
            $errors[] = 'Ошибка при сохранении данных: ' . $stmt->error;
            $stmt->close();
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

    <link rel="dns-prefetch" href="//fonts.googleapis.com">
    <link rel="dns-prefetch" href="//cdnjs.cloudflare.com">
    <link rel="preload" href="css/styles_register.css" as="style">

    <title>Регистрация на SportNutrition</title>

    <link rel="stylesheet" href="css/styles_register.css">
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

    <section class="register-form">
        <a href="index.php" class="close-button">×</a>

        <h2>Регистрация</h2>

        <!-- Вывод сообщений об ошибках -->
        <?php if (!empty($errors)): ?>
            <div class="error-messages">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form action="" method="post" autocomplete="on" role="form" aria-label="Форма регистрации">
            <?php csrf_field(); ?>
            <div class="input-wrapper">
                <label for="reg-username" class="hidden-label">Имя пользователя (обязательно):</label>
                <input type="text" id="reg-username" name="username"
                    placeholder="Введите имя пользователя" required
                    autocomplete="username"
                    value="<?= htmlspecialchars($formData['username']) ?>">
                <i class="fa fa-address-card input-icon"></i>
            </div>

            <div class="input-wrapper">
                <label for="reg-login" class="hidden-label">Логин:</label>
                <input type="text" id="reg-login" name="login"
                    placeholder="Введите логин" required
                    autocomplete="login"
                    value="<?= htmlspecialchars($formData['login']) ?>">
                <i class="fa fa-user input-icon"></i>
            </div>

            <div class="input-wrapper">
                <label for="reg-email" class="hidden-label">Email (обязательно):</label>
                <input type="email" id="reg-email" name="email"
                    placeholder="Введите email" required
                    autocomplete="email"
                    value="<?= htmlspecialchars($formData['email']) ?>">
                <i class="fa fa-envelope input-icon"></i>
            </div>

            <div class="input-wrapper">
                <label for="reg-password" class="hidden-label">Пароль (обязательно):</label>
                <input type="password" id="reg-password" name="password"
                    placeholder="Введите пароль" required
                    autocomplete="new-password">
                <i class="fa fa-lock input-icon"></i>
            </div>

            <div class="input-wrapper">
                <label for="reg-confirm-password" class="hidden-label">Повторить пароль:</label>
                <input type="password" id="reg-confirm-password" name="confirm_password"
                    placeholder="Повторите пароль" required
                    autocomplete="new-password">
                <i class="fa fa-lock input-icon"></i>
            </div>

            <div class="terms-agreement">
                <label>
                    <input type="checkbox" name="terms" <?= isset($_POST['terms']) ? 'checked' : '' ?>>
                    <span>Я согласен с <a href="index.php">условиями использования</a> и <a href="index.php"
                            >политикой конфиденциальности</a></span>
                </label>
            </div>

            <button type="submit" class="register-button">Зарегистрироваться</button>
        </form>

        <p class="login-hint">
            Уже есть учётная запись?<br>
            <a href="login.php" class="login-link">Войти</a>
        </p>
    </section>

</body>

</html>