<?php
/**
 * Подключение к MySQL — та же база, что в phpMyAdmin (по умолчанию хост 127.0.1.31:3306 для OSPanel).
 *
 * Папка sql/ (например sql/db.sql) — только дамп для ручного импорта: phpMyAdmin → ваша база → «Импорт» → выбрать файл.
 * PHP при открытии страниц файлы .sql не подключает и не читает — каталог, пользователи и заказы берутся только из MySQL ниже.
 *
 * Переопределение (другой хост/порт/имя базы): переменные окружения для PHP/Apache
 * DB_HOST, DB_PORT, DB_SOCKET, DB_USER, DB_PASS, DB_NAME
 */

$host = getenv('DB_HOST');
$host = ($host !== false && $host !== '') ? $host : '127.0.1.31';

$portEnv = getenv('DB_PORT');
$port = ($portEnv !== false && $portEnv !== '') ? (int) $portEnv : 3306;

$username = getenv('DB_USER');
$username = ($username !== false && $username !== '') ? $username : 'root';

$dbpass = getenv('DB_PASS');
$password = ($dbpass !== false) ? $dbpass : '';

$database = getenv('DB_NAME');
$database = ($database !== false && $database !== '') ? $database : 'db';

$socket = getenv('DB_SOCKET');
$socket = ($socket !== false && $socket !== '') ? $socket : null;

if ($socket !== null) {
    $link = new mysqli($host, $username, $password, $database, $port, $socket);
} else {
    $link = new mysqli($host, $username, $password, $database, $port);
}

if ($link->connect_error) {
    die(
        'Ошибка подключения к MySQL: ' . htmlspecialchars($link->connect_error, ENT_QUOTES, 'UTF-8')
        . '<br><small>Проверьте, что MySQL запущен, база создана и при необходимости импортирован файл sql/db.sql через phpMyAdmin. '
        . 'Хост по умолчанию 127.0.1.31, порт 3306, база db — задайте DB_HOST/DB_PORT/DB_NAME при другой конфигурации.</small>'
    );
}

if (!$link->set_charset('utf8mb4')) {
    die('Ошибка установки кодировки: ' . htmlspecialchars($link->error, ENT_QUOTES, 'UTF-8'));
}
