<?php

function getDB() {
    // Database credentials should be loaded from environment variables
    // or secure configuration file not committed to version control
    $host = getenv('DB_HOST') ?: 'localhost';
    $dbname = getenv('DB_NAME') ?: 'database_name';
    $user = getenv('DB_USER') ?: 'user';
    $pass = getenv('DB_PASS') ?: 'password';
    
    return new PDO(
        'mysql:host=' . $host . ';dbname=' . $dbname . ';charset=utf8',
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]
    );
}