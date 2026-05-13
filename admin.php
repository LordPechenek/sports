<?php
require_once 'check_admin.php';
require_once 'connect_db.php';
require_once __DIR__ . '/includes/shop_db.php';

shop_ensure_schema($link);

require_once 'csrf.php';

$admin_id = $_SESSION['user_id'];
$msg = '';

$post_ok = ($_SERVER['REQUEST_METHOD'] !== 'POST');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $post_ok = csrf_validate($_POST['csrf_token'] ?? null);
    if (!$post_ok) {
        $msg = 'Сессия устарела или запрос отклонён. Обновите страницу и повторите.';
    }
}

/** Сохраняет загруженное изображение каталога или возвращает ошибку. */
function admin_process_catalog_upload(array $file): array
{
    if (empty($file['name'])) {
        return ['ok' => true, 'name' => null, 'error' => null];
    }
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'name' => null, 'error' => 'Ошибка загрузки файла'];
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowedExt = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (!in_array($ext, $allowedExt, true)) {
        return ['ok' => false, 'name' => null, 'error' => 'Допустимы только jpg, png, gif, webp'];
    }
    $info = @getimagesize($file['tmp_name']);
    if ($info === false) {
        return ['ok' => false, 'name' => null, 'error' => 'Файл не является изображением'];
    }
    switch ($info[2]) {
        case IMAGETYPE_JPEG:
            $newExt = 'jpg';
            break;
        case IMAGETYPE_PNG:
            $newExt = 'png';
            break;
        case IMAGETYPE_GIF:
            $newExt = 'gif';
            break;
        case IMAGETYPE_WEBP:
            $newExt = 'webp';
            break;
        default:
            return ['ok' => false, 'name' => null, 'error' => 'Неподдерживаемый тип изображения'];
    }
    $new_name = uniqid('img_', true) . '.' . $newExt;
    $dir = 'uploads/catalog/';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    if (!move_uploaded_file($file['tmp_name'], $dir . $new_name)) {
        return ['ok' => false, 'name' => null, 'error' => 'Не удалось сохранить файл'];
    }
    return ['ok' => true, 'name' => $new_name, 'error' => null];
}

// --- ЛОГИКА ОБРАБОТКИ ДАННЫХ (CRUD) ---

// 1. УДАЛЕНИЕ ТОВАРА
if ($post_ok && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $pid = (int) $_POST['delete_id'];
    $stmt = $link->prepare("SELECT image FROM catalog WHERE product_id = ?");
    $stmt->bind_param("i", $pid);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        if (!empty($row['image']) && file_exists("uploads/catalog/" . $row['image'])) {
            unlink("uploads/catalog/" . $row['image']);
        }
    }
    $stmt = $link->prepare("DELETE FROM catalog WHERE product_id = ?");
    $stmt->bind_param("i", $pid);
    $stmt->execute();
    $stmt->close();
    $msg = 'Товар удалён';
}

