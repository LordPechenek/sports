<?php
session_start();
require_once __DIR__ . '/connect_db.php';
require_once __DIR__ . '/includes/shop_db.php';
require_once __DIR__ . '/csrf.php';

shop_ensure_schema($link);

// Инициализация корзины в сессии
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Обработка действий с корзиной
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate($_POST['csrf_token'] ?? null)) {
        $error = 'Сессия устарела. Обновите страницу.';
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'add') {
            $product_id = (int) ($_POST['product_id'] ?? 0);
            $qty = max(1, (int) ($_POST['qty'] ?? 1));
            
            if ($product_id > 0) {
                if (isset($_SESSION['cart'][$product_id])) {
                    $_SESSION['cart'][$product_id] += $qty;
                } else {
                    $_SESSION['cart'][$product_id] = $qty;
                }
            }
            header('Location: cart.php?added=1');
            exit;
        }
        
        if ($action === 'update') {
            foreach ($_POST['items'] ?? [] as $pid => $qty) {
                $pid = (int) $pid;
                $qty = (int) $qty;
                if ($qty <= 0) {
                    unset($_SESSION['cart'][$pid]);
                } else {
                    $_SESSION['cart'][$pid] = $qty;
                }
            }
            header('Location: cart.php?updated=1');
            exit;
        }
        
        if ($action === 'remove') {
            $pid = (int) ($_POST['product_id'] ?? 0);
            if ($pid > 0 && isset($_SESSION['cart'][$pid])) {
                unset($_SESSION['cart'][$pid]);
            }
            header('Location: cart.php?removed=1');
            exit;
        }
        
        if ($action === 'clear') {
            $_SESSION['cart'] = [];
            header('Location: cart.php?cleared=1');
            exit;
        }
    }
}

// Получение товаров корзины из БД
$cart_items = [];
$total = 0;

if (!empty($_SESSION['cart'])) {
    $ids = implode(',', array_map('intval', array_keys($_SESSION['cart'])));
    $sql = "SELECT product_id, title, price, image, stock_qty FROM catalog WHERE product_id IN ($ids)";
    $result = $link->query($sql);
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $pid = (int) $row['product_id'];
            $qty = $_SESSION['cart'][$pid] ?? 0;
            if ($qty > 0) {
                $row['qty'] = $qty;
                $row['subtotal'] = (float) $row['price'] * $qty;
                $total += $row['subtotal'];
                $cart_items[] = $row;
            }
        }
        $result->close();
    }
}

$site = shop_site_settings($link);
$link->close();

