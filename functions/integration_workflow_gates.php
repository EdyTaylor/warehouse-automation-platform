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

/**
 * Решение: считается ли сделка оплаченной/реализованной по правилам.
 * - $dealData — результат crm.deal.get (массив).
 * - $gate — структура из config.php (filter_enabled + rules).
 *
 * Правила:
 * - Если gate.filter_enabled = false: старая эвристика — SEMANTICS == 's' или STAGE_ID содержит 'WON'/'FINAL_INVOICE'.
 * - Если gate.filter_enabled = true: пройти по rules; для rule:
 *     - если category_ids задан и CATEGORY_ID не входит — пропустить;
 *     - если stages_exact задан и STAGE_ID строго совпадает с одним из них — true.
 */
if (!function_exists('bitrixRealizationIsPaid')) {
    function bitrixRealizationIsPaid(array $dealData, $gate = null) {
        if (!is_array($dealData)) {
            return false;
        }

        // Normalise
        $category = null;
        if (isset($dealData['CATEGORY_ID'])) {
            $category = (string)$dealData['CATEGORY_ID'];
        } elseif (isset($dealData['CATEGORY']) ) {
            $category = (string)$dealData['CATEGORY'];
        }

        $stage = '';
        if (isset($dealData['STAGE_ID'])) {
            $stage = (string)$dealData['STAGE_ID'];
        } elseif (isset($dealData['STATUS_ID'])) {
            $stage = (string)$dealData['STATUS_ID'];
        }
        $stageNorm = strtoupper(trim($stage));

        $sem = '';
        if (isset($dealData['SEMANTICS'])) {
            $sem = strtolower(trim((string)$dealData['SEMANTICS']));
        }

        // Use default gate if none
        if ($gate === null) {
            $gate = array('filter_enabled' => false, 'rules' => array());
        }

        // If filter disabled — legacy heuristic
        if (empty($gate['filter_enabled'])) {
            if ($sem === 's') {
                return true;
            }
            if ($stageNorm !== '' && (strpos($stageNorm, 'WON') !== false || strpos($stageNorm, 'FINAL_INVOICE') !== false || strpos($stageNorm, 'CLOSED') !== false)) {
                return true;
            }
            return false;
        }

        // Filter enabled — evaluate explicit rules
        if (isset($gate['rules']) && is_array($gate['rules'])) {
            foreach ($gate['rules'] as $rule) {
                // category check (if present)
                if (isset($rule['category_ids']) && is_array($rule['category_ids']) && !empty($rule['category_ids'])) {
                    $matchedCategory = false;
                    foreach ($rule['category_ids'] as $cid) {
                        if ((string)$cid === (string)$category) {
                            $matchedCategory = true;
                            break;
                        }
                    }
                    if (!$matchedCategory) {
                        continue;
                    }
                }

                // stages exact
                if (isset($rule['stages_exact']) && is_array($rule['stages_exact']) && !empty($rule['stages_exact'])) {
                    foreach ($rule['stages_exact'] as $s) {
                        if ($stage === (string)$s || $stageNorm === strtoupper((string)$s) || strpos($stage, (string)$s) === 0) {
                            return true;
                        }
                    }
                }

                // fallback: check semantics if provided in rule
                if (isset($rule['semantics']) && is_array($rule['semantics'])) {
                    foreach ($rule['semantics'] as $sv) {
                        if ($sem === strtolower((string)$sv)) {
                            return true;
                        }
                    }
                }
            }
        }

        return false;
    }
}
