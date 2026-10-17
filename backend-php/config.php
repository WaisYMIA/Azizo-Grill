<?php
/**
 * Azizo Butchery & Grill — backend configuration.
 *
 * On cPanel: edit the four DB_* values to match the database you create in
 * MySQL Databases (cPanel prefixes both the DB name and username with your
 * account username automatically, e.g. "cpaneluser_azizo"). Change
 * JWT_SECRET and ADMIN_PASSWORD to your own values before going live —
 * the ones below are development placeholders, not secrets to keep.
 */

// ---- Database ----
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'azizo_db');
define('DB_USER', getenv('DB_USER') ?: 'azizo_user');
define('DB_PASS', getenv('DB_PASS') ?: 'azizo_pass_test');

// ---- Auth ----
// Signs customer/admin login tokens. CHANGE THIS before going live — anyone
// who knows it can forge a valid admin token. Generate a real one with:
//   php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
define('JWT_SECRET', getenv('JWT_SECRET') ?: 'dev-only-secret-change-me-before-launch');

// Backend-level admin password (separate from the site's own Staff/Admin
// passcodes in app.js) — used by /api/auth/admin-login.php and required by
// every admin-only endpoint (product writes, order list, Excel import/export).
define('ADMIN_PASSWORD', getenv('ADMIN_PASSWORD') ?: 'majani90');

// ---- Uploads ----
// Where uploaded product photos are saved on disk, and the public URL path
// the frontend uses to display them. Must be writable by the web server
// (on cPanel, File Manager → right-click → Permissions → 755 is usually
// already correct for a folder you create yourself).
define('UPLOAD_DIR', __DIR__ . '/uploads/products/');
define('UPLOAD_URL_PATH', '/uploads/products/'); // relative to this backend's own base URL
define('MAX_UPLOAD_BYTES', 5 * 1024 * 1024); // 5MB per photo

// ---- CORS ----
// "*" works everywhere but allows any site to call this API. Once you know
// your real frontend domain, change this to that exact origin for tighter
// security (e.g. "https://azizobutchery.co.ke").
define('ALLOWED_ORIGIN', getenv('ALLOWED_ORIGIN') ?: '*');

// ---- M-Pesa Daraja (optional — leave blank until you have real credentials) ----
define('MPESA_CONSUMER_KEY', getenv('MPESA_CONSUMER_KEY') ?: '');
define('MPESA_CONSUMER_SECRET', getenv('MPESA_CONSUMER_SECRET') ?: '');
define('MPESA_SHORTCODE', getenv('MPESA_SHORTCODE') ?: '');
define('MPESA_PASSKEY', getenv('MPESA_PASSKEY') ?: '');
define('MPESA_ENV', getenv('MPESA_ENV') ?: 'sandbox'); // 'sandbox' or 'production'
define('MPESA_CALLBACK_URL', getenv('MPESA_CALLBACK_URL') ?: '');

date_default_timezone_set('Africa/Nairobi');
