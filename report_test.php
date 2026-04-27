<?php
echo "START";

require 'db.php';
$db = getDB();

echo " DB OK";

$data = $db->query("SELECT 1")->fetchAll();

echo " SQL OK";