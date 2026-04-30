<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require 'db.php';
$db = getDB();

function ensureReportFinanceColumns($db) {
    $cols = array(
        'cost_fact' => "`cost_fact` decimal(14,2) NOT NULL DEFAULT 0",
        'gross_profit' => "`gross_profit` decimal(14,2) NOT NULL DEFAULT 0",
        'gross_margin_percent' => "`gross_margin_percent` decimal(8,2) NOT NULL DEFAULT 0"
    );
    foreach ($cols as $name => $sql) {
        $stmt = $db->prepare("SHOW COLUMNS FROM `sales` LIKE ?");
        $stmt->execute(array($name));
        if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
            $db->exec("ALTER TABLE `sales` ADD COLUMN {$sql}");
        }
    }
}

ensureReportFinanceColumns($db);
$page_title = 'Отчет за все время';
require 'includes/header.php';
?>

<main class="container">

<?php

// 🔥 СВОДКА
$data = $db->query("
    SELECT 
        products.name,
        SUM(sales.quantity) as total_qty,
        SUM(sales.total) as revenue,
        SUM(COALESCE(sales.cost_fact, 0)) as cost_fact,
        SUM(COALESCE(sales.gross_profit, 0)) as gross_profit
    FROM sales
    LEFT JOIN products ON products.id = sales.product_id
    GROUP BY products.id, products.name
")->fetchAll(PDO::FETCH_ASSOC);


// 🔥 ОБЩИЙ ИТОГ
$total = $db->query("
    SELECT
        SUM(total) as total_sum,
        SUM(COALESCE(cost_fact, 0)) as total_cost,
        SUM(COALESCE(gross_profit, 0)) as total_profit
    FROM sales
")->fetch(PDO::FETCH_ASSOC);


// 🔥 ДЕТАЛИЗАЦИЯ
$details = $db->query("
    SELECT sales.*, products.name
    FROM sales
    LEFT JOIN products ON products.id = sales.product_id
    ORDER BY sales.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

?>

<h2>📊 Отчет за всё время</h2>

<?php if (empty($data)) { ?>

<p>Нет данных</p>

<?php } else { ?>

<h3>Сводка</h3>

<table class="table">
<tr>
    <th>Товар</th>
    <th>Количество</th>
    <th>Выручка</th>
    <th>Себестоимость</th>
    <th>Валовая прибыль</th>
    <th>Маржа %</th>
</tr>

<?php foreach ($data as $row) { ?>
<?php
    $rowRevenue = floatval(isset($row['revenue']) ? $row['revenue'] : 0);
    $rowProfit = floatval(isset($row['gross_profit']) ? $row['gross_profit'] : 0);
    $rowMargin = $rowRevenue > 0 ? ($rowProfit / $rowRevenue) * 100 : 0;
?>
<tr>
    <td><?php echo htmlspecialchars(isset($row['name']) ? $row['name'] : ''); ?></td>
    <td><?php echo isset($row['total_qty']) ? $row['total_qty'] : 0; ?></td>
    <td><?php echo number_format($rowRevenue, 2, '.', ' '); ?></td>
    <td><?php echo number_format(floatval(isset($row['cost_fact']) ? $row['cost_fact'] : 0), 2, '.', ' '); ?></td>
    <td><?php echo number_format($rowProfit, 2, '.', ' '); ?></td>
    <td><?php echo number_format($rowMargin, 2, '.', ' '); ?>%</td>
</tr>
<?php } ?>
</table>

<?php
    $sumRevenue = floatval(isset($total['total_sum']) ? $total['total_sum'] : 0);
    $sumCost = floatval(isset($total['total_cost']) ? $total['total_cost'] : 0);
    $sumProfit = floatval(isset($total['total_profit']) ? $total['total_profit'] : 0);
    $sumMargin = $sumRevenue > 0 ? ($sumProfit / $sumRevenue) * 100 : 0;
?>
<h3>Итого выручка: <?php echo number_format($sumRevenue, 2, '.', ' '); ?></h3>
<h3>Итого себестоимость: <?php echo number_format($sumCost, 2, '.', ' '); ?></h3>
<h3>Итого валовая прибыль: <?php echo number_format($sumProfit, 2, '.', ' '); ?> (<?php echo number_format($sumMargin, 2, '.', ' '); ?>%)</h3>

<?php } ?>

<h3>🕒 Все операции</h3>

<?php if (empty($details)) { ?>

<p>Нет операций</p>

<?php } else { ?>

<table class="table">
<tr>
    <th>Дата</th>
    <th>Время</th>
    <th>Товар</th>
    <th>Тип</th>
    <th>Операция</th>
    <th>Количество</th>
    <th>Цена</th>
    <th>Сумма</th>
    <th>Себестоимость</th>
    <th>Прибыль</th>
    <th>Маржа %</th>
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
    <td><?php echo number_format(floatval(isset($d['cost_fact']) ? $d['cost_fact'] : 0), 2, '.', ' '); ?></td>
    <td><?php echo number_format(floatval(isset($d['gross_profit']) ? $d['gross_profit'] : 0), 2, '.', ' '); ?></td>
    <td><?php echo number_format(floatval(isset($d['gross_margin_percent']) ? $d['gross_margin_percent'] : 0), 2, '.', ' '); ?>%</td>

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
</main>

<?php require 'includes/footer.php'; ?>