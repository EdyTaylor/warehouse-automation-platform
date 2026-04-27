<?php

// 🔥 Получить доступные рулоны по товару
function getAvailableRolls($db, $product_id) {
    $stmt = $db->prepare("
        SELECT * FROM rolls 
        WHERE product_id = ?
        AND current_length > 0
        ORDER BY current_length ASC
    ");
    $stmt->execute([$product_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


// 🔥 Найти лучший рулон (минимальный подходящий)
function findBestRoll($rolls, $length) {
    foreach ($rolls as $roll) {
        if ($roll['current_length'] >= $length) {
            return $roll;
        }
    }
    return null;
}


// 🔥 Отрезать кусок от рулона
function cutRoll($db, $roll, $length, $minScrap) {

    $newLength = $roll['current_length'] - $length;

    if ($newLength < 0) {
        throw new Exception("Недостаточно длины в рулоне ID {$roll['id']}");
    }

    // 🔥 логика статусов
    if ($newLength == 0) {
        $status = 'sold';
    } elseif ($newLength < $roll['min_full_length']) {
        $status = 'scrap';
    } else {
        $status = 'active';
    }

    // 🔥 если слишком маленький остаток → в отход
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


// 🔥 РАЗБИВКА МЕТРОВ (ключевая вещь)
function splitMeters($meters) {

    $pieces = [];
    $remaining = $meters;

    while ($remaining > 0) {

        // 🔥 можно заменить 30 на roll_length из БД позже
        if ($remaining >= 30) {
            $pieces[] = 30;
            $remaining -= 30;
        } else {
            $pieces[] = $remaining;
            break;
        }
    }

    return $pieces;
}


// 🔥 Главная функция раскроя
function allocateMeters($db, $product_id, $meters, $config = []) {

    $config = array_merge([
        'min_scrap_length' => 1
    ], $config);

    $result = [];

    // 🔥 1. ПЫТАЕМСЯ ВЗЯТЬ ЦЕЛЫЙ РУЛОН
    $stmt = $db->prepare("
        SELECT * FROM rolls
        WHERE product_id = ?
        AND status = 'active'
        AND current_length >= ?
        ORDER BY current_length ASC
        LIMIT 1
    ");
    $stmt->execute([$product_id, $meters]);
    $roll = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($roll) {

        $cut = cutRoll($db, $roll, $meters, $config['min_scrap_length']);
        $result[] = $cut;

        return $result;
    }

    // 🔥 2. ЕСЛИ НЕТ → ДОБИРАЕМ
    $remaining = $meters;

    while ($remaining > 0) {

        $rolls = getAvailableRolls($db, $product_id);

        $roll = null;

        // сначала обрезки
        foreach ($rolls as $r) {
            if ($r['status'] === 'scrap' && $r['current_length'] >= $remaining) {
                $roll = $r;
                break;
            }
        }

        // если нет подходящего — берем любой
        if (!$roll) {
            foreach ($rolls as $r) {
                if ($r['current_length'] > 0) {
                    $roll = $r;
                    break;
                }
            }
        }

        if (!$roll) {
            throw new Exception("Недостаточно материала на складе");
        }

        $cutLength = min($roll['current_length'], $remaining);

        $cut = cutRoll($db, $roll, $cutLength, $config['min_scrap_length']);
        $result[] = $cut;

        $remaining -= $cutLength;
    }

    return $result;
}