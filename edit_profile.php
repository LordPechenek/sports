<?php
session_start();
require_once __DIR__ . '/csrf.php';

if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header("Location: login.php");
    exit;
}
require_once('connect_db.php');
if ($link->connect_error) die("Ошибка подключения к БД: " . $link->connect_error);

$user_id = $_SESSION['user_id'];
$errors = [];
$success_message = '';
$upload_dir = 'uploads/avatars/';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Сессия устарела. Обновите страницу.';
    } else {
    $username = trim($_POST['username']);
    $login = trim($_POST['login']);
    $email = trim($_POST['email']);

    // Валидация текстовых полей
    if (empty($username)) $errors[] = 'Имя не может быть пустым';
    if (empty($login)) $errors[] = 'Логин не может быть пустым';
    if (strlen($login) < 3) $errors[] = 'Логин минимум 3 символа';
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Некорректный email';

    // Проверка уникальности логина
    if (empty($errors)) {
        $stmt_check = $link->prepare("SELECT user_id FROM user WHERE login = ? AND user_id != ?");
        $stmt_check->bind_param("si", $login, $user_id);
        $stmt_check->execute();
        if ($stmt_check->get_result()->num_rows > 0) $errors[] = 'Логин уже занят';
        $stmt_check->close();
    }

    // Получаем текущий аватар из БД
    $stmt_cur = $link->prepare("SELECT avatar FROM user WHERE user_id = ?");
    $stmt_cur->bind_param("i", $user_id);
    $stmt_cur->execute();
    $current_avatar = $stmt_cur->get_result()->fetch_assoc()['avatar'] ?? '';
    $stmt_cur->close();
    $avatar_filename = $current_avatar;

    // Обработка загрузки файла
    if (empty($errors) && !empty($_FILES['avatar']['name'])) {
        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $max_size = 2 * 1024 * 1024;

        if (!in_array($_FILES['avatar']['type'], $allowed)) $errors[] = 'Неподдерживаемый формат';
        elseif ($_FILES['avatar']['size'] > $max_size) $errors[] = 'Файл > 2MB';
        else {
            $ext = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
            $new_name = "avatar_{$user_id}_" . time() . ".{$ext}";
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

            if (move_uploaded_file($_FILES['avatar']['tmp_name'], $upload_dir . $new_name)) {
                // Удаляем старый файл
                if (!empty($current_avatar) && file_exists($upload_dir . $current_avatar)) {
                    unlink($upload_dir . $current_avatar);
                }
                $avatar_filename = $new_name;
            } else {
                $errors[] = 'Ошибка сохранения файла';
            }
        }
    }

    // Сохранение в БД
    if (empty($errors)) {
        $stmt = $link->prepare("UPDATE user SET username=?, login=?, email=?, avatar=? WHERE user_id=?");
        $stmt->bind_param("ssssi", $username, $login, $email, $avatar_filename, $user_id);
        if ($stmt->execute()) {
            $success_message = 'Профиль обновлён!';
            $_SESSION['username'] = $username;
            $_SESSION['login'] = $login;
        } else {
            $errors[] = 'Ошибка БД: ' . $link->error;
        }
        $stmt->close();
    }
    }
}

// Получаем актуальные данные (с аватаром)
$stmt = $link->prepare("SELECT username, login, email, avatar FROM user WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();
$link->close();
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Редактирование профиля — SportNutrition</title>
    <link rel="stylesheet" href="css/styles_profile.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
    <style>
        .avatar-preview {
            margin-top: 0.5rem;
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #eee;
            display: block;
        }

        .form-hint {
            font-size: 0.8rem;
            color: #666;
            margin-top: 0.2rem;
            display: block;
        }
    </style>
</head>

<body>
    <header class="profile-header">
        <div class="logo">SportNutrition</div>
        <nav class="user-nav">
            <span class="welcome-message">Добро пожаловать, <?= htmlspecialchars($user['username'] ?? 'Пользователь') ?>!</span>
            <a href="logout.php" class="logout-link">Выйти</a>
        </nav>
    </header>
    <main class="profile-container">
        <aside class="profile-sidebar">
            <div class="avatar-placeholder">
                <?php if (!empty($user['avatar']) && file_exists($upload_dir . $user['avatar'])): ?>
                    <img src="<?= htmlspecialchars($upload_dir . $user['avatar']) ?>" class="avatar-img" alt="Аватар">
                <?php else: ?>
                    <i class="fas fa-user fa-3x"></i>
                <?php endif; ?>
            </div>
            <ul class="profile-menu">
                <li><a href="profile.php"><i class="fas fa-user"></i> Мой профиль</a></li>
                <li><a href="settings.php?tab=orders"><i class="fas fa-shopping-cart"></i> Мои заказы</a></li>
                <li><a href="settings.php?tab=favorites"><i class="fas fa-heart"></i> Избранное</a></li>
                <li><a href="edit_profile.php" class="active"><i class="fas fa-cog"></i> Настройки</a></li>
            </ul>
        </aside>
        <section class="profile-content">
            <h1>Редактирование профиля</h1>
            <?php if (!empty($errors)): ?>
                <div class="error-message"><strong>Ошибка:</strong>
                    <ul><?php foreach ($errors as $err): ?><li><?= htmlspecialchars($err) ?></li><?php endforeach; ?></ul>
                </div>
            <?php endif; ?>
            <?php if ($success_message): ?>
                <div class="success-message" style="background:#e8f5e9;color:#2e7d32;padding:1rem;border-radius:var(--border-radius);border-left:4px solid #2e7d32;margin-bottom:1.5rem;"><?= htmlspecialchars($success_message) ?></div>
            <?php endif; ?>
            <!-- ВАЖНО: enctype обязателен для файлов -->
            <form method="POST" enctype="multipart/form-data" class="profile-form">
                <?php csrf_field(); ?>
                <div class="form-group">
                    <label for="username">Имя:</label>
                    <input type="text" id="username" name="username" value="<?= htmlspecialchars($user['username'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label for="login">Логин:</label>
                    <input type="text" id="login" name="login" value="<?= htmlspecialchars($user['login'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label for="avatar">Аватар:</label>
                    <input type="file" id="avatar" name="avatar" accept="image/jpeg,image/png,image/gif,image/webp">
                    <span class="form-hint">JPG, PNG, GIF, WEBP. Макс. 2MB.</span>
                    <?php if (!empty($user['avatar']) && file_exists($upload_dir . $user['avatar'])): ?>
                        <img src="<?= htmlspecialchars($upload_dir . $user['avatar']) ?>" class="avatar-preview" alt="Текущий аватар">
                    <?php endif; ?>
                </div>
                <div class="profile-actions">
                    <button type="submit" class="btn btn-edit">Сохранить изменения</button>
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