$added = isset($_GET['added']);
$updated = isset($_GET['updated']);
$removed = isset($_GET['removed']);
$cleared = isset($_GET['cleared']);
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Корзина — SportNutrition</title>
    <link rel="stylesheet" href="css/styles_catalog.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
    <style>
        .cart-page { max-width: 1100px; margin: 0 auto; padding: 2rem 1rem; }
        .cart-page h1 { margin-bottom: 1.5rem; }
        .cart-table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,.08); }
        .cart-table th { background: #f5f5f5; padding: 1rem; text-align: left; font-weight: 500; }
        .cart-table td { padding: 1rem; border-top: 1px solid #eee; vertical-align: middle; }
        .cart-item-img { width: 60px; height: 60px; object-fit: cover; border-radius: 8px; }
        .cart-qty-input { width: 70px; padding: 0.4rem; text-align: center; border: 1px solid #ddd; border-radius: 6px; }
        .cart-total { margin-top: 1.5rem; text-align: right; font-size: 1.25rem; font-weight: 500; }
        .cart-actions { margin-top: 1.5rem; display: flex; gap: 1rem; justify-content: flex-end; }
        .flash-msg { background: #e8f5e9; color: #2e7d32; padding: 0.75rem 1rem; border-radius: 8px; margin-bottom: 1rem; }
        .empty-cart { text-align: center; padding: 3rem; color: #888; }
        .empty-cart i { font-size: 3rem; margin-bottom: 1rem; color: #ccc; }
        .btn-checkout { background: var(--primary-color); color: #fff; padding: 0.75rem 2rem; border: none; border-radius: 8px; cursor: pointer; font-size: 1rem; }
        .btn-checkout:hover { opacity: 0.9; }
        .btn-clear { background: #f5f5f5; color: #666; padding: 0.75rem 1.5rem; border: 1px solid #ddd; border-radius: 8px; cursor: pointer; }
        .btn-remove { background: #ffebee; color: #c62828; padding: 0.4rem 0.75rem; border: none; border-radius: 6px; cursor: pointer; }
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

    <main class="cart-page">
        <h1>Корзина</h1>

        <?php if ($added): ?><div class="flash-msg">Товар добавлен в корзину</div><?php endif; ?>
        <?php if ($updated): ?><div class="flash-msg">Корзина обновлена</div><?php endif; ?>
        <?php if ($removed): ?><div class="flash-msg">Товар удалён из корзины</div><?php endif; ?>
        <?php if ($cleared): ?><div class="flash-msg">Корзина очищена</div><?php endif; ?>

        <?php if (empty($cart_items)): ?>
            <div class="empty-cart">
                <i class="fas fa-shopping-cart"></i>
                <p>Ваша корзина пуста</p>
                <p style="margin-top: 0.5rem;"><a href="catalog.php" style="color: var(--primary-color);">Перейти в каталог</a></p>
            </div>
        <?php else: ?>
            <form method="POST">
                <?php csrf_field(); ?>
                <input type="hidden" name="action" value="update">
                
                <table class="cart-table">
                    <thead>
                        <tr>
                            <th>Товар</th>
                            <th>Цена</th>
                            <th>Количество</th>
                            <th>Сумма</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cart_items as $item): ?>
                            <tr>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 1rem;">
                                        <img src="<?= !empty($item['image']) ? 'uploads/catalog/' . htmlspecialchars($item['image']) : 'img/placeholder.png' ?>" 
                                             alt="<?= htmlspecialchars($item['title']) ?>" class="cart-item-img">
                                        <span><?= htmlspecialchars($item['title']) ?></span>
                                    </div>
                                </td>
                                <td><?= number_format((float) $item['price'], 0, '', ' ') ?> ₽</td>
                                <td>
                                    <input type="number" name="items[<?= (int) $item['product_id'] ?>]" 
                                           value="<?= (int) $item['qty'] ?>" min="1" max="<?= (int) $item['stock_qty'] ?>" 
                                           class="cart-qty-input">
                                </td>
                                <td><?= number_format($item['subtotal'], 0, '', ' ') ?> ₽</td>
                                <td>
                                    <form method="POST" style="display: inline;">
                                        <?php csrf_field(); ?>
                                        <input type="hidden" name="action" value="remove">
                                        <input type="hidden" name="product_id" value="<?= (int) $item['product_id'] ?>">
                                        <button type="submit" class="btn-remove"><i class="fas fa-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="cart-total">
                    Итого: <?= number_format($total, 0, '', ' ') ?> ₽
                </div>

                <div class="cart-actions">
                    <button type="button" onclick="document.querySelector('form[action=\'clear\']').submit()" class="btn-clear">
                        <i class="fas fa-trash"></i> Очистить
                    </button>
                    <button type="submit" class="btn-checkout">Обновить корзину</button>
                    <a href="checkout.php" class="btn-checkout" style="text-decoration: none;">Оформить заказ</a>
                </div>
            </form>

            <form method="POST" action="cart.php" style="display: none;" id="clear-form">
                <?php csrf_field(); ?>
                <input type="hidden" name="action" value="clear">
            </form>
            
            <script>
                document.querySelector('.btn-clear').addEventListener('click', function() {
                    if (confirm('Вы уверены, что хотите очистить корзину?')) {
                        document.getElementById('clear-form').submit();
                    }
                });
            </script>
        <?php endif; ?>
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
