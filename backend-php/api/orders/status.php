<?php
require_once __DIR__ . '/../../lib/response.php';
require_once __DIR__ . '/../../lib/auth_guard.php';
require_once __DIR__ . '/../../db.php';
cors_and_preflight();
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'PATCH') json_error('POST or PATCH required', 405);
require_admin();

$id = $_GET['id'] ?? '';
$body = read_json_body();
$status = $body['status'] ?? '';
$valid = ['received', 'preparing', 'ready', 'out_for_delivery', 'completed', 'cancelled'];
if (!in_array($status, $valid, true)) json_error('status must be one of: ' . implode(', ', $valid));

$pdo = get_db();
$result = $pdo->prepare('UPDATE orders SET status = ? WHERE id = ?');
$result->execute([$status, $id]);
if ($result->rowCount() === 0) json_error('Order not found', 404);

json_response(['ok' => true]);