// 2. ДОБАВЛЕНИЕ ИЛИ РЕДАКТИРОВАНИЕ ТОВАРА
if ($post_ok && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_product'])) {
    $title = trim($_POST['title']);
    $price = (float) str_replace(',', '.', $_POST['price']);
    $stock = (int) $_POST['stock'];
    $desc = trim($_POST['desc']);
    $edit_id = isset($_POST['edit_id']) ? (int) $_POST['edit_id'] : 0;
    $category_id = ($_POST['category_id'] ?? '') === '' ? null : (int) $_POST['category_id'];
    if ($category_id !== null && $category_id <= 0) {
        $category_id = null;
    }

    $img = $_POST['current_image'] ?? '';
    $upload_ok = true;
    if (!empty($_FILES['img']['name'])) {
        $up = admin_process_catalog_upload($_FILES['img']);
        if (!$up['ok']) {
            $msg = $up['error'] ?? 'Ошибка загрузки изображения';
            $upload_ok = false;
        } elseif (!empty($up['name'])) {
            if ($edit_id && !empty($img) && file_exists('uploads/catalog/' . $img)) {
                unlink('uploads/catalog/' . $img);
            }
            $img = $up['name'];
        }
    }

    if ($upload_ok) {
        if ($edit_id > 0) {
            if ($category_id === null) {
                $stmt = $link->prepare("UPDATE catalog SET title=?, price=?, stock_qty=?, description=?, image=?, category_id=NULL WHERE product_id=?");
                $stmt->bind_param("sdissi", $title, $price, $stock, $desc, $img, $edit_id);
            } else {
                $stmt = $link->prepare("UPDATE catalog SET title=?, price=?, stock_qty=?, description=?, image=?, category_id=? WHERE product_id=?");
                $stmt->bind_param("sdissii", $title, $price, $stock, $desc, $img, $category_id, $edit_id);
            }
            $stmt->execute();
            $stmt->close();
            $msg = 'Товар обновлён';
        } else {
            if ($category_id === null) {
                $stmt = $link->prepare("INSERT INTO catalog (title, price, stock_qty, description, image) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("sdiss", $title, $price, $stock, $desc, $img);
            } else {
                $stmt = $link->prepare("INSERT INTO catalog (title, price, stock_qty, description, image, category_id) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sdissi", $title, $price, $stock, $desc, $img, $category_id);
            }
            $stmt->execute();
            $stmt->close();
            $msg = 'Товар добавлен';
        }
    }
}

// 3. ОБНОВЛЕНИЕ СТАТУСА ЗАКАЗА
if ($post_ok && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_order_status'])) {
    $order_id = (int) $_POST['order_id'];
    $new_status = $_POST['new_status'];

    // Проверка на допустимые статусы
    $allowed_statuses = ['new', 'processing', 'shipped', 'delivered', 'cancelled'];
    if (in_array($new_status, $allowed_statuses)) {
        try {
            $stmt = $link->prepare("UPDATE orders SET status=? WHERE order_id=?");
            $stmt->bind_param("si", $new_status, $order_id);
            if ($stmt->execute()) {
                $msg = "Статус заказа #$order_id изменён";
            } else {
                $msg = "Ошибка при обновлении статуса";
            }
            $stmt->close();
        } catch (mysqli_sql_exception) {
            $msg = 'Таблица заказов недоступна. Импортируйте дамп базы через phpMyAdmin (например sql/db.sql) или создайте таблицу orders в текущей базе.';
        }
    }
}

// 4. Управление пользователями (роль / статус)
if ($post_ok && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_action'])) {
    $target_id = (int) ($_POST['user_id'] ?? 0);
    if ($target_id > 0 && $target_id !== (int) $admin_id) {
        if ($_POST['user_action'] === 'role' && isset($_POST['new_role'])) {
            $nr = (string) $_POST['new_role'];
            if (in_array($nr, ['user', 'moderator', 'admin'], true)) {
                $stmt = $link->prepare("UPDATE user SET role=? WHERE user_id=?");
                $stmt->bind_param("si", $nr, $target_id);
                $stmt->execute();
                $stmt->close();
                $msg = 'Роль обновлена';
            }
        } elseif ($_POST['user_action'] === 'status') {
            $stmt = $link->prepare("SELECT status FROM user WHERE user_id=?");
            $stmt->bind_param("i", $target_id);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($row) {
                $newStatus = (($row['status'] ?? '') === 'active') ? 'blocked' : 'active';
                $stmt = $link->prepare("UPDATE user SET status=? WHERE user_id=?");
                $stmt->bind_param("si", $newStatus, $target_id);
                $stmt->execute();
                $stmt->close();
                $msg = 'Статус пользователя обновлён';
            }
        }
    }
}

// 5. Комментарий администратора к заказу
if ($post_ok && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_order_comment'])) {
    $oid = (int) ($_POST['order_id'] ?? 0);
    $comment = trim((string) ($_POST['admin_comment'] ?? ''));
    if ($oid > 0) {
        try {
            $stmt = $link->prepare('UPDATE orders SET admin_comment=? WHERE order_id=?');
            $stmt->bind_param('si', $comment, $oid);
            $stmt->execute();
            $stmt->close();
            $msg = 'Комментарий к заказу сохранён';
        } catch (mysqli_sql_exception) {
            $msg = 'Не удалось сохранить комментарий к заказу';
        }
    }
}

// 6. Модерация отзывов
if ($post_ok && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['review_action'], $_POST['review_id'])) {
    $rid = (int) $_POST['review_id'];
    $act = (string) $_POST['review_action'];
    if ($rid > 0 && in_array($act, ['approve', 'delete'], true)) {
        try {
            if ($act === 'approve') {
                $stmt = $link->prepare("UPDATE shop_review SET status='approved' WHERE review_id=?");
                $stmt->bind_param('i', $rid);
                $stmt->execute();
                $stmt->close();
                $msg = 'Отзыв одобрен';
            } else {
                $stmt = $link->prepare('DELETE FROM shop_review WHERE review_id=?');
                $stmt->bind_param('i', $rid);
                $stmt->execute();
                $stmt->close();
                $msg = 'Отзыв удалён';
            }
        } catch (mysqli_sql_exception) {
            $msg = 'Ошибка при обработке отзыва';
        }
    }
}

// 7. Категории
if ($post_ok && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_category'])) {
    $cname = trim((string) ($_POST['cat_name'] ?? ''));
    $cslug = trim((string) ($_POST['cat_slug'] ?? ''));
    $cslug = preg_replace('/[^a-z0-9\-_]/', '', strtolower($cslug));
    $sort = (int) ($_POST['cat_sort'] ?? 0);
    $edit_cat = (int) ($_POST['edit_category_id'] ?? 0);
    if ($cname === '' || $cslug === '') {
        $msg = 'Укажите название и slug категории (латиница, цифры, дефис)';
    } else {
        try {
            if ($edit_cat > 0) {
                $stmt = $link->prepare('UPDATE shop_category SET name=?, slug=?, sort_order=? WHERE category_id=?');
                $stmt->bind_param('ssii', $cname, $cslug, $sort, $edit_cat);
            } else {
                $stmt = $link->prepare('INSERT INTO shop_category (name, slug, sort_order) VALUES (?, ?, ?)');
                $stmt->bind_param('ssi', $cname, $cslug, $sort);
            }
            $stmt->execute();
            $stmt->close();
            $msg = $edit_cat > 0 ? 'Категория обновлена' : 'Категория добавлена';
        } catch (mysqli_sql_exception) {
            $msg = 'Не удалось сохранить категорию (возможно, slug уже занят)';
        }
    }
}

