<?php
require_once 'connect_db.php';

// Получаем всех пользователей
$result = $link->query("SELECT user_id, password FROM user");

while ($user = $result->fetch_assoc()) {
    $hashed_password = password_hash($user['password'], PASSWORD_DEFAULT);
    
    $stmt = $link->prepare("UPDATE user SET password = ? WHERE user_id = ?");
    $stmt->bind_param("si", $hashed_password, $user['user_id']);
    $stmt->execute();
    
    echo "User ID {$user['user_id']} password hashed<br>";
}

echo "Готово!";
$link->close();
?>