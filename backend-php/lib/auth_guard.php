<?php
require_once __DIR__ . '/jwt.php';
require_once __DIR__ . '/response.php';

/** Returns the verified token payload, or sends a 401 and exits. */
function require_auth(): array {
    $token = bearer_token();
    if (!$token) json_error('Missing or invalid Authorization header', 401);
    $payload = jwt_verify($token);
    if (!$payload) json_error('Invalid or expired token', 401);
    return $payload;
}

/** Same as require_auth(), but additionally requires role === 'admin'. */
function require_admin(): array {
    $payload = require_auth();
    if (($payload['role'] ?? null) !== 'admin') json_error('Admin access required', 403);
    return $payload;
}
