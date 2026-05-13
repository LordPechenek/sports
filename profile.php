<?php
// Инициализируем сессию
session_start();
// Проверяем, авторизован ли пользователь
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header("Location: login.php");
    exit;
}
// Подключение к БД для получения данных пользователя
require_once('connect_db.php');
if ($link->connect_error) {
    die("Ошибка подключения к БД: " . $link->connect_error);
}
// Получаем данные пользователя из БД
$user_id = $_SESSION['user_id'];
$stmt = $link->prepare("SELECT username, login, email, avatar, phone, birthdate FROM user WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();
$link->close();
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Профиль — SportNutrition</title>
    <link rel="stylesheet" href="css/styles_profile.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
</head>

<body>
    <header class="profile-header">
        <a href="index.php" class="logo">SportNutrition</a>
        <nav class="user-nav">
            <a href="index.php" class="logout-link" style="margin-right:auto;">На главную</a>
            <span class="welcome-message">Добро пожаловать, <?= htmlspecialchars($user['username']) ?>!</span>
            <a href="logout.php" class="logout-link">Выйти</a>
        </nav>
    </header>
    <main class="profile-container">
        <aside class="profile-sidebar">
            <div class="avatar-placeholder">
                <?php if (!empty($user['avatar']) && file_exists('uploads/avatars/' . $user['avatar'])): ?>
                    <img src="uploads/avatars/<?= rawurlencode($user['avatar']) ?>" alt="Аватар" class="avatar-img">
                <?php else: ?>
                    <i class="fas fa-user fa-3x"></i>
                <?php endif; ?>
            </div>
            <ul class="profile-menu">
                <li><a href="profile.php" class="active"><i class="fas fa-user"></i> Мой профиль</a></li>
                <li><a href="settings.php?tab=orders"><i class="fas fa-shopping-cart"></i> Мои заказы</a></li>
                <li><a href="settings.php?tab=favorites"><i class="fas fa-heart"></i> Избранное</a></li>
                <li><a href="settings.php"><i class="fas fa-cog"></i> Настройки</a></li>
                <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
                    <li><a href="admin.php" style="background:#ffeaea;color:#d32f2f"><i class="fas fa-shield-alt"></i> Админ-панель</a></li>
                <?php endif; ?>
            </ul>
        </aside>
        <section class="profile-content">
            <h1>Мой профиль</h1>
            <div class="profile-info">
                <div class="info-item"><label>Имя:</label><span><?= htmlspecialchars($user['username'] ?? '') ?></span></div>
                <div class="info-item"><label>Логин:</label><span><?= htmlspecialchars($user['login'] ?? '') ?></span></div>
                <div class="info-item"><label>Email:</label><span><?= htmlspecialchars($user['email'] ?? '') ?></span></div>
                <div class="info-item"><label>Телефон:</label><span><?= htmlspecialchars($user['phone'] ?? 'Не указан') ?></span></div>
                <div class="info-item"><label>Дата рождения:</label><span><?= !empty($user['birthdate']) ? date('d.m.Y', strtotime($user['birthdate'])) : 'Не указана' ?></span></div>
                <div class="info-item"><label>Статус:</label><span>Активный пользователь</span></div>
            </div>
        </section>
    </main>
    <footer class="profile-footer">
        <p>&copy; 2024 SportNutrition. Все права защищены.</p>
    </footer>
</body>

</html>