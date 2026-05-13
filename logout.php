<?php
session_start();
require_once('connect_db.php');
require_once('auth_helper.php');

// Очищаем токен в БД, если пользователь был авторизован
if (isset($_SESSION['user_id'])) {
    $stmt = $link->prepare("UPDATE user SET remember_token = NULL, token_expires = NULL WHERE user_id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $stmt->close();
}

// Очищаем куки
clearRememberCookie();
// Опционально: сохраняем логин для предзаполнения (если нужно)
// setcookie('remember_login', $_SESSION['username'] ?? '', [...]);

$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires' => time() - 42000,
        'path' => $params["path"],
        'domain' => $params["domain"],
        'secure' => $params["secure"],
        'httponly' => $params["httponly"],
        'samesite' => $params["samesite"] ?? 'Strict'
    ]);
}
session_destroy();
$link->close();

header('Location: login.php');
exit;