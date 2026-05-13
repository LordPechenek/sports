<?php
session_start();
require_once __DIR__ . '/connect_db.php';
require_once __DIR__ . '/includes/shop_db.php';
require_once __DIR__ . '/csrf.php';

shop_ensure_schema($link);

// Обработка AJAX запроса для быстрого просмотра
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    if (isset($_GET['quick_view'])) {
        $pid = (int)$_GET['quick_view'];
        $stmt = $link->prepare("SELECT * FROM catalog WHERE product_id = ?");
        $stmt->bind_param("i", $pid);
        $stmt->execute();
        $product = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($product) {
            echo '<h2 style="margin-bottom:1rem;">' . htmlspecialchars($product['title']) . '</h2>';
            echo '<img src="' . (!empty($product['image']) ? 'uploads/catalog/' . htmlspecialchars($product['image']) : 'img/placeholder.png') . '" alt="' . htmlspecialchars($product['title']) . '" style="width:100%;max-height:400px;object-fit:contain;margin-bottom:1rem;border-radius:8px;">';
            echo '<p style="color:#666;margin-bottom:1rem;">' . nl2br(htmlspecialchars($product['description'] ?? '')) . '</p>';
            echo '<p style="font-size:1.5rem;font-weight:bold;color:var(--primary-color);margin-bottom:1rem;">' . number_format($product['price'], 0, '', ' ') . ' ₽</p>';
            if (isset($product['stock_qty'])) {
                echo '<p style="color:' . ($product['stock_qty'] > 0 ? '#28a745' : '#dc3545') . ';margin-bottom:1rem;">' . ($product['stock_qty'] > 0 ? 'В наличии: ' . (int)$product['stock_qty'] . ' шт.' : 'Нет в наличии') . '</p>';
            }
        }
        $link->close();
        exit;
    }
}

$filter_cats = [];
if (shop_table_exists($link, 'shop_category')) {
    $r = $link->query('SELECT slug, name FROM shop_category ORDER BY sort_order, name');
    if ($r) {
        $filter_cats = $r->fetch_all(MYSQLI_ASSOC);
        $r->close();
    }
}

// Поиск и фильтры
$search = $_GET['search'] ?? '';
$min_price = $_GET['min_price'] ?? '';
$max_price = $_GET['max_price'] ?? '';
$category = $_GET['category'] ?? '';

