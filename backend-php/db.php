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

    // Databases created before v1.2 already have these tables without the new cost columns; add them once.
    foreach ([['products', 'cost_price'], ['order_items', 'cost_price'], ['products', 'in_stock'], ['orders', 'notes'], ['orders', 'area'], ['orders', 'recurring']] as [$table, $col]) {
        $has = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
        $has->execute([$table, $col]);
        if (!(int)$has->fetchColumn()) {
            $def = $col === 'in_stock' ? 'TINYINT(1) NOT NULL DEFAULT 1' : ($col === 'notes' ? 'VARCHAR(300) NULL' : (in_array($col, ['area', 'recurring'], true) ? 'VARCHAR(80) NULL' : 'INT NOT NULL DEFAULT 0'));
            $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$col` $def");
        }
    }

    return $pdo;
}
