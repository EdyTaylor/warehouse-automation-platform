<?php

function getDB() {
    return new PDO(
        'mysql:host=localhost;dbname=friendcrm_film;charset=utf8',
        'friendcrm_film',
        'friendcrm_film1',
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]
    );
}