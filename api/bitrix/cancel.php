<?php
require __DIR__ . '/../../db.php';
$db = getDB();
require_once __DIR__ . '/../../functions/stock_movements.php';

$data = json_decode(file_get_contents("php://input"), true);

$deal_id = intval($data['deal_id'] ?? 0);

$rows = $db->prepare("
    SELECT id, product_id, reserved_length
    FROM rolls
    WHERE deal_id=? AND reserved=1
");
$rows->execute([$deal_id]);
$reservedRolls = $rows->fetchAll(PDO::FETCH_ASSOC);

$db->prepare("
    UPDATE rolls 
    SET reserved=0, deal_id=NULL, reserved_length=0
    WHERE deal_id=?
")->execute([$deal_id]);

foreach ($reservedRolls as $r) {
    logAndSyncMovement($db, [
        'product_id' => intval($r['product_id']),
        'roll_id' => intval($r['id']),
        'movement_type' => 'reserve_release',
        'quantity_m' => floatval($r['reserved_length']),
        'quantity_rolls' => 0,
        'deal_id' => $deal_id,
        'comment' => 'Снятие резерва по отмене сделки'
    ]);
}

echo json_encode(["status" => "released"]);