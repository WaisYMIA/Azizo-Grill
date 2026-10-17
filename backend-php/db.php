<?php
require_once __DIR__ . '/config.php';

function get_db(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    // Idempotent schema creation — safe to run on every request (CREATE TABLE
    // IF NOT EXISTS is cheap once the tables exist), so there's no separate
    // "migration" step to remember. schema.sql has the same thing for anyone
    // who'd rather import it by hand via phpMyAdmin.
    $pdo->exec(file_get_contents(__DIR__ . '/schema.sql'));

    return $pdo;
}