if ($post_ok && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_category_id'])) {
    $cid = (int) $_POST['delete_category_id'];
    if ($cid > 0) {
        try {
            $chk = $link->prepare('SELECT COUNT(*) AS c FROM catalog WHERE category_id=?');
            $chk->bind_param('i', $cid);
            $chk->execute();
            $n = (int) ($chk->get_result()->fetch_assoc()['c'] ?? 0);
            $chk->close();
            if ($n > 0) {
                $msg = 'Нельзя удалить категорию: к ней привязаны товары';
            } else {
                $stmt = $link->prepare('DELETE FROM shop_category WHERE category_id=?');
                $stmt->bind_param('i', $cid);
                $stmt->execute();
                $stmt->close();
                $msg = 'Категория удалена';
            }
        } catch (mysqli_sql_exception) {
            $msg = 'Ошибка при удалении категории';
        }
    }
}

// 8. Настройки сайта (контакты)
if ($post_ok && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_site_settings'])) {
    $phone = trim((string) ($_POST['site_phone'] ?? ''));
    $email = trim((string) ($_POST['site_email'] ?? ''));
    $addr = trim((string) ($_POST['site_address'] ?? ''));
    $tg = trim((string) ($_POST['site_telegram'] ?? ''));
    if ($phone === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $msg = 'Укажите телефон и корректный email';
    } else {
        try {
            $stmt = $link->prepare('UPDATE site_settings SET phone=?, email=?, address_line=?, telegram_url=? WHERE id=1');
            $stmt->bind_param('ssss', $phone, $email, $addr, $tg);
            $stmt->execute();
            $stmt->close();
            $msg = 'Настройки сайта сохранены';
        } catch (mysqli_sql_exception) {
            $msg = 'Не удалось сохранить настройки';
        }
    }
}

// --- ЗАГРУЗКА ДАННЫХ ДЛЯ ОТОБРАЖЕНИЯ ---

// Данные товара для редактирования
$edit_product = null;
if (isset($_GET['edit_id'])) {
    $edit_get_id = (int) $_GET['edit_id'];
    $stmt = $link->prepare("SELECT * FROM catalog WHERE product_id = ?");
    $stmt->bind_param("i", $edit_get_id);
    $stmt->execute();
    $edit_product = $stmt->get_result()->fetch_assoc();
}

// Списки данных
$users = [];
$catalog_products = [];
$orders = [];

$res_users = $link->query("SELECT user_id, username, login, email, role, status FROM user ORDER BY user_id DESC");
if ($res_users) {
    $users = $res_users->fetch_all(MYSQLI_ASSOC);
    $res_users->close();
}

$res_cat = $link->query("SELECT product_id, title, price, image, stock_qty, category_id FROM catalog ORDER BY product_id DESC");
if ($res_cat) {
    $catalog_products = $res_cat->fetch_all(MYSQLI_ASSOC);
    $res_cat->close();
}

$categories_list = [];
if (shop_table_exists($link, 'shop_category')) {
    $rc = $link->query('SELECT category_id, name, slug, sort_order FROM shop_category ORDER BY sort_order, name');
    if ($rc) {
        $categories_list = $rc->fetch_all(MYSQLI_ASSOC);
        $rc->close();
    }
}

$edit_category = null;
if (isset($_GET['edit_cat_id']) && shop_table_exists($link, 'shop_category')) {
    $ecid = (int) $_GET['edit_cat_id'];
    if ($ecid > 0) {
        $st = $link->prepare('SELECT category_id, name, slug, sort_order FROM shop_category WHERE category_id=?');
        $st->bind_param('i', $ecid);
        $st->execute();
        $edit_category = $st->get_result()->fetch_assoc();
        $st->close();
    }
}

$reviews_admin = [];
if (shop_table_exists($link, 'shop_review')) {
    $rr = $link->query("SELECT r.review_id, r.user_id, r.product_id, r.rating, r.body, r.status, r.created_at, u.username FROM shop_review r JOIN user u ON u.user_id = r.user_id ORDER BY r.created_at DESC");
    if ($rr) {
        $reviews_admin = $rr->fetch_all(MYSQLI_ASSOC);
        $rr->close();
    }
}

$reviews_pending_count = count(array_filter($reviews_admin, static fn ($r) => ($r['status'] ?? '') === 'pending'));

$site_settings = shop_site_settings($link);

$low_stock = [];
if (shop_table_exists($link, 'catalog')) {
    $ls = $link->query('SELECT product_id, title, stock_qty FROM catalog WHERE stock_qty < 3 AND stock_qty >= 0 ORDER BY stock_qty ASC, title');
    if ($ls) {
        $low_stock = $ls->fetch_all(MYSQLI_ASSOC);
        $ls->close();
    }
}

