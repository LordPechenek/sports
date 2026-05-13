<?php
session_start();
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/connect_db.php';
require_once __DIR__ . '/includes/shop_db.php';

if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header('Location: login.php');
    exit;
}

shop_ensure_schema($link);

$user_id = (int) $_SESSION['user_id'];
$product_id = isset($_GET['product_id']) ? (int) $_GET['product_id'] : 0;
$errors = [];
$product = null;

if ($product_id > 0) {
    $st = $link->prepare('SELECT product_id, title, price, stock_qty FROM catalog WHERE product_id = ?');
    $st->bind_param('i', $product_id);
    $st->execute();
    $product = $st->get_result()->fetch_assoc();
    $st->close();
}

if (!$product) {
    $link->close();
    header('Location: catalog.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Сессия устарела. Обновите страницу.';
    } else {
        $qty = max(1, (int) ($_POST['qty'] ?? 1));
        if ($qty > (int) $product['stock_qty']) {
            $errors[] = 'Недостаточно товара на складе.';
        } else {
            $note = trim((string) ($_POST['customer_request'] ?? ''));
            $items_note = $product['title'] . ' × ' . $qty;
            $total = (float) $product['price'] * $qty;
            try {
                $ins = $link->prepare('INSERT INTO orders (user_id, total_amount, status, items_note, customer_request) VALUES (?, ?, ?, ?, ?)');
                $status = 'new';
                $ins->bind_param('idsss', $user_id, $total, $status, $items_note, $note);
                $ins->execute();
                $ins->close();
                $link->close();
                header('Location: settings.php?tab=orders&ordered=1');
                exit;
            } catch (mysqli_sql_exception) {
                $errors[] = 'Не удалось создать заказ. Проверьте таблицу orders в базе.';
            }
        }
    }
}

$link->close();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Заявка на заказ — SportNutrition</title>
    <link rel="stylesheet" href="css/styles_catalog.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
    <style>
        .order-card { max-width: 480px; margin: 2rem auto; background: #fff; padding: 1.5rem; border-radius: 10px; box-shadow: 0 4px 14px rgba(0,0,0,.08); }
        .order-card h1 { font-size: 1.25rem; margin-bottom: 1rem; }
        .err { background: #ffebee; color: #b71c1c; padding: 0.75rem; border-radius: 8px; margin-bottom: 1rem; }
    </style>
</head>
<body>
    <main class="catalog-section">
        <div class="order-card">
            <h1>Заявка на товар</h1>
            <p><strong><?= htmlspecialchars($product['title']) ?></strong> — <?= number_format((float) $product['price'], 0, '', ' ') ?> ₽ / шт.</p>
            <?php if ($errors): ?>
                <div class="err"><?= htmlspecialchars(implode(' ', $errors)) ?></div>
            <?php endif; ?>
            <form method="post">
                <?php csrf_field(); ?>
                <label>Количество</label>
                <input type="number" name="qty" min="1" max="<?= (int) $product['stock_qty'] ?>" value="1" class="search-input" style="width:100%;margin-bottom:1rem;">
                <label>Комментарий к заказу (необязательно)</label>
                <textarea name="customer_request" class="search-input" style="width:100%;min-height:80px;margin-bottom:1rem;" placeholder="Например: позвоните после 18:00"></textarea>
                <button type="submit" class="item-order-btn" style="width:100%;border:none;cursor:pointer;">Отправить заявку</button>
                <p style="margin-top:1rem;"><a href="catalog.php">← В каталог</a></p>
            </form>
        </div>
    </main>
</body>
</html>
