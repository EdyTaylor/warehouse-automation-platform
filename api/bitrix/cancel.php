<?php
require 'db.php';
$db = getDB();

$data = json_decode(file_get_contents("php://input"), true);

$deal_id = intval($data['deal_id'] ?? 0);

$db->prepare("
    UPDATE rolls 
    SET reserved=0, deal_id=NULL
    WHERE deal_id=?
")->execute([$deal_id]);

echo json_encode(["status" => "released"]);