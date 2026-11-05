<?php
// Shared helpers: database, JSON responses, login sessions, input cleaning.
// Reusable as-is for your other tools.
declare(strict_types=1);

if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'lib.php') { http_response_code(404); exit; }

$cfgFile = __DIR__ . '/config.php';
if (!is_file($cfgFile)) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Setup is not finished: copy api/config.sample.php to api/config.php and fill in your database details.']);
    exit;
}
$CONFIG = require $cfgFile;
date_default_timezone_set($CONFIG['timezone'] ?? 'Asia/Kolkata');

set_exception_handler(function (Throwable $e) {
    global $CONFIG;
    error_log('[homestay] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    $msg = 'Something went wrong on the server. Try again.';
    if ($e instanceof PDOException && str_contains($e->getMessage(), 'Access denied')) {
        $msg = 'Could not connect to the database. Check the details in api/config.php.';
    }
    if (!empty($CONFIG['debug'])) $msg .= ' (' . $e->getMessage() . ')';
    json_out(['error' => $msg], 500);
});

function db(): PDO {
    static $pdo = null;
    global $CONFIG;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . $CONFIG['db_host'] . ';dbname=' . $CONFIG['db_name'] . ';charset=utf8mb4';
        $pdo = new PDO($dsn, $CONFIG['db_user'], $CONFIG['db_pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

function json_out($data, int $code = 200): never {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function fail(string $msg, int $code = 400): never {
    if (db_in_tx()) db()->rollBack();
    json_out(['error' => $msg], $code);
}

function db_in_tx(): bool {
    try { return db()->inTransaction(); } catch (Throwable $e) { return false; }
}

function method(): string { return $_SERVER['REQUEST_METHOD'] ?? 'GET'; }

function body(): array {
    $d = json_decode(file_get_contents('php://input') ?: 'null', true);
    if (!is_array($d)) fail('The request was not understood.');
    return $d;
}

// ---- sessions & login -------------------------------------------------
function start_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
           || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $life = 60 * 60 * 24 * 30; // stay logged in for 30 days on a device
    $dir = __DIR__ . '/.sessions';
    if (!is_dir($dir)) @mkdir($dir, 0700, true);
    if (is_dir($dir) && is_writable($dir)) session_save_path($dir);
    ini_set('session.gc_maxlifetime', (string)$life);
    ini_set('session.use_strict_mode', '1');
    session_name('hsm_sid');
    session_set_cookie_params(['lifetime' => $life, 'path' => '/', 'secure' => $secure, 'httponly' => true, 'samesite' => 'Strict']);
    session_start();
}

function require_login(): array {
    start_session();
    if (empty($_SESSION['uid'])) fail('Please log in.', 401);
    if (method() !== 'GET') {
        $t = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!is_string($t) || !hash_equals($_SESSION['csrf'] ?? '', $t)) fail('Your session has expired. Reload the page.', 403);
    }
    return ['id' => (int)$_SESSION['uid'], 'name' => $_SESSION['uname'] ?? '', 'username' => $_SESSION['uuser'] ?? ''];
}

// ---- input cleaning ------------------------------------------------------
function str_in($v, int $max = 255): string { return mb_substr(trim(is_scalar($v) ? (string)$v : ''), 0, $max); }
function num_in($v, float $min = 0, float $max = 99999999): float { $f = is_numeric($v) ? (float)$v : 0.0; return max($min, min($max, round($f, 2))); }
function int_in($v, int $min = 0, int $max = 100000): int { $i = is_numeric($v) ? (int)$v : 0; return max($min, min($max, $i)); }
function date_in($v): ?string {
    $v = is_string($v) ? $v : '';
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $v, $m) || !checkdate((int)$m[2], (int)$m[3], (int)$m[1])) return null;
    return $v;
}
function time_in($v): ?string { $v = is_string($v) ? $v : ''; return preg_match('/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', $v) ? substr($v, 0, 5) : null; }
function id_in($v): string {
    $v = is_string($v) ? $v : '';
    if (!preg_match('/^[A-Za-z0-9_-]{1,40}$/', $v)) fail('Invalid record id.');
    return $v;
}
