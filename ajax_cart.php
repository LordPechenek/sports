<?php
session_start();
require_once('connect_db.php');
require_once('csrf.php');

header('Content-Type: application/json');

// Проверка CSRF токена
if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'error' => 'Неверный CSRF токен']);
    exit;
}

$action = $_POST['action'] ?? '';
$response = ['success' => false];

// Инициализация корзины
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

switch ($action) {
    case 'add':
        $product_id = (int)($_POST['product_id'] ?? 0);
        $quantity = max(1, (int)($_POST['quantity'] ?? 1));
        
        if ($product_id <= 0) {
            echo json_encode(['success' => false, 'error' => 'Неверный ID товара']);
            exit;
        }
        
        // Проверяем наличие товара
        $stmt = $link->prepare("SELECT id, name, price, stock FROM shop_catalog WHERE id = ?");
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            echo json_encode(['success' => false, 'error' => 'Товар не найден']);
            $stmt->close();
            exit;
        }
        
        $product = $result->fetch_assoc();
        $stmt->close();
        
        // Проверяем доступное количество
        $current_qty = $_SESSION['cart'][$product_id]['quantity'] ?? 0;
        if ($current_qty + $quantity > $product['stock']) {
            echo json_encode([
                'success' => false, 
                'error' => 'Недостаточно товара на складе. Доступно: ' . $product['stock'] . ' шт.'
            ]);
            exit;
        }
        
        // Добавляем в корзину
        if (isset($_SESSION['cart'][$product_id])) {
            $_SESSION['cart'][$product_id]['quantity'] += $quantity;
        } else {
            $_SESSION['cart'][$product_id] = [
                'id' => $product_id,
                'name' => $product['name'],
                'price' => $product['price'],
                'quantity' => $quantity
            ];
        }
        
        // Считаем общее количество товаров
        $total_items = array_sum(array_column($_SESSION['cart'], 'quantity'));
        
        $response = [
            'success' => true,
            'message' => 'Товар добавлен в корзину',
            'cart_count' => $total_items
        ];
        break;
        
    case 'remove':
        $product_id = (int)($_POST['product_id'] ?? 0);
        
        if (isset($_SESSION['cart'][$product_id])) {
            unset($_SESSION['cart'][$product_id]);
            $total_items = array_sum(array_column($_SESSION['cart'], 'quantity'));
            $response = [
                'success' => true,
                'message' => 'Товар удален из корзины',
                'cart_count' => $total_items
            ];
        } else {
            $response = ['success' => false, 'error' => 'Товар не найден в корзине'];
        }
        break;
        
    case 'update':
        $product_id = (int)($_POST['product_id'] ?? 0);
        $quantity = max(0, (int)($_POST['quantity'] ?? 0));
        
        if ($quantity === 0) {
            if (isset($_SESSION['cart'][$product_id])) {
                unset($_SESSION['cart'][$product_id]);
            }
            $response = ['success' => true, 'message' => 'Товар удален'];
        } elseif (isset($_SESSION['cart'][$product_id])) {
            // Проверяем наличие
            $stmt = $link->prepare("SELECT stock FROM shop_catalog WHERE id = ?");
            $stmt->bind_param("i", $product_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $product = $result->fetch_assoc();
            $stmt->close();
            
            if ($quantity > $product['stock']) {
                echo json_encode([
                    'success' => false, 
                    'error' => 'Недостаточно товара на складе. Доступно: ' . $product['stock'] . ' шт.'
                ]);
                exit;
            }
            
            $_SESSION['cart'][$product_id]['quantity'] = $quantity;
            $total_items = array_sum(array_column($_SESSION['cart'], 'quantity'));
            $response = [
                'success' => true,
                'message' => 'Количество обновлено',
                'cart_count' => $total_items
            ];
        } else {
            $response = ['success' => false, 'error' => 'Товар не найден в корзине'];
        }
        break;
        
    case 'clear':
        $_SESSION['cart'] = [];
        $response = ['success' => true, 'message' => 'Корзина очищена', 'cart_count' => 0];
        break;
        
    case 'check':
        // Проверка доступности товаров в корзине
        $unavailable = [];
        foreach ($_SESSION['cart'] as $product_id => $item) {
            $stmt = $link->prepare("SELECT stock FROM shop_catalog WHERE id = ?");
            $stmt->bind_param("i", $product_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0 || $result->fetch_assoc()['stock'] < $item['quantity']) {
                $unavailable[] = $product_id;
            }
            $stmt->close();
        }
        
        if (!empty($unavailable)) {
            foreach ($unavailable as $id) {
                unset($_SESSION['cart'][$id]);
            }
            $response = [
                'success' => true,
                'removed' => $unavailable,
                'message' => 'Некоторые товары недоступны и были удалены'
            ];
        } else {
            $response = ['success' => true, 'message' => 'Все товары доступны'];
        }
        break;
        
    default:
        $response = ['success' => false, 'error' => 'Неизвестное действие'];
}

$link->close();
echo json_encode($response);
