<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: text/html; charset=utf-8');

require 'db.php';
$db = getDB();
$page_title = 'Остатки';
require 'includes/header.php';

$search = trim(isset($_GET['q']) ? $_GET['q'] : '');
$minFree = floatval(isset($_GET['min_free']) ? $_GET['min_free'] : 0);
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'name';

$where = [];
$params = [];
if ($search !== '') {
    $where[] = "products.name LIKE ?";
    $params[] = '%' . $search . '%';
}
$having = [];
if ($minFree > 0) {
    $having[] = "SUM(CASE WHEN rolls.reserved = 0 THEN rolls.current_length ELSE 0 END) >= ?";
    $params[] = $minFree;
}

$orderBy = "products.name ASC";
if ($sort === 'free_desc') {
    $orderBy = "free_meters DESC";
} elseif ($sort === 'free_asc') {
    $orderBy = "free_meters ASC";
} elseif ($sort === 'full_rolls_asc') {
    $orderBy = "full_rolls ASC";
}

$sql = "
    SELECT 
        products.name,
        COUNT(CASE WHEN rolls.status = 'active' THEN 1 END) as full_rolls,
        COUNT(CASE WHEN rolls.status = 'cut' THEN 1 END) as cut_rolls,
        SUM(rolls.current_length) as total_meters,
        SUM(CASE WHEN rolls.reserved = 0 THEN rolls.current_length ELSE 0 END) as free_meters
    FROM products
    LEFT JOIN rolls ON rolls.product_id = products.id
    GROUP BY products.id
";
if (!empty($where)) {
    $sql = str_replace("GROUP BY", "WHERE " . implode(" AND ", $where) . " GROUP BY", $sql);
}
if (!empty($having)) {
    $sql .= " HAVING " . implode(" AND ", $having);
}
$sql .= " ORDER BY " . $orderBy;
$stmt = $db->prepare($sql);
$stmt->execute($params);
$stock = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<main class="container">
<h2>📦 Остатки</h2>

<div class="card">
    <form method="GET">
        <div class="form-row">
            <div class="form-group">
                <label>Поиск товара</label>
                <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" class="form-control">
            </div>
            <div class="form-group">
                <label>Мин. свободно (м)</label>
                <input type="number" name="min_free" step="0.1" min="0" value="<?= htmlspecialchars((string)$minFree) ?>" class="form-control">
            </div>
            <div class="form-group">
                <label>Сортировка</label>
                <select name="sort" class="form-control">
                    <option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>По названию</option>
                    <option value="free_desc" <?= $sort === 'free_desc' ? 'selected' : '' ?>>Свободно (убыв.)</option>
                    <option value="free_asc" <?= $sort === 'free_asc' ? 'selected' : '' ?>>Свободно (возр.)</option>
                    <option value="full_rolls_asc" <?= $sort === 'full_rolls_asc' ? 'selected' : '' ?>>Целых рулонов (возр.)</option>
                </select>
            </div>
            <div class="form-group">
                <label>&nbsp;</label>
                <button type="submit" class="btn btn-light">Применить</button>
                <a href="stock.php" class="btn btn-light">Сброс</a>
            </div>
        </div>
    </form>
</div>

<table class="table">
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