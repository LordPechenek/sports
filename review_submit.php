<?php
session_start();
require_once __DIR__ . '/csrf.php';

if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_validate($_POST['csrf_token'] ?? null)) {
    header('Location: catalog.php#reviews');
    exit;
}

require_once __DIR__ . '/connect_db.php';
require_once __DIR__ . '/includes/shop_db.php';
shop_ensure_schema($link);

$user_id = (int) $_SESSION['user_id'];
$body = trim((string) ($_POST['body'] ?? ''));
$rating = (int) ($_POST['rating'] ?? 5);
$product_id = ($_POST['product_id'] ?? '') === '' ? null : (int) $_POST['product_id'];
if ($product_id !== null && $product_id <= 0) {
    $product_id = null;
}
if ($rating < 1) {
    $rating = 1;
}
if ($rating > 5) {
    $rating = 5;
}

if ($body === '' || mb_strlen($body) < 5) {
    $link->close();
    header('Location: catalog.php#reviews&review_err=1');
    exit;
}

if ($product_id === null) {
    $st = $link->prepare("INSERT INTO shop_review (user_id, product_id, rating, body, status) VALUES (?, NULL, ?, ?, 'pending')");
    $st->bind_param('iis', $user_id, $rating, $body);
} else {
    $st = $link->prepare("INSERT INTO shop_review (user_id, product_id, rating, body, status) VALUES (?, ?, ?, ?, 'pending')");
    $st->bind_param('iiis', $user_id, $product_id, $rating, $body);
}
$st->execute();
$st->close();

$link->close();
header('Location: catalog.php#reviews&review_ok=1');
exit;
