<?php
// Login, logout, password change and staff logins.
declare(strict_types=1);
require __DIR__ . '/lib.php';

start_session();
$action = $_GET['action'] ?? '';

function user_out(): array {
    return ['user' => ['name' => $_SESSION['uname'], 'username' => $_SESSION['uuser']], 'csrf' => $_SESSION['csrf']];
}

if ($action === 'me') {
    if (empty($_SESSION['uid'])) json_out(['user' => null]);
    json_out(user_out());
}

if ($action === 'login' && method() === 'POST') {
    $b = body();
    $u = str_in($b['username'] ?? '', 40);
    $p = is_string($b['password'] ?? null) ? $b['password'] : '';
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $pdo = db();
    $pdo->exec('DELETE FROM login_attempts WHERE attempted_at < (NOW() - INTERVAL 1 DAY)');
    $st = $pdo->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND attempted_at > (NOW() - INTERVAL 15 MINUTE)');
    $st->execute([$ip]);
    if ((int)$st->fetchColumn() >= 10) fail('Too many wrong attempts. Wait 15 minutes and try again.', 429);

    $st = $pdo->prepare('SELECT id, username, name, password_hash FROM users WHERE username = ? AND active = 1');
    $st->execute([$u]);
    $row = $st->fetch();
    if (!$row || !password_verify($p, $row['password_hash'])) {
        $pdo->prepare('INSERT INTO login_attempts (ip, attempted_at) VALUES (?, NOW())')->execute([$ip]);
        fail('Wrong username or password.', 401);
    }
    if (password_needs_rehash($row['password_hash'], PASSWORD_DEFAULT)) {
        $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($p, PASSWORD_DEFAULT), $row['id']]);
    }
    $pdo->prepare('DELETE FROM login_attempts WHERE ip = ?')->execute([$ip]);
    session_regenerate_id(true);
    $_SESSION['uid'] = (int)$row['id'];
    $_SESSION['uname'] = $row['name'];
    $_SESSION['uuser'] = $row['username'];
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
    json_out(user_out());
}

if ($action === 'logout' && method() === 'POST') {
    require_login();
    $_SESSION = [];
    session_destroy();
    json_out(['ok' => true]);
}

if ($action === 'password' && method() === 'POST') {
    $me = require_login();
    $b = body();
    $new = is_string($b['new'] ?? null) ? $b['new'] : '';
    if (strlen($new) < 8) fail('The new password needs at least 8 characters.');
    $st = db()->prepare('SELECT password_hash FROM users WHERE id = ?');
    $st->execute([$me['id']]);
    if (!password_verify(is_string($b['current'] ?? null) ? $b['current'] : '', (string)$st->fetchColumn())) fail('Your current password is not correct.');
    db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($new, PASSWORD_DEFAULT), $me['id']]);
    json_out(['ok' => true]);
}

if ($action === 'users' && method() === 'GET') {
    $me = require_login();
    $rows = db()->query('SELECT id, name, username FROM users WHERE active = 1 ORDER BY id')->fetchAll();
    json_out(['users' => array_map(fn($r) => ['id' => (int)$r['id'], 'name' => $r['name'], 'username' => $r['username'], 'self' => (int)$r['id'] === $me['id']], $rows)]);
}

if ($action === 'adduser' && method() === 'POST') {
    require_login();
    $b = body();
    $name = str_in($b['name'] ?? '', 80);
    $user = strtolower(str_in($b['username'] ?? '', 40));
    $pass = is_string($b['password'] ?? null) ? $b['password'] : '';
    if ($name === '') fail('Enter the person\'s name.');
    if (!preg_match('/^[a-z0-9._-]{3,40}$/', $user)) fail('Usernames need 3 to 40 letters or numbers (dots, dashes and underscores are fine).');
    if (strlen($pass) < 8) fail('The password needs at least 8 characters.');
    $st = db()->prepare('SELECT id, active FROM users WHERE username = ?');
    $st->execute([$user]);
    if ($ex = $st->fetch()) {
        if ((int)$ex['active'] === 1) fail('That username is already taken.');
        db()->prepare('UPDATE users SET name = ?, password_hash = ?, active = 1 WHERE id = ?')->execute([$name, password_hash($pass, PASSWORD_DEFAULT), $ex['id']]);
    } else {
        db()->prepare('INSERT INTO users (username, name, password_hash) VALUES (?, ?, ?)')->execute([$user, $name, password_hash($pass, PASSWORD_DEFAULT)]);
    }
    json_out(['ok' => true]);
}

if ($action === 'deluser' && method() === 'POST') {
    $me = require_login();
    $id = int_in(body()['id'] ?? 0, 0, PHP_INT_MAX);
    if ($id === $me['id']) fail('You cannot remove your own login.');
    db()->prepare('UPDATE users SET active = 0 WHERE id = ?')->execute([$id]);
    json_out(['ok' => true]);
}

fail('Unknown request.', 404);
