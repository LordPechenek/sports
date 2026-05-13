<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$role = $_SESSION['role'] ?? '';
$logged = !empty($_SESSION['logged_in']);

if (!$logged || $role !== 'admin') {
    header('Location: login.php');
    exit;
}