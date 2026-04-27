<?php

function getAvailableRolls($db) {
    $stmt = $db->query("SELECT * FROM rolls WHERE current_length > 0 ORDER BY current_length ASC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function findBestRoll($rolls, $length) {
    foreach ($rolls as $roll) {
        if ($roll['current_length'] >= $length) {
            return $roll;
        }
    }
    return null;
}

function cutRoll($db, $roll, $length, $minScrap) {
    $newLength = $roll['current_length'] - $length;

    if ($newLength < 0) {
        throw new Exception("Недостаточно длины в рулоне");
    }

    $status = ($newLength < $roll['min_full_length']) ? 'scrap' : 'active';

    if ($newLength < $minScrap) {
        $newLength = 0;
        $status = 'waste';
    }

    $stmt = $db->prepare("
        UPDATE rolls 
        SET current_length = ?, status = ? 
        WHERE id = ?
    ");
    $stmt->execute([$newLength, $status, $roll['id']]);

    return [
        'roll_id' => $roll['id'],
        'used' => $length,
        'remaining' => $newLength
    ];
}

function allocatePieces($db, $pieces, $config) {
    $result = [];

    foreach ($pieces as $piece) {

        $rolls = getAvailableRolls($db);

        // 1. сначала обрезки
            $scraps = array_filter($rolls, function($r) {
            return $r['status'] === 'scrap';
});

        // 2. потом обычные рулоны
        if (!$roll) {
            $active = array_filter($rolls, function($r) {
            return $r['status'] === 'active';
});
        }

        if (!$roll) {
            throw new Exception("Нет рулона под кусок {$piece} м");
        }

        $cut = cutRoll($db, $roll, $piece, $config['min_scrap_length']);
        $result[] = $cut;
    }

    return $result;
}