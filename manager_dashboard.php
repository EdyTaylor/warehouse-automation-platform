<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: text/html; charset=utf-8');

require 'db.php';
require_once __DIR__ . '/functions/b24_sale_pricing.php';
$db = getDB();

function ensureManagerFinanceColumns($db) {
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
    ensureSalesRevenuePlannedColumn($db);
}

function normalizeDateYmd($value, $fallback) {
    $value = trim((string)$value);
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return $value;
    }
    return $fallback;
}

ensureManagerFinanceColumns($db);

$dateFrom = normalizeDateYmd(isset($_GET['from']) ? $_GET['from'] : '', date('Y-m-01'));
$dateTo = normalizeDateYmd(isset($_GET['to']) ? $_GET['to'] : '', date('Y-m-d'));
if ($dateFrom > $dateTo) {
    $tmp = $dateFrom;
    $dateFrom = $dateTo;
    $dateTo = $tmp;
}
$fromTs = $dateFrom . ' 00:00:00';
$toTs = $dateTo . ' 23:59:59';

$summary = array('revenue' => 0, 'revenue_planned' => 0, 'cost' => 0, 'profit' => 0, 'qty' => 0, 'orders' => 0);
$daily = array();
$topRows = array();
$bottomRows = array();

try {
    $sumStmt = $db->prepare("
        SELECT
            SUM(total) as revenue,
            SUM(COALESCE(revenue_planned,0)) as revenue_planned,
            SUM(COALESCE(cost_fact,0)) as cost,
            SUM(COALESCE(gross_profit,0)) as profit,
            SUM(quantity) as qty,
            COUNT(*) as orders
        FROM sales
        WHERE created_at BETWEEN ? AND ?
    ");
    $sumStmt->execute(array($fromTs, $toTs));
    $summary = $sumStmt->fetch(PDO::FETCH_ASSOC);

    $dailyStmt = $db->prepare("
        SELECT
            DATE(created_at) as d,
            SUM(total) as revenue,
            SUM(COALESCE(revenue_planned,0)) as revenue_planned,
            SUM(COALESCE(cost_fact,0)) as cost,
            SUM(COALESCE(gross_profit,0)) as profit
        FROM sales
        WHERE created_at BETWEEN ? AND ?
        GROUP BY DATE(created_at)
        ORDER BY DATE(created_at) ASC
    ");
    $dailyStmt->execute(array($fromTs, $toTs));
    $daily = $dailyStmt->fetchAll(PDO::FETCH_ASSOC);

    $topStmt = $db->prepare("
        SELECT
            p.id as product_id,
            p.name,
            SUM(s.quantity) as qty,
            SUM(s.total) as revenue,
            SUM(COALESCE(s.revenue_planned,0)) as revenue_planned,
            SUM(COALESCE(s.cost_fact,0)) as cost,
            SUM(COALESCE(s.gross_profit,0)) as profit
        FROM sales s
        LEFT JOIN products p ON p.id = s.product_id
        WHERE s.created_at BETWEEN ? AND ?
        GROUP BY p.id, p.name
        ORDER BY profit DESC
        LIMIT 300
    ");
    $topStmt->execute(array($fromTs, $toTs));
    $topRows = $topStmt->fetchAll(PDO::FETCH_ASSOC);

    $bottomStmt = $db->prepare("
        SELECT
            p.id as product_id,
            p.name,
            SUM(s.quantity) as qty,
            SUM(s.total) as revenue,
            SUM(COALESCE(s.revenue_planned,0)) as revenue_planned,
            SUM(COALESCE(s.cost_fact,0)) as cost,
            SUM(COALESCE(s.gross_profit,0)) as profit
        FROM sales s
        LEFT JOIN products p ON p.id = s.product_id
        WHERE s.created_at BETWEEN ? AND ?
        GROUP BY p.id, p.name
        ORDER BY profit ASC
        LIMIT 10
    ");
    $bottomStmt->execute(array($fromTs, $toTs));
    $bottomRows = $bottomStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Keep page available.
}

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="manager_dashboard_' . $dateFrom . '_' . $dateTo . '.csv"');
    echo chr(239) . chr(187) . chr(191);
    $out = fopen('php://output', 'w');
    $sumRevenue = floatval(isset($summary['revenue']) ? $summary['revenue'] : 0);
    $sumCost = floatval(isset($summary['cost']) ? $summary['cost'] : 0);
    $sumProfit = floatval(isset($summary['profit']) ? $summary['profit'] : 0);
    $sumMargin = $sumRevenue > 0 ? ($sumProfit / $sumRevenue) * 100 : 0;
    fputcsv($out, array('Период', $dateFrom . ' - ' . $dateTo), ';');
    $sumPlannedCsv = floatval(isset($summary['revenue_planned']) ? $summary['revenue_planned'] : 0);
    fputcsv($out, array('Выручка факт', 'Выручка план тип', 'Себестоимость', 'Валовая прибыль', 'Маржа %', 'Продано метров', 'Операций'), ';');
    fputcsv($out, array(
        number_format($sumRevenue, 2, '.', ''),
        number_format($sumPlannedCsv, 2, '.', ''),
        number_format($sumCost, 2, '.', ''),
        number_format($sumProfit, 2, '.', ''),
        number_format($sumMargin, 2, '.', ''),
        number_format(floatval(isset($summary['qty']) ? $summary['qty'] : 0), 2, '.', ''),
        intval(isset($summary['orders']) ? $summary['orders'] : 0)
    ), ';');
    fputcsv($out, array(), ';');
    fputcsv($out, array('Товар', 'Продано, м', 'Выручка факт', 'Выручка план тип', 'Себестоимость', 'Прибыль', 'Маржа %'), ';');
    foreach ($topRows as $r) {
        $revenue = floatval(isset($r['revenue']) ? $r['revenue'] : 0);
        $revPlan = floatval(isset($r['revenue_planned']) ? $r['revenue_planned'] : 0);
        $profit = floatval(isset($r['profit']) ? $r['profit'] : 0);
        $margin = $revenue > 0 ? ($profit / $revenue) * 100 : 0;
        fputcsv($out, array(
            isset($r['name']) ? $r['name'] : '',
            number_format(floatval(isset($r['qty']) ? $r['qty'] : 0), 2, '.', ''),
            number_format($revenue, 2, '.', ''),
            number_format($revPlan, 2, '.', ''),
            number_format(floatval(isset($r['cost']) ? $r['cost'] : 0), 2, '.', ''),
            number_format($profit, 2, '.', ''),
            number_format($margin, 2, '.', '')
        ), ';');
    }
    fclose($out);
    exit;
}

$page_title = 'Дашборд руководителя';
require 'includes/header.php';

$sumRevenue = floatval(isset($summary['revenue']) ? $summary['revenue'] : 0);
$sumRevenuePlanned = floatval(isset($summary['revenue_planned']) ? $summary['revenue_planned'] : 0);
$sumCost = floatval(isset($summary['cost']) ? $summary['cost'] : 0);
$sumProfit = floatval(isset($summary['profit']) ? $summary['profit'] : 0);
$sumMargin = $sumRevenue > 0 ? ($sumProfit / $sumRevenue) * 100 : 0;
?>
<main class="container">
    <h2>📈 Дашборд руководителя</h2>
    <p class="text-muted">
        Ключевые метрики бизнеса: продаваемость, прибыльность, маржа и динамика.
        Детальные операционные реестры доступны во вкладке <a href="report_day.php">Опер. отчеты</a>.
        Для строк из Битрикс24 выручка, прибыль и маржа считаются от <strong>фактической</strong> суммы сделки (со скидкой).
        Колонка «план тип» — сумма по тарифу без учёта скидки, для контроля скидок.
    </p>

    <div class="card">
        <h3>Фильтр периода</h3>
        <form method="GET" class="form-row">
            <div class="form-group">
                <label>Дата от</label>
                <input type="date" name="from" value="<?= htmlspecialchars($dateFrom) ?>">
            </div>
            <div class="form-group">
                <label>Дата до</label>
                <input type="date" name="to" value="<?= htmlspecialchars($dateTo) ?>">
            </div>
            <div class="form-group" style="display:flex;align-items:flex-end;gap:8px;">
                <button type="submit" class="btn btn-primary btn-sm">Показать</button>
                <a class="btn btn-light btn-sm" href="manager_dashboard.php">Сброс</a>
                <a class="btn btn-light btn-sm" href="manager_dashboard.php?from=<?= urlencode($dateFrom) ?>&to=<?= urlencode($dateTo) ?>&export=csv">Экспорт CSV</a>
            </div>
        </form>
    </div>

    <div class="stats-grid">
        <div class="stat-card"><div class="stat-number"><?= number_format($sumRevenue, 0, '.', ' ') ?></div><div>Выручка факт, KGS</div></div>
        <div class="stat-card"><div class="stat-number"><?= number_format($sumRevenuePlanned, 0, '.', ' ') ?></div><div>По типу (план), KGS</div></div>
        <div class="stat-card"><div class="stat-number"><?= number_format($sumCost, 0, '.', ' ') ?></div><div>Себестоимость, KGS</div></div>
        <div class="stat-card"><div class="stat-number"><?= number_format($sumProfit, 0, '.', ' ') ?></div><div>Валовая прибыль, KGS</div></div>
        <div class="stat-card"><div class="stat-number"><?= number_format($sumMargin, 2, '.', ' ') ?>%</div><div>Маржа</div></div>
        <div class="stat-card"><div class="stat-number"><?= number_format(floatval(isset($summary['qty']) ? $summary['qty'] : 0), 1, '.', ' ') ?></div><div>Продано метров</div></div>
        <div class="stat-card"><div class="stat-number"><?= intval(isset($summary['orders']) ? $summary['orders'] : 0) ?></div><div>Операций</div></div>
    </div>

    <div class="card">
        <h3>Динамика показателей</h3>
        <div style="width:100%;overflow:auto;">
            <canvas id="mgrTrendChart" height="120"></canvas>
        </div>
    </div>

    <div class="card">
        <h3>Товарная аналитика (сортируемая таблица)</h3>
        <p class="text-muted">
            Важно для бизнеса: объем продаж, выручка, прибыль, маржа, товар с максимальной продаваемостью и товар с лучшей рентабельностью.
        </p>
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:8px;">
            <button type="button" class="btn btn-light btn-sm js-sort-preset" data-sort="qty" data-dir="desc">Самый продаваемый</button>
            <button type="button" class="btn btn-light btn-sm js-sort-preset" data-sort="profit" data-dir="desc">Самый прибыльный</button>
            <button type="button" class="btn btn-light btn-sm js-sort-preset" data-sort="margin" data-dir="desc">Самый маржинальный</button>
            <button type="button" class="btn btn-light btn-sm js-sort-preset" data-sort="profit" data-dir="asc">Убыточные сверху</button>
        </div>
        <div class="table-responsive">
            <table class="table" id="manager-products-table">
                <thead>
                    <tr>
                        <th data-sort="name">Товар ↕</th>
                        <th data-sort="qty">Продано, м ↕</th>
                        <th data-sort="revenue">Выручка факт ↕</th>
                        <th>План тип</th>
                        <th data-sort="cost">Себестоимость ↕</th>
                        <th data-sort="profit">Прибыль ↕</th>
                        <th data-sort="margin">Маржа % ↕</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($topRows as $r): ?>
                        <?php
                            $revenue = floatval(isset($r['revenue']) ? $r['revenue'] : 0);
                            $revenuePlan = floatval(isset($r['revenue_planned']) ? $r['revenue_planned'] : 0);
                            $profit = floatval(isset($r['profit']) ? $r['profit'] : 0);
                            $margin = $revenue > 0 ? ($profit / $revenue) * 100 : 0;
                        ?>
                        <tr
                            data-name="<?= htmlspecialchars(mb_strtolower((string)(isset($r['name']) ? $r['name'] : ''), 'UTF-8')) ?>"
                            data-qty="<?= htmlspecialchars(number_format(floatval(isset($r['qty']) ? $r['qty'] : 0), 6, '.', '')) ?>"
                            data-revenue="<?= htmlspecialchars(number_format($revenue, 6, '.', '')) ?>"
                            data-cost="<?= htmlspecialchars(number_format(floatval(isset($r['cost']) ? $r['cost'] : 0), 6, '.', '')) ?>"
                            data-profit="<?= htmlspecialchars(number_format($profit, 6, '.', '')) ?>"
                            data-margin="<?= htmlspecialchars(number_format($margin, 6, '.', '')) ?>"
                        >
                            <td><?= htmlspecialchars(isset($r['name']) ? $r['name'] : '') ?></td>
                            <td><?= number_format(floatval(isset($r['qty']) ? $r['qty'] : 0), 2, '.', ' ') ?></td>
                            <td><?= number_format($revenue, 2, '.', ' ') ?></td>
                            <td><?= number_format($revenuePlan, 2, '.', ' ') ?></td>
                            <td><?= number_format(floatval(isset($r['cost']) ? $r['cost'] : 0), 2, '.', ' ') ?></td>
                            <td><?= number_format($profit, 2, '.', ' ') ?></td>
                            <td><?= number_format($margin, 2, '.', ' ') ?>%</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <h3>Топ-10 убыточных/низкомаржинальных</h3>
        <table class="table">
            <tr><th>Товар</th><th>Продано, м</th><th>Прибыль</th><th>Маржа %</th></tr>
            <?php foreach ($bottomRows as $b): ?>
                <?php
                    $revenue = floatval(isset($b['revenue']) ? $b['revenue'] : 0);
                    $profit = floatval(isset($b['profit']) ? $b['profit'] : 0);
                    $margin = $revenue > 0 ? ($profit / $revenue) * 100 : 0;
                ?>
                <tr>
                    <td><?= htmlspecialchars(isset($b['name']) ? $b['name'] : '') ?></td>
                    <td><?= number_format(floatval(isset($b['qty']) ? $b['qty'] : 0), 2, '.', ' ') ?></td>
                    <td><?= number_format($profit, 2, '.', ' ') ?></td>
                    <td><?= number_format($margin, 2, '.', ' ') ?>%</td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function () {
    var rows = <?= json_encode($daily, JSON_UNESCAPED_UNICODE) ?>;
    var labels = [], revenue = [], planned = [], cost = [], profit = [];
    for (var i = 0; i < rows.length; i++) {
        labels.push(rows[i].d || '');
        revenue.push(parseFloat(rows[i].revenue || 0));
        planned.push(parseFloat(rows[i].revenue_planned || 0));
        cost.push(parseFloat(rows[i].cost || 0));
        profit.push(parseFloat(rows[i].profit || 0));
    }
    var canvas = document.getElementById('mgrTrendChart');
    if (canvas && typeof Chart !== 'undefined') {
        new Chart(canvas, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    { label: 'Выручка факт', data: revenue, borderColor: '#2E86DE', tension: 0.2 },
                    { label: 'Выручка план тип', data: planned, borderColor: '#9B59B6', tension: 0.2, borderDash: [4, 4] },
                    { label: 'Себестоимость', data: cost, borderColor: '#F39C12', tension: 0.2 },
                    { label: 'Прибыль', data: profit, borderColor: '#27AE60', tension: 0.2 }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom' } },
                interaction: { mode: 'index', intersect: false },
                scales: { y: { beginAtZero: true } }
            }
        });
    }
})();

