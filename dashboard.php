<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: text/html; charset=utf-8');

require 'db.php';
$db = getDB();
require_once __DIR__ . '/functions/stock_movements.php';
require_once __DIR__ . '/functions/app_settings.php';
require_once __DIR__ . '/api/bitrix/send.php';

$dashboardMessage = '';
$dashboardError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    if ($action === 'save_usd_rate') {
        $usdRate = floatval(isset($_POST['usd_rate']) ? $_POST['usd_rate'] : 0);
        if ($usdRate <= 0) {
            $dashboardError = 'Курс USD должен быть больше 0.';
        } else {
            setAppSetting($db, 'usd_rate', number_format($usdRate, 4, '.', ''));
            $dashboardMessage = 'Курс USD сохранен.';
        }
    }

    if ($action === 'receipt_quick') {
        $productId = intval(isset($_POST['product_id']) ? $_POST['product_id'] : 0);
        $newProductName = trim(isset($_POST['new_product_name']) ? $_POST['new_product_name'] : '');
        $quantity = intval(isset($_POST['quantity']) ? $_POST['quantity'] : 0);
        $rollLength = floatval(isset($_POST['roll_length']) ? $_POST['roll_length'] : 30);
        $minFull = floatval(isset($_POST['min_full']) ? $_POST['min_full'] : 0.5);
        $priceUsd = floatval(isset($_POST['price_usd']) ? $_POST['price_usd'] : 0);
        $usdRate = floatval(isset($_POST['usd_rate']) ? $_POST['usd_rate'] : 0);
        $purchasePriceKzt = floatval(isset($_POST['purchase_price_kzt']) ? $_POST['purchase_price_kzt'] : 0);

        if ($usdRate <= 0 || $quantity <= 0 || $rollLength <= 0) {
            $dashboardError = 'Проверьте курс USD, количество и длину рулона.';
        } else {
            if ($purchasePriceKzt <= 0 && $priceUsd > 0) {
                $purchasePriceKzt = $priceUsd * $usdRate;
            }

            try {
                $db->beginTransaction();

                if ($productId <= 0) {
                    if ($newProductName === '') {
                        throw new Exception('Выберите товар или введите новое наименование.');
                    }
                    $findStmt = $db->prepare("SELECT id FROM products WHERE name = ? ORDER BY id ASC LIMIT 1");
                    $findStmt->execute([$newProductName]);
                    $existing = $findStmt->fetch(PDO::FETCH_ASSOC);
                    if ($existing) {
                        $productId = intval($existing['id']);
                    } else {
                        $insProduct = $db->prepare("
                            INSERT INTO products (name, roll_length, purchase_price, price_per_meter)
                            VALUES (?, ?, ?, 0)
                        ");
                        $insProduct->execute([$newProductName, $rollLength, $purchasePriceKzt]);
                        $productId = intval($db->lastInsertId());
                    }
                }

                $productStmt = $db->prepare("SELECT * FROM products WHERE id = ?");
                $productStmt->execute([$productId]);
                $product = $productStmt->fetch(PDO::FETCH_ASSOC);
                if (!$product) {
                    throw new Exception('Товар не найден.');
                }

                $pricePerMeter = 0;
                if ($purchasePriceKzt > 0 && $rollLength > 0) {
                    $pricePerMeter = $purchasePriceKzt / $rollLength;
                }

                // Keep product prices актуальными для последующих операций.
                if ($purchasePriceKzt > 0) {
                    $updPrice = $db->prepare("UPDATE products SET purchase_price = ?, roll_length = ?, price_per_meter = ? WHERE id = ?");
                    $updPrice->execute([$purchasePriceKzt, $rollLength, $pricePerMeter, $productId]);
                }

                for ($i = 0; $i < $quantity; $i++) {
                    $insRoll = $db->prepare("
                        INSERT INTO rolls (product_id, original_length, current_length, min_full_length, status)
                        VALUES (?, ?, ?, ?, 'active')
                    ");
                    $insRoll->execute([$productId, $rollLength, $rollLength, $minFull]);
                    $rollId = intval($db->lastInsertId());

                    logAndSyncMovement($db, [
                        'product_id' => $productId,
                        'roll_id' => $rollId,
                        'movement_type' => 'receipt',
                        'quantity_m' => $rollLength,
                        'quantity_rolls' => 1,
                        'price_per_unit' => $purchasePriceKzt,
                        'total' => $purchasePriceKzt,
                        'comment' => 'Оприходование через виджет dashboard'
                    ]);
                }

                setAppSetting($db, 'usd_rate', number_format($usdRate, 4, '.', ''));

                // Ensure product exists in B24 for two-way sync.
                $productSyncStmt = $db->prepare("
                    SELECT id, name, b24_product_id, price_per_meter
                    FROM products
                    WHERE id = ?
                    LIMIT 1
                ");
                $productSyncStmt->execute([$productId]);
                $syncProduct = $productSyncStmt->fetch(PDO::FETCH_ASSOC);
                if ($syncProduct) {
                    $priceForB24 = floatval($syncProduct['price_per_meter']) > 0
                        ? floatval($syncProduct['price_per_meter'])
                        : floatval($pricePerMeter);

                    if (intval($syncProduct['b24_product_id']) > 0) {
                        $updatePayload = [
                            'id' => intval($syncProduct['b24_product_id']),
                            'fields' => [
                                'NAME' => $syncProduct['name']
                            ]
                        ];
                        if ($priceForB24 > 0) {
                            $updatePayload['fields']['PRICE'] = $priceForB24;
                        }
                        sendToBitrix('crm.product.update', $updatePayload);
                    } else {
                        $createPayload = [
                            'fields' => [
                                'NAME' => $syncProduct['name']
                            ]
                        ];
                        if ($priceForB24 > 0) {
                            $createPayload['fields']['PRICE'] = $priceForB24;
                        }
                        $createResp = sendToBitrix('crm.product.add', $createPayload);
                        if (is_array($createResp) && !isset($createResp['error']) && isset($createResp['result'])) {
                            $newB24Id = intval($createResp['result']);
                            if ($newB24Id > 0) {
                                $bindStmt = $db->prepare("UPDATE products SET b24_product_id = ? WHERE id = ?");
                                $bindStmt->execute([$newB24Id, $productId]);
                            }
                        } elseif (is_array($createResp) && isset($createResp['error'])) {
                            $dashboardError = 'Товар оприходован локально, но не создан в Б24: '
                                . (isset($createResp['error_description']) ? $createResp['error_description'] : $createResp['error']);
                        }
                    }
                }

                $db->commit();
                $dashboardMessage = 'Оприходование выполнено: добавлено рулонов ' . $quantity . '.';
            } catch (Exception $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                $dashboardError = $e->getMessage();
            }
        }
    }
}

// Простая статистика без сложных запросов
try {
    $productsCount = $db->query("SELECT COUNT(*) as count FROM products")->fetch(PDO::FETCH_ASSOC)['count'];
    $rollsCount = $db->query("SELECT COUNT(*) as count FROM rolls WHERE status = 'active'")->fetch(PDO::FETCH_ASSOC)['count'];
} catch (Exception $e) {
    $productsCount = 0;
    $rollsCount = 0;
}

$monthFrom = date('Y-m-01 00:00:00');
$monthTo = date('Y-m-t 23:59:59');
$financeSummary = array('revenue' => 0, 'cost' => 0, 'profit' => 0);
$financeByProduct = array();
try {
    $financeStmt = $db->prepare("
        SELECT
            SUM(total) as revenue,
            SUM(COALESCE(cost_fact,0)) as cost,
            SUM(COALESCE(gross_profit,0)) as profit
        FROM sales
        WHERE created_at BETWEEN ? AND ?
    ");
    $financeStmt->execute(array($monthFrom, $monthTo));
    $financeSummary = $financeStmt->fetch(PDO::FETCH_ASSOC);

    $financeProductStmt = $db->prepare("
        SELECT
            p.name,
            SUM(s.total) as revenue,
            SUM(COALESCE(s.cost_fact,0)) as cost,
            SUM(COALESCE(s.gross_profit,0)) as profit,
            SUM(s.quantity) as qty
        FROM sales s
        LEFT JOIN products p ON p.id = s.product_id
        WHERE s.created_at BETWEEN ? AND ?
        GROUP BY s.product_id, p.name
        ORDER BY profit DESC
        LIMIT 15
    ");
    $financeProductStmt->execute(array($monthFrom, $monthTo));
    $financeByProduct = $financeProductStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $financeSummary = array('revenue' => 0, 'cost' => 0, 'profit' => 0);
    $financeByProduct = array();
}

$productsForReceipt = $db->query("SELECT id, name, roll_length, purchase_price FROM products ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$usdRateValue = floatval(getAppSetting($db, 'usd_rate', '500'));
 
$page_title = 'Главная';
require 'includes/header.php';
?>

<main class="container">
        <?php if ($dashboardMessage): ?>
            <div class="alert alert-success"><?= htmlspecialchars($dashboardMessage) ?></div>
        <?php endif; ?>
        <?php if ($dashboardError): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($dashboardError) ?></div>
        <?php endif; ?>

        <!-- Статистика -->
        <div class="card">
            <h3>📊 Статистика склада</h3>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?= $productsCount ?></div>
                    <div>Товаров в базе</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $rollsCount ?></div>
                    <div>Активных рулонов</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">✅</div>
                    <div>Система работает</div>
                </div>
            </div>
        </div>

        <div class="card">
            <h3>💼 Дашборд руководителя (текущий месяц)</h3>
            <?php
                $sumRevenue = floatval(isset($financeSummary['revenue']) ? $financeSummary['revenue'] : 0);
                $sumCost = floatval(isset($financeSummary['cost']) ? $financeSummary['cost'] : 0);
                $sumProfit = floatval(isset($financeSummary['profit']) ? $financeSummary['profit'] : 0);
                $sumMargin = $sumRevenue > 0 ? ($sumProfit / $sumRevenue) * 100 : 0;
            ?>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?= number_format($sumRevenue, 0, '.', ' ') ?></div>
                    <div>Выручка, KGS</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= number_format($sumCost, 0, '.', ' ') ?></div>
                    <div>Себестоимость, KGS</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= number_format($sumProfit, 0, '.', ' ') ?></div>
                    <div>Валовая прибыль, KGS</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= number_format($sumMargin, 2, '.', ' ') ?>%</div>
                    <div>Маржа</div>
                </div>
            </div>

            <h4>Топ товаров по прибыли (таблица)</h4>
            <?php if (empty($financeByProduct)): ?>
                <p>Пока нет продаж за текущий месяц.</p>
            <?php else: ?>
                <table class="table">
                    <tr>
                        <th>Товар</th>
                        <th>Кол-во</th>
                        <th>Выручка</th>
                        <th>Себестоимость</th>
                        <th>Прибыль</th>
                        <th>Маржа %</th>
                    </tr>
                    <?php foreach ($financeByProduct as $fp): ?>
                        <?php
                            $pRevenue = floatval(isset($fp['revenue']) ? $fp['revenue'] : 0);
                            $pProfit = floatval(isset($fp['profit']) ? $fp['profit'] : 0);
                            $pMargin = $pRevenue > 0 ? ($pProfit / $pRevenue) * 100 : 0;
                        ?>
                        <tr>
                            <td><?= htmlspecialchars(isset($fp['name']) ? $fp['name'] : '') ?></td>
                            <td><?= number_format(floatval(isset($fp['qty']) ? $fp['qty'] : 0), 2, '.', ' ') ?></td>
                            <td><?= number_format($pRevenue, 2, '.', ' ') ?></td>
                            <td><?= number_format(floatval(isset($fp['cost']) ? $fp['cost'] : 0), 2, '.', ' ') ?></td>
                            <td><?= number_format($pProfit, 2, '.', ' ') ?></td>
                            <td><?= number_format($pMargin, 2, '.', ' ') ?>%</td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <a href="report_day.php" class="btn btn-light">Отчет за период</a>
                <a href="report_month.php" class="btn btn-light">Отчет за месяц</a>
                <a href="report_all.php" class="btn btn-light">Отчет за всё время</a>
            </div>
        </div>

        <!-- Основные действия -->
        <div class="card">
            <h3>⚡ Основные действия</h3>
            <div class="actions-grid">
                <div class="action-card">
                    <h4>🧰 Место кладовщика</h4>
                    <p>Очередь заказов из Б24, резерв и отгрузка</p>
                    <a href="warehouse_orders.php" class="btn btn-success">Открыть</a>
                </div>

                <div class="action-card">
                    <h4>🧾 Складские операции</h4>
                    <p>Приход, списание и реализация в одном месте</p>
                    <a href="stock_operations.php" class="btn">Открыть</a>
                </div>
                
                <div class="action-card">
                    <h4>🏬 Склад</h4>
                    <p>Просмотр текущих рулонов и фильтрация остатков</p>
                    <a href="warehouse.php" class="btn">Открыть</a>
                </div>

                <div class="action-card">
                    <h4>📦 Каталог товаров</h4>
                    <p>Управление товарами, ценами и синком в Б24</p>
                    <a href="products.php" class="btn">Открыть</a>
                </div>
                
                <div class="action-card">
                    <h4>💰 Продажи из Б24</h4>
                    <p>Фактические продажи по сделкам Б24</p>
                    <a href="sell.php" class="btn">Открыть</a>
                </div>
                
                <div class="action-card">
                    <h4>🔄 Б24 (ручные операции)</h4>
                    <p>Ручной резерв, подтверждение и повтор синка</p>
                    <a href="b24_sales.php" class="btn">Открыть</a>
                </div>
            </div>
        </div>

        <!-- Синхронизация -->
        <div class="card">
            <h3>🔄 Синхронизация с Битрикс24</h3>
            <div class="actions-grid">
                <div class="action-card">
                    <h4>📤 Остатки в Б24</h4>
                    <p>Отправить остатки на склад Б24</p>
                    <a href="api/bitrix/sync_stock.php?push=1" class="btn btn-warning" target="_blank">Синхронизировать</a>
                </div>
                
                <div class="action-card">
                    <h4>💰 Цены в Б24</h4>
                    <p>Отправить цены в Б24</p>
                    <a href="api/sync_prices.php?action=to_b24" class="btn btn-warning" target="_blank">Синхронизировать</a>
                </div>
                
                <div class="action-card">
                    <h4>📥 Товары из Б24</h4>
                    <p>Импортировать товары из Б24</p>
                    <a href="api/bitrix/import_products.php" class="btn btn-success" target="_blank">Импортировать</a>
                </div>
            </div>
        </div>

        <!-- Информация -->
        <div class="card">
            <h3>ℹ️ Информация</h3>
            <p><strong>Статус системы:</strong> ✅ Работает в штатном режиме</p>
            <p><strong>База данных:</strong> Подключена</p>
            <p><strong>Интеграция Б24:</strong> Настроена</p>
            <p><strong>Последнее обновление:</strong> <?= date('d.m.Y H:i') ?></p>
        </div>
</main>

<?php require 'includes/footer.php'; ?>
