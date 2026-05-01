<?php

function sendToBitrix($method, $data = array()) {
    static $config = null;
    static $pausedResolveDone = false;
    static $pausedWritesBlock = false;
    static $syncCtlLoaded = false;
    if (!$syncCtlLoaded) {
        $syncCtlLoaded = true;
        require_once __DIR__ . '/../../functions/integration_sync_control.php';
    }
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
            return array(
                'error' => 'integration_sync_paused',
                'error_description' => 'Отправка изменений в Битрикс24 отключена (Центр интеграции → пауза синхронизации).',
            );
        }
    }

    if ($config === null) {
        $config = require __DIR__ . '/config.php';
    }
    $url = null;

    if (isset($config['method_urls']) && isset($config['method_urls'][$method])) {
        $url = $config['method_urls'][$method];
    } else {
        $isCatalogMethod = strpos((string)$method, 'catalog.') === 0;
        $webhook = '';
        if ($isCatalogMethod && isset($config['catalog_webhook']) && trim((string)$config['catalog_webhook']) !== '') {
            $webhook = $config['catalog_webhook'];
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
        return array(
            'error' => 'network_error',
            'error_description' => 'Bitrix request failed for method ' . $method
        );
    }

    $decoded = json_decode((string)$result, true);
    if (is_array($decoded)) {
        return $decoded;
    }
    $raw = trim((string)$result);
    if ($raw === '') {
        return array(
            'error' => 'empty_response',
            'error_description' => 'Empty body from Bitrix (' . $method . ')'
        );
    }
    $preview = function_exists('mb_substr') ? mb_substr($raw, 0, 240) : substr($raw, 0, 240);
    return array(
        'error' => 'bad_json',
        'error_description' => 'Invalid JSON from Bitrix (' . $method . '): ' . $preview
    );
}