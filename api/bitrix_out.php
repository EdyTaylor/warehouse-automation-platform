<?php

function sendToBitrix($method, $data) {
    $webhook = "https://YOUR_BITRIX/webhook/";

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