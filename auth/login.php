<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . url('login'));
    exit;
}

if (!csrfValid((string)($_POST['csrf'] ?? ''))) {
    flash('Bezpečnostná chyba. Skúste znova.', 'error');
    header('Location: ' . url('login'));
    exit;
}

$username = trim($_POST['username'] ?? '');
$password = (string)($_POST['password'] ?? '');

if ($username === '' || $password === '') {
    $_SESSION['old_input'] = ['username' => $username];
    flash('Vyplňte e-mail aj heslo.', 'error');
    header('Location: ' . url('login'));
    exit;
}

$db  = getDB();
$ip  = getRealIp();
$now = time();
$windowStart = $now - 900; // 15 minutes

// ── Rate limit check ──────────────────────────────────────────────
$stCount = $db->prepare(
    "SELECT COUNT(*) FROM login_attempts WHERE ip_address = ? AND timestamp >= ?"
);
$stCount->execute([$ip, $windowStart]);
if ((int)$stCount->fetchColumn() >= 5) {
    $_SESSION['old_input'] = ['username' => $username];
    flash('Príliš veľa neúspešných pokusov. Skúste to znova o 15 minút.', 'error');
    header('Location: ' . url('login'));
    exit;
}

// ── Verify credentials ────────────────────────────────────────────
$st = $db->prepare("SELECT id, username, password, role, venue_limit, is_verified FROM users WHERE username = ?");
$st->execute([$username]);
$user = $st->fetch();

if (!$user || !password_verify($password, $user['password'])) {
    // Record failed attempt + clean up old records (> 24 h)
    $db->prepare("DELETE FROM login_attempts WHERE timestamp < ?")->execute([$now - 86400]);
    $db->prepare("INSERT INTO login_attempts (ip_address, timestamp) VALUES (?, ?)")
       ->execute([$ip, $now]);

    $_SESSION['old_input'] = ['username' => $username];
    flash('Nesprávny e-mail alebo heslo.', 'error');
    header('Location: ' . url('login'));
    exit;
}

// ── Email verification check ──────────────────────────────────────
if (!(int)($user['is_verified'] ?? 0)) {
    $_SESSION['old_input'] = ['username' => $username];
    flash('Váš účet nie je aktivovaný. Skontrolujte e-mail s aktivačným odkazom.', 'error');
    header('Location: ' . url('login'));
    exit;
}

// ── Success: clear attempts for this IP ───────────────────────────
$db->prepare("DELETE FROM login_attempts WHERE ip_address = ?")->execute([$ip]);

regenerateSession();
unset($_SESSION['csrf']);

$db->prepare("UPDATE users SET last_login_at = strftime('%Y-%m-%dT%H:%M:%SZ','now') WHERE id = ?")
   ->execute([(int)$user['id']]);

$_SESSION['user_id']     = (int)$user['id'];
$_SESSION['username']    = $user['username'];
$_SESSION['user_role']   = $user['role'];
$_SESSION['venue_limit'] = (int)$user['venue_limit'];
$_SESSION['is_verified'] = 1;

$target = ($user['role'] === 'admin') ? url('admin') : url('dashboard');
header('Location: ' . $target);
exit;