// Загрузка заказов с именами пользователей и элементами заказа
$orders = [];
try {
    $res_orders = $link->query("SELECT o.order_id, o.created_at, o.total_amount, o.status, o.admin_comment, o.items_note, o.customer_request, u.username, u.user_id FROM orders o JOIN user u ON o.user_id = u.user_id ORDER BY o.created_at DESC");
    if ($res_orders) {
        $orders = $res_orders->fetch_all(MYSQLI_ASSOC);
        $res_orders->close();
        
        // Загружаем элементы для каждого заказа
        foreach ($orders as &$order) {
            $oid = (int) $order['order_id'];
            $items_res = $link->query("SELECT product_id, title, price, quantity, subtotal FROM order_items WHERE order_id = $oid ORDER BY item_id");
            if ($items_res) {
                $order['items'] = $items_res->fetch_all(MYSQLI_ASSOC);
                $items_res->close();
            } else {
                $order['items'] = [];
            }
        }
        unset($order);
    }
} catch (mysqli_sql_exception) {
    $orders = [];
}

$cat_by_id = [];
foreach ($categories_list as $c) {
    $cat_by_id[(int) $c['category_id']] = $c['name'];
}

$link->close();

// Функция для цвета бейджа статуса
function getStatusBadge($status)
{
    $classes = [
        'new' => 'badge-role', // синий
        'processing' => 'badge-warning', // желтый (нужно добавить стиль)
        'shipped' => 'badge-info', // голубой
        'delivered' => 'badge-success', // зеленый
        'cancelled' => 'badge-danger' // красный
    ];
    $labels = [
        'new' => 'Новый',
        'processing' => 'Обработан',
        'shipped' => 'Отправлен',
        'delivered' => 'Доставлен',
        'cancelled' => 'Отменен'
    ];

    // Стиль для предупреждения (добавим в CSS или используем инлайн для простоты)
    $style = 'background:#fff3cd; color:#856404;';
    if ($status == 'delivered')
        $style = 'background:#d4edda; color:#155724;';
    if ($status == 'cancelled')
        $style = 'background:#f8d7da; color:#721c24;';
    if ($status == 'shipped')
        $style = 'background:#d1ecf1; color:#0c5460;';
    if ($status == 'new')
        $style = 'background:#cce5ff; color:#004085;';

    return '<span style="padding:0.2rem 0.6rem;border-radius:12px;font-size:var(--font-xs);font-weight:500;' . $style . '">' . ($labels[$status] ?? $status) . '</span>';
}

?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Админ-панель — SportNutrition</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
    <link rel="stylesheet" href="css/styles_admin.css">
    <style>
        /* Доп стили для статусов заказов */
        .status-select {
            padding: 0.3rem;
            border-radius: 6px;
            border: 1px solid #ccc;
            font-size: var(--font-xs);
            cursor: pointer;
        }
        .low-stock-alert {
            background: #ffebee;
            border: 1px solid #ef9a9a;
            border-left: 4px solid #d32f2f;
            color: #b71c1c;
            padding: 1rem 1.25rem;
            border-radius: var(--border-radius);
            margin-bottom: 1.5rem;
        }
        .low-stock-alert h3 {
            margin: 0 0 0.5rem 0;
            font-size: var(--font-lg);
        }
        .low-stock-alert ul {
            margin: 0;
            padding-left: 1.2rem;
        }
    </style>
</head>

