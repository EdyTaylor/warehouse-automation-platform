<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../db.php';
$db = getDB();
require_once __DIR__ . '/../../functions/stock_movements.php';

$data = json_decode(file_get_contents("php://input"), true);

$deal_id = intval($data['deal_id'] ?? 0);
$products = $data['products'] ?? [];

if (!$deal_id || empty($products)) {
    echo json_encode(["error" => "Нет данных"]);
    exit;
}

$db->beginTransaction();

try {

    foreach ($products as $p) {

        $b24_id = intval($p['id']);
        $qty = floatval($p['quantity']);
        $price = floatval($p['price']);

        // 🔍 товар
        $stmt = $db->prepare("SELECT * FROM products WHERE b24_product_id=?");
        $stmt->execute([$b24_id]);
        $product = $stmt->fetch();

        if (!$product) {
            throw new Exception("Товар не найден");
        }

        $product_id = $product['id'];

        // 🔥 берем зарезервированные рулоны
        $stmt = $db->prepare("
            SELECT * FROM rolls
            WHERE product_id=?
            AND deal_id=?
            AND reserved=1
        ");
        $stmt->execute([$product_id, $deal_id]);

        $rolls = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $remaining = $qty;

        foreach ($rolls as $roll) {

            if ($remaining <= 0) break;

            $reservedLen = isset($roll['reserved_length']) ? floatval($roll['reserved_length']) : 0;
            $take = min($reservedLen, $remaining);

            if ($take <= 0) {
                continue;
            }

            $new_length = $roll['current_length'] - $take;

            $status = ($new_length <= 0) ? 'sold' : 'cut';

            $db->prepare("
                UPDATE rolls 
                SET current_length=?, status=?, reserved=0, deal_id=NULL, reserved_length=0
                WHERE id=?
            ")->execute([$new_length, $status, $roll['id']]);

            logAndSyncMovement($db, [
                'product_id' => $product_id,
                'roll_id' => intval($roll['id']),
                'movement_type' => 'sale_meter',
                'quantity_m' => $take,
                'quantity_rolls' => 0,
                'price_per_unit' => $price,
                'total' => $take * $price,
                'deal_id' => $deal_id,
                'comment' => 'Закрытие сделки и фактическое списание'
            ]);

            $remaining -= $take;
        }

        if ($remaining > 0) {
            throw new Exception("Не хватает зарезервированного товара");
        }

        // 🟢 записываем продажу
        $total = $price * $qty;

        $db->prepare("
            INSERT INTO sales 
            (product_id, type, quantity, price_per_unit, total, deal_id)
            VALUES (?, 'meter', ?, ?, ?, ?)
        ")->execute([$product_id, $qty, $price, $total, $deal_id]);
    }

    // обновляем статус сделки
    $db->prepare("
        UPDATE deals SET status='closed' WHERE b24_deal_id=?
    ")->execute([$deal_id]);

    $db->commit();

    echo json_encode(["status" => "completed"]);

} catch (Exception $e) {

    $db->rollBack();

    echo json_encode([
        "error" => $e->getMessage()
    ]);
}