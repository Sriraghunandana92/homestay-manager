<?php
// Bookings with their payments, meal counts and product purchases.
declare(strict_types=1);
require __DIR__ . '/lib.php';

$me  = require_login();
$pdo = db();
const STATUSES = ['confirmed', 'checkedin', 'checkedout', 'cancelled'];

function load_bookings(PDO $pdo, ?string $onlyId = null): array {
    $one = $onlyId !== null;
    $st = $pdo->prepare('SELECT * FROM bookings' . ($one ? ' WHERE id = ?' : '') . ' ORDER BY check_in, id');
    $st->execute($one ? [$onlyId] : []);
    $rows = $st->fetchAll();
    if (!$rows) return [];
    $w = $one ? ' WHERE booking_id = ?' : '';
    $a = $one ? [$onlyId] : [];

    $pay = [];
    $st = $pdo->prepare("SELECT booking_id, amount, paid_on, mode FROM payments$w ORDER BY paid_on, id");
    $st->execute($a);
    foreach ($st as $p) $pay[$p['booking_id']][] = ['amount' => (float)$p['amount'], 'date' => $p['paid_on'], 'mode' => $p['mode']];

    $meals = [];
    $st = $pdo->prepare("SELECT booking_id, day, breakfast, lunch, dinner FROM booking_meals$w");
    $st->execute($a);
    foreach ($st as $m) $meals[$m['booking_id']][$m['day']] = ['b' => (int)$m['breakfast'], 'l' => (int)$m['lunch'], 'd' => (int)$m['dinner']];

    $pur = [];
    $st = $pdo->prepare("SELECT booking_id, product_id, name, unit, price, qty, disc_pct, sold_on FROM purchases$w ORDER BY id");
    $st->execute($a);
    foreach ($st as $p) $pur[$p['booking_id']][] = [
        'pid' => $p['product_id'], 'name' => $p['name'], 'unit' => $p['unit'], 'price' => (float)$p['price'],
        'qty' => (int)$p['qty'], 'disc' => (float)$p['disc_pct'], 'date' => $p['sold_on'],
    ];

    return array_map(fn($r) => [
        'id' => $r['id'], 'guest' => $r['guest'], 'phone' => $r['phone'], 'room' => $r['room_id'], 'pax' => (int)$r['pax'],
        'checkIn' => $r['check_in'], 'checkOut' => $r['check_out'],
        'checkInTime' => $r['check_in_time'] ? substr($r['check_in_time'], 0, 5) : null,
        'checkOutTime' => $r['check_out_time'] ? substr($r['check_out_time'], 0, 5) : null,
        'rate' => (float)$r['rate'], 'extras' => (float)$r['extras'], 'discount' => (float)$r['discount'], 'discType' => $r['disc_type'],
        'source' => $r['source'], 'status' => $r['status'], 'notes' => $r['notes'] ?? '',
        'billNo' => $r['bill_no'], 'billDate' => $r['bill_date'],
        'payments' => $pay[$r['id']] ?? [], 'meals' => (object)($meals[$r['id']] ?? []), 'purchases' => $pur[$r['id']] ?? [],
    ], $rows);
}

if (method() === 'GET') {
    json_out(['bookings' => load_bookings($pdo)]);
}

if (method() === 'DELETE') {
    $id = id_in($_GET['id'] ?? '');
    $pdo->prepare('DELETE FROM bookings WHERE id = ?')->execute([$id]);
    json_out(['ok' => true]);
}

if (method() !== 'POST') fail('Unknown request.', 405);

// ---- save (create or update) ----------------------------------------------
$b     = body();
$id    = id_in($b['id'] ?? '');
$guest = str_in($b['guest'] ?? '', 120);
$ci    = date_in($b['checkIn'] ?? null);
$co    = date_in($b['checkOut'] ?? null);
if ($guest === '') fail('Enter the guest name.');
if (!$ci || !$co) fail('Enter valid check-in and check-out dates.');
if ($co <= $ci) fail('Check-out must be after check-in.');
$status = in_array($b['status'] ?? '', STATUSES, true) ? $b['status'] : 'confirmed';
$room   = str_in($b['room'] ?? '', 40);
if ($room === '') fail('Choose a room.');

$pdo->beginTransaction();

