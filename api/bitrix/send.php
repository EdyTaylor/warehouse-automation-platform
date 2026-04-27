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
        $webhook = isset($config['webhook']) ? $config['webhook'] : '';
        $url = rtrim($webhook, '/') . '/' . $method;
    }

    $options = [
        "http" => [
            "header"  => "Content-type: application/json",
            "method"  => "POST",
            "content" => json_encode($data)
        ]
    ];

    $result = file_get_contents($url, false, stream_context_create($options));

    return json_decode($result, true);
}