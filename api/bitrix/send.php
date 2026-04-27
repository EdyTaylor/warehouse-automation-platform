<?php

function sendToBitrix($method, $data = []) {
    static $config = null;
    if ($config === null) {
        $config = require __DIR__ . '/config.php';
    }
    $webhook = $config['webhook'];

    $url = $webhook . $method;

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