if ($status !== 'cancelled') {
    $st = $pdo->prepare("SELECT b.guest, b.check_in, b.check_out, r.name AS room_name FROM bookings b LEFT JOIN rooms r ON r.id = b.room_id
                         WHERE b.room_id = ? AND b.id <> ? AND b.status <> 'cancelled' AND b.check_in < ? AND b.check_out > ? LIMIT 1 FOR UPDATE");
    $st->execute([$room, $id, $co, $ci]);
    if ($c = $st->fetch()) {
        fail(sprintf('%s is already booked by %s from %s to %s.', $c['room_name'] ?: 'This room', $c['guest'],
            date('j M', strtotime($c['check_in'])), date('j M', strtotime($c['check_out']))), 409);
    }
}

// Bill numbers are given out by the server so two phones never create the same number.
$st = $pdo->prepare('SELECT bill_no, bill_date FROM bookings WHERE id = ? FOR UPDATE');
$st->execute([$id]);
$existing = $st->fetch() ?: ['bill_no' => null, 'bill_date' => null];
$billNo = $existing['bill_no'];
$billDate = $existing['bill_date'];
if (($b['billNo'] ?? null) === 'NEW' && !$billNo) {
    $next = (int)$pdo->query("SELECT COALESCE(MAX(CAST(SUBSTRING(bill_no, 5) AS UNSIGNED)), 0) + 1 FROM bookings WHERE bill_no LIKE 'INV-%' FOR UPDATE")->fetchColumn();
    $billNo = sprintf('INV-%04d', $next);
    $billDate = date_in($b['billDate'] ?? null) ?? date('Y-m-d');
}

$pdo->prepare('INSERT INTO bookings
    (id, guest, phone, room_id, pax, check_in, check_out, check_in_time, check_out_time, rate, extras, discount, disc_type, source, status, notes, bill_no, bill_date, created_by, updated_by)
    VALUES (:id, :guest, :phone, :room, :pax, :ci, :co, :cit, :cot, :rate, :extras, :disc, :dtype, :source, :status, :notes, :billno, :billdate, :uid, :uid2)
    ON DUPLICATE KEY UPDATE guest = VALUES(guest), phone = VALUES(phone), room_id = VALUES(room_id), pax = VALUES(pax),
      check_in = VALUES(check_in), check_out = VALUES(check_out), check_in_time = VALUES(check_in_time), check_out_time = VALUES(check_out_time),
      rate = VALUES(rate), extras = VALUES(extras), discount = VALUES(discount), disc_type = VALUES(disc_type), source = VALUES(source),
      status = VALUES(status), notes = VALUES(notes), bill_no = VALUES(bill_no), bill_date = VALUES(bill_date), updated_by = VALUES(updated_by)')
->execute([
    ':id' => $id, ':guest' => $guest, ':phone' => str_in($b['phone'] ?? '', 30), ':room' => $room, ':pax' => int_in($b['pax'] ?? 1, 1, 500),
    ':ci' => $ci, ':co' => $co, ':cit' => time_in($b['checkInTime'] ?? null), ':cot' => time_in($b['checkOutTime'] ?? null),
    ':rate' => num_in($b['rate'] ?? 0), ':extras' => num_in($b['extras'] ?? 0),
    ':disc' => num_in($b['discount'] ?? 0), ':dtype' => ($b['discType'] ?? '') === 'pct' ? 'pct' : 'amt',
    ':source' => str_in($b['source'] ?? '', 40), ':status' => $status, ':notes' => str_in($b['notes'] ?? '', 5000),
    ':billno' => $billNo, ':billdate' => $billDate, ':uid' => $me['id'], ':uid2' => $me['id'],
]);

foreach (['payments', 'booking_meals', 'purchases'] as $t) $pdo->prepare("DELETE FROM $t WHERE booking_id = ?")->execute([$id]);

$ins = $pdo->prepare('INSERT INTO payments (booking_id, amount, paid_on, mode) VALUES (?, ?, ?, ?)');
foreach (array_slice(is_array($b['payments'] ?? null) ? $b['payments'] : [], 0, 200) as $p) {
    $amt = num_in($p['amount'] ?? 0);
    if ($amt <= 0) continue;
    $ins->execute([$id, $amt, date_in($p['date'] ?? null) ?? date('Y-m-d'), str_in($p['mode'] ?? '', 30)]);
}

$ins = $pdo->prepare('INSERT INTO booking_meals (booking_id, day, breakfast, lunch, dinner) VALUES (?, ?, ?, ?, ?)');
foreach (is_array($b['meals'] ?? null) ? $b['meals'] : [] as $day => $m) {
    $day = date_in((string)$day);
    if (!$day || $day < $ci || $day > $co || !is_array($m)) continue;
    $bk = int_in($m['b'] ?? 0, 0, 999); $ln = int_in($m['l'] ?? 0, 0, 999); $dn = int_in($m['d'] ?? 0, 0, 999);
    if ($bk + $ln + $dn === 0) continue;
    $ins->execute([$id, $day, $bk, $ln, $dn]);
}

$ins = $pdo->prepare('INSERT INTO purchases (booking_id, product_id, name, unit, price, qty, disc_pct, sold_on) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
foreach (array_slice(is_array($b['purchases'] ?? null) ? $b['purchases'] : [], 0, 300) as $p) {
    $qty = int_in($p['qty'] ?? 0, 0, 100000);
    $name = str_in($p['name'] ?? '', 80);
    if ($qty <= 0 || $name === '') continue;
    $pid = is_string($p['pid'] ?? null) && preg_match('/^[A-Za-z0-9_-]{1,40}$/', $p['pid']) ? $p['pid'] : null;
    $ins->execute([$id, $pid, $name, str_in($p['unit'] ?? '', 30), num_in($p['price'] ?? 0), $qty, num_in($p['disc'] ?? 0, 0, 100), date_in($p['date'] ?? null) ?? date('Y-m-d')]);
}

$pdo->commit();
json_out(load_bookings($pdo, $id)[0]);
