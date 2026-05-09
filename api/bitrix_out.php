<?php

function sendToBitrix($method, $data) {
    // Webhook URL should be loaded from environment variable or config file
    // DO NOT store webhooks directly in code
    $webhook = getenv('BITRIX_WEBHOOK_URL') ?: '';
    
    if (empty($webhook)) {
        return json_encode(['error' => 'Webhook URL not configured']);
    }

    $url = $webhook . $method;

    $options = [
        "http" => [
            "header"  => "Content-type: application/json",
            "method"  => "POST",
            "content" => json_encode($data),
        ],
    ];

    $context  = stream_context_create($options);
    return file_get_contents($url, false, $context);
}