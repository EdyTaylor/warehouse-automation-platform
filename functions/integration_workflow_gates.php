<?php

require_once __DIR__ . '/app_settings.php';

function integrationWarehouseReserveGateSettingsKey()
{
    return 'integration_warehouse_reserve_gate_json';
}

function integrationWarehouseRealizationGateSettingsKey()
{
    return 'integration_warehouse_realization_gate_json';
}

function integrationGateLoadJson($db, $key)
{
    $raw = getAppSetting($db, $key, '');
    if ($raw === '' || $raw === null) {
        return null;
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : null;
}

function integrationGateApplyStoredOverrides(array $defaults, $storedOrNull)
{
    if ($storedOrNull === null) {
        return $defaults;
    }
    $out = $defaults;
    if (array_key_exists('filter_enabled', $storedOrNull)) {
        $out['filter_enabled'] = !empty($storedOrNull['filter_enabled']);
    }
    if (isset($storedOrNull['rules']) && is_array($storedOrNull['rules'])) {
        $out['rules'] = $storedOrNull['rules'];
    }
    return $out;
}

function integrationMergedReserveGate(PDO $db, array $cfg)
{
    $defaults = isset($cfg['warehouse_queue']) && is_array($cfg['warehouse_queue'])
        ? $cfg['warehouse_queue']
        : array('filter_enabled' => false, 'rules' => array());
    return integrationGateApplyStoredOverrides(
        $defaults,
        integrationGateLoadJson($db, integrationWarehouseReserveGateSettingsKey())
    );
}

function integrationMergedRealizationGate(PDO $db, array $cfg)
{
    $defaults = isset($cfg['warehouse_realization']) && is_array($cfg['warehouse_realization'])
        ? $cfg['warehouse_realization']
        : array('filter_enabled' => false, 'rules' => array());
    return integrationGateApplyStoredOverrides(
        $defaults,
        integrationGateLoadJson($db, integrationWarehouseRealizationGateSettingsKey())
    );
}

function integrationStagesSelectedMapFromRules(array $rules)
{
    $map = array();
    foreach ($rules as $rule) {
        if (!is_array($rule)) {
            continue;
        }
        $cats = isset($rule['category_ids']) && is_array($rule['category_ids']) ? $rule['category_ids'] : array();
        $exact = isset($rule['stages_exact']) && is_array($rule['stages_exact']) ? $rule['stages_exact'] : array();
        foreach ($cats as $cid) {
            $cid = (int)$cid;
            if (!isset($map[$cid])) {
                $map[$cid] = array();
            }
            foreach ($exact as $st) {
                $map[$cid][(string)$st] = true;
            }
        }
    }
    return $map;
}

function integrationBuildRulesFromPostStageMatrix($postMatrix)
{
    if (!is_array($postMatrix)) {
        return array();
    }
    $rules = array();
    foreach ($postMatrix as $cidStr => $stages) {
        if (!is_array($stages)) {
            continue;
        }
        $cid = (int)$cidStr;
        $clean = array();
        foreach ($stages as $st) {
            $st = trim((string)$st);
            if ($st !== '') {
                $clean[] = $st;
            }
        }
        $clean = array_values(array_unique($clean));
        if (count($clean) > 0) {
            $rules[] = array('category_ids' => array($cid), 'stages_exact' => $clean);
        }
    }
    return $rules;
}

function integrationBuildGateFromPost($filterEnabled, $stagesPostMatrix)
{
    return array(
        'filter_enabled' => !empty($filterEnabled),
        'rules' => integrationBuildRulesFromPostStageMatrix($stagesPostMatrix),
    );
}
