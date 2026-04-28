<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: text/html; charset=utf-8');

require 'db.php';
require_once __DIR__ . '/functions/stock_movements.php';
require_once __DIR__ . '/api/bitrix/send.php';
$db = getDB();

function ensureStockOperationTables($db) {
    $db->exec("
        CREATE TABLE IF NOT EXISTS stock_operation_docs (
            id int NOT NULL AUTO_INCREMENT,
            operation_type varchar(20) NOT NULL,
            doc_number varchar(64) DEFAULT NULL,
            supplier varchar(255) DEFAULT NULL,
            comment_text text,
            total_amount decimal(14,2) NOT NULL DEFAULT 0,
            status varchar(20) NOT NULL DEFAULT 'posted',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_stock_operation_type (operation_type, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS stock_operation_lines (
            id int NOT NULL AUTO_INCREMENT,
            doc_id int NOT NULL,
            product_id int NOT NULL,
            product_name varchar(255) DEFAULT NULL,
            qty_rolls int NOT NULL DEFAULT 0,
            roll_length decimal(10,2) NOT NULL DEFAULT 0,
            quantity_m decimal(12,2) NOT NULL DEFAULT 0,
            price_per_roll decimal(14,2) NOT NULL DEFAULT 0,
            line_total decimal(14,2) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_stock_operation_doc (doc_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

function ensureProductForReceipt($db, $productId, $productName, $rollLength, $pricePerRoll) {
    if ($productId > 0) {
        $stmt = $db->prepare("SELECT * FROM products WHERE id = ? LIMIT 1");
        $stmt->execute(array($productId));
        $p = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($p) {
            if ($pricePerRoll > 0 || $rollLength > 0) {
                $db->prepare("UPDATE products SET purchase_price = ?, roll_length = ? WHERE id = ?")
                    ->execute(array($pricePerRoll, $rollLength, $productId));
            }
            return $p;
        }
    }

    $name = trim((string)$productName);
    if ($name === '') {
        throw new Exception('Укажите товар в строке прихода.');
    }

    $find = $db->prepare("SELECT * FROM products WHERE name = ? ORDER BY id ASC LIMIT 1");
    $find->execute(array($name));
    $existing = $find->fetch(PDO::FETCH_ASSOC);
    if ($existing) {
        $db->prepare("UPDATE products SET purchase_price = ?, roll_length = ? WHERE id = ?")
            ->execute(array($pricePerRoll, $rollLength, intval($existing['id'])));
        return $existing;
    }

    $ins = $db->prepare("
        INSERT INTO products (name, roll_length, purchase_price, price_per_meter)
        VALUES (?, ?, ?, 0)
    ");
    $ins->execute(array($name, $rollLength, $pricePerRoll));
    $newId = intval($db->lastInsertId());

    $created = array(
        'id' => $newId,
        'name' => $name,
        'b24_product_id' => 0
    );

    // Keep behavior same as dashboard: if product doesn't exist in B24, try creating it.
    $payload = array('fields' => array('NAME' => $name));
    if ($pricePerRoll > 0) {
        $payload['fields']['PRICE'] = $pricePerRoll;
    }
    $resp = sendToBitrix('crm.product.add', $payload);
    if (is_array($resp) && !isset($resp['error']) && isset($resp['result'])) {
        $b24Id = intval($resp['result']);
        if ($b24Id > 0) {
            $db->prepare("UPDATE products SET b24_product_id = ? WHERE id = ?")
                ->execute(array($b24Id, $newId));
            $created['b24_product_id'] = $b24Id;
        }
    }

    return $created;
}

function consumeWriteoffMeters($db, $productId, $meters) {
    $need = floatval($meters);
    if ($need <= 0) {
        return array();
    }

    $stmt = $db->prepare("
        SELECT *
        FROM rolls
        WHERE product_id = ?
          AND status NOT IN ('sold', 'written_off', 'waste')
          AND current_length > 0
          AND reserved = 0
        ORDER BY
            CASE WHEN status='cut' THEN 0 ELSE 1 END,
            current_length ASC,
            id ASC
    ");
    $stmt->execute(array($productId));
    $rolls = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $taken = array();
    foreach ($rolls as $roll) {
        if ($need <= 0) {
            break;
        }
        $available = floatval($roll['current_length']);
        if ($available <= 0) {
            continue;
        }

        $take = min($available, $need);
        $newLen = $available - $take;
        $newStatus = $newLen <= 0 ? 'written_off' : 'cut';
        if ($newLen < 0) {
            $newLen = 0;
        }

        $db->prepare("
            UPDATE rolls
            SET current_length = ?, status = ?
            WHERE id = ?
        ")->execute(array($newLen, $newStatus, intval($roll['id'])));

        $taken[] = array(
            'roll_id' => intval($roll['id']),
            'meters' => $take
        );
        $need -= $take;
    }

    if ($need > 0.0001) {
        throw new Exception('Недостаточно метров для списания по товару ID ' . intval($productId) . '.');
    }

    return $taken;
}

$successMsg = '';
$errorMsg = '';
ensureStockOperationTables($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_receipt') {
    $docNumber = trim(isset($_POST['doc_number']) ? $_POST['doc_number'] : '');
    $supplier = trim(isset($_POST['supplier']) ? $_POST['supplier'] : '');
    $commentText = trim(isset($_POST['comment_text']) ? $_POST['comment_text'] : '');
    $minFull = floatval(isset($_POST['min_full']) ? $_POST['min_full'] : 0.5);
    $lineProductId = isset($_POST['line_product_id']) && is_array($_POST['line_product_id']) ? $_POST['line_product_id'] : array();
    $lineProductName = isset($_POST['line_product_name']) && is_array($_POST['line_product_name']) ? $_POST['line_product_name'] : array();
    $lineQtyRolls = isset($_POST['line_qty_rolls']) && is_array($_POST['line_qty_rolls']) ? $_POST['line_qty_rolls'] : array();
    $lineRollLength = isset($_POST['line_roll_length']) && is_array($_POST['line_roll_length']) ? $_POST['line_roll_length'] : array();
    $linePrice = isset($_POST['line_price_per_roll']) && is_array($_POST['line_price_per_roll']) ? $_POST['line_price_per_roll'] : array();

    try {
        $db->beginTransaction();
        $insDoc = $db->prepare("
            INSERT INTO stock_operation_docs (operation_type, doc_number, supplier, comment_text, total_amount, status)
            VALUES ('receipt', ?, ?, ?, 0, 'posted')
        ");
        $insDoc->execute(array($docNumber, $supplier, $commentText));
        $docId = intval($db->lastInsertId());

        $insLine = $db->prepare("
            INSERT INTO stock_operation_lines
            (doc_id, product_id, product_name, qty_rolls, roll_length, quantity_m, price_per_roll, line_total)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $totalAmount = 0.0;
        $addedAny = false;

        for ($i = 0; $i < count($lineQtyRolls); $i++) {
            $qtyRolls = intval($lineQtyRolls[$i]);
            $rollLength = floatval(isset($lineRollLength[$i]) ? $lineRollLength[$i] : 0);
            $pricePerRoll = floatval(isset($linePrice[$i]) ? $linePrice[$i] : 0);
            $productId = intval(isset($lineProductId[$i]) ? $lineProductId[$i] : 0);
            $productName = isset($lineProductName[$i]) ? trim($lineProductName[$i]) : '';

            if ($qtyRolls <= 0 || $rollLength <= 0) {
                continue;
            }

            $product = ensureProductForReceipt($db, $productId, $productName, $rollLength, $pricePerRoll);
            $localProductId = intval($product['id']);
            $localProductName = isset($product['name']) ? $product['name'] : $productName;

            $quantityM = $qtyRolls * $rollLength;
            $lineTotal = $qtyRolls * $pricePerRoll;
            $totalAmount += $lineTotal;
            $addedAny = true;

            $insLine->execute(array(
                $docId,
                $localProductId,
                $localProductName,
                $qtyRolls,
                $rollLength,
                $quantityM,
                $pricePerRoll,
                $lineTotal
            ));

            for ($r = 0; $r < $qtyRolls; $r++) {
                $insRoll = $db->prepare("
                    INSERT INTO rolls (product_id, original_length, current_length, min_full_length, status)
                    VALUES (?, ?, ?, ?, 'active')
                ");
                $insRoll->execute(array($localProductId, $rollLength, $rollLength, $minFull));
                $rollId = intval($db->lastInsertId());

                logAndSyncMovement($db, array(
                    'product_id' => $localProductId,
                    'roll_id' => $rollId,
                    'movement_type' => 'receipt',
                    'quantity_m' => $rollLength,
                    'quantity_rolls' => 1,
                    'price_per_unit' => $pricePerRoll,
                    'total' => $pricePerRoll,
                    'comment' => 'Оприходование через документ #' . $docId
                ));
            }
        }

        if (!$addedAny) {
            throw new Exception('Добавьте хотя бы одну корректную строку прихода.');
        }

        $db->prepare("UPDATE stock_operation_docs SET total_amount = ? WHERE id = ?")
            ->execute(array($totalAmount, $docId));
        $db->commit();
        $successMsg = 'Документ прихода #' . $docId . ' проведен. Сумма: ' . number_format($totalAmount, 2, '.', ' ');
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $errorMsg = $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_writeoff') {
    $docNumber = trim(isset($_POST['writeoff_doc_number']) ? $_POST['writeoff_doc_number'] : '');
    $commentText = trim(isset($_POST['writeoff_comment_text']) ? $_POST['writeoff_comment_text'] : '');
    $lineProductId = isset($_POST['writeoff_product_id']) && is_array($_POST['writeoff_product_id']) ? $_POST['writeoff_product_id'] : array();
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
        for ($i = 0; $i < count($lineProductId); $i++) {
            $productId = intval(isset($lineProductId[$i]) ? $lineProductId[$i] : 0);
            $meters = floatval(isset($lineMeters[$i]) ? $lineMeters[$i] : 0);
            $reason = isset($lineReason[$i]) ? trim($lineReason[$i]) : '';
            if ($productId <= 0 || $meters <= 0) {
                continue;
            }

            $pStmt = $db->prepare("SELECT id, name FROM products WHERE id = ? LIMIT 1");
            $pStmt->execute(array($productId));
            $product = $pStmt->fetch(PDO::FETCH_ASSOC);
            if (!$product) {
                throw new Exception('Товар для списания не найден (ID ' . $productId . ').');
            }

            $taken = consumeWriteoffMeters($db, $productId, $meters);
            foreach ($taken as $piece) {
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
            }

            $db->prepare("
                INSERT INTO sales (product_id, type, quantity, price_per_unit, total, deal_id, deal_url)
                VALUES (?, 'writeoff', ?, 0, 0, NULL, NULL)
            ")->execute(array($productId, $meters));

            $insLine->execute(array(
                $docId,
                $productId,
                $product['name'],
                $meters
            ));
            $addedAny = true;
        }

        if (!$addedAny) {
            throw new Exception('Добавьте хотя бы одну корректную строку списания.');
        }

        $db->commit();
        $successMsg = 'Документ списания #' . $docId . ' проведен.';
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $errorMsg = $e->getMessage();
    }
}

$products = $db->query("SELECT id, name, roll_length, purchase_price FROM products ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$recentDocs = $db->query("
    SELECT id, operation_type, doc_number, supplier, total_amount, status, created_at
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
            </div>
            <div class="form-group">
                <label>Комментарий</label>
                <input type="text" name="comment_text" placeholder="Примечание к приходу">
            </div>

            <table class="table" id="receipt-lines">
                <thead>
                    <tr>
                        <th>Товар (из базы)</th>
                        <th>Название (если новый)</th>
                        <th>Рулонов</th>
                        <th>Длина рулона (м)</th>
                        <th>Закупка за рулон</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <select name="line_product_id[]">
                                <option value="0">-- Новый товар --</option>
                                <?php foreach ($products as $p): ?>
                                    <option value="<?= intval($p['id']) ?>" data-roll-length="<?= htmlspecialchars((string)$p['roll_length']) ?>" data-price="<?= htmlspecialchars((string)$p['purchase_price']) ?>">
                                        <?= htmlspecialchars($p['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td><input type="text" name="line_product_name[]" placeholder="Если новый товар"></td>
                        <td><input type="number" name="line_qty_rolls[]" min="1" value="1"></td>
                        <td><input type="number" name="line_roll_length[]" min="0.1" step="0.1" value="30"></td>
                        <td><input type="number" name="line_price_per_roll[]" min="0" step="0.01" value="0"></td>
                        <td><button type="button" class="btn btn-danger btn-sm remove-line">×</button></td>
                    </tr>
                </tbody>
            </table>
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
                        <th>Товар</th>
                        <th>К списанию (м)</th>
                        <th>Причина</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <select name="writeoff_product_id[]">
                                <option value="0">-- Выберите товар --</option>
                                <?php foreach ($products as $p): ?>
                                    <option value="<?= intval($p['id']) ?>"><?= htmlspecialchars($p['name']) ?></option>
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
                        <th>Дата</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentDocs as $d): ?>
                        <tr>
                            <td><?= intval($d['id']) ?></td>
                            <td><?= htmlspecialchars($d['operation_type']) ?></td>
                            <td><?= htmlspecialchars((string)$d['doc_number']) ?></td>
                            <td><?= htmlspecialchars((string)$d['supplier']) ?></td>
                            <td><?= number_format(floatval($d['total_amount']), 2, '.', ' ') ?></td>
                            <td><?= htmlspecialchars((string)$d['status']) ?></td>
                            <td><?= htmlspecialchars((string)$d['created_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div class="card">
        <h3>Синхронизация</h3>
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            <a href="api/bitrix/sync_stock.php?push=1" class="btn btn-warning" target="_blank">📤 Синхронизировать остатки</a>
            <a href="api/sync_prices.php?action=to_b24" class="btn btn-warning" target="_blank">💰 Синхронизировать цены</a>
            <a href="api/bitrix/import_products.php" class="btn btn-success" target="_blank">📥 Импортировать товары</a>
        </div>
    </div>
</main>

<script>
(function () {
    var addBtn = document.getElementById('add-receipt-line');
    var tableBody = document.querySelector('#receipt-lines tbody');
    if (!addBtn || !tableBody) {
        return;
    }

    var bindRow = function (row) {
        var select = row.querySelector('select[name="line_product_id[]"]');
        var lenInput = row.querySelector('input[name="line_roll_length[]"]');
        var priceInput = row.querySelector('input[name="line_price_per_roll[]"]');
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
                if (priceInput && opt.getAttribute('data-price')) {
                    priceInput.value = opt.getAttribute('data-price');
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
            } else if (inputs[i].name === 'line_price_per_roll[]') {
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
        var select = newRow.querySelector('select[name="writeoff_product_id[]"]');
        if (select) {
            select.value = '0';
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
})();
</script>

<?php require 'includes/footer.php'; ?>
