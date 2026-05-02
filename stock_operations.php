<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: text/html; charset=utf-8');
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require 'db.php';
require_once __DIR__ . '/functions/stock_movements.php';
require_once __DIR__ . '/api/bitrix/send.php';
require_once __DIR__ . '/functions/app_settings.php';
$db = getDB();

require_once __DIR__ . '/includes/stock_operations_core.php';
require_once __DIR__ . '/functions/integration_sync_control.php';

$successMsg = '';
$errorMsg = '';
ensureStockOperationTables($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'retry_b24_sync') {
    if (!validateFormToken('retry_b24_sync', isset($_POST['form_token']) ? $_POST['form_token'] : '')) {
        $errorMsg = 'Сессия формы устарела. Обновите страницу и повторите.';
    } else {
    $docId = intval(isset($_POST['doc_id']) ? $_POST['doc_id'] : 0);
    if ($docId <= 0) {
        $errorMsg = 'Некорректный документ для повторного синка.';
    } else {
        try {
            $docStmt = $db->prepare("
                SELECT id, operation_type, doc_number, comment_text, supplier, b24_sync_status, b24_document_id, b24_sync_response
                FROM stock_operation_docs
                WHERE id = ?
                LIMIT 1
            ");
            $docStmt->execute(array($docId));
            $doc = $docStmt->fetch(PDO::FETCH_ASSOC);
            if (!$doc) {
                throw new Exception('Документ не найден.');
            }
            if (!in_array($doc['operation_type'], array('receipt', 'writeoff'), true)) {
                throw new Exception('Повторный синк доступен только для прихода и списания.');
            }

            $b24ResolvedId = stockOperationsResolveB24DocumentIdForRetry($db, $doc);

            if (intval(isset($doc['b24_document_id']) ? $doc['b24_document_id'] : 0) > 0 && (string)$doc['b24_sync_status'] === 'sent') {
                throw new Exception('Документ уже синхронизирован в Б24: #' . intval($doc['b24_document_id']));
            }

            $linesStmt = $db->prepare("
                SELECT product_id, qty_rolls, quantity_m, roll_length, price_per_roll, delivery_price_per_roll, line_total
                FROM stock_operation_lines
                WHERE doc_id = ?
                ORDER BY id ASC
            ");
            $linesStmt->execute(array($docId));
            $lineRows = $linesStmt->fetchAll(PDO::FETCH_ASSOC);
            if (empty($lineRows)) {
                throw new Exception('У документа нет строк для синка.');
            }

            if ($b24ResolvedId > 0) {
                $syncResult = addLinesAndConductExistingB24Document(
                    $db,
                    $b24ResolvedId,
                    (string)$doc['operation_type'],
                    $lineRows,
                    isset($doc['supplier']) ? (string)$doc['supplier'] : ''
                );
                if (is_array($syncResult)) {
                    $syncResult['retry_reused_b24_document_id'] = $b24ResolvedId;
                    if (intval(isset($doc['b24_document_id']) ? $doc['b24_document_id'] : 0) <= 0) {
                        $fromJson = stockOperationsExtractB24DocumentIdFromSavedSyncJson(
                            isset($doc['b24_sync_response']) ? (string)$doc['b24_sync_response'] : ''
                        );
                        $syncResult['retry_b24_document_id_source'] =
                            (intval($fromJson) === $b24ResolvedId) ? 'stored_json' : 'doc_number_lookup';
                    }
                }
            } else {
                $syncResult = syncOperationDocumentToBitrix(
                    $db,
                    $docId,
                    (string)$doc['operation_type'],
                    (string)$doc['doc_number'],
                    (string)$doc['comment_text'],
                    $lineRows,
                    isset($doc['supplier']) ? (string)$doc['supplier'] : ''
                );
            }
            $syncStatus = resolveB24SyncStatus($syncResult);
            $db->prepare("UPDATE stock_operation_docs SET b24_document_id = ?, b24_sync_status = ?, b24_sync_response = ? WHERE id = ?")
                ->execute(array(
                    isset($syncResult['b24_document_id']) ? intval($syncResult['b24_document_id']) : null,
                    $syncStatus,
                    json_encode($syncResult, JSON_UNESCAPED_UNICODE),
                    $docId
                ));

            if ($syncStatus === 'sent') {
                $successMsg = 'Повторный синк документа #' . $docId . ' выполнен. Б24 документ #' . intval($syncResult['b24_document_id']);
            } elseif ($syncStatus === 'partial') {
                $errorMsg = 'Документ #' . $docId . ' уже создан в Б24 (#' . intval($syncResult['b24_document_id']) . '), но есть ошибка в фиксации данных.';
            } else {
                $errorMsg = 'Повторный синк документа #' . $docId . ' завершился с ошибкой.';
            }
        } catch (Exception $e) {
            $errorMsg = $e->getMessage();
        }
    }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_doc') {
    if (!validateFormToken('delete_doc', isset($_POST['form_token']) ? $_POST['form_token'] : '')) {
        $errorMsg = 'Сессия формы устарела. Обновите страницу и повторите.';
    } else {
    $docId = intval(isset($_POST['doc_id']) ? $_POST['doc_id'] : 0);
    if ($docId <= 0) {
        $errorMsg = 'Некорректный документ для удаления.';
    } else {
        try {
            $db->beginTransaction();
            $docStmt = $db->prepare("SELECT id, operation_type FROM stock_operation_docs WHERE id = ? LIMIT 1");
            $docStmt->execute(array($docId));
            $doc = $docStmt->fetch(PDO::FETCH_ASSOC);
            if (!$doc) {
                throw new Exception('Документ не найден.');
            }

            $db->prepare("DELETE FROM stock_operation_lines WHERE doc_id = ?")->execute(array($docId));
            $db->prepare("DELETE FROM stock_operation_docs WHERE id = ?")->execute(array($docId));
            $db->commit();
            $successMsg = 'Документ #' . $docId . ' удален.';
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $errorMsg = $e->getMessage();
        }
    }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_receipt') {
    if (!validateFormToken('create_receipt', isset($_POST['form_token']) ? $_POST['form_token'] : '')) {
        $errorMsg = 'Сессия формы устарела. Обновите страницу и повторите.';
    } else {
        $docNumber = trim(isset($_POST['doc_number']) ? $_POST['doc_number'] : '');
        $supplier = trim(isset($_POST['supplier']) ? $_POST['supplier'] : '');
        $commentText = trim(isset($_POST['comment_text']) ? $_POST['comment_text'] : '');
        $receiptCurrency = strtoupper(trim(isset($_POST['receipt_currency']) ? $_POST['receipt_currency'] : 'USD'));
        if (!in_array($receiptCurrency, array('USD', 'KGS'), true)) {
            $receiptCurrency = 'USD';
        }
        $minFull = floatval(isset($_POST['min_full']) ? $_POST['min_full'] : 0.5);
        $lineProductId = isset($_POST['line_product_id']) && is_array($_POST['line_product_id']) ? $_POST['line_product_id'] : array();
        $lineProductName = isset($_POST['line_product_name']) && is_array($_POST['line_product_name']) ? $_POST['line_product_name'] : array();
        $lineQtyRolls = isset($_POST['line_qty_rolls']) && is_array($_POST['line_qty_rolls']) ? $_POST['line_qty_rolls'] : array();
        $lineRollLength = isset($_POST['line_roll_length']) && is_array($_POST['line_roll_length']) ? $_POST['line_roll_length'] : array();
        $linePriceUsd = isset($_POST['line_price_per_roll_usd']) && is_array($_POST['line_price_per_roll_usd']) ? $_POST['line_price_per_roll_usd'] : array();
        $lineDeliveryPriceUsd = isset($_POST['line_delivery_price_per_roll_usd']) && is_array($_POST['line_delivery_price_per_roll_usd']) ? $_POST['line_delivery_price_per_roll_usd'] : array();

        $payloadLines = array();
        for ($i = 0; $i < count($lineQtyRolls); $i++) {
            $payloadLines[] = array(
                'product_id' => intval(isset($lineProductId[$i]) ? $lineProductId[$i] : 0),
                'product_name' => isset($lineProductName[$i]) ? trim((string)$lineProductName[$i]) : '',
                'qty_rolls' => intval($lineQtyRolls[$i]),
                'roll_length' => floatval(isset($lineRollLength[$i]) ? $lineRollLength[$i] : 0),
                'purchase_per_roll' => floatval(isset($linePriceUsd[$i]) ? $linePriceUsd[$i] : 0),
                'delivery_per_roll' => floatval(isset($lineDeliveryPriceUsd[$i]) ? $lineDeliveryPriceUsd[$i] : 0),
            );
        }

        $receiptResult = stockOperationsProcessCreateReceiptPayload($db, array(
            'doc_number' => $docNumber,
            'supplier' => $supplier,
            'comment_text' => $commentText,
            'receipt_currency' => $receiptCurrency,
            'min_full' => $minFull,
            'lines' => $payloadLines,
            'local_only' => !empty($_POST['receipt_local_only']),
        ));

        if (!empty($receiptResult['ok'])) {
            $successMsg = isset($receiptResult['success_message']) ? $receiptResult['success_message'] : '';
            if (!empty($receiptResult['error_message'])) {
                $errorMsg = $receiptResult['error_message'];
            }
        } else {
            $errorMsg = isset($receiptResult['error_message']) ? $receiptResult['error_message'] : 'Ошибка прихода.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_writeoff') {
    if (!validateFormToken('create_writeoff', isset($_POST['form_token']) ? $_POST['form_token'] : '')) {
        $errorMsg = 'Сессия формы устарела. Обновите страницу и повторите.';
    } else {
    $docNumber = trim(isset($_POST['writeoff_doc_number']) ? $_POST['writeoff_doc_number'] : '');
    $commentText = trim(isset($_POST['writeoff_comment_text']) ? $_POST['writeoff_comment_text'] : '');
    $lineRollId = isset($_POST['writeoff_roll_id']) && is_array($_POST['writeoff_roll_id']) ? $_POST['writeoff_roll_id'] : array();
    $lineMeters = isset($_POST['writeoff_meters']) && is_array($_POST['writeoff_meters']) ? $_POST['writeoff_meters'] : array();
    $lineReason = isset($_POST['writeoff_reason']) && is_array($_POST['writeoff_reason']) ? $_POST['writeoff_reason'] : array();

    try {
        $db->beginTransaction();
        $insDoc = $db->prepare("
            INSERT INTO stock_operation_docs (operation_type, doc_number, supplier, comment_text, total_amount, status)
            VALUES ('writeoff', ?, '', ?, 0, 'posted')
        ");
        $insDoc->execute(array($docNumber, $commentText));
        $docId = intval($db->lastInsertId());

        $insLine = $db->prepare("
            INSERT INTO stock_operation_lines
            (doc_id, product_id, product_name, qty_rolls, roll_length, quantity_m, price_per_roll, line_total)
            VALUES (?, ?, ?, 0, 0, ?, 0, 0)
        ");

        $addedAny = false;
        for ($i = 0; $i < count($lineRollId); $i++) {
            $rollId = intval(isset($lineRollId[$i]) ? $lineRollId[$i] : 0);
            $meters = floatval(isset($lineMeters[$i]) ? $lineMeters[$i] : 0);
            $reason = isset($lineReason[$i]) ? trim($lineReason[$i]) : '';
            if ($rollId <= 0 || $meters <= 0) {
                continue;
            }

            $piece = consumeWriteoffFromRoll($db, $rollId, $meters);
            $productId = intval($piece['product_id']);
            $productName = (string)$piece['product_name'];

            logAndSyncMovement($db, array(
                'product_id' => $productId,
                'roll_id' => intval($piece['roll_id']),
                'movement_type' => 'writeoff',
                'quantity_m' => floatval($piece['meters']),
                'quantity_rolls' => 0,
                'price_per_unit' => 0,
                'total' => 0,
                'comment' => 'Списание через документ #' . $docId . ($reason !== '' ? ' | ' . $reason : '')
            ));

            $db->prepare("
                INSERT INTO sales (product_id, type, quantity, price_per_unit, total, deal_id, deal_url, cost_fact, gross_profit, gross_margin_percent)
                VALUES (?, 'writeoff', ?, 0, 0, NULL, NULL, 0, 0, 0)
            ")->execute(array($productId, $meters));

            $insLine->execute(array(
                $docId,
                $productId,
                $productName,
                $meters
            ));
            $addedAny = true;
        }

        if (!$addedAny) {
            throw new Exception('Добавьте хотя бы одну корректную строку списания.');
        }

        $db->commit();
        $lineRowsForSync = $db->query("SELECT product_id, qty_rolls, quantity_m, roll_length, price_per_roll, delivery_price_per_roll, line_total FROM stock_operation_lines WHERE doc_id=" . intval($docId))->fetchAll(PDO::FETCH_ASSOC);
        $syncResult = syncOperationDocumentToBitrix($db, $docId, 'writeoff', $docNumber, $commentText, $lineRowsForSync, '');
        $syncResult = tryFinalizePartialDocument($db, 'writeoff', $syncResult, $lineRowsForSync, '');
        $syncStatus = resolveB24SyncStatus($syncResult);
        $db->prepare("UPDATE stock_operation_docs SET b24_document_id = ?, b24_sync_status = ?, b24_sync_response = ? WHERE id = ?")
            ->execute(array(
                isset($syncResult['b24_document_id']) ? intval($syncResult['b24_document_id']) : null,
                $syncStatus,
                json_encode($syncResult, JSON_UNESCAPED_UNICODE),
                $docId
            ));
        $successMsg = 'Документ списания #' . $docId . ' проведен.';
        if ($syncStatus === 'sent') {
            $successMsg .= ' | Б24 документ #' . intval($syncResult['b24_document_id']);
        } elseif ($syncStatus === 'partial') {
            $errorMsg = 'Списание создано в Б24 (#' . intval($syncResult['b24_document_id']) . '), но фиксация строк/проведение завершились с ошибкой.';
        } else {
            $errorMsg = 'Списание проведено локально, но синк в Б24 завершился с ошибкой.';
        }
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $errorMsg = $e->getMessage();
    }
    }
}

$receiptToken = ensureFormToken('create_receipt');
$writeoffToken = ensureFormToken('create_writeoff');
$deleteToken = ensureFormToken('delete_doc');
$retryToken = ensureFormToken('retry_b24_sync');

$products = $db->query("SELECT id, name, roll_length, purchase_price, delivery_price FROM products ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$usdToKgsRate = getUsdToKgsRate($db);
$stockProducts = $db->query("
    SELECT
        p.id,
        p.name,
        ROUND(SUM(r.current_length), 2) as free_meters
    FROM rolls r
    JOIN products p ON p.id = r.product_id
    WHERE r.status NOT IN ('sold', 'written_off', 'waste')
      AND r.current_length > 0
      AND r.reserved = 0
    GROUP BY p.id, p.name
    HAVING SUM(r.current_length) > 0
    ORDER BY p.name ASC
")->fetchAll(PDO::FETCH_ASSOC);
$stockRolls = $db->query("
    SELECT
        r.id,
        r.product_id,
        p.name as product_name,
        r.current_length,
        r.status
    FROM rolls r
    JOIN products p ON p.id = r.product_id
    WHERE r.status NOT IN ('sold', 'written_off', 'waste')
      AND r.current_length > 0
      AND r.reserved = 0
    ORDER BY p.name ASC, r.current_length ASC, r.id ASC
")->fetchAll(PDO::FETCH_ASSOC);
$integrationSyncPaused = integrationAllSyncPaused($db);

$recentDocs = $db->query("
    SELECT id, operation_type, doc_number, supplier, total_amount, status, created_at, b24_document_id, b24_sync_status, b24_sync_response
    FROM stock_operation_docs
    ORDER BY id DESC
    LIMIT 20
")->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Складские операции';
require 'includes/header.php';
?>

<main class="container">
    <div class="card">
        <h2>🧾 Складские операции</h2>
        <p class="text-muted">
            Единая точка работы со складом: приход, списание, реализация и синхронизация с Б24.
        </p>
    </div>

    <div class="card">
        <h3>📥 Создать приход</h3>
        <?php if ($successMsg): ?><div class="alert alert-success"><?= htmlspecialchars($successMsg) ?></div><?php endif; ?>
        <?php if ($errorMsg): ?><div class="alert alert-danger"><?= htmlspecialchars($errorMsg) ?></div><?php endif; ?>

        <form method="POST" id="receipt-doc-form">
            <input type="hidden" name="action" value="create_receipt">
            <input type="hidden" name="form_token" value="<?= htmlspecialchars($receiptToken) ?>">
            <div class="form-row">
                <div class="form-group">
                    <label>Номер документа</label>
                    <input type="text" name="doc_number" placeholder="Например: ПР-2026-04-28">
                </div>
                <div class="form-group">
                    <label>Поставщик</label>
                    <input type="text" name="supplier" placeholder="Название поставщика">
                </div>
                <div class="form-group">
                    <label>Мин. остаток рулона (м)</label>
                    <input type="number" name="min_full" step="0.1" min="0" value="0.5">
                </div>
                <div class="form-group">
                    <label>Валюта оприходования</label>
                    <select name="receipt_currency" id="receipt-currency">
                        <option value="USD" selected>USD</option>
                        <option value="KGS">KGS</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>Комментарий</label>
                <input type="text" name="comment_text" placeholder="Примечание к приходу">
            </div>
            <p class="text-muted">Ввод цен в выбранной валюте. Отчет и синк в Б24 всегда в KGS. Курс USD из страницы «Настройки»: <strong><?= htmlspecialchars(number_format($usdToKgsRate, 2, '.', ' ')) ?></strong>.</p>
            <?php if ($integrationSyncPaused): ?>
                <div class="alert alert-warning">Синхронизация <strong>выключена</strong>. Приход с Б24 недоступен. Чтобы при этом создавать рулоны, в Центре интеграции должна быть включена опция <strong>«Разрешить локальный приход при паузе»</strong>, и здесь нужна галочка «Только локально». Иначе приход будет отклонён.</div>
            <?php endif; ?>
            <div class="form-group">
                <label style="display:flex;gap:10px;align-items:flex-start;cursor:pointer;">
                    <input type="checkbox" name="receipt_local_only" value="1" style="margin-top:4px;">
                    <span><strong>Только локально</strong> — приход в приложение без документа и без вызовов Битрикс24 (как <code>local_only</code> в JSON).</span>
                </label>
            </div>

            <div class="table-responsive receipt-table-wrap">
                <table class="table" id="receipt-lines">
                    <thead>
                        <tr>
                            <th>Товар (из базы)</th>
                            <th>Название (если новый)</th>
                            <th>Рулонов</th>
                            <th>Длина рулона (м)</th>
                            <th class="js-price-head">Закупка за рулон (USD)</th>
                            <th class="js-delivery-head">С доставкой за рулон (USD)</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>
                                <select name="line_product_id[]">
                                    <option value="0">-- Новый товар --</option>
                                    <?php foreach ($products as $p): ?>
                                        <option value="<?= intval($p['id']) ?>" data-roll-length="<?= htmlspecialchars((string)$p['roll_length']) ?>" data-price-kgs="<?= htmlspecialchars((string)floatval($p['purchase_price'])) ?>" data-delivery-price-kgs="<?= htmlspecialchars((string)floatval($p['delivery_price'])) ?>" data-price-usd="<?= htmlspecialchars((string)($usdToKgsRate > 0 ? (floatval($p['purchase_price']) / $usdToKgsRate) : 0)) ?>" data-delivery-price-usd="<?= htmlspecialchars((string)($usdToKgsRate > 0 ? (floatval($p['delivery_price']) / $usdToKgsRate) : 0)) ?>">
                                            <?= htmlspecialchars($p['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td><input type="text" name="line_product_name[]" placeholder="Если новый товар"></td>
                            <td><input type="number" name="line_qty_rolls[]" min="1" value="1"></td>
                            <td><input type="number" name="line_roll_length[]" min="0.1" step="0.1" value="30"></td>
                            <td><input type="number" name="line_price_per_roll_usd[]" min="0" step="0.01" value="0"></td>
                            <td><input type="number" name="line_delivery_price_per_roll_usd[]" min="0" step="0.01" value="0"></td>
                            <td><button type="button" class="btn btn-danger btn-sm remove-line">×</button></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div style="display:flex; gap:10px; flex-wrap:wrap;">
                <button type="button" class="btn btn-light" id="add-receipt-line">+ Добавить строку</button>
                <button type="submit" class="btn btn-success">Провести приход</button>
            </div>
        </form>
    </div>

    <div class="card">
        <h3>🗑️ Создать списание</h3>
        <form method="POST" id="writeoff-doc-form">
            <input type="hidden" name="action" value="create_writeoff">
            <input type="hidden" name="form_token" value="<?= htmlspecialchars($writeoffToken) ?>">
            <div class="form-row">
                <div class="form-group">
                    <label>Номер документа</label>
                    <input type="text" name="writeoff_doc_number" placeholder="Например: СП-2026-04-28">
                </div>
                <div class="form-group">
                    <label>Комментарий</label>
                    <input type="text" name="writeoff_comment_text" placeholder="Общее основание списания">
                </div>
            </div>

            <table class="table" id="writeoff-lines">
                <thead>
                    <tr>
                        <th>Рулон</th>
                        <th>К списанию (м)</th>
                        <th>Причина</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <input type="text" class="writeoff-roll-search" placeholder="Поиск: товар / рулон / метраж">
                            <select name="writeoff_roll_id[]" class="writeoff-roll-select">
                                <option value="0">-- Выберите рулон --</option>
                                <?php foreach ($stockRolls as $roll): ?>
                                    <option
                                        value="<?= intval($roll['id']) ?>"
                                        data-length="<?= htmlspecialchars(number_format(floatval($roll['current_length']), 2, '.', '')) ?>"
                                        data-label="<?= htmlspecialchars(strtolower((string)$roll['product_name'] . ' #' . intval($roll['id']) . ' ' . number_format(floatval($roll['current_length']), 2, '.', ' ') . ' м')) ?>"
                                    >
                                        #<?= intval($roll['id']) ?> | <?= htmlspecialchars($roll['product_name']) ?> | остаток: <?= number_format(floatval($roll['current_length']), 2, '.', ' ') ?> м
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td><input type="number" name="writeoff_meters[]" min="0.1" step="0.1" value="1"></td>
                        <td><input type="text" name="writeoff_reason[]" placeholder="Например: брак/повреждение"></td>
                        <td><button type="button" class="btn btn-danger btn-sm remove-writeoff-line">×</button></td>
                    </tr>
                </tbody>
            </table>
            <div style="display:flex; gap:10px; flex-wrap:wrap;">
                <button type="button" class="btn btn-light" id="add-writeoff-line">+ Добавить строку</button>
                <button type="submit" class="btn btn-warning">Провести списание</button>
            </div>
        </form>
    </div>

    <div class="card">
        <h3>Последние документы</h3>
        <?php if (empty($recentDocs)): ?>
            <p>Документов пока нет.</p>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Тип</th>
                        <th>№ документа</th>
                        <th>Поставщик</th>
                        <th>Сумма</th>
                        <th>Статус</th>
                        <th>Б24</th>
                        <th>Дата</th>
                        <th>Документ</th>
                        <th>Синк Б24</th>
                        <th>Ошибка</th>
                        <th>Удалить</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentDocs as $d): ?>
                        <tr>
                            <td><?= intval($d['id']) ?></td>
                            <td><?= htmlspecialchars(localizeOperationType(isset($d['operation_type']) ? $d['operation_type'] : '')) ?></td>
                            <td><?= htmlspecialchars((string)$d['doc_number']) ?></td>
                            <td><?= htmlspecialchars((string)$d['supplier']) ?></td>
                            <td><?= number_format(floatval($d['total_amount']), 2, '.', ' ') ?></td>
                            <td><?= htmlspecialchars((string)$d['status']) ?></td>
                            <td>
                                <?php if (!empty($d['b24_document_id'])): ?>
                                    #<?= intval($d['b24_document_id']) ?> (<?= htmlspecialchars((string)$d['b24_sync_status']) ?>)
                                <?php else: ?>
                                    <?= htmlspecialchars((string)$d['b24_sync_status']) ?>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars((string)$d['created_at']) ?></td>
                            <td>
                                <a href="stock_operation_print.php?id=<?= intval($d['id']) ?>" class="btn btn-light btn-sm" target="_blank">Открыть</a>
                            </td>
                            <td>
                                <?php if ((string)$d['b24_sync_status'] !== 'sent' && in_array((string)$d['operation_type'], array('receipt', 'writeoff'), true)): ?>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="retry_b24_sync">
                                        <input type="hidden" name="form_token" value="<?= htmlspecialchars($retryToken) ?>">
                                        <input type="hidden" name="doc_id" value="<?= intval($d['id']) ?>">
                                        <button type="submit" class="btn btn-warning btn-sm"><?= intval(isset($d['b24_document_id']) ? $d['b24_document_id'] : 0) > 0 ? 'Дофиксировать' : 'Повторить' ?></button>
                                    </form>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (in_array((string)$d['b24_sync_status'], array('error', 'partial'), true) && !empty($d['b24_sync_response'])): ?>
                                    <button type="button" class="btn btn-light btn-sm js-show-b24-error" data-error="<?= htmlspecialchars((string)$d['b24_sync_response']) ?>">Подробнее</button>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Удалить документ #<?= intval($d['id']) ?>?');">
                                    <input type="hidden" name="action" value="delete_doc">
                                    <input type="hidden" name="form_token" value="<?= htmlspecialchars($deleteToken) ?>">
                                    <input type="hidden" name="doc_id" value="<?= intval($d['id']) ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">Удалить</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div class="card">
        <h3>Технические моменты</h3>
        <p>Синк, настройки интеграции и скорость вынесены в отдельную вкладку.</p>
        <a href="sync_monitor.php" class="btn btn-secondary">⚙️ Открыть настройки</a>
    </div>
</main>

<script>
(function () {
    var addBtn = document.getElementById('add-receipt-line');
    var tableBody = document.querySelector('#receipt-lines tbody');
    var currencySelect = document.getElementById('receipt-currency');
    var priceHead = document.querySelector('.js-price-head');
    var deliveryHead = document.querySelector('.js-delivery-head');
    if (!addBtn || !tableBody) {
        return;
    }

    var getCurrentCurrency = function () {
        if (!currencySelect) {
            return 'USD';
        }
        return currencySelect.value === 'KGS' ? 'KGS' : 'USD';
    };

    var refreshReceiptHeadings = function () {
        var curr = getCurrentCurrency();
        if (priceHead) {
            priceHead.textContent = 'Закупка за рулон (' + curr + ')';
        }
        if (deliveryHead) {
            deliveryHead.textContent = 'С доставкой за рулон (' + curr + ')';
        }
    };

    var bindRow = function (row) {
        var select = row.querySelector('select[name="line_product_id[]"]');
        var lenInput = row.querySelector('input[name="line_roll_length[]"]');
        var priceInput = row.querySelector('input[name="line_price_per_roll_usd[]"]');
        var deliveryPriceInput = row.querySelector('input[name="line_delivery_price_per_roll_usd[]"]');
        var removeBtn = row.querySelector('.remove-line');

        if (select) {
            select.addEventListener('change', function () {
                var opt = select.options[select.selectedIndex];
                if (!opt || select.value === '0') {
                    return;
                }
                if (lenInput && opt.getAttribute('data-roll-length')) {
                    lenInput.value = opt.getAttribute('data-roll-length');
                }
                var curr = getCurrentCurrency();
                if (priceInput) {
                    var pAttr = curr === 'KGS' ? 'data-price-kgs' : 'data-price-usd';
                    var pVal = parseFloat(opt.getAttribute(pAttr));
                    priceInput.value = isNaN(pVal) ? '0' : pVal.toFixed(2);
                }
                if (deliveryPriceInput) {
                    var dpAttr = curr === 'KGS' ? 'data-delivery-price-kgs' : 'data-delivery-price-usd';
                    var dpVal = parseFloat(opt.getAttribute(dpAttr));
                    deliveryPriceInput.value = isNaN(dpVal) ? '0' : dpVal.toFixed(2);
                }
            });
        }

        if (removeBtn) {
            removeBtn.addEventListener('click', function () {
                if (tableBody.querySelectorAll('tr').length <= 1) {
                    return;
                }
                row.parentNode.removeChild(row);
            });
        }
    };

    bindRow(tableBody.querySelector('tr'));
    refreshReceiptHeadings();

    if (currencySelect) {
        currencySelect.addEventListener('change', function () {
            refreshReceiptHeadings();
            var rows = tableBody.querySelectorAll('tr');
            for (var i = 0; i < rows.length; i++) {
                var select = rows[i].querySelector('select[name="line_product_id[]"]');
                if (select && select.value !== '0') {
                    var evt = document.createEvent('HTMLEvents');
                    evt.initEvent('change', true, false);
                    select.dispatchEvent(evt);
                }
            }
        });
    }

    addBtn.addEventListener('click', function () {
        var lastRow = tableBody.querySelector('tr:last-child');
        if (!lastRow) {
            return;
        }
        var newRow = lastRow.cloneNode(true);
        var inputs = newRow.querySelectorAll('input');
        for (var i = 0; i < inputs.length; i++) {
            if (inputs[i].name === 'line_qty_rolls[]') {
                inputs[i].value = '1';
            } else if (inputs[i].name === 'line_roll_length[]') {
                inputs[i].value = '30';
            } else if (inputs[i].name === 'line_price_per_roll_usd[]') {
                inputs[i].value = '0';
            } else if (inputs[i].name === 'line_delivery_price_per_roll_usd[]') {
                inputs[i].value = '0';
            } else {
                inputs[i].value = '';
            }
        }
        var select = newRow.querySelector('select[name="line_product_id[]"]');
        if (select) {
            select.value = '0';
        }
        bindRow(newRow);
        bindSearch(newRow);
        tableBody.appendChild(newRow);
    });
})();

(function () {
    var addBtn = document.getElementById('add-writeoff-line');
    var tableBody = document.querySelector('#writeoff-lines tbody');
    if (!addBtn || !tableBody) {
        return;
    }

    var bindRow = function (row) {
        var removeBtn = row.querySelector('.remove-writeoff-line');
        if (removeBtn) {
            removeBtn.addEventListener('click', function () {
                if (tableBody.querySelectorAll('tr').length <= 1) {
                    return;
                }
                row.parentNode.removeChild(row);
            });
        }
    };

    bindRow(tableBody.querySelector('tr'));

    addBtn.addEventListener('click', function () {
        var lastRow = tableBody.querySelector('tr:last-child');
        if (!lastRow) {
            return;
        }
        var newRow = lastRow.cloneNode(true);
        var select = newRow.querySelector('select[name="writeoff_roll_id[]"]');
        if (select) {
            select.value = '0';
            var optionList = select.querySelectorAll('option');
            for (var j = 0; j < optionList.length; j++) {
                optionList[j].hidden = false;
            }
        }
        var inputs = newRow.querySelectorAll('input');
        for (var i = 0; i < inputs.length; i++) {
            if (inputs[i].name === 'writeoff_meters[]') {
                inputs[i].value = '1';
            } else {
                inputs[i].value = '';
            }
        }
        bindRow(newRow);
        tableBody.appendChild(newRow);
    });

    var bindSearch = function (row) {
        var searchInput = row.querySelector('.writeoff-roll-search');
        var select = row.querySelector('.writeoff-roll-select');
        var metersInput = row.querySelector('input[name="writeoff_meters[]"]');
        if (!searchInput || !select) {
            return;
        }

        searchInput.addEventListener('input', function () {
            var term = (searchInput.value || '').toLowerCase().trim();
            var options = select.querySelectorAll('option');
            for (var i = 0; i < options.length; i++) {
                var opt = options[i];
                if (opt.value === '0') {
                    opt.hidden = false;
                    continue;
                }
                var label = (opt.getAttribute('data-label') || '').toLowerCase();
                opt.hidden = term !== '' && label.indexOf(term) === -1;
            }
        });

        select.addEventListener('change', function () {
            var selected = select.options[select.selectedIndex];
            if (!selected || !metersInput) {
                return;
            }
            var lengthValue = parseFloat(selected.getAttribute('data-length') || '0');
            if (lengthValue > 0) {
                metersInput.max = String(lengthValue);
                if (parseFloat(metersInput.value || '0') > lengthValue) {
                    metersInput.value = String(lengthValue);
                }
            }
        });
    };

    var allRows = tableBody.querySelectorAll('tr');
    for (var r = 0; r < allRows.length; r++) {
        bindSearch(allRows[r]);
    }
})();

(function () {
    function ensureErrorModal() {
        var existing = document.getElementById('b24-error-modal');
        if (existing) return existing;
        var wrap = document.createElement('div');
        wrap.id = 'b24-error-modal';
        wrap.style.cssText = 'display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:99999;padding:20px;box-sizing:border-box;';
        wrap.innerHTML = ''
            + '<div style="max-width:760px;margin:8vh auto;background:#fff;border-radius:10px;overflow:hidden;">'
            + '<div style="padding:12px 16px;background:#f5f7fb;display:flex;justify-content:space-between;align-items:center;">'
            + '<strong>Ошибка синка Б24</strong><button type="button" id="b24-error-close" style="border:none;background:transparent;font-size:20px;cursor:pointer;">×</button>'
            + '</div>'
            + '<pre id="b24-error-body" style="margin:0;padding:14px 16px;max-height:60vh;overflow:auto;white-space:pre-wrap;"></pre>'
            + '</div>';
        document.body.appendChild(wrap);
        wrap.querySelector('#b24-error-close').addEventListener('click', function () { wrap.style.display = 'none'; });
        wrap.addEventListener('click', function (e) { if (e.target === wrap) wrap.style.display = 'none'; });
        return wrap;
    }
    document.querySelectorAll('.js-show-b24-error').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var modal = ensureErrorModal();
            var body = modal.querySelector('#b24-error-body');
            var text = btn.getAttribute('data-error') || '';
            try { text = JSON.stringify(JSON.parse(text), null, 2); } catch (_e) {}
            body.textContent = text;
            modal.style.display = 'block';
        });
    });
})();

(function () {
    document.querySelectorAll('form#receipt-doc-form, form#writeoff-doc-form').forEach(function(form) {
        form.addEventListener('submit', function() {
            var btn = form.querySelector('button[type="submit"]');
            if (btn) {
                btn.disabled = true;
                btn.textContent = '⏳ Ждем ответ Б24...';
            }
        });
    });
})();
</script>

<?php require 'includes/footer.php'; ?>
