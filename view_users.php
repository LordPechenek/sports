<!DOCTYPE html>
<html lang="ru">

<head>
    <title>Просмотр всех пользователей</title>
    <meta charset="UTF-8">
    <style>
        .user-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }

        .user-table th,
        .user-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }

        .user-table th {
            background-color: #f2f2f2;
        }

        .user-row:hover {
            background-color: #f5f5f5;
        }

        .error {
            color: red;
            font-weight: bold;
        }
    </style>
</head>

<body>
    <h2>Список пользователей</h2>

    <?php

    require_once('connect_db.php');

    // Проверяем, что подключение успешно
    if ($link->connect_error) {
        die('<p class="error">Ошибка подключения к БД: ' . htmlspecialchars($link->connect_error) . '</p>');
    }

    // Выполняем запрос ПОСЛЕ подключения и ДО закрытия соединения
    $sql = "SELECT * FROM user";
    $result = $link->query($sql);

    if ($result === false) {
        echo '<p class="error">Ошибка выполнения запроса: ' . htmlspecialchars($link->error) . '</p>';
    } else {
        if ($result->num_rows > 0) {
            $rowsCount = $result->num_rows; // количество полученных строк
            echo "<p>Получено объектов: $rowsCount</p>";
            echo '<table class="user-table">';
            echo '<thead><tr><th>ID</th><th>Имя пользователя</th><th>Email</th></tr></thead>';
            echo '<tbody>';

            while ($row = $result->fetch_assoc()) {
                echo '<tr class="user-row">';
                echo '<td>' . htmlspecialchars((string) $row['user_id']) . '</td>';
                echo '<td>' . htmlspecialchars((string) $row['username']) . '</td>';
                echo '<td>' . htmlspecialchars((string) $row['email']) . '</td>';
                echo '</tr>';
            }

            echo '</tbody></table>';
        } else {
            echo '<p>В базе данных нет пользователей.</p>';
        }
    }

    // Закрываем соединение ПОСЛЕ всех операций с БД
    $link->close();
    ?>

    <p class="back-link"><a href="../index.php">← Назад на главную</a></p>
</body>

</html>