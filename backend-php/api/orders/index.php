<?php
require_once __DIR__ . '/../../lib/response.php';
require_once __DIR__ . '/../../lib/jwt.php';
require_once __DIR__ . '/../../lib/auth_guard.php';
require_once __DIR__ . '/../../db.php';
cors_and_preflight();

$pdo = get_db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = read_json_body();
    $required = ['customer_phone', 'fulfilment', 'payment_method', 'total', 'items'];
    foreach ($required as $f) {
        if (empty($body[$f])) json_error("customer_phone, fulfilment, payment_method, total, and a non-empty items array are required");
    }
    if (!is_array($body['items']) || count($body['items']) === 0) {
        json_error('customer_phone, fulfilment, payment_method, total, and a non-empty items array are required');
    }

    $customerId = null;
    $token = bearer_token();
    if ($token) {
        $payload = jwt_verify($token);
        if ($payload && ($payload['role'] ?? null) === 'customer') $customerId = $payload['id'];
    }

    $pdo->beginTransaction();
    try {
        $pdo->prepare('INSERT INTO orders (customer_id, customer_name, customer_phone, fulfilment, branch, pickup_time,
            delivery_address, delivery_lat, delivery_lng, delivery_fee, payment_method, promo_code, items_subtotal, total)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([
                $customerId, $body['customer_name'] ?? null, $body['customer_phone'], $body['fulfilment'],
                $body['branch'] ?? null, $body['pickup_time'] ?? null, $body['delivery_address'] ?? null,
                $body['delivery_lat'] ?? null, $body['delivery_lng'] ?? null, $body['delivery_fee'] ?? 0,
                $body['payment_method'], $body['promo_code'] ?? null, $body['items_subtotal'] ?? $body['total'], $body['total'],
            ]);
        $orderId = (int)$pdo->lastInsertId();

        $itemStmt = $pdo->prepare('INSERT INTO order_items (order_id, product_id, name, qty, unit, unit_price, line_total) VALUES (?, ?, ?, ?, ?, ?, ?)');
        foreach ($body['items'] as $item) {
            $itemStmt->execute([$orderId, $item['id'] ?? null, $item['name'], $item['qty'], $item['unit'] ?? '', $item['price'], $item['price'] * $item['qty']]);
        }
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        json_error('Could not save the order: ' . $e->getMessage(), 500);
    }

    json_response(['ok' => true, 'orderId' => $orderId], 201);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    require_admin();
    $conditions = []; $params = [];
    if (!empty($_GET['status'])) { $conditions[] = 'status = ?'; $params[] = $_GET['status']; }
    if (!empty($_GET['phone'])) { $conditions[] = 'customer_phone LIKE ?'; $params[] = '%' . $_GET['phone'] . '%'; }
    $sql = 'SELECT * FROM orders' . ($conditions ? ' WHERE ' . implode(' AND ', $conditions) : '') . ' ORDER BY created_at DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    json_response(['orders' => $stmt->fetchAll()]);
}

json_error('Method not allowed', 405);
