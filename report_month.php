<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require 'db.php';
$db = getDB();
?>

<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<title>Отчет за месяц</title>
</head>
<body>

<?php require_once 'menu.php'; ?>

<?php

$data = $db->query("
    SELECT 
        products.name,
        SUM(sales.quantity) as total_qty,
        SUM(sales.total) as revenue
    FROM sales
    LEFT JOIN products ON products.id = sales.product_id
    WHERE MONTH(sales.created_at) = MONTH(CURDATE())
    AND YEAR(sales.created_at) = YEAR(CURDATE())
    GROUP BY products.id, products.name
")->fetchAll(PDO::FETCH_ASSOC);


$total = $db->query("
    SELECT SUM(total) as total_sum 
    FROM sales 
    WHERE MONTH(created_at) = MONTH(CURDATE())
    AND YEAR(created_at) = YEAR(CURDATE())
")->fetch(PDO::FETCH_ASSOC);


$details = $db->query("
    SELECT sales.*, products.name
    FROM sales
    LEFT JOIN products ON products.id = sales.product_id
    WHERE MONTH(sales.created_at) = MONTH(CURDATE())
    AND YEAR(sales.created_at) = YEAR(CURDATE())
    ORDER BY sales.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

?>

<h2>📈 Отчет за месяц</h2>

<?php if (empty($data)) { ?>

<p>Нет данных</p>

<?php } else { ?>

<h3>Сводка</h3>

<table border="1">
<tr>
    <th>Товар</th>
    <th>Количество</th>
    <th>Выручка</th>
</tr>

<?php foreach ($data as $row) { ?>
<tr>
    <td><?php echo htmlspecialchars(isset($row['name']) ? $row['name'] : ''); ?></td>
    <td><?php echo isset($row['total_qty']) ? $row['total_qty'] : 0; ?></td>
    <td><?php echo isset($row['revenue']) ? $row['revenue'] : 0; ?></td>
</tr>
<?php } ?>
</table>

<h3>Итого: <?php echo isset($total['total_sum']) ? $total['total_sum'] : 0; ?></h3>

<?php } ?>

<h3>🕒 Детализация</h3>

<?php if (empty($details)) { ?>

<p>Нет операций</p>

<?php } else { ?>

<table border="1">
<tr>
    <th>Дата</th>
    <th>Время</th>
    <th>Товар</th>
    <th>Тип</th>
    <th>Операция</th>
    <th>Количество</th>
    <th>Цена</th>
    <th>Сумма</th>
    <th>Сделка</th>
    <th>Менеджер</th>
</tr>

<?php foreach ($details as $d) { ?>
<tr>
    <td><?php echo !empty($d['created_at']) ? date('d.m.Y', strtotime($d['created_at'])) : '-'; ?></td>
    <td><?php echo !empty($d['created_at']) ? date('H:i:s', strtotime($d['created_at'])) : '-'; ?></td>
    <td><?php echo htmlspecialchars(isset($d['name']) ? $d['name'] : ''); ?></td>
    <td><?php echo htmlspecialchars(isset($d['type']) ? $d['type'] : ''); ?></td>

    <td>
        <?php if ((isset($d['type']) ? $d['type'] : '') == 'reserve') { ?>
            🟡 Резерв
        <?php } elseif ((isset($d['type']) ? $d['type'] : '') == 'writeoff') { ?>
            🔴 Списание
        <?php } else { ?>
            🟢 Продажа
        <?php } ?>
    </td>

    <td><?php echo isset($d['quantity']) ? $d['quantity'] : 0; ?></td>
    <td><?php echo isset($d['price_per_unit']) ? $d['price_per_unit'] : 0; ?></td>
    <td><?php echo isset($d['total']) ? $d['total'] : 0; ?></td>

    <!-- 🔥 ВОТ ЭТО МЫ ДОБАВИЛИ -->
    <td>
        <?php if (!empty($d['deal_id'])) { ?>
            <a href="<?php echo htmlspecialchars(isset($d['deal_url']) ? $d['deal_url'] : '#'); ?>" target="_blank">
                Сделка #<?php echo intval($d['deal_id']); ?>
            </a>
        <?php } else { ?>
            —
        <?php } ?>
    </td>
    <td><?php echo htmlspecialchars(isset($d['responsible']) ? $d['responsible'] : ''); ?></td>

</tr>
<?php } ?>
</table>

<?php } ?>

</body>
</html>