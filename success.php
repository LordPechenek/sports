<?php
session_start();
require_once('connect_db.php');
require_once('csrf.php');

// Проверка наличия номера заказа
if (!isset($_GET['order_id']) || !is_numeric($_GET['order_id'])) {
    header('Location: index.php');
    exit;
}

$order_id = (int)$_GET['order_id'];

// Получаем данные заказа
$stmt = $link->prepare("SELECT * FROM orders WHERE id = ?");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: index.php');
    exit;
}

$order = $result->fetch_assoc();
$stmt->close();

// Получаем товары заказа
$items_stmt = $link->prepare("SELECT * FROM order_items WHERE order_id = ?");
$items_stmt->bind_param("i", $order_id);
$items_stmt->execute();
$items_result = $items_stmt->get_result();
$order_items = [];
while ($item = $items_result->fetch_assoc()) {
    $order_items[] = $item;
}
$items_stmt->close();

// Очищаем корзину после успешного заказа
unset($_SESSION['cart']);

$link->close();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Заказ оформлен - SportFit</title>
    <link rel="stylesheet" href="css/styles.css">
</head>
<body>
    <header class="header">
        <div class="container header-content">
            <a href="index.php" class="logo">SportFit</a>
            <nav class="nav">
                <a href="index.php">Главная</a>
                <a href="catalog.php">Каталог</a>
                <a href="settings.php">Личный кабинет</a>
            </nav>
            <div class="header-actions">
                <a href="cart.php" class="cart-icon">
                    🛒 <span class="cart-count">0</span>
                </a>
            </div>
        </div>
    </header>

    <main class="success-page">
        <div class="container">
            <div class="success-box">
                <div class="success-icon">✅</div>
                <h1>Заказ успешно оформлен!</h1>
                <p class="success-message">Спасибо за ваш заказ. Мы свяжемся с вами в ближайшее время.</p>
                
                <div class="order-details">
                    <h2>Детали заказа №<?= htmlspecialchars($order_id) ?></h2>
                    <div class="order-info">
                        <p><strong>Дата:</strong> <?= htmlspecialchars($order['created_at']) ?></p>
                        <p><strong>Имя:</strong> <?= htmlspecialchars($order['customer_name']) ?></p>
                        <p><strong>Телефон:</strong> <?= htmlspecialchars($order['customer_phone']) ?></p>
                        <?php if (!empty($order['customer_email'])): ?>
                            <p><strong>Email:</strong> <?= htmlspecialchars($order['customer_email']) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($order['customer_address'])): ?>
                            <p><strong>Адрес:</strong> <?= htmlspecialchars($order['customer_address']) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($order['customer_comment'])): ?>
                            <p><strong>Комментарий:</strong> <?= nl2br(htmlspecialchars($order['customer_comment'])) ?></p>
                        <?php endif; ?>
                    </div>
                    
                    <h3>Товары в заказе:</h3>
                    <table class="order-table">
                        <thead>
                            <tr>
                                <th>Товар</th>
                                <th>Цена</th>
                                <th>Кол-во</th>
                                <th>Сумма</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($order_items as $item): ?>
                            <tr>
                                <td><?= htmlspecialchars($item['product_name']) ?></td>
                                <td><?= number_format($item['price'], 2) ?> ₽</td>
                                <td><?= $item['quantity'] ?> шт.</td>
                                <td><?= number_format($item['total'], 2) ?> ₽</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="3"><strong>Итого:</strong></td>
                                <td><strong><?= number_format($order['total_amount'], 2) ?> ₽</strong></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                
                <div class="success-actions">
                    <a href="catalog.php" class="btn btn-primary">Продолжить покупки</a>
                    <a href="settings.php?tab=orders" class="btn btn-secondary">Мои заказы</a>
                </div>
            </div>
        </div>
    </main>

    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3>SportFit</h3>
                    <p>Ваш надежный партнер в мире спорта и здорового образа жизни.</p>
                </div>
                <div class="footer-section">
                    <h3>Контакты</h3>
                    <p>📞 +7 (999) 000-00-00</p>
                    <p>✉️ info@sportfit.ru</p>
                    <p>📍 г. Москва, ул. Спортивная, д. 1</p>
                </div>
                <div class="footer-section">
                    <h3>Режим работы</h3>
                    <p>Пн-Пт: 9:00 - 21:00</p>
                    <p>Сб-Вс: 10:00 - 20:00</p>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; <?= date('Y') ?> SportFit. Все права защищены.</p>
            </div>
        </div>
    </footer>
</body>
</html>
