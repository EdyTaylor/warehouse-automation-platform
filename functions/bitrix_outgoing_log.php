<?php

function bitrixOutgoingLogEnsureSchema(PDO $db)
{
    $db->exec("
        CREATE TABLE IF NOT EXISTS bitrix_outgoing_log (
            id int NOT NULL AUTO_INCREMENT,
            method varchar(120) NOT NULL,
            endpoint varchar(1000) NOT NULL,
            request_payload MEDIUMTEXT NOT NULL,
            response_payload MEDIUMTEXT NOT NULL,
            status varchar(32) NOT NULL DEFAULT '',
            error_code varchar(120) DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_b24_out_created (created_at),
            KEY idx_b24_out_method (method)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

function bitrixOutgoingLogLimitText($value, $maxLen)
{
    $text = trim((string)$value);
    if ($text === '') {
        return '';
    }
    $limit = max(50, intval($maxLen));
    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        if (mb_strlen($text, 'UTF-8') > $limit) {
            $text = mb_substr($text, 0, $limit, 'UTF-8') . '…';
        }
    } elseif (strlen($text) > $limit) {
        $text = substr($text, 0, $limit) . '...';
    }
    return $text;
}

function bitrixOutgoingLogWrite(PDO $db, $method, $endpoint, $requestPayload, $responsePayload, $status, $errorCode)
{
    $methodStr = bitrixOutgoingLogLimitText($method, 120);
    $endpointStr = bitrixOutgoingLogLimitText($endpoint, 1000);
    $requestStr = bitrixOutgoingLogLimitText($requestPayload, 4000);
    $responseStr = bitrixOutgoingLogLimitText($responsePayload, 4000);
    $statusStr = bitrixOutgoingLogLimitText($status, 32);
    $errorCodeStr = bitrixOutgoingLogLimitText($errorCode, 120);
    if ($errorCodeStr === '') {
        $errorCodeStr = null;
    }

    $st = $db->prepare("
        INSERT INTO bitrix_outgoing_log
            (method, endpoint, request_payload, response_payload, status, error_code, created_at)
        VALUES
            (?, ?, ?, ?, ?, ?, NOW())
    ");
    $st->execute(array(
        $methodStr,
        $endpointStr,
        $requestStr,
        $responseStr,
        $statusStr,
        $errorCodeStr
    ));
}