(function () {
    var table = document.getElementById('manager-products-table');
    if (!table) return;
    var tbody = table.querySelector('tbody');
    var headers = table.querySelectorAll('thead th[data-sort]');

    function sortTable(field, direction) {
        var rows = Array.prototype.slice.call(tbody.querySelectorAll('tr'));
        rows.sort(function (a, b) {
            var av = a.getAttribute('data-' + field) || '';
            var bv = b.getAttribute('data-' + field) || '';
            var cmp = 0;
            if (field === 'name') {
                cmp = av.localeCompare(bv);
            } else {
                cmp = parseFloat(av || '0') - parseFloat(bv || '0');
            }
            return direction === 'asc' ? cmp : -cmp;
        });
        for (var i = 0; i < rows.length; i++) {
            tbody.appendChild(rows[i]);
        }
    }

    for (var i = 0; i < headers.length; i++) {
        (function (th) {
            th.setAttribute('data-dir', 'desc');
            th.style.cursor = 'pointer';
            th.addEventListener('click', function () {
                var field = th.getAttribute('data-sort');
                var dir = th.getAttribute('data-dir') === 'desc' ? 'asc' : 'desc';
                th.setAttribute('data-dir', dir);
                sortTable(field, dir);
            });
        })(headers[i]);
    }

    var presets = document.querySelectorAll('.js-sort-preset');
    for (var j = 0; j < presets.length; j++) {
        presets[j].addEventListener('click', function () {
            sortTable(this.getAttribute('data-sort'), this.getAttribute('data-dir'));
        });
    }
})();
</script>

<?php require 'includes/footer.php'; ?>

