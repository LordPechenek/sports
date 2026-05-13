<?php 
session_start();
require_once __DIR__ . '/csrf.php';
?>

<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description"
        content="Магазин спортивного питания SportNutrition. Широкий выбор протеинов, витаминов, аминокислот. Доставка по всей России.">
    <meta property="og:title" content="SportNutrition - магазин спортивного питания">
    <meta property="og:description" content="Широкий выбор спортивного питания с доставкой.">
    <meta property="og:image" content="img/og-banner.jpg">
    <meta property="og:url" content="https://your-site.ru">

    <title>Спортивное питание</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
    <link rel="shortcut icon" type="image/x-icon" href="favicon/favicon.ico">
    <link rel="icon" type="image/png" sizes="16x16" href="favicon/favicon-16x16.png">
    <link rel="icon" type="image/png" sizes="32x32" href="favicon/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="192x192" href="favicon/android-chrome-192x192.png">
    <link rel="icon" type="image/png" sizes="512x512" href="favicon/android-chrome-512x512.png">
    <link rel="apple-touch-icon" sizes="180x180" href="favicon/apple-touch-icon.png">
    <link rel="manifest" href="favicon/site.webmanifest">
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
                <a href="cart.php"><i class="fas fa-shopping-cart"></i></a>
                <?php if ($cart_count > 0): ?>
                    <span style="position:absolute;top:-8px;right:-8px;background:var(--primary-color);color:#fff;font-size:0.7rem;padding:2px 6px;border-radius:50%;min-width:18px;text-align:center;"><?= $cart_count ?></span>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <main>
        <img src="img/banner.jpeg" alt="Главный баннер: спортивное питание">
        <section class="cards" id="tariffs">
            <h2>Наши тарифы</h2>
            <div class="card-container">
                <article class="card">
                    <div class="card-header">
                        <h3>Базовый</h3>
                        <p>Идеален для новичков</p>
                    </div>
                    <div class="card-body">
                        <span class="card-pricing">1000 ₽/мес.</span>
                        <ul class="card-features">
                            <li>Доступ ко всем продуктам</li>
                            <li>Бесплатная доставка</li>
                            <li>Поддержка 24/7</li>
                            <li>Специальные акции</li>
                            <li>Программа лояльности</li>
                        </ul>
                        <button type="button" class="btn-buy">Купить</button>
                    </div>
                </article>

                <article class="card">
                    <div class="card-header">
                        <h3>Премиум</h3>
                        <p>Для опытных спортсменов</p>
                    </div>
                    <div class="card-body">
                        <span class="card-pricing">2000 ₽/мес.</span>
                        <ul class="card-features">
                            <li>Все продукты премиум-класса</li>
                            <li>Быстрая доставка</li>
                            <li>Индивидуальная поддержка</li>
                            <li>Эксклюзивные предложения</li>
                            <li>Максимальная скидка</li>
                        </ul>
                        <button type="button" class="btn-buy">Купить</button>
                    </div>
                </article>

                <article class="card">
                    <div class="card-header">
                        <h3>VIP</h3>
                        <p>Только лучшие условия</p>
                    </div>
                    <div class="card-body">
                        <span class="card-pricing">3000 ₽/мес.</span>
                        <ul class="card-features">
                            <li>Персонализированные рекомендации</li>
                            <li>Безлимитная доставка</li>
                            <li>Особое внимание консультантов</li>
                            <li>Постоянные бонусы</li>
                            <li>Специальные цены</li>
                        </ul>
                        <button type="button" class="btn-buy">Купить</button>
                    </div>
                </article>
            </div>
        </section>

        <section id="collection">
            <h2>Коллекция товаров</h2>
            <div class="collection-grid">
                <article class="grid-item item-standard">
                    <img class="item-img" src="img/product1.png" alt="Мульти протеин ваниль" loading="lazy">
                    <h3 class="item-title">Мульти протеин 900 г.</h3>
                    <p class="item-description">Со вкусом ванили</p>
                    <span class="item-price">4 200 ₽/шт.</span>
                    <button type="button" class="item-order-btn">Заказать</button>
                </article>
                <article class="grid-item item-standard">
                    <img class="item-img" src="img/product2.png" alt="Мульти протеин клубника" loading="lazy">
                    <h3 class="item-title">Мульти протеин 900 г.</h3>
                    <p class="item-description">Со вкусом клубники</p>
                    <span class="item-price">4 200 ₽/шт.</span>
                    <button type="button" class="item-order-btn">Заказать</button>
                </article>
                <article class="grid-item item-standard">
                    <img class="item-img" src="img/product3.png" alt="Мульти протеин шоколад" loading="lazy">
                    <h3 class="item-title">Мульти протеин 900 г.</h3>
                    <p class="item-description">Со вкусом шоколада</p>
                    <span class="item-price">4 200 ₽/шт.</span>
                    <button type="button" class="item-order-btn">Заказать</button>
                </article>

                <article class="grid-item item-wide">
                    <img class="item-img" src="img/product4.jpeg" alt="Optimum Nutrition набор" loading="lazy">
                    <h3 class="item-title">Optimum Nutrition</h3>
                    <p class="item-description">Комплект для повышения силы и выносливости: 100% сывороточный протеин,
                        BCAA 1000, креатин и AMIN.O. ENERGY</p>
                    <span class="item-price">14 240 ₽/шт.</span>
                    <button type="button" class="item-order-btn">Заказать</button>
                </article>
                <article class="grid-item item-standard">
                    <img class="item-img" src="img/product5.png" alt="Мульти протеин капучино" loading="lazy">
                    <h3 class="item-title">Мульти протеин 900 г.</h3>
                    <p class="item-description">Со вкусом капучино</p>
                    <span class="item-price">4 200 ₽/шт.</span>
                    <button type="button" class="item-order-btn">Заказать</button>
                </article>

                <article class="grid-item item-full">
                    <img class="item-img" src="img/product6.png" alt="FIT-Rx комплект" loading="lazy">
                    <h3 class="item-title">FIT-Rx</h3>
                    <p class="item-description">Продукция FIT-Rx производится на высокотехнологичном оборудовании при
                        соблюдении международных стандартов производства, что обеспечивает отличное качество,
                        эффективность и "чистоту" продуктов. Это касается как порошковых, капсульных, так и жидких форм
                        продуктов, которые являются отличительной особенностью бренда.</p>
                    <span class="item-price">30 000 ₽/шт.</span>
                    <button type="button" class="item-order-btn">Заказать</button>
                </article>
            </div>
        </section>

        <section class="advantages-section" id="advantages">
            <h2>Преимущества нашего магазина</h2>
            <div class="advantage-cards">
                <article class="advantage-card">
                    <div class="icon"><i class="fas fa-truck" aria-hidden="true"></i></div>
                    <h3>Быстрая доставка</h3>
                    <p>Получайте заказы вовремя!</p>
                </article>
                <article class="advantage-card">
                    <div class="icon"><i class="fas fa-dollar-sign" aria-hidden="true"></i></div>
                    <h3>Выгодные цены</h3>
                    <p>Экономьте на каждом заказе!</p>
                </article>
                <article class="advantage-card">
                    <div class="icon"><i class="fas fa-headphones" aria-hidden="true"></i></div>
                    <h3>Поддержка 24/7</h3>
                    <p>Всегда готовы помочь!</p>
                </article>
                <article class="advantage-card">
                    <div class="icon"><i class="fas fa-shield-alt" aria-hidden="true"></i></div>
                    <h3>Безопасность покупок</h3>
                    <p>Ваш заказ защищён!</p>
                </article>
                <article class="advantage-card">
                    <div class="icon"><i class="fas fa-star" aria-hidden="true"></i></div>
                    <h3>Высокое качество</h3>
                    <p>Проверенные товары!</p>
                </article>
                <article class="advantage-card">
                    <div class="icon"><i class="fas fa-thumbs-up" aria-hidden="true"></i></div>
                    <h3>Хорошее обслуживание</h3>
                    <p>Комфорт и забота о вас!</p>
                </article>
            </div>
        </section>
    </main>

    <?php
    require_once __DIR__ . '/connect_db.php';
    require_once __DIR__ . '/includes/shop_db.php';
    shop_ensure_schema($link);
    $site = shop_site_settings($link);
    $link->close();
    ?>
    <footer id="contacts">
        <div class="footer-left">
            <a href="index.php" class="logo">SportNutrition</a>
            <p class="social-icons">
                <a href="https://vk.com" rel="noopener noreferrer" target="_blank"><i class="fab fa-vk" aria-label="VK"></i></a>
                <a href="https://instagram.com" rel="noopener noreferrer" target="_blank"><i class="fab fa-instagram" aria-label="Instagram"></i></a>
                <a href="<?= htmlspecialchars($site['telegram_url'], ENT_QUOTES, 'UTF-8') ?>" rel="noopener noreferrer" target="_blank"><i class="fab fa-telegram-plane" aria-label="Telegram"></i></a>
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