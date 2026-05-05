<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require 'db.php';
require_once __DIR__ . '/api/bitrix/send.php';
$db = getDB();

function bitrixDealUrlById($dealId) {
    $id = intval($dealId);
    if ($id <= 0) {
        return '';
    }
    return 'https://llumar.bitrix24.kz/crm/deal/details/' . $id . '/';
}

function responsibleLabel($rawResponsible) {
    $raw = trim((string)$rawResponsible);
    if ($raw === '') {
        return '';
    }
    if (!preg_match('/^User\s+(\d+)$/i', $raw, $m)) {
        return $raw;
    }

    $uid = intval($m[1]);
    if ($uid <= 0) {
        return $raw;
    }

    static $cache = array();
    if (isset($cache[$uid])) {
        return $cache[$uid];
    }

    $label = 'User ' . $uid;
    $resp = sendToBitrix('user.get', array(
        'FILTER' => array('ID' => $uid)
    ));
    if (is_array($resp) && isset($resp['result']) && is_array($resp['result']) && !empty($resp['result'][0])) {
        $u = $resp['result'][0];
        $parts = array();
        if (!empty($u['NAME'])) {
            $parts[] = trim((string)$u['NAME']);
        }
        if (!empty($u['LAST_NAME'])) {
            $parts[] = trim((string)$u['LAST_NAME']);
        }
        $fullName = trim(implode(' ', $parts));
        if ($fullName !== '') {
            $label .= ' (' . $fullName . ')';
        }
    }

    $cache[$uid] = $label;
    return $label;
}

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
$page_title = 'Отчеты';
require 'includes/header.php';
$dateFrom = isset($_GET['date_from']) ? trim((string)$_GET['date_from']) : date('Y-m-d');
$dateTo = isset($_GET['date_to']) ? trim((string)$_GET['date_to']) : date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
    $dateFrom = date('Y-m-d');
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
    $dateTo = date('Y-m-d');
}
if ($dateFrom > $dateTo) {
    $tmp = $dateFrom;
    $dateFrom = $dateTo;
    $dateTo = $tmp;
}
$fromStart = $dateFrom . ' 00:00:00';
$toEnd = $dateTo . ' 23:59:59';

$summaryStmt = $db->prepare("
    SELECT
        products.name,
        SUM(sales.quantity) as total_qty,
        SUM(sales.total) as revenue,
        SUM(COALESCE(sales.cost_fact, 0)) as cost_fact,
        SUM(COALESCE(sales.gross_profit, 0)) as gross_profit
    FROM sales
    LEFT JOIN products ON products.id = sales.product_id
    WHERE sales.created_at BETWEEN ? AND ?
    GROUP BY products.id, products.name
");
$summaryStmt->execute(array($fromStart, $toEnd));
$data = $summaryStmt->fetchAll(PDO::FETCH_ASSOC);

$totalStmt = $db->prepare("
    SELECT
        SUM(total) as total_sum,
        SUM(COALESCE(cost_fact, 0)) as total_cost,
        SUM(COALESCE(gross_profit, 0)) as total_profit
    FROM sales
    WHERE created_at BETWEEN ? AND ?
");
$totalStmt->execute(array($fromStart, $toEnd));
$total = $totalStmt->fetch(PDO::FETCH_ASSOC);

$detailsStmt = $db->prepare("
    SELECT sales.*, products.name
    FROM sales
    LEFT JOIN products ON products.id = sales.product_id
    WHERE sales.created_at BETWEEN ? AND ?
    ORDER BY sales.created_at DESC
");
$detailsStmt->execute(array($fromStart, $toEnd));
$details = $detailsStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<main class="container">
    <h2>📊 Отчеты</h2>
    <div class="card">
        <p class="text-muted">
            Это <strong>операционный отчет</strong>: детальные строки продаж, суммы и проверка данных.
            Для управленческих KPI, динамики и топов по бизнесу используйте
            <a href="manager_dashboard.php">вкладку «Руководитель»</a>.
        </p>
    </div>
    <div class="card">
        <h3>Период</h3>
        <form method="GET" class="form-row">
            <div class="form-group">
                <label>Дата от</label>
                <input type="date" name="date_from" value="<?php echo htmlspecialchars($dateFrom); ?>">
            </div>
            <div class="form-group">
                <label>Дата до</label>
                <input type="date" name="date_to" value="<?php echo htmlspecialchars($dateTo); ?>">
            </div>
            <div class="form-group" style="display:flex;align-items:flex-end;gap:8px;">
                <button class="btn btn-primary btn-sm" type="submit">Показать</button>
                <a class="btn btn-light btn-sm" href="report_day.php">Сегодня</a>
            </div>
        </form>
    </div>

    <div class="card">
        <h3>Сводка за период <?php echo htmlspecialchars($dateFrom); ?> — <?php echo htmlspecialchars($dateTo); ?></h3>
        <?php if (empty($data)) { ?>
            <p>Нет данных за выбранный период.</p>
        <?php } else { ?>
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
        <?php } ?>
        <?php
            $sumRevenue = floatval(isset($total['total_sum']) ? $total['total_sum'] : 0);
            $sumCost = floatval(isset($total['total_cost']) ? $total['total_cost'] : 0);
            $sumProfit = floatval(isset($total['total_profit']) ? $total['total_profit'] : 0);
            $sumMargin = $sumRevenue > 0 ? ($sumProfit / $sumRevenue) * 100 : 0;
        ?>
        <h3>Итого выручка: <?php echo number_format($sumRevenue, 2, '.', ' '); ?></h3>
        <h3>Итого себестоимость: <?php echo number_format($sumCost, 2, '.', ' '); ?></h3>
        <h3>Итого валовая прибыль: <?php echo number_format($sumProfit, 2, '.', ' '); ?> (<?php echo number_format($sumMargin, 2, '.', ' '); ?>%)</h3>
    </div>

    <div class="card">
        <h3>🕒 Детализация</h3>
        <?php if (empty($details)) { ?>
            <p>Нет операций.</p>
        <?php } else { ?>
            <div class="table-responsive">
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
                                <?php if ((isset($d['type']) ? $d['type'] : '') === 'reserve') { ?>
                                    🟡 Резерв
                                <?php } elseif ((isset($d['type']) ? $d['type'] : '') === 'writeoff') { ?>
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
                            <td>
                                <?php if (!empty($d['deal_id'])) { ?>
                                    <?php
                                        $dealLink = !empty($d['deal_url']) ? (string)$d['deal_url'] : bitrixDealUrlById($d['deal_id']);
                                    ?>
                                    <a href="<?php echo htmlspecialchars($dealLink); ?>" target="_blank" rel="noopener">
                                        Сделка #<?php echo intval($d['deal_id']); ?>
                                    </a>
                                <?php } else { ?>
                                    —
                                <?php } ?>
                            </td>
                            <td><?php echo htmlspecialchars(responsibleLabel(isset($d['responsible']) ? $d['responsible'] : '')); ?></td>
                        </tr>
                    <?php } ?>
                </table>
            </div>
        <?php } ?>
    </div>
</main>

<?php require 'includes/footer.php'; ?>