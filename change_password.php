<?php
session_start();
require_once __DIR__ . '/csrf.php';

// Проверяем, авторизован ли пользователь
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header("Location: login.php");
    exit;
}

// Подключение к БД
require_once('connect_db.php');

if ($link->connect_error) {
    die("Ошибка подключения к БД: " . $link->connect_error);
}

$user_id = $_SESSION['user_id'];
$errors = [];
$success_message = '';

// Обработка формы смены пароля
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Сессия устарела. Обновите страницу.';
    } else {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // Получаем текущий хеш пароля из БД
    $stmt = $link->prepare("SELECT password FROM user WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    // Проверяем текущий пароль
    if (!password_verify($current_password, $user['password'])) {
        $errors[] = 'Текущий пароль введён неверно';
    }

    // Проверяем новый пароль
    if (strlen($new_password) < 6) {
        $errors[] = 'Новый пароль должен содержать минимум 6 символов';
    }

    if ($new_password !== $confirm_password) {
        $errors[] = 'Новые пароли не совпадают';
    }

    if (empty($errors)) {
        // Хешируем новый пароль
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

        // Обновляем пароль в БД
        $stmt = $link->prepare("UPDATE user SET password = ? WHERE user_id = ?");
        $stmt->bind_param("si", $hashed_password, $user_id);

        if ($stmt->execute()) {
            $success_message = 'Пароль успешно изменён!';
        } else {
            $errors[] = 'Ошибка при смене пароля: ' . $link->error;
        }
        $stmt->close();
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
    <title>Сменить пароль — SportNutrition</title>
    <link rel="stylesheet" href="../css/styles_profile.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
</head>
<body>
    <header class="profile-header">
        <div class="logo">SportNutrition</div>
        <nav class="user-nav">
            <span class="welcome-message">Добро пожаловать, <?= htmlspecialchars($_SESSION['username'] ?? 'Пользователь') ?>!</span>
            <a href="logout.php" class="logout-link">Выйти</a>
        </nav>
    </header>

    <main class="profile-container">
        <aside class="profile-sidebar">
            <div class="avatar-placeholder">
                <i class="fas fa-user fa-3x"></i>
            </div>
            <ul class="profile-menu">
                <li><a href="profile.php" class="active"><i class="fas fa-user"></i> Мой профиль</a></li>
                <li><a href="settings.php?tab=orders"><i class="fas fa-shopping-cart"></i> Мои заказы</a></li>
                <li><a href="settings.php?tab=favorites"><i class="fas fa-heart"></i> Избранное</a></li>
                <li><a href="settings.php"><i class="fas fa-cog"></i> Настройки</a></li>
            </ul>
        </aside>

        <section class="profile-content">
            <h1>Сменить пароль</h1>

            <?php if (!empty($errors)): ?>
                <div class="error-message">
                    <strong>Ошибка при смене пароля:</strong>
                    <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
                </div>
            <?php endif; ?>

            <?php if ($success_message): ?>
                <div class="success-message" style="background-color: #e8f5e9; color: #2e7d32; padding: 1rem; border-radius: var(--border-radius); border-left: 4px solid #2e7d32; margin-bottom: 1.5rem;">
                    <?= htmlspecialchars($success_message) ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="profile-form">
                <?php csrf_field(); ?>
                <div class="form-group">
                    <label for="current_password">Текущий пароль:</label>
                    <input type="password" id="current_password" name="current_password" required>
                </div>

                <div class="form-group">
                    <label for="new_password">Новый пароль:</label>
            <input type="password" id="new_password" name="new_password" minlength="6" required>
            <small class="form-hint">Минимум 6 символов</small>
                </div>

                <div class="form-group">
            <label for="confirm_password">Подтвердите новый пароль:</label>
            <input type="password" id="confirm_password" name="confirm_password" required>
                </div>

                <div class="profile-actions">
            <button type="submit" class="btn btn-edit">Сменить пароль</button>
            <a href="profile.php" class="btn btn-secondary">Отмена</a>
                </div>
            </form>
        </section>
    </main>

    <footer class="profile-footer">
        <p>&copy; 2024 SportNutrition. Все права защищены.</p>
    </footer>
</body>
</html>