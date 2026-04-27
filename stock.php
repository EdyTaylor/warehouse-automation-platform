<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: text/html; charset=utf-8');

require 'db.php';
$db = getDB();
$page_title = 'Остатки';
require 'includes/header.php';

$stock = $db->query("
    SELECT 
        products.name,
        COUNT(CASE WHEN rolls.status = 'active' THEN 1 END) as full_rolls,
        COUNT(CASE WHEN rolls.status = 'cut' THEN 1 END) as cut_rolls,
        SUM(rolls.current_length) as total_meters,
        SUM(CASE WHEN rolls.reserved = 0 THEN rolls.current_length ELSE 0 END) as free_meters
    FROM products
    LEFT JOIN rolls ON rolls.product_id = products.id
    GROUP BY products.id
")->fetchAll(PDO::FETCH_ASSOC);
?>

<main class="container">
<h2>📦 Остатки</h2>

<table border="1">
<tr>
    <th>Товар</th>
    <th>Целые рулоны</th>
    <th>Обрезки</th>
    <th>Всего метров</th>
    <th>Свободно (м)</th>
</tr>

<?php foreach ($stock as $s): ?>
<tr>
    <td><?= $s['name'] ?></td>
    <td><?= $s['full_rolls'] ?></td>
    <td><?= $s['cut_rolls'] ?></td>
    <td><?= round($s['total_meters'], 2) ?></td>
    <td><?= round($s['free_meters'], 2) ?></td>
</tr>
<?php endforeach; ?>
</table>

<?php
$limit = 3;

foreach ($stock as $s) {
    if ($s['full_rolls'] <= $limit) {
        echo "<p style='color:red; font-weight:bold;'>
            ⚠ Нужно заказать: {$s['name']}
        </p>";
    }
}
?>
</main>

<?php require 'includes/footer.php'; ?>