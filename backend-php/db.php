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

    // v2.0: first run only - load the starter recipes and blog posts.
    if ((int)$pdo->query('SELECT COUNT(*) FROM recipes')->fetchColumn() === 0) {
        $seed = json_decode((string)@file_get_contents(__DIR__ . '/data/recipes-seed.json'), true);
        if (is_array($seed)) {
            $ic = $pdo->prepare('INSERT IGNORE INTO recipe_cats (name, icon, sort_order) VALUES (?, ?, ?)');
            foreach ($seed['categories'] as $c) $ic->execute([$c['name'], $c['icon'] ?? '', (int)$c['sort']]);
            $ir = $pdo->prepare('INSERT INTO recipes (kind, slug, title, category, style, cuisine, difficulty, prep_min, cook_min, servings, summary, ingredients, steps, tips, body, tags, badge, image, featured, status, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
            foreach ($seed['recipes'] as $r) {
                $ir->execute([$r['kind'], $r['slug'], $r['title'], $r['category'], $r['style'], $r['cuisine'], $r['difficulty'], (int)$r['prep_min'], (int)$r['cook_min'], (int)$r['servings'], $r['summary'],
                    implode("\n", $r['ingredients'] ?? []), implode("\n", $r['steps'] ?? []), $r['tips'] ?? '', $r['body'] ?? '', $r['tags'] ?? '', $r['badge'] ?? '', $r['image'] ?? '', !empty($r['featured']) ? 1 : 0, 'published', $r['created_at'] . ' 00:00:00', $r['created_at'] . ' 00:00:00']);
            }
        }
    }

    return $pdo;
}
