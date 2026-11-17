<?php
declare(strict_types=1);

define('APP_DIR', dirname(__DIR__));
define('DATA_DIR', APP_DIR . '/data');
define('PHOTO_DIR', DATA_DIR . '/photos');

if (!is_file(APP_DIR . '/config.php')) {
    http_response_code(500);
    exit('config.php manquant : copiez config.sample.php en config.php puis lancez install.php');
}
$GLOBALS['CFG'] = require APP_DIR . '/config.php';
date_default_timezone_set(cfg('timezone', 'Europe/Paris'));
mb_internal_encoding('UTF-8');

function cfg(string $key, $default = null)
{
    $v = $GLOBALS['CFG'];
    foreach (explode('.', $key) as $k) {
        if (!is_array($v) || !array_key_exists($k, $v)) return $default;
        $v = $v[$k];
    }
    return $v;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo) return $pdo;
    $c = cfg('db');
    if ($c['driver'] === 'sqlite') {
        $pdo = new PDO('sqlite:' . $c['sqlite']);
        $pdo->exec('PRAGMA foreign_keys = ON');
    } else {
        $pdo = new PDO(
            "mysql:host={$c['host']};dbname={$c['name']};charset=utf8mb4",
            $c['user'], $c['pass']
        );
    }
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    return $pdo;
}

function q(string $sql, array $p = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($p);
    return $st;
}
function q1(string $sql, array $p = []): ?array
{
    $r = q($sql, $p)->fetch();
    return $r ?: null;
}
function qv(string $sql, array $p = [])
{
    return q($sql, $p)->fetchColumn();
}
function insert(string $table, array $data): int
{
    $cols = array_keys($data);
    q("INSERT INTO $table (" . implode(',', $cols) . ") VALUES (" . implode(',', array_fill(0, count($cols), '?')) . ")", array_values($data));
    return (int) db()->lastInsertId();
}
function update(string $table, int $id, array $data): void
{
    $set = implode(',', array_map(fn($c) => "$c = ?", array_keys($data)));
    q("UPDATE $table SET $set WHERE id = ?", [...array_values($data), $id]);
}

function now(): string { return date('Y-m-d H:i:s'); }
function plus_jours(string $date, int $j): string { return date('Y-m-d H:i:s', strtotime($date . " +$j days")); }
function fdate(?string $d, bool $heure = false): string
{
    if (!$d) return '';
    return date($heure ? 'd/m/Y H:i' : 'd/m/Y', strtotime($d));
}
function h($s): string { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function token(int $bytes = 16): string { return bin2hex(random_bytes($bytes)); }
function url(string $path = ''): string { return rtrim(cfg('base_url'), '/') . '/' . ltrim($path, '/'); }
function redirect(string $to): never { header('Location: ' . $to); exit; }
function post(string $k, $d = '') { return isset($_POST[$k]) ? (is_string($_POST[$k]) ? trim($_POST[$k]) : $_POST[$k]) : $d; }
function get(string $k, $d = '') { return isset($_GET[$k]) ? trim((string) $_GET[$k]) : $d; }

function nom_propre(string $s): string { return mb_strtoupper(trim(preg_replace('/\s+/', ' ', $s))); }
function prenom_propre(string $s): string
{
    $s = mb_strtolower(trim(preg_replace('/\s+/', ' ', $s)));
    return preg_replace_callback('/(^|[\s\-])(\p{L})/u', fn($m) => $m[1] . mb_strtoupper($m[2]), $s);
}
function club_propre(string $s): string { return trim(preg_replace('/\s+/', ' ', trim($s, " \t\n\r\"'"))); }

// ---------- Session / sécurité ----------
function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    session_name('cadavre');
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS'])]);
    session_start();
}
function csrf(): string
{
    start_session();
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = token();
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . csrf() . '">'; }
function check_csrf(): void
{
    start_session();
    if (!hash_equals($_SESSION['csrf'] ?? '', (string) ($_POST['csrf'] ?? ''))) {
        http_response_code(400);
        exit('Jeton de sécurité invalide, rechargez la page.');
    }
}
function flash(?string $msg = null, string $type = 'ok')
{
    start_session();
    if ($msg !== null) { $_SESSION['flash'][] = [$type, $msg]; return null; }
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}
function admin(): ?array
{
    start_session();
    if (empty($_SESSION['admin_id'])) return null;
    static $a = null;
    return $a ??= q1('SELECT * FROM admins WHERE id = ?', [$_SESSION['admin_id']]);
}
function require_admin(): array
{
    $a = admin();
    if (!$a) redirect(url('admin/login.php'));
    return $a;
}

require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/logic.php';
