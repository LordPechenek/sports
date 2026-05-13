<?php
session_start();
require_once __DIR__ . '/connect_db.php';
require_once __DIR__ . '/includes/shop_db.php';
require_once __DIR__ . '/csrf.php';

if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header('Location: login.php');
    exit;
}

shop_ensure_schema($link);

// Инициализация корзины
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$errors = [];
$cart_items = [];
$total = 0;

// Получение товаров корзины из БД
if (!empty($_SESSION['cart'])) {
    $ids = implode(',', array_map('intval', array_keys($_SESSION['cart'])));
    $sql = "SELECT product_id, title, price, image, stock_qty FROM catalog WHERE product_id IN ($ids)";
    $result = $link->query($sql);
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $pid = (int) $row['product_id'];
            $qty = $_SESSION['cart'][$pid] ?? 0;
            if ($qty > 0) {
                // Проверка наличия
                if ($qty > (int) $row['stock_qty']) {
                    $errors[] = 'Товар "' . htmlspecialchars($row['title']) . '" недоступен в таком количестве';
                }
                $row['qty'] = $qty;
                $row['subtotal'] = (float) $row['price'] * $qty;
                $total += $row['subtotal'];
                $cart_items[] = $row;
            }
        }
        $result->close();
    }
}

if (empty($cart_items)) {
    header('Location: cart.php');
    exit;
}

$user_id = (int) $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Сессия устарела. Обновите страницу.';
    } else {
        $delivery_name = trim($_POST['delivery_name'] ?? '');
        $delivery_phone = trim($_POST['delivery_phone'] ?? '');
        $delivery_address = trim($_POST['delivery_address'] ?? '');
        $customer_request = trim($_POST['customer_request'] ?? '');
        
        if ($delivery_name === '') {
            $errors[] = 'Укажите имя получателя';
        }
        if ($delivery_phone === '') {
            $errors[] = 'Укажите телефон для связи';
        }
        if ($delivery_address === '') {
            $errors[] = 'Укажите адрес доставки';
        }
        
        if (empty($errors)) {
            try {
                $link->begin_transaction();
                
                // Создание заказа
                $status = 'new';
                $items_note = '';
                $item_details = [];
                foreach ($cart_items as $item) {
                    $item_details[] = $item['title'] . ' × ' . $item['qty'];
                }
                $items_note = implode(', ', $item_details);
                
                $stmt = $link->prepare('INSERT INTO orders (user_id, total_amount, status, items_note, customer_request) VALUES (?, ?, ?, ?, ?)');
                $stmt->bind_param('idsss', $user_id, $total, $status, $items_note, $customer_request);
                $stmt->execute();
                $order_id = $link->insert_id;
                $stmt->close();
                
                // Создание элементов заказа (если есть таблица order_items)
                // Пока просто сохраняем список товаров в items_note
                
                $link->commit();
                
                // Очистка корзины
                $_SESSION['cart'] = [];
                
                $link->close();
                header('Location: settings.php?tab=orders&ordered=1');
                exit;
            } catch (mysqli_sql_exception $e) {
                $link->rollback();
                $errors[] = 'Не удалось создать заказ. Попробуйте позже.';
            }
        }
    }
}

