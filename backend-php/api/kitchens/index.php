<?php
// Azizo Kitchens API (mirrors backend/routes/kitchens.js), routed by .htaccess to ?p=<path after /api/kitchens>
require_once __DIR__ . '/../../lib/response.php';
require_once __DIR__ . '/../../lib/auth_guard.php';
require_once __DIR__ . '/../../db.php';
cors_and_preflight();
$pdo = get_db();
header('Cache-Control: no-store');
$method = $_SERVER['REQUEST_METHOD'];
$P = array_values(array_filter(explode('/', trim((string)($_GET['p'] ?? ''), '/')), fn($x) => $x !== ''));
$body = in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true) ? read_json_body() : [];
$route = $method . ' ' . implode('/', array_map(fn($x) => ctype_digit($x) ? ':id' : $x, $P));

$clean = fn($v, $max) => mb_substr(trim(preg_replace('/[\x00-\x08\x0b\x0c\x0e-\x1f]/', ' ', (string)($v ?? ''))), 0, $max);
$slugify = function ($t) { $s = substr(trim(preg_replace('/[^a-z0-9]+/', '-', strtolower((string)$t)), '-'), 0, 60); return $s ?: 'kitchen'; };
$last9 = fn($s) => substr(preg_replace('/\D/', '', (string)$s), -9);
$lines = fn($t) => array_values(array_filter(array_map('trim', preg_split('/\r?\n/', (string)$t)), fn($x) => $x !== ''));
$jp = function ($t, $d) { $v = json_decode((string)$t, true); return $v === null ? $d : $v; };
$one = function ($sql, $a = []) use ($pdo) { $q = $pdo->prepare($sql); $q->execute($a); return $q->fetch(); };
$all = function ($sql, $a = []) use ($pdo) { $q = $pdo->prepare($sql); $q->execute($a); return $q->fetchAll(); };
$run = function ($sql, $a = []) use ($pdo) { $q = $pdo->prepare($sql); $q->execute($a); return $q; };
$val = fn($sql, $a = []) => ($r = $one($sql, $a)) ? (int)array_values($r)[0] : 0;
$now = fn() => gmdate('Y-m-d H:i:s');
$f = fn($v) => (float)$v;
$bool = fn($v) => !empty($v) && $v !== 'false' && $v !== '0';

// ---- settings / time / geo ----
$DEF = ['commissionPct' => 20, 'lunch' => ['label' => 'Lunch', 'from' => '12:00', 'to' => '14:00', 'cutoff' => '10:30'], 'dinner' => ['label' => 'Dinner', 'from' => '18:00', 'to' => '20:00', 'cutoff' => '16:30'],
    'fee' => ['base' => 100, 'perKm' => 25, 'bandKm' => 2, 'max' => 400], 'defaultRadiusKm' => 4, 'roadFactor' => 1.3, 'zoneCap' => 4, 'zoneCaps' => new stdClass(), 'minOrder' => 300, 'holdMinutes' => 120];
