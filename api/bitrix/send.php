<?php

function sendToBitrix($method, $data = []) {
    static $config = null;
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
        return [
            'error' => 'network_error',
            'error_description' => 'Bitrix request failed for method ' . $method
        ];
    }

    return json_decode($result, true);
}