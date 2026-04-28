<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: text/html; charset=utf-8');

require 'db.php';
$db = getDB();

$docId = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($docId <= 0) {
    http_response_code(400);
    echo 'Некорректный ID документа.';
    exit;
}

$docStmt = $db->prepare("
    SELECT id, operation_type, doc_number, supplier, comment_text, total_amount, status, created_at
    FROM stock_operation_docs
    WHERE id = ?
    LIMIT 1
");
$docStmt->execute(array($docId));
$doc = $docStmt->fetch(PDO::FETCH_ASSOC);
if (!$doc) {
    http_response_code(404);
    echo 'Документ не найден.';
    exit;
}

$lineStmt = $db->prepare("
    SELECT product_id, product_name, qty_rolls, roll_length, quantity_m, price_per_roll, line_total
    FROM stock_operation_lines
    WHERE doc_id = ?
    ORDER BY id ASC
");
$lineStmt->execute(array($docId));
$lines = $lineStmt->fetchAll(PDO::FETCH_ASSOC);

function h($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

$typeMap = array(
    'receipt' => 'Приход',
    'writeoff' => 'Списание',
    'sale' => 'Реализация'
);
$typeLabel = isset($typeMap[$doc['operation_type']]) ? $typeMap[$doc['operation_type']] : $doc['operation_type'];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Документ #<?= intval($doc['id']) ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; color: #111; }
        .head { margin-bottom: 16px; }
        .head h1 { margin: 0 0 8px 0; font-size: 22px; }
        .meta { margin: 2px 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #aaa; padding: 6px 8px; font-size: 14px; text-align: left; }
        th { background: #f3f3f3; }
        .actions { margin-bottom: 14px; display: flex; gap: 10px; }
        .btn { border: 1px solid #aaa; padding: 6px 10px; text-decoration: none; color: #111; border-radius: 4px; }
        .total { margin-top: 12px; font-size: 16px; font-weight: bold; }
        @media print {
            .actions { display: none; }
            body { margin: 0; }
        }
    </style>
</head>
<body>
    <div class="actions">
        <a class="btn" href="stock_operations.php">Назад</a>
        <button class="btn" onclick="window.print()">Печать</button>
    </div>

    <div class="head">
        <h1><?= h($typeLabel) ?> #<?= intval($doc['id']) ?></h1>
        <div class="meta"><strong>№ документа:</strong> <?= h($doc['doc_number']) ?></div>
        <div class="meta"><strong>Дата:</strong> <?= h($doc['created_at']) ?></div>
        <div class="meta"><strong>Статус:</strong> <?= h($doc['status']) ?></div>
        <div class="meta"><strong>Поставщик:</strong> <?= h($doc['supplier']) ?></div>
        <div class="meta"><strong>Комментарий:</strong> <?= h($doc['comment_text']) ?></div>
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Товар</th>
                <th>Рулонов</th>
                <th>Длина рулона</th>
                <th>Кол-во, м</th>
                <th>Цена за рулон</th>
                <th>Сумма</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($lines)): ?>
                <tr><td colspan="7">Строки документа отсутствуют.</td></tr>
            <?php else: ?>
                <?php foreach ($lines as $i => $line): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><?= h($line['product_name']) ?> (ID <?= intval($line['product_id']) ?>)</td>
                        <td><?= intval($line['qty_rolls']) ?></td>
                        <td><?= number_format(floatval($line['roll_length']), 2, '.', ' ') ?></td>
                        <td><?= number_format(floatval($line['quantity_m']), 2, '.', ' ') ?></td>
                        <td><?= number_format(floatval($line['price_per_roll']), 2, '.', ' ') ?></td>
                        <td><?= number_format(floatval($line['line_total']), 2, '.', ' ') ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="total">Итого: <?= number_format(floatval($doc['total_amount']), 2, '.', ' ') ?></div>
</body>
</html>