$settings = function () use ($one, $jp, $DEF) {
    $r = $one("SELECT svalue FROM kk_meta WHERE skey = 'kk_settings'"); $s = $r ? $jp($r['svalue'], []) : [];
    $o = array_merge($DEF, $s);
    $o['lunch'] = array_merge($DEF['lunch'], $s['lunch'] ?? []); $o['dinner'] = array_merge($DEF['dinner'], $s['dinner'] ?? []); $o['fee'] = array_merge($DEF['fee'], $s['fee'] ?? []);
    $o['zoneCaps'] = (array)($s['zoneCaps'] ?? []);
    return $o;
};
$S = $settings();
$hm = function ($t) { [$h, $m] = array_map('intval', explode(':', $t . ':0')); return $h * 60 + $m; };
$nowEAT = function () { $t = time() + TZ_OFFSET_HOURS * 3600; return ['date' => gmdate('Y-m-d', $t), 'min' => (int)gmdate('G', $t) * 60 + (int)gmdate('i', $t)]; };
$addDays = fn($d, $n) => gmdate('Y-m-d', strtotime($d . ' UTC') + $n * 86400);
$slotOpen = function ($date, $meal) use ($S, $nowEAT, $addDays, $hm) {
    $n = $nowEAT(); if (!in_array($meal, ['lunch', 'dinner'], true)) return [false, 'Choose lunch or dinner'];
    if ($date < $n['date'] || $date > $addDays($n['date'], 2)) return [false, 'Choose today, tomorrow or the day after'];
    if ($date === $n['date'] && $n['min'] >= $hm($S[$meal]['cutoff'])) return [false, $S[$meal]['label'] . ' orders for today closed at ' . $S[$meal]['cutoff']];
    return [true, ''];
};
$haversine = function ($a, $b, $c, $d) { $r = M_PI / 180; $dl = ($c - $a) * $r; $dg = ($d - $b) * $r; $h = sin($dl / 2) ** 2 + cos($a * $r) * cos($c * $r) * sin($dg / 2) ** 2; return 2 * 6371 * asin(sqrt($h)); };
$roadKm = fn($k, $lat, $lng) => round($haversine((float)$k['lat'], (float)$k['lng'], $lat, $lng) * (float)$S['roadFactor'] * 10) / 10;
$feeFor = function ($km) use ($S) { $band = max($S['fee']['bandKm'], ceil($km / $S['fee']['bandKm']) * $S['fee']['bandKm']); return (int)min($S['fee']['max'], round(($S['fee']['base'] + $S['fee']['perKm'] * $band) / 10) * 10); };
$point = function ($b) use ($one) {
    if (!empty($b['areaId'])) { $a = $one('SELECT lat, lng, name, region FROM areas WHERE id = ?', [(string)$b['areaId']]); if ($a) return ['lat' => (float)$a['lat'], 'lng' => (float)$a['lng'], 'area' => $a]; }
    if (isset($b['lat'], $b['lng']) && is_numeric($b['lat']) && is_numeric($b['lng'])) { $la = (float)$b['lat']; $ln = (float)$b['lng']; if (abs($la) <= 90 && abs($ln) <= 180 && ($la || $ln)) return ['lat' => $la, 'lng' => $ln, 'area' => null]; }
    return null;
};
// ---- shapes ----
$dishPub = fn($d) => ['id' => (int)$d['id'], 'slug' => $d['slug'], 'name' => $d['name'], 'tradition' => $d['tradition'], 'category' => $d['category'], 'meal' => $d['meal'], 'price' => (int)$d['price'], 'serves' => (int)$d['serves'], 'description' => $d['description'], 'image' => $d['image'], 'options' => $jp($d['options'], [])];
$dishFull = fn($d) => $dishPub($d) + ['components' => $lines($d['components']), 'method' => $lines($d['method']), 'packaging' => $d['packaging'], 'tips' => $d['tips'], 'active' => (bool)$d['active'], 'sort' => (int)$d['sort_order']];
$rating = function ($id) use ($one) { $r = $one('SELECT COUNT(*) AS n, AVG(rating) AS a FROM kk_orders WHERE kitchen_id = ? AND rating > 0', [$id]); return ['avg' => (int)$r['n'] ? round((float)$r['a'], 1) : 0, 'count' => (int)$r['n']]; };
$firstName = function ($n) use ($clean) { $p = preg_split('/\s+/', $clean($n, 60)); return $p[0] ?: 'Cook'; };
$kPub = function ($k) use ($rating, $firstName) { $r = $rating($k['id']); return ['id' => (int)$k['id'], 'slug' => $k['slug'], 'name' => $k['name'], 'cook' => $firstName($k['cook_name']), 'bio' => $k['bio'], 'specialities' => $k['specialities'], 'region' => $k['region'], 'rating' => $r['avg'], 'ratingCount' => $r['count'], 'prepMin' => (int)$k['prep_min']]; };
$queuePos = fn($k) => $k['status'] !== 'queued' ? 0 : $val("SELECT COUNT(*) FROM kk_kitchens WHERE status = 'queued' AND region = ? AND id <= ?", [$k['region'], $k['id']]);
$LIVE = "status IN ('pilot','live') AND paused = 0";
$slotUsed = fn($kid, $date, $meal) => $val("SELECT COUNT(*) FROM kk_orders WHERE kitchen_id = ? AND date = ? AND meal = ? AND status NOT IN ('cancelled','rejected')", [$kid, $date, $meal]);
$limit = function ($max, $tag) {
    $file = sys_get_temp_dir() . '/azizo_kk_' . $tag . md5($_SERVER['REMOTE_ADDR'] ?? 'x') . '.json'; $now = time();
    $hits = is_file($file) ? (json_decode((string)@file_get_contents($file), true) ?: []) : []; $hits = array_values(array_filter($hits, fn($t) => $now - $t < 3600));
    if (count($hits) >= $max) json_error('Too many attempts - please try again later', 429);
    $hits[] = $now; @file_put_contents($file, json_encode($hits));
};
// ---- images / docs ----
$readImage = function ($data, $maxBytes) {
    if (!preg_match('#^data:image/(jpeg|png|webp);base64,([A-Za-z0-9+/=]+)$#', (string)$data, $m)) return ['error' => 'Upload a JPG, PNG or WebP image'];
    $buf = base64_decode($m[2], true); if ($buf === false || strlen($buf) > $maxBytes) return ['error' => 'Image is too large'];
    $ok = ($m[1] === 'jpeg' && substr($buf, 0, 2) === "\xFF\xD8") || ($m[1] === 'png' && substr($buf, 1, 3) === 'PNG') || ($m[1] === 'webp' && substr($buf, 0, 4) === 'RIFF');
    if (!$ok) return ['error' => 'That file is not a valid image'];
    return ['buf' => $buf, 'ext' => $m[1] === 'jpeg' ? 'jpg' : $m[1]];
};
$PRIVATE = __DIR__ . '/../../private/kitchen-docs';
$DOC_TYPES = ['id', 'permit', 'health', 'kra', 'premises', 'other'];
$addDocs = function ($k, $b) use ($jp, $clean, $readImage, $PRIVATE, $DOC_TYPES, $slugify) {
    $docs = $jp($k['docs'], []); $nums = is_array($b['docNumbers'] ?? null) ? $b['docNumbers'] : [];
    foreach ($DOC_TYPES as $t) if (!empty($nums[$t]) && (!empty($nums[$t]['no']) || !empty($nums[$t]['expiry']))) {
        $idx = null; foreach ($docs as $i => $d) if ($d['type'] === $t && empty($d['file'])) $idx = $i;
        if ($idx === null) { $docs[] = ['type' => $t]; $idx = count($docs) - 1; }
        $docs[$idx]['no'] = $clean($nums[$t]['no'] ?? '', 60); $docs[$idx]['expiry'] = $clean($nums[$t]['expiry'] ?? '', 12);
    }
    foreach (array_slice(is_array($b['docImages'] ?? null) ? $b['docImages'] : [], 0, 6) as $im) {
        if (!is_array($im) || !in_array($im['type'] ?? '', $DOC_TYPES, true)) continue;
        $r = $readImage($im['data'] ?? '', 1500 * 1024); if (isset($r['error'])) return ['error' => $im['type'] . ': ' . $r['error']];
        if (!is_dir($PRIVATE)) @mkdir($PRIVATE, 0755, true);
        $file = 'k' . $k['id'] . '-' . $slugify($im['type']) . '-' . bin2hex(random_bytes(5)) . '.' . $r['ext']; file_put_contents($PRIVATE . '/' . $file, $r['buf']);
        $docs[] = ['type' => $im['type'], 'file' => $file, 'uploaded' => gmdate('Y-m-d')];
    }
    return ['docs' => $docs];
};
$statement = function ($kid) use ($one, $all, $val) {
    $e = $one("SELECT COUNT(*) AS n, COALESCE(SUM(subtotal),0) AS gross, COALESCE(SUM(commission_amt),0) AS comm, COALESCE(SUM(kitchen_amt),0) AS net FROM kk_orders WHERE kitchen_id = ? AND status = 'delivered'", [$kid]);
    $paid = $val('SELECT COALESCE(SUM(amount),0) FROM kk_payouts WHERE kitchen_id = ?', [$kid]);
    return ['delivered' => (int)$e['n'], 'gross' => (int)$e['gross'], 'commission' => (int)$e['comm'], 'earned' => (int)$e['net'], 'paid' => $paid, 'balance' => (int)$e['net'] - $paid,
        'payouts' => $all('SELECT amount, reference, note, created_at FROM kk_payouts WHERE kitchen_id = ? ORDER BY id DESC LIMIT 50', [$kid])];
};
$itemsOf = fn($oid) => array_map(fn($i) => ['name' => $i['name'], 'qty' => (int)$i['qty'], 'options' => $i['options_text'], 'total' => (int)$i['line_total']], $all('SELECT name, qty, options_text, line_total FROM kk_order_items WHERE order_id = ?', [$oid]));
$int = fn($v, $d, $lo, $hi) => max($lo, min($hi, is_numeric($v) ? (int)$v : $d));
$num = fn($v, $d, $lo, $hi) => max($lo, min($hi, is_numeric($v) ? (float)$v : $d));