<body>
    <header class="admin-header">
        <a href="index.php" class="logo">SportNutrition</a>
        <nav class="admin-nav">
            <a href="index.php">На сайт</a>
            <span><?= htmlspecialchars($_SESSION['username']) ?></span>
            <a href="logout.php">Выйти</a>
        </nav>
    </header>
    <div class="admin-wrap">
        <aside class="admin-sidebar">
            <h3><i class="fas fa-shield-alt"></i> Админка</h3>
            <a href="?page=dashboard" class="<?= ($_GET['page'] ?? 'dashboard') === 'dashboard' ? 'active' : '' ?>"><i
                    class="fas fa-chart-line"></i> Дашборд</a>
            <a href="?page=users" class="<?= ($_GET['page'] ?? '') === 'users' ? 'active' : '' ?>"><i
                    class="fas fa-users"></i> Пользователи</a>
            <a href="?page=catalog" class="<?= ($_GET['page'] ?? '') === 'catalog' ? 'active' : '' ?>"><i
                    class="fas fa-store"></i> Каталог</a>
            <!-- ССЫЛКА НА ЗАКАЗЫ -->
            <a href="?page=orders" class="<?= ($_GET['page'] ?? '') === 'orders' ? 'active' : '' ?>">
                <i class="fas fa-shopping-cart"></i> Заказы
                <?php if (count($orders) > 0): ?><span class="badge-danger"
                        style="margin-left:auto"><?= count($orders) ?></span><?php endif; ?>
            </a>
            <a href="?page=reviews" class="<?= ($_GET['page'] ?? '') === 'reviews' ? 'active' : '' ?>">
                <i class="fas fa-star"></i> Отзывы
                <?php if ($reviews_pending_count > 0): ?><span class="badge-danger" style="margin-left:auto"><?= $reviews_pending_count ?></span><?php endif; ?>
            </a>
            <a href="?page=categories" class="<?= ($_GET['page'] ?? '') === 'categories' ? 'active' : '' ?>"><i
                    class="fas fa-folder"></i> Категории</a>
            <a href="?page=site" class="<?= ($_GET['page'] ?? '') === 'site' ? 'active' : '' ?>"><i
                    class="fas fa-cog"></i> Контакты сайта</a>
        </aside>

        <main class="admin-main">
            <?php if ($msg): ?>
                <div class="success-message"
                    style="<?= (strpos($msg, 'Ошибка') !== false || strpos($msg, 'Сессия') !== false || strpos($msg, 'Допустимы') !== false || strpos($msg, 'Неподдерживаемый') !== false || strpos($msg, 'Файл не') !== false || strpos($msg, 'Не удалось') !== false) ? 'background:#ffeaea;border-left-color:#d32f2f;color:#d32f2f' : '' ?>">
                    <?= htmlspecialchars($msg) ?>
                </div>
            <?php endif; ?>

            <?php if (($_GET['page'] ?? '') === 'catalog'): ?>
                <!-- ... КОД КАТАЛОГА (оставь свой старый код или скопируй из предыдущего ответа) ... -->
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;">
                    <h2>Управление каталогом</h2>
                    <?php if (!$edit_product): ?>
                        <button type="button" class="btn-add" onclick="document.getElementById('addForm').classList.toggle('active')"><i
                                class="fas fa-plus"></i> Добавить товар</button>
                    <?php endif; ?>
                </div>
                <?php if (!$edit_product): ?>
                    <div id="addForm" class="admin-form-card">
                        <h3>Новый товар</h3>
                        <form method="POST" enctype="multipart/form-data">
                            <?php csrf_field(); ?>
                            <input type="hidden" name="save_product" value="1">
                            <div class="form-grid">
                                <input type="text" name="title" class="admin-input" placeholder="Название" required>
                                <input type="number" step="0.01" name="price" class="admin-input" placeholder="Цена" required>
                                <input type="number" name="stock" class="admin-input" placeholder="Кол-во" value="10">
                                <div>
                                    <label style="display:block;font-size:var(--font-sm);margin-bottom:0.25rem;">Категория</label>
                                    <select name="category_id" class="admin-input">
                                        <option value="">— без категории —</option>
                                        <?php foreach ($categories_list as $c): ?>
                                            <option value="<?= (int) $c['category_id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <textarea name="desc" class="admin-input" rows="2" placeholder="Описание"
                                style="margin-bottom:1rem;"></textarea>
                            <input type="file" name="img" accept="image/*" style="margin-bottom:1rem;">
                            <div class="form-actions">
                                <button type="submit" class="btn-card approve">Сохранить</button>
                                <button type="button" class="btn-card reject"
                                    onclick="document.getElementById('addForm').classList.remove('active')">Отмена</button>
                            </div>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="admin-form-card active">
                        <h3>Редактирование: <?= htmlspecialchars($edit_product['title']) ?></h3>
                        <form method="POST" enctype="multipart/form-data">
                            <?php csrf_field(); ?>
                            <input type="hidden" name="save_product" value="1">
                            <input type="hidden" name="edit_id" value="<?= $edit_product['product_id'] ?>">
                            <input type="hidden" name="current_image"
                                value="<?= htmlspecialchars($edit_product['image'] ?? '') ?>">
                            <div class="form-grid">
                                <input type="text" name="title" class="admin-input"
                                    value="<?= htmlspecialchars($edit_product['title']) ?>" required>
                                <input type="number" step="0.01" name="price" class="admin-input"
                                    value="<?= $edit_product['price'] ?>" required>
                                <input type="number" name="stock" class="admin-input" value="<?= $edit_product['stock_qty'] ?>">
                                <div>
                                    <label style="display:block;font-size:var(--font-sm);margin-bottom:0.25rem;">Категория</label>
                                    <?php $curCat = (int) ($edit_product['category_id'] ?? 0); ?>
                                    <select name="category_id" class="admin-input">
                                        <option value="">— без категории —</option>
                                        <?php foreach ($categories_list as $c): ?>
                                            <option value="<?= (int) $c['category_id'] ?>" <?= $curCat === (int) $c['category_id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <textarea name="desc" class="admin-input" rows="2"
                                style="margin-bottom:1rem;"><?= htmlspecialchars($edit_product['description']) ?></textarea>
                            <input type="file" name="img" accept="image/*" style="margin-bottom:1rem;">
                            <div class="form-actions">
                                <button type="submit" class="btn-card approve">Обновить</button>
                                <a href="?page=catalog" class="btn-card reject btn-cancel">Отмена</a>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>

                <div style="overflow-x:auto; margin-top:2rem;">
                    <table class="table-admin">
                        <thead>
                            <tr>
                                <th>Фото</th>
                                <th>Название</th>
                                <th>Цена</th>
                                <th>Остаток</th>
                                <th>Категория</th>
                                <th style="width:120px; text-align:right;">Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($catalog_products as $p): ?>
                                <tr>
                                    <td><?php if ($p['image']): ?><img src="uploads/catalog/<?= htmlspecialchars($p['image']) ?>"
                                                class="product-img-thumb"><?php endif; ?></td>
                                    <td><strong><?= htmlspecialchars($p['title']) ?></strong></td>
                                    <td><?= number_format($p['price'], 0, '', ' ') ?> ₽</td>
                                    <td><?= $p['stock_qty'] ?> шт.</td>
                                    <td><?php
                                        $cid = (int) ($p['category_id'] ?? 0);
                                        echo $cid && isset($cat_by_id[$cid]) ? htmlspecialchars($cat_by_id[$cid]) : '—';
                                    ?></td>
                                    <td style="text-align:right;">
                                        <a href="?page=catalog&edit_id=<?= $p['product_id'] ?>" class="action-btn edit"
                                            style="display:inline-flex; text-decoration:none;"><i class="fas fa-pen"></i></a>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Удалить?')">
                                            <?php csrf_field(); ?>
                                            <input type="hidden" name="delete_id" value="<?= $p['product_id'] ?>">
                                            <button type="submit" class="action-btn delete"><i
                                                    class="fas fa-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            <?php elseif (($_GET['page'] ?? '') === 'orders'): ?>
                <h2>Управление заказами</h2>
                <p style="color:#666;margin-bottom:1rem;font-size:var(--font-sm);">Статусы: <strong>Новый</strong> → <strong>Обработан</strong> → <strong>Отправлен</strong> → <strong>Доставлен</strong>. Комментарий виден только в админке.</p>
                <?php if (empty($orders)): ?>
                    <p style="color:#666; text-align:center; padding:2rem;">Заказов пока нет. Пользователь может оформить заявку из <a href="catalog.php">каталога</a>.</p>
                <?php else: ?>
                    <div style="overflow-x:auto;">
                        <table class="table-admin">
                            <thead>
                                <tr>
                                    <th>№</th>
                                    <th>Дата</th>
                                    <th>Клиент</th>
                                    <th>Состав / пожелание</th>
                                    <th>Сумма</th>
                                    <th>Статус</th>
                                    <th>Действия</th>
                                </tr>
                            </thead>
                            <?php foreach ($orders as $order): ?>
                                <tbody>
                                    <tr>
                                        <td><strong>#<?= (int) $order['order_id'] ?></strong></td>
                                        <td><?= date('d.m.Y H:i', strtotime($order['created_at'])) ?></td>
                                        <td>
                                            <a href="profile.php" style="color:var(--primary-color);"><?= htmlspecialchars($order['username']) ?></a>
                                            <div style="font-size:var(--font-xs);color:#888;">user_id: <?= (int) $order['user_id'] ?></div>
                                        </td>
                                        <td style="max-width:280px;font-size:var(--font-sm);">
                                            <?php if (!empty($order['items'])): ?>
                                                <div><strong>Товары:</strong></div>
                                                <ul style="margin:0.5rem 0 0 1rem; padding:0;">
                                                    <?php foreach ($order['items'] as $item): ?>
                                                        <li>
                                                            <?= htmlspecialchars($item['title']) ?> 
                                                            × <?= (int) $item['quantity'] ?> 
                                                            = <?= number_format((float) $item['subtotal'], 0, '', ' ') ?> ₽
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            <?php else: ?>
                                                <?php if (!empty($order['items_note'])): ?>
                                                    <div><strong>Заказ:</strong> <?= nl2br(htmlspecialchars((string) $order['items_note'], ENT_QUOTES, 'UTF-8')) ?></div>
                                                <?php else: ?>
                                                    <span class="text-muted">—</span>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                            <?php if (!empty($order['customer_request'])): ?>
                                                <div style="margin-top:0.5rem;"><strong>От клиента:</strong> <?= nl2br(htmlspecialchars((string) $order['customer_request'], ENT_QUOTES, 'UTF-8')) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= number_format((float) $order['total_amount'], 2, ',', ' ') ?> ₽</td>
                                        <td><?= getStatusBadge($order['status']) ?></td>
                                        <td>
                                            <form method="POST" style="display:flex; gap:5px;flex-wrap:wrap;">
                                                <?php csrf_field(); ?>
                                                <input type="hidden" name="order_id" value="<?= (int) $order['order_id'] ?>">
                                                <input type="hidden" name="update_order_status" value="1">
                                                <select name="new_status" class="status-select" onchange="this.form.submit()">
                                                    <option value="new" <?= $order['status'] === 'new' ? 'selected' : '' ?>>Новый</option>
                                                    <option value="processing" <?= $order['status'] === 'processing' ? 'selected' : '' ?>>Обработан</option>
                                                    <option value="shipped" <?= $order['status'] === 'shipped' ? 'selected' : '' ?>>Отправлен</option>
                                                    <option value="delivered" <?= $order['status'] === 'delivered' ? 'selected' : '' ?>>Доставлен</option>
                                                    <option value="cancelled" <?= $order['status'] === 'cancelled' ? 'selected' : '' ?>>Отменен</option>
                                                </select>
                                            </form>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td colspan="7" style="background:#fafafa;border-bottom:2px solid #eee;">
                                            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1rem; padding:0.5rem 0;">
                                                <div>
                                                    <form method="POST" style="display:flex;flex-direction:column;gap:0.5rem;">
                                                        <?php csrf_field(); ?>
                                                        <input type="hidden" name="save_order_comment" value="1">
                                                        <input type="hidden" name="order_id" value="<?= (int) $order['order_id'] ?>">
                                                        <label style="font-size:var(--font-sm);font-weight:500;">Комментарий администратора</label>
                                                        <textarea name="admin_comment" class="admin-input" rows="2" placeholder="Например: клиент просил позвонить после 18:00"><?= htmlspecialchars((string) ($order['admin_comment'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                                                        <button type="submit" class="btn-card approve" style="align-self:flex-start;max-width:200px;">Сохранить комментарий</button>
                                                    </form>
                                                </div>
                                                <div>
                                                    <label style="font-size:var(--font-sm);font-weight:500;">Детали заказа</label>
                                                    <div style="font-size:var(--font-xs); color:#666; margin-top:0.5rem;">
                                                        <div>Заказ создан: <?= date('d.m.Y H:i', strtotime($order['created_at'])) ?></div>
                                                        <div>Клиент: <?= htmlspecialchars($order['username']) ?></div>
                                                        <div>Сумма: <?= number_format((float) $order['total_amount'], 2, ',', ' ') ?> ₽</div>
                                                        <div>Статус: <?= getStatusBadge($order['status']) ?></div>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            <?php endforeach; ?>
                        </table>
                    </div>
                <?php endif; ?>

            <?php elseif (($_GET['page'] ?? '') === 'reviews'): ?>
                <h2>Модерация отзывов</h2>
                <?php if (empty($reviews_admin)): ?>
                    <p style="color:#666;text-align:center;padding:2rem;">Отзывов пока нет.</p>
                <?php else: ?>
                    <div style="overflow-x:auto;">
                        <table class="table-admin">
                            <thead>
                                <tr>
                                    <th>Дата</th>
                                    <th>Пользователь</th>
                                    <th>Оценка</th>
                                    <th>Текст</th>
                                    <th>Статус</th>
                                    <th>Действия</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reviews_admin as $rw): ?>
                                    <tr>
                                        <td><?= date('d.m.Y H:i', strtotime($rw['created_at'])) ?></td>
                                        <td><?= htmlspecialchars($rw['username']) ?></td>
                                        <td><?= (int) $rw['rating'] ?> / 5</td>
                                        <td style="max-width:280px;"><?= nl2br(htmlspecialchars($rw['body'], ENT_QUOTES, 'UTF-8')) ?></td>
                                        <td><?php
                                            $st = $rw['status'] ?? 'pending';
                                            $cls = $st === 'approved' ? 'badge-success' : ($st === 'pending' ? 'badge-role' : 'badge-danger');
                                            ?><span class="<?= $cls ?>"><?= htmlspecialchars($st) ?></span></td>
                                        <td>
                                            <?php if (($rw['status'] ?? '') === 'pending'): ?>
                                                <form method="POST" style="display:inline;">
                                                    <?php csrf_field(); ?>
                                                    <input type="hidden" name="review_id" value="<?= (int) $rw['review_id'] ?>">
                                                    <input type="hidden" name="review_action" value="approve">
                                                    <button type="submit" class="btn-success" style="margin-right:0.25rem;" title="Одобрить">✅</button>
                                                </form>
                                            <?php endif; ?>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Удалить отзыв?');">
                                                <?php csrf_field(); ?>
                                                <input type="hidden" name="review_id" value="<?= (int) $rw['review_id'] ?>">
                                                <input type="hidden" name="review_action" value="delete">
                                                <button type="submit" class="btn-sm btn-danger" title="Удалить">❌</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

            <?php elseif (($_GET['page'] ?? '') === 'categories'): ?>
                <h2>Категории каталога</h2>
                <p style="color:#666;margin-bottom:1rem;font-size:var(--font-sm);">Slug используется в фильтре на сайте (латиница, например <code>isotonic</code>).</p>
                <div class="admin-form-card active" style="margin-bottom:2rem;">
                    <h3><?= $edit_category ? 'Редактировать категорию' : 'Новая категория' ?></h3>
                    <form method="POST">
                        <?php csrf_field(); ?>
                        <input type="hidden" name="save_category" value="1">
                        <?php if ($edit_category): ?>
                            <input type="hidden" name="edit_category_id" value="<?= (int) $edit_category['category_id'] ?>">
                        <?php endif; ?>
                        <div class="form-grid">
                            <div><label>Название</label><input class="admin-input" name="cat_name" required value="<?= $edit_category ? htmlspecialchars($edit_category['name']) : '' ?>"></div>
                            <div><label>Slug</label><input class="admin-input" name="cat_slug" required pattern="[a-z0-9\-_]+" value="<?= $edit_category ? htmlspecialchars($edit_category['slug']) : '' ?>" placeholder="isotonic"></div>
                            <div><label>Порядок</label><input class="admin-input" type="number" name="cat_sort" value="<?= $edit_category ? (int) $edit_category['sort_order'] : '0' ?>"></div>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn-card approve"><?= $edit_category ? 'Сохранить' : 'Добавить' ?></button>
                            <?php if ($edit_category): ?>
                                <a href="?page=categories" class="btn-card reject btn-cancel">Отмена</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
                <div style="overflow-x:auto;">
                    <table class="table-admin">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Название</th>
                                <th>Slug</th>
                                <th>Порядок</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categories_list as $c): ?>
                                <tr>
                                    <td><?= (int) $c['category_id'] ?></td>
                                    <td><?= htmlspecialchars($c['name']) ?></td>
                                    <td><code><?= htmlspecialchars($c['slug']) ?></code></td>
                                    <td><?= (int) $c['sort_order'] ?></td>
                                    <td style="text-align:right;">
                                        <a href="?page=categories&edit_cat_id=<?= (int) $c['category_id'] ?>" class="action-btn edit" style="display:inline-flex;text-decoration:none;"><i class="fas fa-pen"></i></a>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Удалить категорию?');">
                                            <?php csrf_field(); ?>
                                            <input type="hidden" name="delete_category_id" value="<?= (int) $c['category_id'] ?>">
                                            <button type="submit" class="action-btn delete"><i class="fas fa-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            <?php elseif (($_GET['page'] ?? '') === 'site'): ?>
                <h2>Настройки сайта (контакты)</h2>
                <p style="color:#666;margin-bottom:1rem;">Отображаются в футере на главной и в каталоге.</p>
                <div class="admin-form-card active" style="max-width:640px;">
                    <form method="POST">
                        <?php csrf_field(); ?>
                        <input type="hidden" name="save_site_settings" value="1">
                        <div class="form-group" style="margin-bottom:1rem;">
                            <label>Телефон</label>
                            <input class="admin-input" name="site_phone" required value="<?= htmlspecialchars($site_settings['phone']) ?>">
                        </div>
                        <div class="form-group" style="margin-bottom:1rem;">
                            <label>Email</label>
                            <input class="admin-input" type="email" name="site_email" required value="<?= htmlspecialchars($site_settings['email']) ?>">
                        </div>
                        <div class="form-group" style="margin-bottom:1rem;">
                            <label>Адрес</label>
                            <input class="admin-input" name="site_address" value="<?= htmlspecialchars($site_settings['address_line']) ?>">
                        </div>
                        <div class="form-group" style="margin-bottom:1rem;">
                            <label>Ссылка на Telegram</label>
                            <input class="admin-input" name="site_telegram" value="<?= htmlspecialchars($site_settings['telegram_url']) ?>" placeholder="https://t.me/username">
                        </div>
                        <button type="submit" class="btn-card approve">Сохранить</button>
                    </form>
                </div>

            <?php elseif (($_GET['page'] ?? '') === 'users'): ?>
                <h2>Управление пользователями</h2>
                <div style="overflow-x:auto">
                    <table class="table-admin">
                        <tr>
                            <th>ID</th>
                            <th>Логин</th>
                            <th>Email</th>
                            <th>Роль</th>
                            <th>Статус</th>
                            <th>Действия</th>
                        </tr>
                        <?php foreach ($users as $u): ?>
                            <tr>
                                <td><?= $u['user_id'] ?></td>
                                <td><?= htmlspecialchars($u['login']) ?></td>
                                <td><?= htmlspecialchars($u['email']) ?></td>
                                <td>
                                    <form method="POST" class="inline-form" <?= $u['user_id'] == $admin_id ? 'style="opacity:0.5;pointer-events:none"' : '' ?>>
                                        <?php csrf_field(); ?>
                                        <input type="hidden" name="user_id" value="<?= $u['user_id'] ?>"><input type="hidden"
                                            name="user_action" value="role">
                                        <select name="new_role" onchange="this.form.submit()">
                                            <option value="user" <?= $u['role'] == 'user' ? 'selected' : '' ?>>User</option>
                                            <option value="moderator" <?= $u['role'] == 'moderator' ? 'selected' : '' ?>>Moderator
                                            </option>
                                            <option value="admin" <?= $u['role'] == 'admin' ? 'selected' : '' ?>>Admin</option>
                                        </select>
                                    </form>
                                </td>
                                <td><span
                                        class="badge-<?= $u['status'] == 'active' ? 'success' : 'danger' ?>"><?= $u['status'] ?></span>
                                </td>
                                <td><?php if ($u['user_id'] != $admin_id): ?>
                                        <form method="POST" class="inline-form"><?php csrf_field(); ?><input type="hidden" name="user_id"
                                                value="<?= $u['user_id'] ?>"><input type="hidden" name="user_action" value="status">
                                            <button type="submit"
                                                class="btn-sm <?= $u['status'] == 'active' ? 'btn-danger' : 'btn-success' ?>"><?= $u['status'] == 'active' ? 'Блок' : 'Разблок' ?></button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr><?php endforeach; ?>
                    </table>
                </div>

            <?php else: ?>
                <h2>Обзор системы</h2>
                <?php if (!empty($low_stock)): ?>
                    <div class="low-stock-alert" role="alert">
                        <h3><i class="fas fa-exclamation-triangle"></i> Низкий склад (меньше 3 шт.)</h3>
                        <ul>
                            <?php foreach ($low_stock as $ls): ?>
                                <li><strong><?= htmlspecialchars($ls['title']) ?></strong> — осталось <?= (int) $ls['stock_qty'] ?> шт.</li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                <div class="stats-grid">
                    <div class="stat-card"><i class="fas fa-users"></i>
                        <div class="num"><?= count($users) ?></div><span>Пользователей</span>
                    </div>
                    <div class="stat-card"><i class="fas fa-box"></i>
                        <div class="num"><?= count($catalog_products) ?></div><span>Товаров в каталоге</span>
                    </div>
                    <div class="stat-card"><i class="fas fa-shopping-cart"></i>
                        <div class="num"><?= count($orders) ?></div><span>Всего заказов</span>
                    </div>
                    <div class="stat-card"><i class="fas fa-star"></i>
                        <div class="num"><?= count(array_filter($reviews_admin, static fn ($r) => ($r['status'] ?? '') === 'approved')) ?></div><span>Одобренных отзывов</span>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>
</body>

</html>