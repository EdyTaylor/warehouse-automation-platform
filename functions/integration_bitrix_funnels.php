<?php

require_once __DIR__ . '/app_settings.php';
require_once __DIR__ . '/../api/bitrix/send.php';

function integrationFunnelsSnapshotSettingsKey()
{
    return 'b24_deal_funnels_snapshot_json';
}

function integrationBitrixDealStageEntityId($categoryId)
{
    $cid = (int)$categoryId;
    if ($cid <= 0) {
        return 'DEAL_STAGE';
    }
    return 'DEAL_STAGE_' . $cid;
}

function integrationBitrixUnwrapResultList($resp)
{
    if (!is_array($resp)) {
        return null;
    }
    if (isset($resp['error']) && $resp['error'] !== null && $resp['error'] !== '') {
        return null;
    }
    if (!array_key_exists('result', $resp)) {
        return null;
    }
    $r = $resp['result'];
    if (!is_array($r)) {
        return array();
    }
    return $r;
}

function integrationFetchStagesForBitrixEntityId($entityId)
{
    $entityId = (string)$entityId;
    $resp = sendToBitrix('crm.status.list', array(
        'filter' => array('ENTITY_ID' => $entityId),
        'order' => array('SORT' => 'ASC'),
    ));
    $list = integrationBitrixUnwrapResultList($resp);
    if ($list === null) {
        return array('stages' => array(), 'error' => $resp);
    }
    $stages = array();
    foreach ($list as $row) {
        if (!is_array($row)) {
            continue;
        }
        $stages[] = array(
            'STATUS_ID' => isset($row['STATUS_ID']) ? (string)$row['STATUS_ID'] : '',
            'NAME' => isset($row['NAME']) ? (string)$row['NAME'] : '',
            'SEMANTICS' => isset($row['SEMANTICS']) ? (string)$row['SEMANTICS'] : '',
            'SORT' => isset($row['SORT']) ? (int)$row['SORT'] : 0,
        );
    }
    return array('stages' => $stages, 'error' => null);
}

function integrationFetchDealCategoriesFromBitrix()
{
    $resp = sendToBitrix('crm.dealcategory.list', array('order' => array('SORT' => 'ASC')));
    $list = integrationBitrixUnwrapResultList($resp);
    $categories = array();
    if ($list !== null && count($list) > 0) {
        foreach ($list as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = isset($row['ID']) ? (int)$row['ID'] : 0;
            if ($id <= 0) {
                continue;
            }
            $categories[] = array(
                'id' => $id,
                'name' => isset($row['NAME']) ? (string)$row['NAME'] : ('Воронка #' . $id),
            );
        }
        return array('categories' => $categories, 'source' => 'crm.dealcategory.list', 'raw_error' => null);
    }

    $resp2 = sendToBitrix('crm.category.list', array('filter' => array('entityTypeId' => 2)));
    $list2 = integrationBitrixUnwrapResultList($resp2);
    if ($list2 === null) {
        return array('categories' => array(), 'source' => 'crm.category.list', 'raw_error' => $resp2);
    }

    $unwrap = array();
    if (isset($list2['categories']) && is_array($list2['categories'])) {
        $unwrap = $list2['categories'];
    } elseif (isset($list2[0]) && is_array($list2[0])) {
        $unwrap = $list2;
    }

    foreach ($unwrap as $row) {
        if (!is_array($row)) {
            continue;
        }
        $id = 0;
        if (isset($row['id'])) {
            $id = (int)$row['id'];
        } elseif (isset($row['ID'])) {
            $id = (int)$row['ID'];
        }
        if ($id <= 0) {
            continue;
        }
        $name = '';
        if (isset($row['name'])) {
            $name = (string)$row['name'];
        } elseif (isset($row['NAME'])) {
            $name = (string)$row['NAME'];
        }
        if ($name === '') {
            $name = 'Воронка #' . $id;
        }
        $categories[] = array('id' => $id, 'name' => $name);
    }

    return array('categories' => $categories, 'source' => 'crm.category.list', 'raw_error' => null);
}

function integrationBuildDealFunnelsSnapshotFromBitrix()
{
    $errors = array();
    $fetchedAt = date('c');
    $categories = array();

    $r0 = integrationFetchStagesForBitrixEntityId('DEAL_STAGE');
    if ($r0['error'] !== null) {
        $errors[] = 'DEAL_STAGE: ' . json_encode($r0['error'], JSON_UNESCAPED_UNICODE);
    }
    $categories[] = array(
        'id' => 0,
        'name' => 'Основная воронка (CATEGORY_ID = 0)',
        'entity_id' => 'DEAL_STAGE',
        'stages' => $r0['stages'],
    );

    $catResult = integrationFetchDealCategoriesFromBitrix();
    if ($catResult['raw_error'] !== null) {
        $errors[] = 'categories: ' . json_encode($catResult['raw_error'], JSON_UNESCAPED_UNICODE);
    }

    foreach ($catResult['categories'] as $c) {
        $cid = (int)$c['id'];
        if ($cid <= 0) {
            continue;
        }
        $eid = integrationBitrixDealStageEntityId($cid);
        $rs = integrationFetchStagesForBitrixEntityId($eid);
        if ($rs['error'] !== null) {
            $errors[] = $eid . ': ' . json_encode($rs['error'], JSON_UNESCAPED_UNICODE);
        }
        $categories[] = array(
            'id' => $cid,
            'name' => $c['name'],
            'entity_id' => $eid,
            'stages' => $rs['stages'],
        );
    }

    return array(
        'fetched_at' => $fetchedAt,
        'categories' => $categories,
        'errors' => $errors,
        'category_source' => isset($catResult['source']) ? $catResult['source'] : '',
    );
}

function integrationSaveFunnelsSnapshot(PDO $db, array $snapshot)
{
    setAppSetting(
        $db,
        integrationFunnelsSnapshotSettingsKey(),
        json_encode($snapshot, JSON_UNESCAPED_UNICODE)
    );
}

function integrationLoadFunnelsSnapshotDecoded($db)
{
    $raw = getAppSetting($db, integrationFunnelsSnapshotSettingsKey(), '');
    if ($raw === '' || $raw === null) {
        return null;
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : null;
}
