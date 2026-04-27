<?php

function sendToBitrix($method, $data = []) {

    $webhook = "https://YOUR_DOMAIN.bitrix24.kz/rest/1/XXXXXXXX/";

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