// ================= PUBLIC =================
if ($route === 'GET config') {
    $trad = array_values(array_filter(array_column($all('SELECT DISTINCT tradition FROM kk_dishes WHERE active = 1 ORDER BY tradition'), 'tradition')));
    $cat = array_column($all('SELECT category, MIN(sort_order) AS s FROM kk_dishes WHERE active = 1 GROUP BY category ORDER BY s'), 'category');
    json_response(['lunch' => $S['lunch'], 'dinner' => $S['dinner'], 'fee' => $S['fee'], 'minOrder' => $S['minOrder'], 'holdMinutes' => $S['holdMinutes'], 'now' => $nowEAT(), 'tz' => TZ_OFFSET_HOURS, 'traditions' => $trad, 'categories' => $cat]);
}
if ($route === 'GET areas') json_response(['areas' => array_map(fn($a) => ['id' => $a['id'], 'region' => $a['region'], 'name' => $a['name'], 'lat' => (float)$a['lat'], 'lng' => (float)$a['lng']], $all('SELECT id, region, name, lat, lng FROM areas ORDER BY region, name'))]);
if ($route === 'GET dishes') json_response(['dishes' => array_map($dishPub, $all('SELECT * FROM kk_dishes WHERE active = 1 ORDER BY sort_order'))]);
if ($route === 'GET search') {
    $p = $point(['areaId' => $_GET['area'] ?? '', 'lat' => $_GET['lat'] ?? null, 'lng' => $_GET['lng'] ?? null]);
    if (!$p) json_error('Choose your area or share your location');
    $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date'] ?? '') ? $_GET['date'] : $nowEAT()['date']; $out = [];
    foreach ($all("SELECT * FROM kk_kitchens WHERE $LIVE AND lat IS NOT NULL") as $k) {
        $km = $roadKm($k, $p['lat'], $p['lng']); if ($km > (float)$k['radius_km']) continue;
        $menu = array_map($dishPub, $all('SELECT d.* FROM kk_kitchen_dishes kd JOIN kk_dishes d ON d.id = kd.dish_id WHERE kd.kitchen_id = ? AND kd.available = 1 AND d.active = 1 ORDER BY d.sort_order', [$k['id']]));
        if (!$menu) continue; $slots = [];
        foreach (['lunch', 'dinner'] as $m) { [$ok, $why] = $slotOpen($date, $m); $slots[$m] = ['open' => $ok, 'why' => $why, 'left' => max(0, (int)$k['capacity'] - $slotUsed($k['id'], $date, $m))]; }
        $out[] = $kPub($k) + ['distanceKm' => $km, 'fee' => $feeFor($km), 'minOrder' => $S['minOrder'], 'slots' => $slots, 'menu' => $menu];
    }
    usort($out, fn($a, $b) => $a['distanceKm'] <=> $b['distanceKm']);
    json_response(['date' => $date, 'area' => $p['area'] ? ['name' => $p['area']['name'], 'region' => $p['area']['region']] : null, 'kitchens' => $out]);
}
if ($route === 'POST orders') {
    $limit(10, 'ord'); $b = $body; if (!empty($b['website'])) json_response(['ok' => true], 201);
    $k = $one("SELECT * FROM kk_kitchens WHERE id = ? AND $LIVE", [(int)($b['kitchenId'] ?? 0)]); if (!$k) json_error('This kitchen is not taking orders right now', 404);
    $name = $clean($b['name'] ?? '', 60); $phone = $clean($b['phone'] ?? '', 20); $address = $clean($b['address'] ?? '', 200);
    if (mb_strlen($name) < 2) json_error('Enter your name'); if (strlen($last9($phone)) < 9) json_error('Enter a valid phone number'); if (mb_strlen($address) < 4) json_error('Enter your delivery address');
    $p = $point($b); if (!$p) json_error('Choose your area or share your location');
    $km = $roadKm($k, $p['lat'], $p['lng']); if ($km > (float)$k['radius_km']) json_error('Sorry, this kitchen does not deliver that far');
    $date = $clean($b['date'] ?? '', 10); $meal = $clean($b['meal'] ?? '', 8); [$ok, $why] = $slotOpen($date, $meal); if (!$ok) json_error($why);
    if ($slotUsed($k['id'], $date, $meal) >= (int)$k['capacity']) json_error('This kitchen is fully booked for that slot. Try another kitchen or time', 409);
    $items = is_array($b['items'] ?? null) ? array_slice($b['items'], 0, 30) : []; if (!$items) json_error('Your basket is empty');
    $rows = []; $sub = 0;
    foreach ($items as $it) {
        $d = $one('SELECT d.* FROM kk_kitchen_dishes kd JOIN kk_dishes d ON d.id = kd.dish_id WHERE kd.kitchen_id = ? AND kd.dish_id = ? AND kd.available = 1 AND d.active = 1', [$k['id'], (int)($it['dishId'] ?? 0)]);
        if (!$d) json_error('One of the dishes is no longer available from this kitchen');
        if ($d['meal'] !== 'both' && $d['meal'] !== $meal) json_error($d['name'] . ' is not served at ' . $meal);
        $qty = max(1, min(20, (int)($it['qty'] ?? 1))); $unit = (int)$d['price']; $picked = [];
        foreach ($jp($d['options'], []) as $g) {
            $want = $it['options'][$g['name']] ?? null; $ch = null; foreach ($g['choices'] as $c) if ($c['name'] === $want) $ch = $c;
            if (!empty($g['required']) && !$ch) json_error('Choose ' . strtolower($g['name']) . ' for ' . $d['name']);
            if ($ch) { $unit += (int)($ch['price'] ?? 0); $picked[] = $g['name'] . ': ' . $ch['name']; }
        }
        $rows[] = ['d' => $d, 'qty' => $qty, 'unit' => $unit, 'text' => implode(', ', $picked), 'total' => $unit * $qty]; $sub += $unit * $qty;
    }
    if ($sub < $S['minOrder']) json_error('Minimum order is KSh ' . $S['minOrder'] . ' of food');
    $fee = $feeFor($km); $total = $sub + $fee; $pct = $k['commission_pct'] === null ? (float)$S['commissionPct'] : (float)$k['commission_pct']; $comm = (int)round($sub * $pct / 100);
    $pdo->beginTransaction();
    try {
        $run('INSERT INTO kk_orders (kitchen_id, name, phone, address, area_id, lat, lng, distance_km, date, meal, notes, subtotal, delivery_fee, total, payment, commission_pct, commission_amt, kitchen_amt, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [$k['id'], $name, $phone, $address, (string)($b['areaId'] ?? ''), $p['lat'], $p['lng'], $km, $date, $meal, $clean($b['notes'] ?? '', 300), $sub, $fee, $total, ($b['payment'] ?? '') === 'mpesa' ? 'mpesa' : 'cash', $pct, $comm, $sub - $comm, $now(), $now()]);
        $oid = (int)$pdo->lastInsertId();
        foreach ($rows as $l) $run('INSERT INTO kk_order_items (order_id, dish_id, name, qty, unit_price, options_text, line_total) VALUES (?,?,?,?,?,?,?)', [$oid, $l['d']['id'], $l['d']['name'], $l['qty'], $l['unit'], $l['text'], $l['total']]);
        $pdo->commit();
    } catch (Throwable $e) { $pdo->rollBack(); json_error('Could not save your order, please try again', 500); }
    json_response(['ok' => true, 'id' => $oid, 'total' => $total, 'fee' => $fee, 'kitchen' => $k['name'], 'window' => $S[$meal]['from'] . '-' . $S[$meal]['to'], 'message' => 'Order received. Your kitchen will confirm shortly.'], 201);
}
if ($route === 'GET orders/track') {
    $limit(60, 'trk'); $o = $one('SELECT * FROM kk_orders WHERE id = ?', [(int)($_GET['id'] ?? 0)]);
    if (!$o || $last9($o['phone']) !== $last9($_GET['phone'] ?? '')) json_error('We could not find that order and phone number', 404);
    $k = $one('SELECT name FROM kk_kitchens WHERE id = ?', [$o['kitchen_id']]);
    json_response(['id' => (int)$o['id'], 'status' => $o['status'], 'kitchen' => $k['name'] ?? '', 'date' => $o['date'], 'meal' => $o['meal'], 'window' => isset($S[$o['meal']]) ? $S[$o['meal']]['from'] . '-' . $S[$o['meal']]['to'] : '', 'total' => (int)$o['total'], 'fee' => (int)$o['delivery_fee'], 'payment' => $o['payment'], 'rating' => (int)$o['rating'], 'reason' => $o['reason'], 'items' => $itemsOf($o['id'])]);
}
if ($route === 'POST orders/:id/rate') {
    $limit(20, 'rate'); $o = $one('SELECT * FROM kk_orders WHERE id = ?', [(int)$P[1]]);
    if (!$o || $last9($o['phone']) !== $last9($body['phone'] ?? '')) json_error('We could not find that order and phone number', 404);
    if ($o['status'] !== 'delivered') json_error('You can rate an order once it is delivered'); if ((int)$o['rating']) json_error('This order is already rated. Thank you!', 409);
    $r = (int)($body['rating'] ?? 0); if ($r < 1 || $r > 5) json_error('Choose 1 to 5 stars');
    $run('UPDATE kk_orders SET rating = ?, review = ?, updated_at = ? WHERE id = ?', [$r, $clean($body['comment'] ?? '', 500), $now(), $o['id']]); json_response(['ok' => true]);
}
if ($route === 'POST apply') {
    $limit(6, 'app'); $b = $body; if (!empty($b['website'])) json_response(['ok' => true], 201);
    $name = $clean($b['name'] ?? '', 80); $cook = $clean($b['cookName'] ?? '', 80); $phone = $clean($b['phone'] ?? '', 20); $email = strtolower($clean($b['email'] ?? '', 100)); $pass = (string)($b['password'] ?? '');
    if (mb_strlen($name) < 3) json_error('Give your kitchen a name (at least 3 letters)'); if (mb_strlen($cook) < 2) json_error("Enter the cook's name");
    if (strlen($last9($phone)) < 9) json_error('Enter a valid phone number'); if (!filter_var($email, FILTER_VALIDATE_EMAIL)) json_error('Enter a valid email address'); if (strlen($pass) < 6) json_error('Password must be at least 6 characters');
    if ($one("SELECT 1 FROM kk_kitchens WHERE email = ? OR RIGHT(REPLACE(phone, ' ', ''), 9) = ?", [$email, $last9($phone)])) json_error('A kitchen with this email or phone is already registered. Please log in', 409);
    $p = $point($b); if (!$p) json_error('Choose your area and drop your kitchen pin (or share your location)');
    $area = !empty($b['areaId']) ? $one('SELECT region, name FROM areas WHERE id = ?', [(string)$b['areaId']]) : null; $region = $area ? $area['region'] : $clean($b['region'] ?? '', 60);
    if ($clean($b['address'] ?? '', 200) === '') json_error('Enter the kitchen address or landmark'); if (empty($b['agree'])) json_error('Please accept the partner terms');
    $slug = $slugify($name); $n = 1; while ($one('SELECT 1 FROM kk_kitchens WHERE slug = ?', [$slug])) $slug = $slugify($name) . '-' . (++$n);
    $cap = (int)($S['zoneCaps'][$region] ?? $S['zoneCap']); $status = $val("SELECT COUNT(*) FROM kk_kitchens WHERE region = ? AND status IN ('pilot','live')", [$region]) >= $cap ? 'queued' : 'applied';
    $run('INSERT INTO kk_kitchens (slug, name, cook_name, phone, email, password_hash, bio, specialities, experience, address, area_id, region, lat, lng, radius_km, capacity, status, vetting, docs, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
        [$slug, $name, $cook, $phone, $email, password_hash($pass, PASSWORD_BCRYPT), $clean($b['bio'] ?? '', 600), $clean($b['specialities'] ?? '', 200), $clean($b['experience'] ?? '', 300), $clean($b['address'] ?? '', 200), (string)($b['areaId'] ?? ''), $region, $p['lat'], $p['lng'], $S['defaultRadiusKm'], $int($b['capacity'] ?? 10, 10, 1, 60), $status, '{}', '[]', $now(), $now()]);
    $k = $one('SELECT * FROM kk_kitchens WHERE id = ?', [(int)$pdo->lastInsertId()]);
    $dd = $addDocs($k, $b); if (isset($dd['error'])) { $run('DELETE FROM kk_kitchens WHERE id = ?', [$k['id']]); json_error($dd['error']); }
    $run('UPDATE kk_kitchens SET docs = ? WHERE id = ?', [json_encode($dd['docs']), $k['id']]);
    json_response(['ok' => true, 'status' => $status, 'queuePosition' => $queuePos(['status' => $status] + $k), 'token' => jwt_sign(['role' => 'kitchen', 'kitchenId' => (int)$k['id']], 14 * 86400),
        'message' => $status === 'queued' ? 'Thank you! Your area is full right now, so you are on the waiting list. We will contact you when a place opens.' : 'Thank you! Your application is in. We will contact you to arrange a tasting and kitchen visit.'], 201);
}

// ================= PARTNER =================
if ($route === 'POST partner/login') {
    $limit(30, 'plog'); $id = strtolower($clean($body['login'] ?? '', 100));
    $k = $one("SELECT * FROM kk_kitchens WHERE email = ? OR RIGHT(REPLACE(phone, ' ', ''), 9) = ?", [$id, $last9($id)]);
    if (!$k || !password_verify((string)($body['password'] ?? ''), (string)$k['password_hash'])) json_error('Incorrect email/phone or password', 401);
    if (in_array($k['status'], ['delisted', 'rejected'], true)) json_error('This kitchen account is not active. Please contact Azizo.', 403);
    json_response(['token' => jwt_sign(['role' => 'kitchen', 'kitchenId' => (int)$k['id']], 14 * 86400)]);
}
$partnerOrders = fn($kid, $where) => array_map(fn($o) => ['id' => (int)$o['id'], 'status' => $o['status'], 'customer' => $o['name'], 'phone' => $o['phone'], 'address' => $o['address'], 'date' => $o['date'], 'meal' => $o['meal'], 'notes' => $o['notes'], 'subtotal' => (int)$o['subtotal'], 'fee' => (int)$o['delivery_fee'], 'total' => (int)$o['total'], 'payment' => $o['payment'], 'earn' => (int)$o['kitchen_amt'], 'commission' => (int)$o['commission_amt'], 'rating' => (int)$o['rating'], 'review' => $o['review'], 'created' => $o['created_at'], 'reason' => $o['reason'], 'items' => $itemsOf($o['id'])],
    $all("SELECT * FROM kk_orders WHERE kitchen_id = ? $where ORDER BY id DESC LIMIT 200", [$kid]));
if ($P && $P[0] === 'partner') {
    $auth = require_kitchen(); $kid = (int)$auth['kitchenId'];
    if ($route === 'GET partner/me') {
        $k = $one('SELECT * FROM kk_kitchens WHERE id = ?', [$kid]); if (!$k) json_error('Kitchen not found', 404); $r = $rating($kid);
        json_response(['kitchen' => ['id' => (int)$k['id'], 'name' => $k['name'], 'cook' => $k['cook_name'], 'phone' => $k['phone'], 'email' => $k['email'], 'bio' => $k['bio'], 'specialities' => $k['specialities'], 'address' => $k['address'], 'region' => $k['region'], 'radiusKm' => (float)$k['radius_km'], 'capacity' => (int)$k['capacity'], 'prepMin' => (int)$k['prep_min'], 'status' => $k['status'], 'paused' => (bool)$k['paused'], 'strikes' => (int)$k['strikes'], 'queuePosition' => $queuePos($k), 'rating' => $r['avg'], 'ratingCount' => $r['count'], 'commissionPct' => $k['commission_pct'] === null ? (float)$S['commissionPct'] : (float)$k['commission_pct'],
            'docs' => array_map(fn($d) => ['type' => $d['type'], 'no' => $d['no'] ?? '', 'expiry' => $d['expiry'] ?? '', 'hasFile' => !empty($d['file'])], $jp($k['docs'], []))],
            'dishes' => array_map(fn($d) => $dishFull($d) + ['available' => (bool)$d['available']], $all('SELECT d.*, kd.available FROM kk_kitchen_dishes kd JOIN kk_dishes d ON d.id = kd.dish_id WHERE kd.kitchen_id = ? AND d.active = 1 ORDER BY d.sort_order', [$kid])),
            'allDishes' => $all('SELECT id, name, tradition, category, price FROM kk_dishes WHERE active = 1 ORDER BY sort_order'), 'settings' => ['lunch' => $S['lunch'], 'dinner' => $S['dinner'], 'holdMinutes' => $S['holdMinutes']], 'statement' => $statement($kid)]);
    }
    if ($route === 'PATCH partner/me') {
        $k = $one('SELECT * FROM kk_kitchens WHERE id = ?', [$kid]); $b = $body;
        $run('UPDATE kk_kitchens SET bio = ?, phone = ?, capacity = ?, prep_min = ?, paused = ?, specialities = ?, updated_at = ? WHERE id = ?', [
            array_key_exists('bio', $b) ? $clean($b['bio'], 600) : $k['bio'], array_key_exists('phone', $b) ? $clean($b['phone'], 20) : $k['phone'], array_key_exists('capacity', $b) ? $int($b['capacity'], $k['capacity'], 1, 60) : $k['capacity'],
            array_key_exists('prepMin', $b) ? $int($b['prepMin'], $k['prep_min'], 10, 180) : $k['prep_min'], array_key_exists('paused', $b) ? ($b['paused'] ? 1 : 0) : $k['paused'], array_key_exists('specialities', $b) ? $clean($b['specialities'], 200) : $k['specialities'], $now(), $kid]);
        json_response(['ok' => true]);
    }
    if ($route === 'POST partner/password') {
        $k = $one('SELECT * FROM kk_kitchens WHERE id = ?', [$kid]); if (!password_verify((string)($body['current'] ?? ''), (string)$k['password_hash'])) json_error('Current password is incorrect', 401);
        if (strlen((string)($body['next'] ?? '')) < 6) json_error('New password must be at least 6 characters');
        $run('UPDATE kk_kitchens SET password_hash = ? WHERE id = ?', [password_hash((string)$body['next'], PASSWORD_BCRYPT), $kid]); json_response(['ok' => true]);
    }
    if ($route === 'POST partner/documents') {
        $k = $one('SELECT * FROM kk_kitchens WHERE id = ?', [$kid]); $dd = $addDocs($k, $body); if (isset($dd['error'])) json_error($dd['error']);
        $run('UPDATE kk_kitchens SET docs = ?, updated_at = ? WHERE id = ?', [json_encode($dd['docs']), $now(), $kid]); json_response(['ok' => true]);
    }
    if ($route === 'POST partner/dishes/:id/available') {
        $q = $run('UPDATE kk_kitchen_dishes SET available = ? WHERE kitchen_id = ? AND dish_id = ?', [!empty($body['available']) ? 1 : 0, $kid, (int)$P[2]]);
        $has = $one('SELECT 1 FROM kk_kitchen_dishes WHERE kitchen_id = ? AND dish_id = ?', [$kid, (int)$P[2]]); if (!$has) json_error('You are not approved for this dish yet', 404); json_response(['ok' => true]);
    }
    if ($route === 'GET partner/orders') {
        if (($_GET['scope'] ?? '') === 'history') json_response(['orders' => $partnerOrders($kid, "AND status IN ('delivered','cancelled','rejected')")]);
        json_response(['orders' => $partnerOrders($kid, "AND status NOT IN ('delivered','cancelled','rejected')"), 'today' => $nowEAT()['date']]);
    }
    if ($route === 'PATCH partner/orders/:id/status') {
        $o = $one('SELECT * FROM kk_orders WHERE id = ? AND kitchen_id = ?', [(int)$P[2], $kid]); if (!$o) json_error('Order not found', 404);
        $to = (string)($body['status'] ?? ''); $allowed = ['new' => ['accepted', 'rejected'], 'accepted' => ['preparing', 'ready', 'rejected'], 'preparing' => ['ready', 'rejected']];
        if (!in_array($to, $allowed[$o['status']] ?? [], true)) json_error('An order that is "' . $o['status'] . '" cannot be marked "' . $to . '"');
        if ($to === 'rejected' && $clean($body['reason'] ?? '', 200) === '') json_error('Please give a short reason');
        $run('UPDATE kk_orders SET status = ?, reason = ?, updated_at = ?, ready_at = ? WHERE id = ?', [$to, $to === 'rejected' ? $clean($body['reason'], 200) : $o['reason'], $now(), $to === 'ready' ? $now() : $o['ready_at'], $o['id']]); json_response(['ok' => true]);
    }
    if ($route === 'GET partner/statement') json_response($statement($kid));
    json_error('Not found', 404);
}

// ================= ADMIN =================
if ($route === 'POST admin-login') {
    $limit(30, 'alog'); if (KITCHENS_ADMIN_PASSWORD === '') json_error('Kitchens Admin is not set up yet: set KITCHENS_ADMIN_PASSWORD in config.php', 503);
    if (!hash_equals(KITCHENS_ADMIN_PASSWORD, (string)($body['password'] ?? ''))) json_error('Incorrect Kitchens Admin password', 401);
    json_response(['token' => jwt_sign(['role' => 'kitchens'], 12 * 3600)]);
}
if (!$P || $P[0] !== 'admin') json_error('Not found', 404);
require_kitchens_admin();
$kAdmin = function ($k) use ($rating, $statement, $S, $jp, $one, $all, $queuePos, $val) {
    $r = $rating($k['id']); $st = $statement($k['id']);
    return ['id' => (int)$k['id'], 'slug' => $k['slug'], 'name' => $k['name'], 'cook' => $k['cook_name'], 'phone' => $k['phone'], 'email' => $k['email'], 'bio' => $k['bio'], 'specialities' => $k['specialities'], 'experience' => $k['experience'], 'address' => $k['address'], 'areaId' => $k['area_id'], 'region' => $k['region'], 'lat' => $k['lat'] === null ? null : (float)$k['lat'], 'lng' => $k['lng'] === null ? null : (float)$k['lng'], 'radiusKm' => (float)$k['radius_km'],
        'capacity' => (int)$k['capacity'], 'prepMin' => (int)$k['prep_min'], 'status' => $k['status'], 'paused' => (bool)$k['paused'], 'commissionPct' => $k['commission_pct'] === null ? null : (float)$k['commission_pct'], 'effectiveCommission' => $k['commission_pct'] === null ? (float)$S['commissionPct'] : (float)$k['commission_pct'],
        'vetting' => (object)$jp($k['vetting'], []), 'notes' => $k['notes'], 'strikes' => (int)$k['strikes'], 'docs' => $jp($k['docs'], []), 'rating' => $r['avg'], 'ratingCount' => $r['count'], 'orders' => $val('SELECT COUNT(*) FROM kk_orders WHERE kitchen_id = ?', [$k['id']]),
        'balance' => $st['balance'], 'earned' => $st['earned'], 'paid' => $st['paid'], 'queuePosition' => $queuePos($k), 'approvedDishes' => array_map('intval', array_column($all('SELECT dish_id FROM kk_kitchen_dishes WHERE kitchen_id = ?', [$k['id']]), 'dish_id')), 'created' => $k['created_at'], 'approvedAt' => $k['approved_at']];
};
if ($route === 'GET admin/overview') {
    $by = []; foreach ($all('SELECT status, COUNT(*) AS n FROM kk_kitchens GROUP BY status') as $r) $by[$r['status']] = (int)$r['n'];
    $regions = $all("SELECT region, SUM(CASE WHEN status IN ('pilot','live') THEN 1 ELSE 0 END) AS live, SUM(CASE WHEN status = 'queued' THEN 1 ELSE 0 END) AS queued, COUNT(*) AS total FROM kk_kitchens WHERE region != '' GROUP BY region ORDER BY region");
    $n = $nowEAT(); $today = $one("SELECT COUNT(*) AS n, COALESCE(SUM(total),0) AS gmv, COALESCE(SUM(commission_amt),0) AS comm FROM kk_orders WHERE date = ? AND status NOT IN ('cancelled','rejected')", [$n['date']]);
    json_response(['byStatus' => (object)$by, 'regions' => array_map(fn($r) => ['region' => $r['region'], 'live' => (int)$r['live'], 'queued' => (int)$r['queued'], 'total' => (int)$r['total'], 'cap' => (int)($S['zoneCaps'][$r['region']] ?? $S['zoneCap'])], $regions),
        'today' => ['n' => (int)$today['n'], 'gmv' => (int)$today['gmv'], 'comm' => (int)$today['comm']], 'newApplications' => $by['applied'] ?? 0, 'openOrders' => $val("SELECT COUNT(*) FROM kk_orders WHERE status NOT IN ('delivered','cancelled','rejected')"), 'date' => $n['date']]);
}
if ($route === 'GET admin/kitchens') {
    $rows = !empty($_GET['status']) ? $all('SELECT * FROM kk_kitchens WHERE status = ? ORDER BY id', [$_GET['status']]) : $all('SELECT * FROM kk_kitchens ORDER BY id DESC');
    json_response(['kitchens' => array_map($kAdmin, $rows)]);
}
if ($route === 'GET admin/kitchens/:id') { $k = $one('SELECT * FROM kk_kitchens WHERE id = ?', [(int)$P[2]]); if (!$k) json_error('Not found', 404); json_response($kAdmin($k)); }
if ($route === 'PATCH admin/kitchens/:id') {
    $k = $one('SELECT * FROM kk_kitchens WHERE id = ?', [(int)$P[2]]); if (!$k) json_error('Not found', 404); $b = $body;
    $v = array_key_exists('vetting', $b) ? (array)$b['vetting'] : $jp($k['vetting'], []); $status = array_key_exists('status', $b) ? (string)$b['status'] : $k['status'];
    if (!in_array($status, ['applied', 'queued', 'vetting', 'pilot', 'live', 'paused', 'delisted', 'rejected'], true)) json_error('Unknown status');
    if (in_array($status, ['pilot', 'live'], true) && !in_array($k['status'], ['pilot', 'live'], true)) {
        $keys = ['taste', 'consistency', 'hygiene', 'premises', 'documents', 'reliability', 'attitude']; $vals = array_map(fn($x) => (float)($v[$x] ?? 0), $keys);
        $complete = count(array_filter($vals, fn($x) => $x >= 1 && $x <= 5)) === 7; $avg = $complete ? array_sum($vals) / 7 : 0;
        $fails = !$complete ? 'Complete all seven vetting scores first' : ($avg < 4 ? sprintf('Vetting average is %.1f; 4.0 is required', $avg) : ((float)$v['hygiene'] <= 1 || (float)$v['documents'] <= 1 ? 'Hygiene and documents cannot score 1' : ''));
        $override = !empty($b['override']);
        if ($fails && !($override && $clean(array_key_exists('notes', $b) ? $b['notes'] : $k['notes'], 400) !== '')) json_error($fails . '. To override, tick override and add a note.', 409);
        $cap = (int)($S['zoneCaps'][$k['region']] ?? $S['zoneCap']); $liveN = $val("SELECT COUNT(*) FROM kk_kitchens WHERE region = ? AND status IN ('pilot','live') AND id != ?", [$k['region'], $k['id']]);
        if ($liveN >= $cap && !$override) json_error($k['region'] . " already has $liveN of $cap kitchens. Raise the zone cap or tick override.", 409);
        if (!$one('SELECT 1 FROM kk_kitchen_dishes WHERE kitchen_id = ?', [$k['id']]) && !is_array($b['approvedDishes'] ?? null)) json_error('Approve at least one dish (after the tasting) before going live', 409);
    }
    $pdo->beginTransaction();
    try {
        $comm = array_key_exists('commissionPct', $b) ? (($b['commissionPct'] === null || $b['commissionPct'] === '') ? null : $num($b['commissionPct'], 20, 0, 60)) : $k['commission_pct'];
        $approvedAt = (in_array($status, ['pilot', 'live'], true) && !$k['approved_at']) ? $now() : $k['approved_at'];
        $run('UPDATE kk_kitchens SET name = ?, status = ?, radius_km = ?, capacity = ?, prep_min = ?, commission_pct = ?, vetting = ?, notes = ?, strikes = ?, lat = ?, lng = ?, bio = ?, region = ?, approved_at = ?, updated_at = ? WHERE id = ?', [
            array_key_exists('name', $b) ? $clean($b['name'], 80) : $k['name'], $status, array_key_exists('radiusKm', $b) ? $num($b['radiusKm'], $k['radius_km'], 0.5, 30) : $k['radius_km'], array_key_exists('capacity', $b) ? $int($b['capacity'], $k['capacity'], 1, 60) : $k['capacity'],
            array_key_exists('prepMin', $b) ? $int($b['prepMin'], $k['prep_min'], 10, 180) : $k['prep_min'], $comm, json_encode((object)$v), array_key_exists('notes', $b) ? $clean($b['notes'], 1000) : $k['notes'], array_key_exists('strikes', $b) ? $int($b['strikes'], 0, 0, 9) : $k['strikes'],
            array_key_exists('lat', $b) ? (float)$b['lat'] : $k['lat'], array_key_exists('lng', $b) ? (float)$b['lng'] : $k['lng'], array_key_exists('bio', $b) ? $clean($b['bio'], 600) : $k['bio'], array_key_exists('region', $b) ? $clean($b['region'], 60) : $k['region'], $approvedAt, $now(), $k['id']]);
        if (is_array($b['approvedDishes'] ?? null)) {
            $ids = array_values(array_filter(array_map('intval', $b['approvedDishes'])));
            if ($ids) $run('DELETE FROM kk_kitchen_dishes WHERE kitchen_id = ? AND dish_id NOT IN (' . implode(',', array_fill(0, count($ids), '?')) . ')', array_merge([$k['id']], $ids)); else $run('DELETE FROM kk_kitchen_dishes WHERE kitchen_id = ?', [$k['id']]);
            foreach ($ids as $id) $run('INSERT IGNORE INTO kk_kitchen_dishes (kitchen_id, dish_id, available) VALUES (?, ?, 1)', [$k['id'], $id]);
        }
        $pdo->commit();
    } catch (Throwable $e) { $pdo->rollBack(); json_error('Could not save changes', 500); }
    json_response(['ok' => true]);
}
if ($route === 'DELETE admin/kitchens/:id') {
    if ($val('SELECT COUNT(*) FROM kk_orders WHERE kitchen_id = ?', [(int)$P[2]])) json_error('This kitchen has orders. Delist it instead of deleting.', 409);
    $run('DELETE FROM kk_kitchen_dishes WHERE kitchen_id = ?', [(int)$P[2]]); $run('DELETE FROM kk_kitchens WHERE id = ?', [(int)$P[2]]); json_response(['ok' => true]);
}
if ($route === 'GET admin/kitchens/:id/docs/' . ($P[4] ?? '')) {
    $f = basename((string)$P[4]); $k = $one('SELECT docs FROM kk_kitchens WHERE id = ?', [(int)$P[2]]); $found = false;
    if ($k) foreach ($jp($k['docs'], []) as $d) if (($d['file'] ?? '') === $f) $found = true;
    $path = $PRIVATE . '/' . $f; if (!$found || !is_file($path)) json_error('Not found', 404);
    $ext = pathinfo($f, PATHINFO_EXTENSION); header('Content-Type: image/' . ($ext === 'jpg' ? 'jpeg' : $ext)); readfile($path); exit;
}
if ($route === 'PATCH admin/kitchens/:id/docs') {
    $k = $one('SELECT docs FROM kk_kitchens WHERE id = ?', [(int)$P[2]]); if (!$k) json_error('Not found', 404); $docs = $jp($k['docs'], []); $i = (int)($body['index'] ?? -1);
    if (!isset($docs[$i])) json_error('Document not found', 404); $docs[$i]['verified'] = !empty($body['verified']); $docs[$i]['verifiedOn'] = !empty($body['verified']) ? $nowEAT()['date'] : '';
    if (array_key_exists('note', $body)) $docs[$i]['note'] = $clean($body['note'], 200);
    $run('UPDATE kk_kitchens SET docs = ? WHERE id = ?', [json_encode($docs), (int)$P[2]]); json_response(['ok' => true]);
}
// dishes
$dishInput = function ($b, $e = null) use ($clean, $lines, $jp, $int) {
    $e = $e ?: []; $pick = fn($k, $max, $d) => array_key_exists($k, $b) ? $clean($b[$k], $max) : ($e[$k] ?? $d);
    $arr = function ($k) use ($b, $e, $clean) { if (!array_key_exists($k, $b)) return $e[$k] ?? ''; $v = is_array($b[$k]) ? $b[$k] : preg_split('/\r?\n/', (string)$b[$k]); return implode("\n", array_values(array_filter(array_map(fn($x) => $clean($x, 300), $v), fn($x) => $x !== ''))); };
    $options = $e['options'] ?? '[]';
    if (array_key_exists('options', $b)) { $o = is_array($b['options']) ? $b['options'] : json_decode((string)$b['options'], true); if (!is_array($o)) return ['error' => 'Options must be a list'];
        $options = json_encode(array_values(array_filter(array_map(fn($g) => ['name' => $clean($g['name'] ?? '', 40), 'required' => !empty($g['required']), 'choices' => array_values(array_filter(array_map(fn($c) => ['name' => $clean($c['name'] ?? '', 40), 'price' => max(0, (int)($c['price'] ?? 0))], $g['choices'] ?? []), fn($c) => $c['name'] !== ''))], $o), fn($g) => $g['name'] !== '' && $g['choices'])));
    }
    $price = array_key_exists('price', $b) ? (int)$b['price'] : (int)($e['price'] ?? -1); if ($price < 0 || $price > 100000) return ['error' => 'Enter a price'];
    $image = $pick('image', 300, ''); if ($image !== '' && !preg_match('#^(/uploads/|https?://)#i', $image)) return ['error' => 'Image must be an uploaded file or a web address'];
    return ['v' => ['name' => $pick('name', 100, ''), 'tradition' => $pick('tradition', 30, ''), 'category' => $pick('category', 30, 'Combo'), 'meal' => in_array($b['meal'] ?? '', ['lunch', 'dinner', 'both'], true) ? $b['meal'] : ($e['meal'] ?? 'both'), 'price' => $price,
        'serves' => $int($b['serves'] ?? ($e['serves'] ?? 1), 1, 1, 50), 'description' => $pick('description', 400, ''), 'components' => $arr('components'), 'method' => $arr('method'), 'options' => $options, 'packaging' => $pick('packaging', 200, ''), 'tips' => $pick('tips', 300, ''), 'image' => $image,
        'active' => array_key_exists('active', $b) ? ($b['active'] ? 1 : 0) : (int)($e['active'] ?? 1), 'sort_order' => array_key_exists('sort', $b) ? (int)$b['sort'] : (int)($e['sort_order'] ?? 0)]];
};
if ($route === 'GET admin/dishes') json_response(['dishes' => array_map($dishFull, $all('SELECT * FROM kk_dishes ORDER BY sort_order, id'))]);
if ($route === 'POST admin/dishes') {
    $p = $dishInput($body); if (isset($p['error'])) json_error($p['error']); $v = $p['v']; if ($v['name'] === '') json_error('Give the dish a name');
    $slug = $slugify($v['name']); $n = 1; while ($one('SELECT 1 FROM kk_dishes WHERE slug = ?', [$slug])) $slug = $slugify($v['name']) . '-' . (++$n);
    $run('INSERT INTO kk_dishes (slug, name, tradition, category, meal, price, serves, description, components, method, options, packaging, tips, image, active, sort_order) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)', [$slug, $v['name'], $v['tradition'], $v['category'], $v['meal'], $v['price'], $v['serves'], $v['description'], $v['components'], $v['method'], $v['options'], $v['packaging'], $v['tips'], $v['image'], $v['active'], $v['sort_order'] ?: 999]);
    json_response(['ok' => true, 'id' => (int)$pdo->lastInsertId()], 201);
}
if ($route === 'PUT admin/dishes/:id') {
    $e = $one('SELECT * FROM kk_dishes WHERE id = ?', [(int)$P[2]]); if (!$e) json_error('Not found', 404); $p = $dishInput($body, $e); if (isset($p['error'])) json_error($p['error']); $v = $p['v'];
    $run('UPDATE kk_dishes SET name = ?, tradition = ?, category = ?, meal = ?, price = ?, serves = ?, description = ?, components = ?, method = ?, options = ?, packaging = ?, tips = ?, image = ?, active = ?, sort_order = ? WHERE id = ?', [$v['name'], $v['tradition'], $v['category'], $v['meal'], $v['price'], $v['serves'], $v['description'], $v['components'], $v['method'], $v['options'], $v['packaging'], $v['tips'], $v['image'], $v['active'], $v['sort_order'], $e['id']]);
    json_response(['ok' => true]);
}
if ($route === 'DELETE admin/dishes/:id') {
    if ($one('SELECT 1 FROM kk_order_items WHERE dish_id = ?', [(int)$P[2]])) json_error('This dish has been ordered. Hide it (untick Active) instead of deleting.', 409);
    $run('DELETE FROM kk_kitchen_dishes WHERE dish_id = ?', [(int)$P[2]]); $run('DELETE FROM kk_dishes WHERE id = ?', [(int)$P[2]]); json_response(['ok' => true]);
}
$IMGDIR = __DIR__ . '/../../uploads/kitchens';
if ($route === 'POST admin/upload') {
    $im = $readImage($body['data'] ?? '', 3 * 1024 * 1024); if (isset($im['error'])) json_error($im['error']); if (!is_dir($IMGDIR)) @mkdir($IMGDIR, 0755, true);
    $file = substr($slugify($body['name'] ?? 'dish'), 0, 30) . '-' . bin2hex(random_bytes(4)) . '.' . $im['ext']; file_put_contents($IMGDIR . '/' . $file, $im['buf']); json_response(['ok' => true, 'url' => '/uploads/kitchens/' . $file], 201);
}
if ($route === 'GET admin/images') { $fs = is_dir($IMGDIR) ? array_values(array_filter(scandir($IMGDIR), fn($x) => preg_match('/\.(jpg|png|webp)$/i', $x))) : []; json_response(['files' => array_map(fn($x) => ['file' => $x, 'url' => '/uploads/kitchens/' . $x], $fs)]); }
// orders
if ($route === 'GET admin/orders') {
    $w = []; $a = []; if (!empty($_GET['status'])) { $w[] = 'o.status = ?'; $a[] = $_GET['status']; } if (!empty($_GET['kitchen'])) { $w[] = 'o.kitchen_id = ?'; $a[] = (int)$_GET['kitchen']; } if (!empty($_GET['date'])) { $w[] = 'o.date = ?'; $a[] = $_GET['date']; }
    $rows = $all('SELECT o.*, k.name AS kitchen_name FROM kk_orders o LEFT JOIN kk_kitchens k ON k.id = o.kitchen_id ' . ($w ? 'WHERE ' . implode(' AND ', $w) : '') . ' ORDER BY o.id DESC LIMIT 300', $a);
    json_response(['orders' => array_map(fn($o) => ['id' => (int)$o['id'], 'kitchen' => $o['kitchen_name'], 'kitchenId' => (int)$o['kitchen_id'], 'customer' => $o['name'], 'phone' => $o['phone'], 'address' => $o['address'], 'date' => $o['date'], 'meal' => $o['meal'], 'status' => $o['status'], 'subtotal' => (int)$o['subtotal'], 'fee' => (int)$o['delivery_fee'], 'total' => (int)$o['total'], 'payment' => $o['payment'], 'commission' => (int)$o['commission_amt'], 'notes' => $o['notes'], 'reason' => $o['reason'], 'rating' => (int)$o['rating'], 'review' => $o['review'], 'km' => (float)$o['distance_km'], 'created' => $o['created_at'], 'items' => $itemsOf($o['id'])], $rows)]);
}
if ($route === 'PATCH admin/orders/:id') {
    $o = $one('SELECT * FROM kk_orders WHERE id = ?', [(int)$P[2]]); if (!$o) json_error('Not found', 404); $to = (string)($body['status'] ?? '');
    if (!in_array($to, ['out_for_delivery', 'delivered', 'cancelled'], true)) json_error('Use out_for_delivery, delivered or cancelled');
    if ($to === 'out_for_delivery' && $o['status'] !== 'ready') json_error('Only a ready order can go out for delivery');
    if ($to === 'delivered' && !in_array($o['status'], ['ready', 'out_for_delivery'], true)) json_error('Only a ready or out-for-delivery order can be delivered');
    if ($to === 'cancelled' && in_array($o['status'], ['delivered', 'cancelled', 'rejected'], true)) json_error('This order is already closed');
    $run('UPDATE kk_orders SET status = ?, reason = ?, updated_at = ? WHERE id = ?', [$to, $to === 'cancelled' ? $clean($body['reason'] ?? '', 200) : $o['reason'], $now(), $o['id']]); json_response(['ok' => true]);
}
if ($route === 'GET admin/payouts') json_response(['kitchens' => array_map(fn($k) => ['id' => (int)$k['id'], 'name' => $k['name'], 'phone' => $k['phone']] + $statement($k['id']), $all("SELECT * FROM kk_kitchens WHERE status != 'rejected' ORDER BY name"))]);
if ($route === 'POST admin/payouts') {
    $amt = (int)($body['amount'] ?? 0); $k = $one('SELECT id FROM kk_kitchens WHERE id = ?', [(int)($body['kitchenId'] ?? 0)]); if (!$k) json_error('Kitchen not found', 404); if ($amt <= 0) json_error('Enter the amount paid');
    $run('INSERT INTO kk_payouts (kitchen_id, amount, reference, note, created_at) VALUES (?,?,?,?,?)', [$k['id'], $amt, $clean($body['reference'] ?? '', 60), $clean($body['note'] ?? '', 200), $now()]); json_response(['ok' => true]);
}
if ($route === 'GET admin/settings') json_response($S);
if ($route === 'POST admin/settings') {
    $b = $body; $fl = fn($v, $d, $lo, $hi) => is_numeric($v) ? max($lo, min($hi, (float)$v)) : $d;
    $slot = function ($k) use ($b, $S, $clean) { $s = array_merge($S[$k], is_array($b[$k] ?? null) ? $b[$k] : []); foreach (['from', 'to', 'cutoff'] as $x) if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', (string)$s[$x])) return null; return ['label' => $clean($s['label'], 20) ?: $S[$k]['label'], 'from' => $s['from'], 'to' => $s['to'], 'cutoff' => $s['cutoff']]; };
    $lunch = $slot('lunch'); $dinner = $slot('dinner'); if (!$lunch || !$dinner) json_error('Times must look like 12:30');
    $caps = []; foreach ((array)($b['zoneCaps'] ?? $S['zoneCaps']) as $r => $c) if ((int)$c > 0) $caps[$clean($r, 60)] = min(50, (int)$c); $fe = is_array($b['fee'] ?? null) ? $b['fee'] : [];
    $next = ['commissionPct' => $fl($b['commissionPct'] ?? null, $S['commissionPct'], 0, 60), 'lunch' => $lunch, 'dinner' => $dinner, 'fee' => ['base' => $fl($fe['base'] ?? null, $S['fee']['base'], 0, 2000), 'perKm' => $fl($fe['perKm'] ?? null, $S['fee']['perKm'], 0, 200), 'bandKm' => $fl($fe['bandKm'] ?? null, $S['fee']['bandKm'], 0.5, 20), 'max' => $fl($fe['max'] ?? null, $S['fee']['max'], 0, 5000)],
        'defaultRadiusKm' => $fl($b['defaultRadiusKm'] ?? null, $S['defaultRadiusKm'], 0.5, 30), 'roadFactor' => $fl($b['roadFactor'] ?? null, $S['roadFactor'], 1, 2.5), 'zoneCap' => (int)round($fl($b['zoneCap'] ?? null, $S['zoneCap'], 1, 50)), 'zoneCaps' => (object)$caps, 'minOrder' => (int)round($fl($b['minOrder'] ?? null, $S['minOrder'], 0, 20000)), 'holdMinutes' => (int)round($fl($b['holdMinutes'] ?? null, $S['holdMinutes'], 30, 480))];
    $run('INSERT INTO kk_meta (skey, svalue) VALUES (?, ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)', ['kk_settings', json_encode($next)]); json_response(['ok' => true, 'settings' => $next]);
}
json_error('Not found', 404);
