<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require 'db.php';
$db = getDB();

echo "<h2>🔍 Диагностика таблиц</h2>";

// Проверяем структуру таблицы rolls
echo "<h3>Структура таблицы rolls:</h3>";
$result = $db->query("DESCRIBE rolls");
echo "<table border='1'>";
echo "<tr><th>Поле</th><th>Тип</th><th>Null</th><th>Key</th></tr>";
while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
    echo "<tr>";
    echo "<td>" . $row['Field'] . "</td>";
    echo "<td>" . $row['Type'] . "</td>";
    echo "<td>" . $row['Null'] . "</td>";
    echo "<td>" . $row['Key'] . "</td>";
    echo "</tr>";
}
echo "</table>";

// Проверяем структуру таблицы products
echo "<h3>Структура таблицы products:</h3>";
$result = $db->query("DESCRIBE products");
echo "<table border='1'>";
echo "<tr><th>Поле</th><th>Тип</th><th>Null</th><th>Key</th></tr>";
while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
    echo "<tr>";
    echo "<td>" . $row['Field'] . "</td>";
    echo "<td>" . $row['Type'] . "</td>";
    echo "<td>" . $row['Null'] . "</td>";
    echo "<td>" . $row['Key'] . "</td>";
    echo "</tr>";
}
echo "</table>";

// Проверяем данные в таблицах
echo "<h3>Пример данных из rolls (первые 3 записи):</h3>";
$result = $db->query("SELECT * FROM rolls LIMIT 3");
echo "<table border='1'>";
if ($result) {
    $columns = array_keys($result->fetch(PDO::FETCH_ASSOC));
    echo "<tr>";
    foreach ($columns as $col) {
        echo "<th>$col</th>";
    }
    echo "</tr>";
    
    $result = $db->query("SELECT * FROM rolls LIMIT 3");
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        echo "<tr>";
        foreach ($row as $value) {
            echo "<td>" . htmlspecialchars($value) . "</td>";
        }
        echo "</tr>";
    }
}
echo "</table>";

echo "<h3>Пример данных из products (первые 3 записи):</h3>";
$result = $db->query("SELECT * FROM products LIMIT 3");
echo "<table border='1'>";
if ($result) {
    $columns = array_keys($result->fetch(PDO::FETCH_ASSOC));
    echo "<tr>";
    foreach ($columns as $col) {
        echo "<th>$col</th>";
    }
    echo "</tr>";
    
    $result = $db->query("SELECT * FROM products LIMIT 3");
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        echo "<tr>";
        foreach ($row as $value) {
            echo "<td>" . htmlspecialchars($value) . "</td>";
        }
        echo "</tr>";
    }
}
echo "</table>";

// Тестируем JOIN
echo "<h3>Тест JOIN запроса:</h3>";
try {
    $result = $db->query("
        SELECT 
            r.id as roll_id,
            r.product_id,
            r.current_length,
            r.status,
            p.name as product_name,
            p.roll_length,
            p.price_per_meter
        FROM rolls r
        LEFT JOIN products p ON r.product_id = p.id
        LIMIT 3
    ");
    
    echo "<table border='1'>";
    if ($result) {
        $columns = array_keys($result->fetch(PDO::FETCH_ASSOC));
        echo "<tr>";
        foreach ($columns as $col) {
            echo "<th>$col</th>";
        }
        echo "</tr>";
        
        $result = $db->query("
            SELECT 
                r.id as roll_id,
                r.product_id,
                r.current_length,
                r.status,
                p.name as product_name,
                p.roll_length,
                p.price_per_meter
            FROM rolls r
            LEFT JOIN products p ON r.product_id = p.id
            LIMIT 3
        ");
        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
            echo "<tr>";
            foreach ($row as $value) {
                echo "<td>" . htmlspecialchars($value) . "</td>";
            }
            echo "</tr>";
        }
    }
    echo "</table>";
    echo "<p>✅ JOIN работает!</p>";
} catch (Exception $e) {
    echo "<p>❌ Ошибка JOIN: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<hr>";
echo "<a href='warehouse.php'>← Вернуться к складу</a>";
?>
