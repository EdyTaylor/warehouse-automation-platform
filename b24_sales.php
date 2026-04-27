<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: text/html; charset=utf-8');

require __DIR__ . '/db.php';
require __DIR__ . '/menu.php';
require_once __DIR__ . '/functions/stock_movements.php';

$db = getDB();
$message = '';
$error = '';

function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_cut') {
        $lineId = intval($_POST['line_id'] ?? 0);
        $rollId = intval($_POST['roll_id'] ?? 0);
        $meters = floatval($_POST['meters'] ?? 0);

        if ($lineId <= 0 || $rollId <= 0 || $meters <= 0) {
            $error = 'Неверные данные для добавления куска.';
        } else {
            $lineStmt = $db->prepare("
                SELECT l.*, r.b24_deal_id
                FROM b24_sale_lines l
                JOIN b24_sale_requests r ON r.id = l.request_id
                WHERE l.id = ?
            ");
            $lineStmt->execute([$lineId]);
            $line = $lineStmt->fetch(PDO::FETCH_ASSOC);

            $rollStmt = $db->prepare("SELECT * FROM rolls WHERE id = ?");
            $rollStmt->execute([$rollId]);
            $roll = $rollStmt->fetch(PDO::FETCH_ASSOC);

            if (!$line || !$roll) {
                $error = 'Строка или рулон не найдены.';
            } elseif (intval($line['product_id']) !== intval($roll['product_id'])) {
                $error = 'Выбран рулон другого товара.';
            } else {
                $currentReserved = floatval($roll['reserved_length']);
                $sameDeal = intval($roll['deal_id']) === intval($line['b24_deal_id']);
                $available = $sameDeal
                    ? (floatval($roll['current_length']) - $currentReserved)
                    : floatval($roll['current_length']);

                if (intval($roll['reserved']) === 1 && !$sameDeal) {
                    $error = 'Рулон уже зарезервирован под другую сделку.';
                } elseif ($meters > $available) {
                    $error = 'Недостаточно доступных метров в выбранном рулоне.';
                } else {
                    $db->beginTransaction();
                    try {
                        $newReserved = $currentReserved + $meters;
                        $db->prepare("
                            UPDATE rolls
                            SET reserved = 1, deal_id = ?, reserved_length = ?
                            WHERE id = ?
                        ")->execute([intval($line['b24_deal_id']), $newReserved, $rollId]);

                        $db->prepare("
                            INSERT INTO b24_sale_line_cuts (line_id, roll_id, meters, created_at)
                            VALUES (?, ?, ?, NOW())
                        ")->execute([$lineId, $rollId, $meters]);

                        $db->prepare("
                            UPDATE b24_sale_lines SET status='in_progress'
                            WHERE id=? AND status='new'
                        ")->execute([$lineId]);
                        $db->prepare("
                            UPDATE b24_sale_requests SET status='in_progress'
                            WHERE id=? AND status='new'
                        ")->execute([intval($line['request_id'])]);

                        logAndSyncMovement($db, [
                            'product_id' => intval($line['product_id']),
                            'roll_id' => $rollId,
                            'movement_type' => 'reserve',
                            'quantity_m' => $meters,
                            'quantity_rolls' => 0,
                            'deal_id' => intval($line['b24_deal_id']),
                            'comment' => 'Ручной резерв в интерфейсе b24_sales'
                        ]);

                        $db->commit();
                        $message = 'Кусок добавлен в резерв.';
                    } catch (Exception $e) {
                        $db->rollBack();
                        $error = $e->getMessage();
                    }
                }
            }
        }
    }

    if ($action === 'remove_cut') {
        $cutId = intval($_POST['cut_id'] ?? 0);
        if ($cutId <= 0) {
            $error = 'Некорректный cut_id.';
        } else {
            $stmt = $db->prepare("
                SELECT c.*, l.product_id, l.request_id, r.b24_deal_id
                FROM b24_sale_line_cuts c
                JOIN b24_sale_lines l ON l.id = c.line_id
                JOIN b24_sale_requests r ON r.id = l.request_id
                WHERE c.id = ?
            ");
            $stmt->execute([$cutId]);
            $cut = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$cut) {
                $error = 'Кусок не найден.';
            } else {
                $rollStmt = $db->prepare("SELECT * FROM rolls WHERE id = ?");
                $rollStmt->execute([intval($cut['roll_id'])]);
                $roll = $rollStmt->fetch(PDO::FETCH_ASSOC);

                if (!$roll) {
                    $error = 'Рулон не найден.';
                } else {
                    $db->beginTransaction();
                    try {
                        $newReserved = max(0, floatval($roll['reserved_length']) - floatval($cut['meters']));
                        if ($newReserved <= 0) {
                            $db->prepare("
                                UPDATE rolls SET reserved=0, deal_id=NULL, reserved_length=0
                                WHERE id=?
                            ")->execute([intval($cut['roll_id'])]);
                        } else {
                            $db->prepare("
                                UPDATE rolls SET reserved_length=?
                                WHERE id=?
                            ")->execute([$newReserved, intval($cut['roll_id'])]);
                        }

                        $db->prepare("DELETE FROM b24_sale_line_cuts WHERE id=?")->execute([$cutId]);

                        logAndSyncMovement($db, [
                            'product_id' => intval($cut['product_id']),
                            'roll_id' => intval($cut['roll_id']),
                            'movement_type' => 'reserve_release',
                            'quantity_m' => floatval($cut['meters']),
                            'quantity_rolls' => 0,
                            'deal_id' => intval($cut['b24_deal_id']),
                            'comment' => 'Удаление куска из резерва'
                        ]);

                        $db->commit();
                        $message = 'Кусок удален из резерва.';
                    } catch (Exception $e) {
                        $db->rollBack();
                        $error = $e->getMessage();
                    }
                }
            }
        }
    }

    if ($action === 'confirm_line') {
        $lineId = intval($_POST['line_id'] ?? 0);
        if ($lineId <= 0) {
            $error = 'Некорректная строка.';
        } else {
            $stmt = $db->prepare("
                SELECT l.*, r.b24_deal_id
                FROM b24_sale_lines l
                JOIN b24_sale_requests r ON r.id = l.request_id
                WHERE l.id=?
            ");
            $stmt->execute([$lineId]);
            $line = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$line) {
                $error = 'Строка не найдена.';
            } else {
                $cutsStmt = $db->prepare("SELECT * FROM b24_sale_line_cuts WHERE line_id = ?");
                $cutsStmt->execute([$lineId]);
                $cuts = $cutsStmt->fetchAll(PDO::FETCH_ASSOC);

                $allocated = 0;
                foreach ($cuts as $c) { $allocated += floatval($c['meters']); }

                $need = floatval($line['quantity_m']);
                if ($allocated + 0.0001 < $need) {
                    $error = 'Недостаточно зарезервировано. Нужно: ' . $need . ' м, есть: ' . round($allocated, 2) . ' м.';
                } else {
                    $db->beginTransaction();
                    try {
                        foreach ($cuts as $c) {
                            $rollStmt = $db->prepare("SELECT * FROM rolls WHERE id=? FOR UPDATE");
                            $rollStmt->execute([intval($c['roll_id'])]);
                            $roll = $rollStmt->fetch(PDO::FETCH_ASSOC);

                            if (!$roll) {
                                throw new Exception('Рулон не найден во время подтверждения.');
                            }

                            $take = floatval($c['meters']);
                            $newLen = floatval($roll['current_length']) - $take;
                            if ($newLen < 0) {
                                throw new Exception('В рулоне недостаточно метров при подтверждении.');
                            }

                            $newReserved = max(0, floatval($roll['reserved_length']) - $take);
                            $newStatus = $newLen <= 0 ? 'sold' : 'cut';
                            $newLen = max(0, $newLen);

                            if ($newReserved <= 0) {
                                $db->prepare("
                                    UPDATE rolls
                                    SET current_length=?, status=?, reserved=0, deal_id=NULL, reserved_length=0
                                    WHERE id=?
                                ")->execute([$newLen, $newStatus, intval($c['roll_id'])]);
                            } else {
                                $db->prepare("
                                    UPDATE rolls
                                    SET current_length=?, status=?, reserved_length=?
                                    WHERE id=?
                                ")->execute([$newLen, $newStatus, $newReserved, intval($c['roll_id'])]);
                            }
                        }

                        $price = floatval($line['price_per_unit']);
                        $qty = floatval($line['quantity_m']);
                        $db->prepare("
                            INSERT INTO sales (product_id, type, quantity, price_per_unit, total, deal_id)
                            VALUES (?, 'meter', ?, ?, ?, ?)
                        ")->execute([
                            intval($line['product_id']),
                            $qty,
                            $price,
                            $qty * $price,
                            intval($line['b24_deal_id'])
                        ]);

                        logAndSyncMovement($db, [
                            'product_id' => intval($line['product_id']),
                            'movement_type' => 'sale_meter',
                            'quantity_m' => $qty,
                            'quantity_rolls' => 0,
                            'price_per_unit' => $price,
                            'total' => $qty * $price,
                            'deal_id' => intval($line['b24_deal_id']),
                            'comment' => 'Подтверждение строки продажи из B24'
                        ]);

                        $db->prepare("UPDATE b24_sale_lines SET status='completed' WHERE id=?")->execute([$lineId]);

                        $checkStmt = $db->prepare("
                            SELECT COUNT(*) as cnt
                            FROM b24_sale_lines
                            WHERE request_id=? AND status != 'completed'
                        ");
                        $checkStmt->execute([intval($line['request_id'])]);
                        $left = intval($checkStmt->fetch(PDO::FETCH_ASSOC)['cnt']);
                        if ($left === 0) {
                            $db->prepare("UPDATE b24_sale_requests SET status='completed' WHERE id=?")
                                ->execute([intval($line['request_id'])]);
                            $db->prepare("UPDATE deals SET status='closed' WHERE b24_deal_id=?")
                                ->execute([intval($line['b24_deal_id'])]);
                        }

                        $db->commit();
                        $message = 'Строка подтверждена и списана со склада.';
                    } catch (Exception $e) {
                        $db->rollBack();
                        $error = $e->getMessage();
                    }
                }
            }
        }
    }
}

$requestId = intval($_GET['request_id'] ?? 0);
$requests = $db->query("
    SELECT *
    FROM b24_sale_requests
    ORDER BY FIELD(status,'new','in_progress','completed','cancelled'), updated_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

$lines = [];
if ($requestId > 0) {
    $stmt = $db->prepare("
        SELECT l.*,
               COALESCE((SELECT SUM(meters) FROM b24_sale_line_cuts c WHERE c.line_id=l.id),0) as allocated_m
        FROM b24_sale_lines l
        WHERE l.request_id = ?
        ORDER BY l.id ASC
    ");
    $stmt->execute([$requestId]);
    $lines = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<h2>Продажи из Б24 (ручная реализация)</h2>

<?php if ($message): ?><p style="color:green;"><?= h($message) ?></p><?php endif; ?>
<?php if ($error): ?><p style="color:red;"><?= h($error) ?></p><?php endif; ?>

<table border="1" cellpadding="6" cellspacing="0">
    <tr>
        <th>ID</th>
        <th>Сделка Б24</th>
        <th>Название</th>
        <th>Ответственный</th>
        <th>Статус</th>
        <th>Открыть</th>
    </tr>
    <?php foreach ($requests as $r): ?>
    <tr>
        <td><?= (int)$r['id'] ?></td>
        <td><?= (int)$r['b24_deal_id'] ?></td>
        <td><?= h($r['deal_name']) ?></td>
        <td><?= h($r['responsible']) ?></td>
        <td><?= h($r['status']) ?></td>
        <td><a href="?request_id=<?= (int)$r['id'] ?>">Открыть</a></td>
    </tr>
    <?php endforeach; ?>
</table>

<?php if ($requestId > 0): ?>
    <h3>Строки заявки #<?= $requestId ?></h3>
    <?php foreach ($lines as $line): ?>
        <?php
        $rollStmt = $db->prepare("
            SELECT *
            FROM rolls
            WHERE product_id = ?
              AND current_length > 0
              AND (
                    reserved = 0
                    OR (reserved = 1 AND deal_id = (SELECT b24_deal_id FROM b24_sale_requests WHERE id = ?))
              )
            ORDER BY current_length ASC
        ");
        $rollStmt->execute([intval($line['product_id']), $requestId]);
        $lineRolls = $rollStmt->fetchAll(PDO::FETCH_ASSOC);

        $cutsStmt = $db->prepare("
            SELECT c.*, r.current_length
            FROM b24_sale_line_cuts c
            LEFT JOIN rolls r ON r.id = c.roll_id
            WHERE c.line_id = ?
            ORDER BY c.id DESC
        ");
        $cutsStmt->execute([intval($line['id'])]);
        $cuts = $cutsStmt->fetchAll(PDO::FETCH_ASSOC);
        ?>

        <div style="border:1px solid #ccc; padding:10px; margin:10px 0;">
            <b><?= h($line['product_name']) ?></b><br>
            Нужно: <?= (float)$line['quantity_m'] ?> м |
            Зарезервировано: <?= round((float)$line['allocated_m'], 2) ?> м |
            Статус: <?= h($line['status']) ?>

            <?php if ($line['status'] !== 'completed'): ?>
            <form method="POST" style="margin-top:10px;">
                <input type="hidden" name="action" value="add_cut">
                <input type="hidden" name="line_id" value="<?= (int)$line['id'] ?>">
                <select name="roll_id" required>
                    <option value="">Выбери рулон</option>
                    <?php foreach ($lineRolls as $roll): ?>
                        <option value="<?= (int)$roll['id'] ?>">
                            #<?= (int)$roll['id'] ?> | остаток <?= (float)$roll['current_length'] ?> м | reserved <?= (float)$roll['reserved_length'] ?> м
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="number" name="meters" step="0.1" min="0.1" placeholder="Сколько метров" required>
                <button type="submit">Добавить кусок</button>
            </form>

            <form method="POST" style="margin-top:8px;">
                <input type="hidden" name="action" value="confirm_line">
                <input type="hidden" name="line_id" value="<?= (int)$line['id'] ?>">
                <button type="submit">Подтвердить строку (списать)</button>
            </form>
            <?php endif; ?>

            <?php if ($cuts): ?>
                <table border="1" cellpadding="5" cellspacing="0" style="margin-top:10px;">
                    <tr>
                        <th>Кусок</th>
                        <th>Рулон</th>
                        <th>Метры</th>
                        <th>Действие</th>
                    </tr>
                    <?php foreach ($cuts as $cut): ?>
                    <tr>
                        <td>#<?= (int)$cut['id'] ?></td>
                        <td>#<?= (int)$cut['roll_id'] ?></td>
                        <td><?= (float)$cut['meters'] ?></td>
                        <td>
                            <?php if ($line['status'] !== 'completed'): ?>
                            <form method="POST">
                                <input type="hidden" name="action" value="remove_cut">
                                <input type="hidden" name="cut_id" value="<?= (int)$cut['id'] ?>">
                                <button type="submit">Убрать</button>
                            </form>
                            <?php else: ?>-<?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
<?php endif; ?>
