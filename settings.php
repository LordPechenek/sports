<?php
session_start();
require_once __DIR__ . '/csrf.php';

require_once(__DIR__ . '/connect_db.php');
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header("Location: login.php");
    exit;
}
$user_id = $_SESSION['user_id'];
$active_tab = $_GET['tab'] ?? 'personal';

// Загрузка данных пользователя
$stmt = $link->prepare("SELECT username, login, email, phone, birthdate, avatar FROM user WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Обработка личных данных
$success = $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_personal') {
    if (!csrf_validate($_POST['csrf_token'] ?? null)) {
        $error = 'Сессия устарела. Обновите страницу.';
    } else {
    $username = trim($_POST['username']);
    $login = trim($_POST['login']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone'] ?? '');
    $birthdate = (trim($_POST['birthdate'] ?? '') === '') ? null : $_POST['birthdate'];

    $errors = [];
    if (empty($username))
        $errors[] = 'Имя не может быть пустым';
    if (empty($login))
        $errors[] = 'Логин не может быть пустым';
    if (strlen($login) < 3)
        $errors[] = 'Логин минимум 3 символа';
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL))
        $errors[] = 'Некорректный email';

    if (empty($errors)) {
        $stmt_check = $link->prepare("SELECT user_id FROM user WHERE login = ? AND user_id != ?");
        $stmt_check->bind_param("si", $login, $user_id);
        $stmt_check->execute();
        if ($stmt_check->get_result()->num_rows > 0)
            $errors[] = 'Логин уже занят';
        $stmt_check->close();
    }

    if (empty($errors)) {
        $avatar = $user['avatar'];
        if (!empty($_FILES['avatar']['name']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

            if (in_array($ext, $allowed)) {
                $avatar_name = "avatar_" . $user_id . "_" . time() . "." . $ext;
                $avatar_name = preg_replace('/[^a-zA-Z0-9_\.]/', '_', $avatar_name);

                $upload_dir = 'uploads/avatars/';
                if (!is_dir($upload_dir))
                    mkdir($upload_dir, 0755, true);

                if (move_uploaded_file($_FILES['avatar']['tmp_name'], $upload_dir . $avatar_name)) {
                    // Удаляем старый аватар
                    if ($avatar && file_exists($upload_dir . $avatar)) {
                        unlink($upload_dir . $avatar);
                    }
                    $avatar = $avatar_name;
                } else {
                    $error = 'Ошибка: не удалось сохранить аватар';
                }
            } else {
                $error = 'Недопустимый формат. Разрешены: jpg, jpeg, png, gif, webp';
            }
        }
        if (empty($error)) {
        $stmt = $link->prepare("UPDATE user SET username=?, login=?, email=?, phone=?, birthdate=?, avatar=? WHERE user_id=?");
        $stmt->bind_param("ssssssi", $username, $login, $email, $phone, $birthdate, $avatar, $user_id);
        $stmt->execute();
        $stmt->close();
        $success = 'Данные обновлены';
        $_SESSION['username'] = $username;
        $_SESSION['login'] = $login;
        $stmt = $link->prepare("SELECT username, login, email, phone, birthdate, avatar FROM user WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $refreshed = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (is_array($refreshed)) {
            $user = $refreshed;
        }
        }
    } else
        $error = implode("\n", $errors);
    }
}
// Смена пароля
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'change_password') {
    if (!csrf_validate($_POST['csrf_token'] ?? null)) {
        $error = 'Сессия устарела. Обновите страницу.';
    } else {
    $old = $_POST['old_password'];
    $new = $_POST['new_password'];
    $stmt = $link->prepare("SELECT password FROM user WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $hash = $stmt->get_result()->fetch_assoc()['password'] ?? '';
    $stmt->close();
    if (password_verify($old, $hash)) {
        $new_hash = password_hash($new, PASSWORD_DEFAULT);
        $stmt = $link->prepare("UPDATE user SET password = ? WHERE user_id = ?");
        $stmt->bind_param("si", $new_hash, $user_id);
        $stmt->execute();
        $stmt->close();
        $success = 'Пароль изменён';
    } else
        $error = 'Неверный текущий пароль';
    }
}
// $link->close(); // Перемещено в конец файла или оставлено открытым для вкладок
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Настройки — SportNutrition</title>
    <link rel="stylesheet" href="css/styles_profile.css">
    <link rel="stylesheet" href="css/styles_settings.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
</head>

<body>
    <header class="profile-header">
        <a href="index.php" class="logo">SportNutrition</a>
        <nav class="user-nav">
            <a href="index.php" class="logout-link">На главную</a>
            <a href="profile.php" class="logout-link">Профиль</a>
            <span class="welcome-message">Привет, <?= htmlspecialchars($user['username']) ?></span>
            <a href="logout.php" class="logout-link">Выйти</a>
        </nav>
    </header>
    <main class="settings-wrapper">
        <aside class="settings-tabs">
            <a href="?tab=personal" class="<?= $active_tab === 'personal' ? 'active' : '' ?>"><i
                    class="fas fa-user"></i> Личные данные</a>
            <a href="?tab=addresses" class="<?= $active_tab === 'addresses' ? 'active' : '' ?>"><i
                    class="fas fa-map-marker-alt"></i> Адреса</a>
            <a href="?tab=payment" class="<?= $active_tab === 'payment' ? 'active' : '' ?>"><i
                    class="fas fa-credit-card"></i> Оплата</a>
            <a href="?tab=orders" class="<?= $active_tab === 'orders' ? 'active' : '' ?>"><i class="fas fa-box"></i>
                Заказы</a>
            <a href="?tab=returns" class="<?= $active_tab === 'returns' ? 'active' : '' ?>"><i class="fas fa-undo"></i>
                Возвраты</a>
            <a href="?tab=subscriptions" class="<?= $active_tab === 'subscriptions' ? 'active' : '' ?>"><i
                    class="fas fa-sync"></i> Подписки</a>
            <a href="?tab=discounts" class="<?= $active_tab === 'discounts' ? 'active' : '' ?>"><i
                    class="fas fa-tag"></i> Скидки</a>
            <a href="?tab=favorites" class="<?= $active_tab === 'favorites' ? 'active' : '' ?>"><i
                    class="fas fa-heart"></i> Избранное</a>
            <a href="?tab=notifications" class="<?= $active_tab === 'notifications' ? 'active' : '' ?>"><i
                    class="fas fa-bell"></i> Уведомления</a>
            <a href="?tab=privacy" class="<?= $active_tab === 'privacy' ? 'active' : '' ?>"><i
                    class="fas fa-shield-alt"></i> Приватность</a>
            <a href="?tab=reviews" class="<?= $active_tab === 'reviews' ? 'active' : '' ?>"><i class="fas fa-star"></i>
                Отзывы</a>
            <a href="?tab=data" class="<?= $active_tab === 'data' ? 'active' : '' ?>"><i class="fas fa-download"></i>
                Экспорт данных</a>
            <a href="?tab=security" class="<?= $active_tab === 'security' ? 'active' : '' ?>"><i
                    class="fas fa-lock"></i> Безопасность</a>
            <a href="?tab=loyalty" class="<?= $active_tab === 'loyalty' ? 'active' : '' ?>"><i class="fas fa-gift"></i>
                Бонусы</a>
            <a href="?tab=delivery" class="<?= $active_tab === 'delivery' ? 'active' : '' ?>"><i
                    class="fas fa-truck"></i> Доставка</a>
        </aside>
        <section class="settings-content">
            <?php if ($success): ?>
                <div class="success-message"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
            <?php if ($error): ?>
                <div class="error-message"><?= nl2br(htmlspecialchars($error, ENT_QUOTES, 'UTF-8')) ?></div><?php endif; ?>

            <!-- Личные данные -->
            <?php if ($active_tab === 'personal'): ?>
                <div class="settings-section active">
                    <h3>Личные данные</h3>
                    <form method="POST" enctype="multipart/form-data" class="profile-form">
                        <?php csrf_field(); ?>
                        <input type="hidden" name="action" value="update_personal">
                        <div class="form-group">
                            <label>Аватар</label>
                            <input type="file" name="avatar" accept="image/*">
                            <?php if ($user['avatar']): ?><img src="uploads/avatars/<?= $user['avatar'] ?>"
                                    class="avatar-preview" alt="Аватар"><?php endif; ?>
                        </div>
                        <div class="form-grid">
                            <div class="form-group"><label>Имя</label><input type="text" name="username"
                                    value="<?= htmlspecialchars($user['username']) ?>" required></div>
                            <div class="form-group"><label>Логин</label><input type="text" name="login"
                                    value="<?= htmlspecialchars($user['login']) ?>" required></div>
                            <div class="form-group"><label>Email</label><input type="email" name="email"
                                    value="<?= htmlspecialchars($user['email']) ?>" required></div>
                            <div class="form-group"><label>Телефон</label><input type="tel" name="phone"
                                    value="<?= htmlspecialchars($user['phone'] ?? '') ?>"></div>
                            <div class="form-group"><label>Дата рождения</label><input type="date" name="birthdate"
                                    value="<?= htmlspecialchars($user['birthdate'] ?? '') ?>"></div>
                        </div>
                        <button type="submit" class="btn btn-edit">Сохранить</button>
                    </form>
                </div>
            <?php endif; ?>

            <!-- Безопасность -->
            <?php if ($active_tab === 'security'): ?>
                <div class="settings-section active">
                    <h3>Безопасность</h3>
                    <form method="POST">
                        <?php csrf_field(); ?>
                        <input type="hidden" name="action" value="change_password">
                        <div class="form-group"><label>Текущий пароль</label><input type="password" name="old_password"
                                required></div>
                        <div class="form-group"><label>Новый пароль</label><input type="password" name="new_password"
                                required minlength="8"></div>
                        <button type="submit" class="btn btn-edit">Сменить пароль</button>
                    </form>
                    <hr style="margin:1.5rem 0;border:0;border-top:1px solid #eee">
                    <h4>Двухфакторная аутентификация</h4>
                    <div class="toggle-row"><span>2FA через SMS</span><input type="checkbox" disabled></div>
                    <div class="toggle-row"><span>2FA через приложение</span><input type="checkbox" disabled></div>
                    <hr style="margin:1.5rem 0;border:0;border-top:1px solid #eee">
                    <h4>Активные сессии</h4>
                    <table class="table-list">
                        <tr>
                            <th>Устройство</th>
                            <th>IP</th>
                            <th>Вход</th>
                            <th>Действие</th>
                        </tr>
                        <tr>
                            <td>Chrome / Windows</td>
                            <td>192.168.1.1</td>
                            <td>Сегодня, 14:30</td>
                            <td><button class="btn-sm btn-secondary">Завершить</button></td>
                        </tr>
                    </table>
                    <form method="POST"><button name="logout_all" class="btn btn-secondary btn-sm">Выйти везде</button>
                    </form>
                    <hr style="margin:1.5rem 0;border:0;border-top:1px solid #eee">
                    <h4 style="color:#d32f2f">Деактивация аккаунта</h4>
                    <p style="font-size:var(--font-sm);color:#666">После деактивации доступ к профилю будет закрыт. Данные
                        сохранятся 30 дней.</p>
                    <form method="POST" onsubmit="return confirm('Вы уверены?')"><button name="deactivate"
                            class="btn btn-danger">Деактивировать</button></form>
                </div>
            <?php endif; ?>

            <!-- Заказы -->
            <?php if ($active_tab === 'orders'): 
                // Загрузка заказов пользователя
                require_once __DIR__ . '/includes/shop_db.php';
                shop_ensure_schema($link);
                
                $user_orders = [];
                try {
                    $stmt = $link->prepare("SELECT order_id, created_at, total_amount, status, items_note, customer_request FROM orders WHERE user_id = ? ORDER BY created_at DESC");
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    while ($row = $result->fetch_assoc()) {
                        $oid = (int) $row['order_id'];
                        // Загружаем элементы заказа
                        $items_res = $link->query("SELECT product_id, title, price, quantity, subtotal FROM order_items WHERE order_id = $oid ORDER BY item_id");
                        $row['items'] = $items_res ? $items_res->fetch_all(MYSQLI_ASSOC) : [];
                        $user_orders[] = $row;
                    }
                    $stmt->close();
                } catch (mysqli_sql_exception) {
                    $user_orders = [];
                }
                // Не закрываем $link здесь, чтобы избежать ошибок при переключении вкладок
                
                $ordered_success = isset($_GET['ordered']);
            ?>
                <div class="settings-section active">
                    <h3>История заказов</h3>
                    
                    <?php if ($ordered_success): ?>
                        <div class="success-message">Заказ успешно оформлен! Менеджер свяжется с вами в ближайшее время.</div>
                    <?php endif; ?>
                    
                    <?php if (empty($user_orders)): ?>
                        <p style="color:#666;">У вас пока нет заказов.</p>
                        <a href="catalog.php" class="btn btn-edit" style="display:inline-block;margin-top:1rem;">Перейти в каталог</a>
                    <?php else: ?>
                        <table class="table-list">
                            <tr>
                                <th>№</th>
                                <th>Дата</th>
                                <th>Состав</th>
                                <th>Сумма</th>
                                <th>Статус</th>
                                <th>Действия</th>
                            </tr>
                            <?php foreach ($user_orders as $o): 
                                $status_labels = [
                                    'new' => ['Новый', 'badge-role'],
                                    'processing' => ['В обработке', 'badge-warning'],
                                    'shipped' => ['Отправлен', 'badge-info'],
                                    'delivered' => ['Доставлен', 'badge-success'],
                                    'cancelled' => ['Отменён', 'badge-danger']
                                ];
                                $status_info = $status_labels[$o['status']] ?? ['Неизвестно', ''];
                            ?>
                                <tr>
                                    <td>#<?= (int) $o['order_id'] ?></td>
                                    <td><?= date('d.m.Y H:i', strtotime($o['created_at'])) ?></td>
                                    <td style="max-width:280px;">
                                        <?php if (!empty($o['items'])): ?>
                                            <ul style="margin:0.5rem 0; padding-left:1rem;">
                                                <?php foreach ($o['items'] as $item): ?>
                                                    <li>
                                                        <?= htmlspecialchars($item['title']) ?> 
                                                        × <?= (int) $item['quantity'] ?> 
                                                        = <?= number_format((float) $item['subtotal'], 0, '', ' ') ?> ₽
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php else: ?>
                                            <?= htmlspecialchars($o['items_note'] ?? '-') ?>
                                        <?php endif; ?>
                                        <?php if (!empty($o['customer_request'])): ?>
                                            <div style="font-size:var(--font-xs); color:#666; margin-top:0.35rem;">
                                                <strong>Пожелание:</strong> <?= nl2br(htmlspecialchars($o['customer_request'], ENT_QUOTES, 'UTF-8')) ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= number_format((float) $o['total_amount'], 0, '', ' ') ?> ₽</td>
                                    <td><span class="badge <?= $status_info[1] ?>"><?= $status_info[0] ?></span></td>
                                    <td>
                                        <?php if ($o['status'] === 'delivered'): ?>
                                            <button class="btn-sm">Повторить</button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Уведомления -->
            <?php if ($active_tab === 'notifications'): ?>
                <div class="settings-section active">
                    <h3>Настройки уведомлений</h3>
                    <div class="toggle-row"><span>Заказы: статус и доставка</span><input type="checkbox" checked></div>
                    <div class="toggle-row"><span>Акции и скидки</span><input type="checkbox"></div>
                    <div class="toggle-row"><span>Email-рассылка</span><input type="checkbox" checked></div>
                    <div class="toggle-row"><span>SMS-уведомления</span><input type="checkbox"></div>
                    <div class="toggle-row"><span>Push-уведомления</span><input type="checkbox" checked></div>
                    <button class="btn btn-edit" style="margin-top:1rem">Сохранить</button>
                </div>
            <?php endif; ?>

            <!-- Заглушки для остальных вкладок -->
            <?php if (!in_array($active_tab, ['personal', 'security', 'orders', 'notifications'])): ?>
                <div class="settings-section active">
                    <h3><?= htmlspecialchars(ucfirst($active_tab)) ?></h3>
                    <p>Раздел в разработке. Здесь будет: <?= match ($active_tab) {
                        'addresses' => 'управление адресами доставки',
                        'payment' => 'привязка карт и кошельков',
                        'returns' => 'оформление возвратов',
                        'subscriptions' => 'управление автозаказами',
                        'discounts' => 'промокоды и купоны',
                        'favorites' => 'история просмотров и желаемое',
                        'privacy' => 'настройки видимости и GDPR',
                        'reviews' => 'управление отзывами',
                        'data' => 'экспорт профиля и заказов',
                        'loyalty' => 'баллы и уровни',
                        'delivery' => 'предпочтения доставки',
                        default => 'функционал раздела'
                    } ?></p>
                </div>
            <?php endif; ?>
        </section>
    </main>
    <footer class="profile-footer">
        <p>&copy; 2024 SportNutrition</p>
</html>
<?php $link->close(); ?>
