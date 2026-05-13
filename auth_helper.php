<?php

/** true при HTTPS или за прокси с X-Forwarded-Proto: https */
function is_request_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    if (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443) {
        return true;
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])
        && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
        return true;
    }
    return false;
}

function cookie_secure_default(): bool
{
    return is_request_https();
}

function generateSecureToken($length = 32) {
    return bin2hex(random_bytes($length));
}

function setRememberCookie($token, $days = 30) {
    $secure = cookie_secure_default();
    setcookie('remember_token', $token, [
        'expires' => time() + ($days * 86400),
        'path' => '/',
        'httponly' => true,
        'secure' => $secure,
        'samesite' => 'Strict'
    ]);
}

function clearRememberCookie() {
    if (isset($_COOKIE['remember_token'])) {
        $secure = cookie_secure_default();
        setcookie('remember_token', '', [
            'expires' => time() - 3600,
            'path' => '/',
            'httponly' => true,
            'secure' => $secure,
            'samesite' => 'Strict'
        ]);
    }
}

function autoLogin($link) {
    if (!isset($_COOKIE['remember_token'])) return false;
    
    $token = $_COOKIE['remember_token'];
    $tokenHash = hash('sha256', $token);
    
    $stmt = $link->prepare("SELECT user_id, username, role, token_expires FROM user WHERE remember_token = ? AND token_expires > NOW()");
    $stmt->bind_param("s", $tokenHash);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    
    if ($user) {
        // Ротируем токен для безопасности
        $newToken = generateSecureToken();
        $newHash = hash('sha256', $newToken);
        $stmt = $link->prepare("UPDATE user SET remember_token = ?, token_expires = DATE_ADD(NOW(), INTERVAL 30 DAY) WHERE user_id = ?");
        $stmt->bind_param("si", $newHash, $user['user_id']);
        $stmt->execute();
        $stmt->close();
        
        setRememberCookie($newToken);
        
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['logged_in'] = true;
        $_SESSION['role'] = $user['role'] ?? 'user';
        return true;
    }
    return false;
}
?>