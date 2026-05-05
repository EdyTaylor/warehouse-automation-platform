<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: text/html; charset=utf-8');

require 'db.php';
$db = getDB();
$page_title = 'Продажи';
require 'includes/header.php';

function bitrixDealUrlById($dealId) {
    $id = intval($dealId);
    if ($id <= 0) {
        return '';
    }
    return 'https://llumar.bitrix24.kz/crm/deal/details/' . $id . '/';
}
$from = isset($_GET['from']) ? trim($_GET['from']) : '';
$to = isset($_GET['to']) ? trim($_GET['to']) : '';
$deal = isset($_GET['deal_id']) ? intval($_GET['deal_id']) : 0;

$where = array();
$params = array();
$where[] = "s.deal_id IS NOT NULL";
$where[] = "s.type IN ('meter','roll')";

if ($from !== '') {
    $where[] = "DATE(s.created_at) >= ?";
    $params[] = $from;
}
if ($to !== '') {
    $where[] = "DATE(s.created_at) <= ?";
    $params[] = $to;
}
if ($deal > 0) {
    $where[] = "s.deal_id = ?";
    $params[] = $deal;
}

$sql = "
    SELECT
        s.*,
        COALESCE(NULLIF(TRIM(p.name), ''), CONCAT('Товар #', s.product_id)) as product_name
    FROM sales s
    LEFT JOIN products p ON p.id = s.product_id
";
if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}
$sql .= " ORDER BY s.created_at DESC, s.id DESC LIMIT 500";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalsSql = "
    SELECT
        COUNT(*) as cnt,
        COALESCE(SUM(s.total), 0) as total_sum
    FROM sales s
    WHERE s.deal_id IS NOT NULL
      AND s.type IN ('meter','roll')
";
$totalsStmt = $db->query($totalsSql);
$totals = $totalsStmt->fetch(PDO::FETCH_ASSOC);
?>

<main class="container">
    <h2>💰 Продажи из Б24</h2>
    <p class="text-muted">
        Здесь только продажи, пришедшие по сделкам Б24. Ручной резерв и подтверждение — во вкладке <a href="b24_sales.php">Б24</a>.
    </p>

    <div class="card">
        <h3>Фильтры</h3>
        <form method="GET">
            <div class="form-row">
                <div class="form-group">
                    <label>С даты</label>
                    <input type="date" name="from" value="<?php echo htmlspecialchars($from); ?>">
                </div>
                <div class="form-group">
                    <label>По дату</label>
                    <input type="date" name="to" value="<?php echo htmlspecialchars($to); ?>">
                </div>
                <div class="form-group">
                    <label>ID сделки Б24</label>
                    <input type="number" name="deal_id" min="1" value="<?php echo $deal > 0 ? intval($deal) : ''; ?>">
                </div>
                <div class="form-group">
                    <label>&nbsp;</label>
                    <button type="submit" class="btn btn-primary">Применить</button>
                    <a href="sell.php" class="btn btn-light">Сброс</a>
                </div>
            </div>
        </form>
    </div>

    <div class="card">
        <h3>Итоги</h3>
        <p><strong>Всего продаж из Б24:</strong> <?php echo isset($totals['cnt']) ? intval($totals['cnt']) : 0; ?></p>
        <p><strong>Сумма:</strong> <?php echo isset($totals['total_sum']) ? number_format(floatval($totals['total_sum']), 2, '.', ' ') : '0.00'; ?></p>
    </div>

    <div class="card">
        <h3>Операции</h3>
        <?php if (empty($rows)): ?>
            <p>Продаж из Б24 пока нет.</p>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Дата</th>
                        <th>Сделка</th>
                        <th>Товар</th>
                        <th>Тип</th>
                        <th>Количество</th>
                        <th>Цена</th>
                        <th>Сумма</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?php echo !empty($row['created_at']) ? htmlspecialchars($row['created_at']) : '-'; ?></td>
                        <td>
                            <?php if (!empty($row['deal_id'])): ?>
                                <?php
                                    $dealLink = !empty($row['deal_url']) ? (string)$row['deal_url'] : bitrixDealUrlById($row['deal_id']);
                                ?>
                                <a href="<?php echo htmlspecialchars($dealLink); ?>" target="_blank" rel="noopener">
                                    #<?php echo intval($row['deal_id']); ?>
                                </a>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($row['product_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['type']); ?></td>
                        <td><?php echo floatval($row['quantity']); ?></td>
                        <td><?php echo number_format(floatval($row['price_per_unit']), 2, '.', ' '); ?></td>
                        <td><?php echo number_format(floatval($row['total']), 2, '.', ' '); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</main>

<?php require 'includes/footer.php'; ?>