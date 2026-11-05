<?php
// Homestay name, bill details, standard times, meal prices, rooms and products.
declare(strict_types=1);
require __DIR__ . '/lib.php';

require_login();
$pdo = db();

function settings_out(PDO $pdo): array {
    $kv = [];
    foreach ($pdo->query('SELECT k, v FROM settings') as $r) $kv[$r['k']] = $r['v'];
    $g = fn($k, $d = '') => $kv[$k] ?? $d;
    return [
        'name' => $g('name', 'Homestay Maintenance'), 'address' => $g('address'), 'phone' => $g('phone'), 'upi' => $g('upi'),
        'gstin' => $g('gstin'), 'gstRate' => (float)$g('gst_rate', '0'), 'billNote' => $g('bill_note'),
        'checkInTime' => $g('check_in_time', '12:00'), 'checkOutTime' => $g('check_out_time', '11:00'),
        'mealRates' => ['b' => (float)$g('meal_rate_b', '0'), 'l' => (float)$g('meal_rate_l', '0'), 'd' => (float)$g('meal_rate_d', '0')],
        'rooms' => array_map(fn($r) => ['id' => $r['id'], 'name' => $r['name'], 'rate' => (float)$r['rate']],
            $pdo->query('SELECT id, name, rate FROM rooms ORDER BY sort_order, id')->fetchAll()),
        'products' => array_map(fn($r) => ['id' => $r['id'], 'name' => $r['name'], 'unit' => $r['unit'], 'price' => (float)$r['price']],
            $pdo->query('SELECT id, name, unit, price FROM products ORDER BY sort_order, id')->fetchAll()),
    ];
}

if (method() === 'GET') json_out(settings_out($pdo));
if (method() !== 'POST') fail('Unknown request.', 405);

$b = body();
$rooms = is_array($b['rooms'] ?? null) ? array_slice($b['rooms'], 0, 100) : [];
$products = is_array($b['products'] ?? null) ? array_slice($b['products'], 0, 500) : [];
if (!$rooms) fail('Keep at least one room.');
$mr = is_array($b['mealRates'] ?? null) ? $b['mealRates'] : [];

$pdo->beginTransaction();
$set = $pdo->prepare('INSERT INTO settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)');
foreach ([
    'name' => str_in($b['name'] ?? '', 80) ?: 'Homestay Maintenance',
    'address' => str_in($b['address'] ?? '', 500), 'phone' => str_in($b['phone'] ?? '', 30), 'upi' => str_in($b['upi'] ?? '', 80),
    'gstin' => str_in($b['gstin'] ?? '', 20), 'gst_rate' => (string)num_in($b['gstRate'] ?? 0, 0, 28), 'bill_note' => str_in($b['billNote'] ?? '', 300),
    'check_in_time' => time_in($b['checkInTime'] ?? null) ?? '12:00', 'check_out_time' => time_in($b['checkOutTime'] ?? null) ?? '11:00',
    'meal_rate_b' => (string)num_in($mr['b'] ?? 0), 'meal_rate_l' => (string)num_in($mr['l'] ?? 0), 'meal_rate_d' => (string)num_in($mr['d'] ?? 0),
] as $k => $v) $set->execute([$k, $v]);

// Replace the room and product lists with what was sent. Old bookings keep their room id and the price paid.
$sync = function (string $table, array $items, callable $row) use ($pdo) {
    $keep = [];
    foreach (array_values($items) as $i => $it) {
        if (!is_array($it)) continue;
        $vals = $row($it);
        if (!$vals) continue;
        $keep[] = $vals[0];
        $cols = $table === 'rooms' ? '(id, name, rate, sort_order) VALUES (?, ?, ?, ?)' : '(id, name, unit, price, sort_order) VALUES (?, ?, ?, ?, ?)';
        $upd  = $table === 'rooms' ? 'name = VALUES(name), rate = VALUES(rate), sort_order = VALUES(sort_order)'
                                   : 'name = VALUES(name), unit = VALUES(unit), price = VALUES(price), sort_order = VALUES(sort_order)';
        $pdo->prepare("INSERT INTO $table $cols ON DUPLICATE KEY UPDATE $upd")->execute([...$vals, $i + 1]);
    }
    if ($keep) {
        $in = implode(',', array_fill(0, count($keep), '?'));
        $pdo->prepare("DELETE FROM $table WHERE id NOT IN ($in)")->execute($keep);
    } else {
        $pdo->exec("DELETE FROM $table");
    }
};
$validId = fn($v) => is_string($v) && preg_match('/^[A-Za-z0-9_-]{1,40}$/', $v) ? $v : null;
$sync('rooms', $rooms, function ($r) use ($validId) {
    $id = $validId($r['id'] ?? null); $name = str_in($r['name'] ?? '', 60);
    return ($id && $name !== '') ? [$id, $name, num_in($r['rate'] ?? 0)] : null;
});
$sync('products', $products, function ($p) use ($validId) {
    $id = $validId($p['id'] ?? null); $name = str_in($p['name'] ?? '', 80);
    return ($id && $name !== '') ? [$id, $name, str_in($p['unit'] ?? '', 30), num_in($p['price'] ?? 0)] : null;
});
$pdo->commit();

json_out(settings_out($pdo));
