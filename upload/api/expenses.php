<?php
// Expenses: electricity, repairs, wages and so on.
declare(strict_types=1);
require __DIR__ . '/lib.php';

$me  = require_login();
$pdo = db();

function expense_out(array $r): array {
    return ['id' => $r['id'], 'date' => $r['spent_on'], 'amount' => (float)$r['amount'], 'category' => $r['category'], 'mode' => $r['mode'], 'note' => $r['note']];
}

if (method() === 'GET') {
    $rows = $pdo->query('SELECT * FROM expenses ORDER BY spent_on DESC, created_at DESC')->fetchAll();
    json_out(['expenses' => array_map('expense_out', $rows)]);
}

if (method() === 'DELETE') {
    $pdo->prepare('DELETE FROM expenses WHERE id = ?')->execute([id_in($_GET['id'] ?? '')]);
    json_out(['ok' => true]);
}

if (method() !== 'POST') fail('Unknown request.', 405);

$b = body();
$id = id_in($b['id'] ?? '');
$date = date_in($b['date'] ?? null);
if (!$date) fail('Enter the date of the expense.');
$amount = num_in($b['amount'] ?? 0);
if ($amount <= 0) fail('Enter the amount spent.');
$category = str_in($b['category'] ?? '', 40) ?: 'Other';

$pdo->prepare('INSERT INTO expenses (id, spent_on, amount, category, mode, note, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE spent_on = VALUES(spent_on), amount = VALUES(amount), category = VALUES(category), mode = VALUES(mode), note = VALUES(note)')
    ->execute([$id, $date, $amount, $category, str_in($b['mode'] ?? '', 30), str_in($b['note'] ?? '', 255), $me['id']]);

$st = $pdo->prepare('SELECT * FROM expenses WHERE id = ?');
$st->execute([$id]);
json_out(expense_out($st->fetch()));
