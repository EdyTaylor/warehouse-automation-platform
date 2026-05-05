<?php

function sendToBitrix($method, $data = array()) {
    static $config = null;
    static $pausedResolveDone = false;
    static $pausedWritesBlock = false;
    static $syncCtlLoaded = false;
    static $outgoingLogLoaded = false;
    static $outgoingLogSchemaReady = false;
    if (!$syncCtlLoaded) {
        $syncCtlLoaded = true;
        require_once __DIR__ . '/../../functions/integration_sync_control.php';
    }
    if (!$outgoingLogLoaded) {
        $outgoingLogLoaded = true;
        require_once __DIR__ . '/../../functions/bitrix_outgoing_log.php';
    }

    $logOutgoing = function ($endpointUrl, $requestPayload, $responsePayload, $status, $errorCode) use (&$outgoingLogSchemaReady, $method) {
        try {
            require_once __DIR__ . '/../../db.php';
            $db = getDB();
            if (!$outgoingLogSchemaReady) {
                bitrixOutgoingLogEnsureSchema($db);
                $outgoingLogSchemaReady = true;
            }
            bitrixOutgoingLogWrite($db, $method, $endpointUrl, $requestPayload, $responsePayload, $status, $errorCode);
        } catch (Exception $e) {
            // Нельзя ломать основную бизнес-операцию из-за проблем логирования.
        }
    };

    if (integrationBitrixMethodLooksLikeWriteMutation((string)$method)) {
        if (!$pausedResolveDone) {
            $pausedResolveDone = true;
            try {
                require_once __DIR__ . '/../../db.php';
                $pausedWritesBlock = integrationAllSyncPaused(getDB());
            } catch (Exception $e) {
                $pausedWritesBlock = false;
            }
        }
        if ($pausedWritesBlock) {
            $pausedResponse = array(
                'error' => 'integration_sync_paused',
                'error_description' => 'Отправка изменений в Битрикс24 отключена (Центр интеграции → пауза синхронизации).',
            );
            $logOutgoing('', json_encode($data), json_encode($pausedResponse), 'blocked', 'integration_sync_paused');
            return $pausedResponse;
        }
    }

    if ($config === null) {
        $config = require __DIR__ . '/config.php';
    }
    $url = null;

    if (isset($config['method_urls']) && isset($config['method_urls'][$method])) {
        $url = $config['method_urls'][$method];
    } else {
        $methodStr = (string)$method;
        $isCatalogMethod = strpos($methodStr, 'catalog.') === 0;
        // crm.product.* на «узком» CRM-входе иногда не даёт полноценно привести карточку к виду для СУ;
        // второй вход (CRM+каталог) — см. api/bitrix/config.php catalog_webhook / use_catalog_webhook_for_crm_product.
        $crmProductViaCatalog = false;
        if (isset($config['use_catalog_webhook_for_crm_product']) && $config['use_catalog_webhook_for_crm_product']) {
            if (strpos($methodStr, 'crm.product.') === 0) {
                $crmProductViaCatalog = true;
            }
        }
        $webhook = '';
        $catUrl = isset($config['catalog_webhook']) ? trim((string)$config['catalog_webhook']) : '';
        if ($catUrl !== '' && ($isCatalogMethod || $crmProductViaCatalog)) {
            $webhook = $catUrl;
        } else {
            $webhook = isset($config['webhook']) ? $config['webhook'] : '';
        }
        // Bitrix incoming webhooks expect *.json endpoint.
        $url = rtrim($webhook, '/') . '/' . $method . '.json';
    }

    $options = [
        "http" => [
            "header"  => "Content-type: application/json",
            "method"  => "POST",
            "content" => json_encode($data),
            "timeout" => 15,
            "ignore_errors" => true
        ]
    ];

    $result = @file_get_contents($url, false, stream_context_create($options));
    if ($result === false) {
        $networkError = array(
            'error' => 'network_error',
            'error_description' => 'Bitrix request failed for method ' . $method
        );
        $logOutgoing($url, json_encode($data), json_encode($networkError), 'error', 'network_error');
        return $networkError;
    }

    $decoded = json_decode((string)$result, true);
    if (is_array($decoded)) {
        $status = isset($decoded['error']) ? 'error' : 'ok';
        $errorCode = isset($decoded['error']) ? (string)$decoded['error'] : '';
        $logOutgoing($url, json_encode($data), json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $status, $errorCode);
        return $decoded;
    }
    $raw = trim((string)$result);
    if ($raw === '') {
        $emptyResp = array(
            'error' => 'empty_response',
            'error_description' => 'Empty body from Bitrix (' . $method . ')'
        );
        $logOutgoing($url, json_encode($data), json_encode($emptyResp), 'error', 'empty_response');
        return $emptyResp;
    }
    $preview = function_exists('mb_substr') ? mb_substr($raw, 0, 240) : substr($raw, 0, 240);
    $badJson = array(
        'error' => 'bad_json',
        'error_description' => 'Invalid JSON from Bitrix (' . $method . '): ' . $preview
    );
    $logOutgoing($url, json_encode($data), $raw, 'error', 'bad_json');
    return $badJson;
}