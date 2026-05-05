<?php

/**
 * Optional gating: enqueue warehouse (b24_sale_requests) only when deal CATEGORY_ID + STAGE_ID match rules.
 *
 * Set in config.php:
 *   'warehouse_queue' => [
 *     'filter_enabled' => false,
 *     'rules' => [
 *       ['category_ids' => [7], 'stages_exact' => ['C7:UC_MYSTAGE']],
 *       ['category_ids' => [9], 'stage_prefixes' => ['C9:UC_']],
 *     ],
 *   ],
 *
 * First matching rule decides: default action is allow. Use ['action' => 'deny'] to block specific cases
 * when paired with a broader allow rule later (rare).
 */

function bitrixMergeDealWebhookAndCrm(array $webhookFields, array $crmDeal) {
    return array_merge(is_array($crmDeal) ? $crmDeal : [], $webhookFields);
}

function bitrixWarehouseGateRuleMatches(array $rule, $categoryId, $stageId) {
    $cat = (string)$categoryId;
    $stage = (string)$stageId;

    $cats = isset($rule['category_ids']) ? $rule['category_ids'] : null;
    if (is_array($cats) && count($cats) > 0) {
        $wanted = array_map('intval', $cats);
        if (!in_array((int)$cat, $wanted, true)) {
            return false;
        }
    }

    $exact = isset($rule['stages_exact']) && is_array($rule['stages_exact']) ? $rule['stages_exact'] : [];
    $prefixes = isset($rule['stage_prefixes']) && is_array($rule['stage_prefixes']) ? $rule['stage_prefixes'] : [];
    $contains = isset($rule['stage_contains']) && is_array($rule['stage_contains']) ? $rule['stage_contains'] : [];

    $hasStageFilter = count($exact) > 0 || count($prefixes) > 0 || count($contains) > 0;
    if (!$hasStageFilter) {
        return true;
    }

    foreach ($exact as $e) {
        if ($stage === (string)$e) {
            return true;
        }
    }
    foreach ($prefixes as $p) {
        $p = (string)$p;
        if ($p !== '' && strpos($stage, $p) === 0) {
            return true;
        }
    }
    foreach ($contains as $c) {
        $c = (string)$c;
        if ($c !== '' && strpos($stage, $c) !== false) {
            return true;
        }
    }

    return false;
}

/**
 * When filter_enabled is true: first matching rule allows (or deny via action).
 * Empty rules => no match.
 *
 * @param array $deal Merged deal fields (webhook overlay on crm.deal.get).
 * @param array $gate  ['filter_enabled'=>bool, 'rules'=>...]
 */
function bitrixWorkflowGateRulesMatchDeal(array $deal, array $gate) {
    $rules = isset($gate['rules']) && is_array($gate['rules']) ? $gate['rules'] : array();
    if (count($rules) === 0) {
        return false;
    }

    $cat = isset($deal['CATEGORY_ID']) ? $deal['CATEGORY_ID'] : '';
    $stage = isset($deal['STAGE_ID']) ? $deal['STAGE_ID'] : '';

    foreach ($rules as $rule) {
        if (!is_array($rule)) {
            continue;
        }
        if (!bitrixWarehouseGateRuleMatches($rule, $cat, $stage)) {
            continue;
        }

        $action = isset($rule['action']) ? strtolower(trim((string)$rule['action'])) : 'allow';

        return $action !== 'deny';
    }

    return false;
}

/**
 * @param array $deal Merged deal fields (webhook overlay on crm.deal.get).
 * @param array $gate  $cfg['warehouse_queue'] from config.php (optional overrides in app_settings).
 */
function bitrixWarehouseQueueAllowed(array $deal, array $gate) {
    if (empty($gate['filter_enabled'])) {
        return true;
    }
    return bitrixWorkflowGateRulesMatchDeal($deal, $gate);
}

/**
 * Legacy «оплачено / успех» по семантике и известным STAGE_ID (когда фильтр реализации выключен).
 *
 * @param array $deal crm.deal.get fields
 */
function bitrixLegacyRealizationIsPaid(array $deal) {
    $stage = strtoupper(trim((string)(isset($deal['STAGE_ID']) ? $deal['STAGE_ID'] : '')));
    $semantic = strtolower(trim((string)(isset($deal['SEMANTICS']) ? $deal['SEMANTICS'] : '')));
    return $semantic === 's' || in_array($stage, array('WON', 'C4:WON', 'FINAL_INVOICE', 'UC_1G5NIZ'), true);
}

/**
 * Реализация (списание sale_meter, completed): либо по правилам стадий, либо по старой эвристике.
 *
 * @param array $deal crm.deal.get fields
 * @param array $gate  $cfg['warehouse_realization']
 */
if (!function_exists('bitrixRealizationIsPaid')) {
    function bitrixRealizationIsPaid(array $deal, array $gate) {
        if (empty($gate['filter_enabled'])) {
            return bitrixLegacyRealizationIsPaid($deal);
        }
        return bitrixWorkflowGateRulesMatchDeal($deal, $gate);
    }
}
