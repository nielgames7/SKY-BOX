<?php
declare(strict_types=1);

date_default_timezone_set('America/La_Paz');

try {
    $pdo = new PDO(
        'mysql:host=localhost;dbname=skybox_db;charset=utf8mb4',
        'root',
        '',
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    $pdo = null;
}