$site = shop_site_settings($link);
$link->close();
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Оформление заказа — SportNutrition</title>
    <link rel="stylesheet" href="css/styles_catalog.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
    <style>
        .checkout-page { max-width: 900px; margin: 0 auto; padding: 2rem 1rem; }
        .checkout-page h1 { margin-bottom: 1.5rem; }
        .checkout-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; }
        @media (max-width: 768px) { .checkout-grid { grid-template-columns: 1fr; } }
        .checkout-section { background: #fff; padding: 1.5rem; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,.08); }
        .checkout-section h2 { font-size: 1.25rem; margin-bottom: 1rem; text-align: left; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; margin-bottom: 0.35rem; font-size: 0.9rem; color: #555; }
        .form-group input, .form-group textarea { width: 100%; padding: 0.6rem; border: 1px solid #ddd; border-radius: 6px; font-size: 1rem; }
        .form-group textarea { min-height: 80px; resize: vertical; }
        .order-summary { background: #f9f9f9; padding: 1rem; border-radius: 8px; margin-bottom: 1rem; }
        .order-item { display: flex; justify-content: space-between; padding: 0.5rem 0; border-bottom: 1px solid #eee; }
        .order-item:last-child { border-bottom: none; }
        .order-total { font-size: 1.25rem; font-weight: 500; text-align: right; margin-top: 1rem; padding-top: 1rem; border-top: 2px solid #ddd; }
        .btn-submit { background: var(--primary-color); color: #fff; padding: 0.75rem 2rem; border: none; border-radius: 8px; cursor: pointer; font-size: 1rem; width: 100%; margin-top: 1rem; }
        .btn-submit:hover { opacity: 0.9; }
        .err-list { background: #ffebee; color: #c62828; padding: 0.75rem 1rem; border-radius: 8px; margin-bottom: 1rem; }
        .err-list ul { margin: 0; padding-left: 1.25rem; }
        .back-link { display: inline-block; margin-bottom: 1rem; color: #666; }
    </style>
</head>

<body>
    <header class="menu">
        <a href="index.php" class="logo">SportNutrition</a>
        <nav>
            <ul>
                <li><a href="catalog.php">Каталог продуктов</a></li>
                <li><a href="index.php#tariffs">Акции и скидки</a></li>
                <li><a href="index.php#contacts">Доставка и оплата</a></li>
                <li><a href="catalog.php#reviews">Отзывы</a></li>
                <li><a href="index.php#contacts">Контакты</a></li>
            </ul>
        </nav>
        <div class="auth-block">
            <?php if (isset($_SESSION['logged_in']) && $_SESSION['logged_in']): ?>
                <div class="block"><?= htmlspecialchars($_SESSION['username'] ?? 'Пользователь') ?></div>
                <div class="block"><a href="profile.php">Профиль</a></div>
            <?php else: ?>
                <div class="block"><a href="login.php">Войти</a></div>
                <div class="block"><a href="reg.php">Регистрация</a></div>
            <?php endif; ?>
        </div>
    </header>

    <main class="checkout-page">
        <h1>Оформление заказа</h1>
        
        <a href="cart.php" class="back-link">← Вернуться в корзину</a>

        <?php if (!empty($errors)): ?>
            <div class="err-list">
                <ul>
                    <?php foreach ($errors as $err): ?>
                        <li><?= htmlspecialchars($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST">
            <?php csrf_field(); ?>
            
            <div class="checkout-grid">
                <div class="checkout-section">
                    <h2>Данные получателя</h2>
                    
                    <div class="form-group">
                        <label>ФИО *</label>
                        <input type="text" name="delivery_name" required value="<?= htmlspecialchars($_SESSION['username'] ?? '') ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Телефон *</label>
                        <input type="tel" name="delivery_phone" required placeholder="+7 (___) ___-__-__">
                    </div>
                    
                    <div class="form-group">
                        <label>Адрес доставки *</label>
                        <textarea name="delivery_address" required placeholder="Город, улица, дом, квартира"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Комментарий к заказу</label>
                        <textarea name="customer_request" placeholder="Например: позвонить за 30 минут до доставки"></textarea>
                    </div>
                </div>

                <div class="checkout-section">
                    <h2>Ваш заказ</h2>
                    
                    <div class="order-summary">
                        <?php foreach ($cart_items as $item): ?>
                            <div class="order-item">
                                <span><?= htmlspecialchars($item['title']) ?> × <?= (int) $item['qty'] ?></span>
                                <span><?= number_format($item['subtotal'], 0, '', ' ') ?> ₽</span>
                            </div>
                        <?php endforeach; ?>
                        
                        <div class="order-total">
                            Итого: <?= number_format($total, 0, '', ' ') ?> ₽
                        </div>
                    </div>
                    
                    <p style="font-size: 0.85rem; color: #666; margin-top: 1rem;">
                        Нажимая кнопку «Оформить заказ», вы соглашаетесь с условиями обработки персональных данных.
                    </p>
                    
                    <button type="submit" class="btn-submit">Оформить заказ</button>
                </div>
            </div>
        </form>
    </main>

    <footer id="contacts">
        <div class="footer-left">
            <a href="index.php" class="logo">SportNutrition</a>
            <p class="social-icons">
                <a href="https://vk.com" rel="noopener noreferrer" target="_blank"><i class="fab fa-vk"></i></a>
                <a href="https://instagram.com" rel="noopener noreferrer" target="_blank"><i class="fab fa-instagram"></i></a>
                <a href="<?= htmlspecialchars($site['telegram_url'], ENT_QUOTES, 'UTF-8') ?>" rel="noopener noreferrer" target="_blank"><i class="fab fa-telegram-plane"></i></a>
            </p>
            <div class="contact-info">
                <p><strong><?= htmlspecialchars($site['phone'], ENT_QUOTES, 'UTF-8') ?></strong></p>
                <p><a href="mailto:<?= htmlspecialchars($site['email'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($site['email'], ENT_QUOTES, 'UTF-8') ?></a></p>
                <p><?= htmlspecialchars($site['address_line'], ENT_QUOTES, 'UTF-8') ?></p>
            </div>
        </div>
        <div class="footer-right">
            <div>
                <h4>Продукты</h4>
                <ul>
                    <li><a href="catalog.php">Каталог</a></li>
                    <li><a href="catalog.php#reviews">Отзывы</a></li>
                </ul>
            </div>
            <div>
                <h4>Полезные статьи</h4>
                <ul>
                    <li><a href="index.php#advantages">Советы новичкам</a></li>
                    <li><a href="index.php#advantages">Правильное питание</a></li>
                    <li><a href="index.php#advantages">Тренировки дома</a></li>
                </ul>
            </div>
            <div>
                <h4>Поддержка</h4>
                <ul>
                    <li><a href="settings.php?tab=returns">Возврат товара</a></li>
                    <li><a href="index.php#contacts">FAQ</a></li>
                    <li><a href="index.php#contacts">Связаться с нами</a></li>
                </ul>
            </div>
            <div>
                <h4>Компания</h4>
                <ul>
                    <li><a href="index.php">О нас</a></li>
                    <li><a href="index.php#contacts">Партнёры</a></li>
                    <li><a href="index.php">Политика конфиденциальности</a></li>
                </ul>
            </div>
        </div>
    </footer>
</body>

</html>
