<?php
// One-time setup: creates the first login. Delete this file after you use it.
declare(strict_types=1);

$error = ''; $done = false; $locked = false;
$cfg = __DIR__ . '/api/config.php';

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

if (!is_file($cfg)) {
    $error = 'api/config.php is missing. Copy api/config.sample.php to api/config.php and fill in your database details first.';
} else {
    $C = require $cfg;
    try {
        $pdo = new PDO('mysql:host=' . $C['db_host'] . ';dbname=' . $C['db_name'] . ';charset=utf8mb4', $C['db_user'], $C['db_pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $count = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
        $locked = $count > 0;
        $ready = true;
        if (!$locked && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            $name = trim((string)($_POST['name'] ?? ''));
            $user = strtolower(trim((string)($_POST['username'] ?? '')));
            $pass = (string)($_POST['password'] ?? '');
            if ($name === '') $error = 'Enter your name.';
            elseif (!preg_match('/^[a-z0-9._-]{3,40}$/', $user)) $error = 'Usernames need 3 to 40 letters or numbers.';
            elseif (strlen($pass) < 8) $error = 'The password needs at least 8 characters.';
            elseif ($pass !== (string)($_POST['password2'] ?? '')) $error = 'The two passwords do not match.';
            else {
                $pdo->prepare('INSERT INTO users (username, name, password_hash) VALUES (?, ?, ?)')
                    ->execute([$user, mb_substr($name, 0, 80), password_hash($pass, PASSWORD_DEFAULT)]);
                $done = true;
            }
        }
    } catch (PDOException $e) {
        $error = str_contains($e->getMessage(), "doesn't exist")
            ? 'The tables are not there yet. Import database/schema.sql in phpMyAdmin first.'
            : 'Could not connect to the database. Check the details in api/config.php. (' . $e->getMessage() . ')';
    }
}
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow"><title>Set up Homestay Maintenance</title>
<style>
body{margin:0;font:15px/1.5 system-ui,-apple-system,"Segoe UI",sans-serif;background:#f3f5f1;color:#1c241f;display:grid;place-items:center;min-height:100vh;padding:16px;box-sizing:border-box}
.box{background:#fff;border:1px solid #d5dbd1;border-radius:12px;padding:24px;width:min(420px,100%);display:flex;flex-direction:column;gap:12px;box-sizing:border-box}
h1{margin:0;font-size:1.3rem}label{font-size:.8rem;font-weight:600;color:#5d6a61}input{font:inherit;padding:9px 10px;border:1px solid #d5dbd1;border-radius:8px;width:100%;box-sizing:border-box}
button{font:inherit;font-weight:600;padding:10px;border-radius:8px;border:0;background:#1f6b4f;color:#fff;cursor:pointer}
.err{color:#b3261e;font-weight:600}.ok{color:#2b7a3d;font-weight:600}a{color:#1f6b4f}
</style></head><body><div class="box">
<h1>Set up Homestay Maintenance</h1>
<?php if ($error): ?><div class="err"><?= h($error) ?></div><?php endif; ?>
<?php if ($done): ?>
  <div class="ok">Your login is ready.</div>
  <p><b>Now delete install.php</b> from the File Manager, then <a href="./">open the app and log in</a>.</p>
<?php elseif ($locked): ?>
  <p>Setup is already done. <b>Delete install.php</b> from the File Manager. To add staff, log in and use Rooms &amp; settings → Logins.</p>
  <p><a href="./">Open the app</a></p>
<?php elseif (!empty($ready)): ?>
  <p>Create the first login (yours). You can add staff logins inside the app later.</p>
  <form method="post" style="display:flex;flex-direction:column;gap:10px">
    <div><label for="name">Your name</label><input id="name" name="name" required value="<?= h($_POST['name'] ?? '') ?>"></div>
    <div><label for="username">Username</label><input id="username" name="username" required autocomplete="username" value="<?= h($_POST['username'] ?? '') ?>"></div>
    <div><label for="password">Password (8+ characters)</label><input id="password" name="password" type="password" required autocomplete="new-password"></div>
    <div><label for="password2">Password again</label><input id="password2" name="password2" type="password" required autocomplete="new-password"></div>
    <button type="submit">Create login</button>
  </form>
<?php endif; ?>
</div></body></html>