$products = [];
try {
    $sql = 'SELECT c.product_id, c.title, c.description, c.price, c.stock_qty, c.image, c.category_id, sc.slug AS cat_slug
        FROM catalog c
        LEFT JOIN shop_category sc ON c.category_id = sc.category_id
        WHERE 1=1';
    
    $params = [];
    $types = '';
    
    if ($search !== '') {
        $sql .= ' AND c.title LIKE ?';
        $params[] = '%' . $search . '%';
        $types .= 's';
    }
    if ($min_price !== '') {
        $sql .= ' AND c.price >= ?';
        $params[] = (float)$min_price;
        $types .= 'd';
    }
    if ($max_price !== '') {
        $sql .= ' AND c.price <= ?';
        $params[] = (float)$max_price;
        $types .= 'd';
    }
    if ($category !== '') {
        $sql .= ' AND sc.slug = ?';
        $params[] = $category;
        $types .= 's';
    }
    
    $sql .= ' ORDER BY c.product_id DESC';
    
    $stmt = $link->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch (mysqli_sql_exception) {
    $products = [];
}

$reviews_public = [];
if (shop_table_exists($link, 'shop_review')) {
    $rr = $link->query("SELECT r.rating, r.body, r.created_at, u.username FROM shop_review r JOIN user u ON u.user_id = r.user_id WHERE r.status = 'approved' ORDER BY r.created_at DESC LIMIT 30");
    if ($rr) {
        $reviews_public = $rr->fetch_all(MYSQLI_ASSOC);
        $rr->close();
    }
}

$site = shop_site_settings($link);
$link->close();

$review_ok = isset($_GET['review_ok']);
$review_err = isset($_GET['review_err']);
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Каталог — SportNutrition</title>
    <link rel="stylesheet" href="css/styles_catalog.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
    <style>
        .reviews-block { max-width: 1100px; margin: 0 auto 3rem; padding: 0 1rem; }
        .reviews-block h2 { margin-bottom: 1rem; }
        .review-card { background: #fafafa; border-radius: 10px; padding: 1rem 1.25rem; margin-bottom: 0.75rem; border: 1px solid #eee; }
        .review-meta { font-size: 0.85rem; color: #666; margin-bottom: 0.35rem; }
        .review-form { background: #fff; border: 1px solid #eee; border-radius: 10px; padding: 1.25rem; margin-top: 1.5rem; max-width: 560px; }
        .flash-ok { background: #e8f5e9; color: #2e7d32; padding: 0.75rem 1rem; border-radius: 8px; margin-bottom: 1rem; }
        .flash-err { background: #ffebee; color: #c62828; padding: 0.75rem 1rem; border-radius: 8px; margin-bottom: 1rem; }
        a.item-order-btn { display: inline-block; text-align: center; text-decoration: none; line-height: inherit; }
    </style>
</head>

<body>
    <header class="menu">
        <a href="index.php" class="logo">SportNutrition</a>
        <nav>
            <ul>
                <li><a href="catalog.php" class="active">Каталог продуктов</a></li>
                <li><a href="index.php#tariffs">Акции и скидки</a></li>
                <li><a href="index.php#contacts">Доставка и оплата</a></li>
                <li><a href="catalog.php#reviews">Отзывы</a></li>
                <li><a href="index.php#contacts">Контакты</a></li>
            </ul>
        </nav>
        <div class="auth-block">
            <?php 
            $cart_count = 0;
            if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
                foreach ($_SESSION['cart'] as $qty) {
                    $cart_count += (int) $qty;
                }
            }
            ?>
            <?php if (isset($_SESSION['logged_in']) && $_SESSION['logged_in']): ?>
                <div class="block"><?= htmlspecialchars($_SESSION['username'] ?? 'Пользователь') ?></div>
                <div class="block"><a href="profile.php">Профиль</a></div>
            <?php else: ?>
                <div class="block"><a href="login.php">Войти</a></div>
                <div class="block"><a href="reg.php">Регистрация</a></div>
            <?php endif; ?>
            <div class="block" style="position:relative;">
                <a href="cart.php" class="cart-icon"><i class="fas fa-shopping-cart"></i></a>
                <?php if ($cart_count > 0): ?>
                    <span class="cart-count" style="position:absolute;top:-8px;right:-8px;background:var(--primary-color);color:#fff;font-size:0.7rem;padding:2px 6px;border-radius:50%;min-width:18px;text-align:center;"><?= $cart_count ?></span>
                <?php else: ?>
                    <span class="cart-count" style="display:none;position:absolute;top:-8px;right:-8px;background:var(--primary-color);color:#fff;font-size:0.7rem;padding:2px 6px;border-radius:50%;min-width:18px;text-align:center;">0</span>
                <?php endif; ?>
            </div>
        </div>
    </header>
    <main>
        <!-- Хлебные крошки -->
        <nav class="breadcrumbs" aria-label="Хлебные крошки">
            <div class="container">
                <a href="index.php">Главная</a>
                <span class="separator">/</span>
                <span class="current">Каталог</span>
            </div>
        </nav>
        
        <section class="catalog-section">
            <h1>Каталог продуктов</h1>
            <div class="catalog-filters">
                <input type="text" placeholder="Поиск по названию..." class="search-input" id="search-products" value="<?= htmlspecialchars($search) ?>">
                <select class="filter-select" id="category-filter">
                    <option value="">Все категории</option>
                    <?php foreach ($filter_cats as $fc): ?>
                        <option value="<?= htmlspecialchars($fc['slug'], ENT_QUOTES, 'UTF-8') ?>" <?= $category === $fc['slug'] ? 'selected' : '' ?>><?= htmlspecialchars($fc['name'], ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="number" placeholder="Цена от" class="search-input" id="price-min" value="<?= htmlspecialchars($min_price) ?>" style="max-width:120px;">
                <input type="number" placeholder="Цена до" class="search-input" id="price-max" value="<?= htmlspecialchars($max_price) ?>" style="max-width:120px;">
            </div>
            <div class="collection-grid" id="productsGrid">
                <?php if (empty($products)): ?>
                    <div class="no-results" style="grid-column:1/-1;text-align:center;padding:2rem;">
                        <i class="fas fa-box-open" style="font-size:3rem;color:#ccc;margin-bottom:1rem;"></i>
                        <p>Товары пока не добавлены</p>
                    </div>
                <?php else:
                    foreach ($products as $p):
                        $cat_slug = !empty($p['cat_slug']) ? preg_replace('/[^a-z0-9\-_]/', '', strtolower((string) $p['cat_slug'])) : 'other';
                        if ($cat_slug === '') {
                            $cat_slug = 'other';
                        }
                        $price_fmt = number_format($p['price'], 0, '', ' ');
                        $img_path = !empty($p['image']) ? 'uploads/catalog/' . htmlspecialchars($p['image']) : 'img/placeholder.png';
                        $pid = (int) $p['product_id'];
                        ?>
                        <article class="grid-item item-standard" data-cat="<?= htmlspecialchars($cat_slug, ENT_QUOTES, 'UTF-8') ?>" data-title="<?= strtolower(htmlspecialchars($p['title'])) ?>">
                            <img class="item-img" src="<?= $img_path ?>" alt="<?= htmlspecialchars($p['title']) ?>" loading="lazy">
                            <h3 class="item-title"><?= htmlspecialchars($p['title']) ?></h3>
                            <p class="item-description"><?= htmlspecialchars((string) ($p['description'] ?? '')) ?></p>

                            <?php if (isset($p['stock_qty'])): ?>
                                <p class="item-stock" style="font-size:0.85rem;color:#666;margin-bottom:0.5rem;">
                                    В наличии: <?= (int) $p['stock_qty'] ?> шт.
                                </p>
                            <?php endif; ?>

                            <span class="item-price"><?= $price_fmt ?> ₽</span>
                            <div style="margin-top:0.75rem;display:flex;gap:0.5rem;flex-wrap:wrap;">
                                <?php if (!empty($_SESSION['logged_in'])): ?>
                                    <button type="button" class="item-order-btn add-to-cart-btn" data-product-id="<?= $pid ?>" style="border:none;cursor:pointer;background:var(--primary-color);color:#fff;padding:0.5rem 1rem;border-radius:6px;">В корзину</button>
                                <?php else: ?>
                                    <a class="item-order-btn" href="login.php" style="border:none;cursor:pointer;background:#666;color:#fff;padding:0.5rem 1rem;border-radius:6px;">Войти для заказа</a>
                                <?php endif; ?>
                                <button type="button" class="quick-view-btn" data-product-id="<?= $pid ?>" style="border:none;cursor:pointer;background:#f0f0f0;color:#333;padding:0.5rem 1rem;border-radius:6px;">Быстрый просмотр</button>
                            </div>
                        </article>
                <?php endforeach;
                endif; ?>
            </div>
        </section>

        <section class="reviews-block" id="reviews">
            <h2>Отзывы покупателей</h2>
            <p style="color:#666;font-size:0.9rem;margin-bottom:1rem;">Новые отзывы проходят модерацию и появляются здесь после одобрения администратором.</p>
            <?php if ($review_ok): ?><div class="flash-ok">Спасибо! Ваш отзыв отправлен на модерацию.</div><?php endif; ?>
            <?php if ($review_err): ?><div class="flash-err">Текст отзыва слишком короткий (от 5 символов).</div><?php endif; ?>

            <?php if (empty($reviews_public)): ?>
                <p style="color:#888;">Пока нет одобренных отзывов.</p>
            <?php else: ?>
                <?php foreach ($reviews_public as $rw): ?>
                    <div class="review-card">
                        <div class="review-meta"><?= htmlspecialchars($rw['username']) ?> · <?= (int) $rw['rating'] ?>/5 · <?= date('d.m.Y', strtotime($rw['created_at'])) ?></div>
                        <div><?= nl2br(htmlspecialchars($rw['body'], ENT_QUOTES, 'UTF-8')) ?></div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php if (!empty($_SESSION['logged_in'])): ?>
                <div class="review-form">
                    <h3 style="margin-bottom:0.75rem;font-size:1.1rem;">Оставить отзыв</h3>
                    <form action="review_submit.php" method="post">
                        <?php csrf_field(); ?>
                        <label style="display:block;margin-bottom:0.35rem;font-size:0.9rem;">Оценка</label>
                        <select name="rating" class="filter-select" style="margin-bottom:0.75rem;">
                            <?php for ($i = 5; $i >= 1; $i--): ?>
                                <option value="<?= $i ?>"><?= $i ?> — отлично</option>
                            <?php endfor; ?>
                        </select>
                        <label style="display:block;margin-bottom:0.35rem;font-size:0.9rem;">Текст</label>
                        <textarea name="body" required minlength="5" rows="4" class="search-input" style="width:100%;min-height:100px;margin-bottom:0.75rem;" placeholder="Расскажите о покупке"></textarea>
                        <button type="submit" class="item-order-btn" style="border:none;cursor:pointer;">Отправить на модерацию</button>
                    </form>
                </div>
            <?php else: ?>
                <p><a href="login.php">Войдите</a>, чтобы оставить отзыв.</p>
            <?php endif; ?>
        </section>
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
    
    <!-- Модальное окно быстрого просмотра -->
    <div id="quickViewModal" class="modal">
        <div class="modal-content">
            <button class="modal-close">&times;</button>
            <div class="modal-content-inner"></div>
        </div>
    </div>
    
    <meta name="csrf-token" content="<?= get_csrf_token() ?>">
    <script src="assets/js/cart.js"></script>
</body>

</html>
