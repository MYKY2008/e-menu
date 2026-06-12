<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . url('register'));
    exit;
}

if (!csrfValid((string)($_POST['csrf'] ?? ''))) {
    flash('Bezpečnostná chyba. Skúste znova.', 'error');
    header('Location: ' . url('register'));
    exit;
}

$username  = strtolower(trim($_POST['username'] ?? ''));
$password  = (string)($_POST['password']  ?? '');
$password2 = (string)($_POST['password2'] ?? '');

// ── Validate input ────────────────────────────────────────────
if (!filter_var($username, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['old_input'] = ['username' => $username];
    flash('Zadajte platný e-mail.', 'error');
    header('Location: ' . url('register'));
    exit;
}

if (strlen($password) < 8) {
    $_SESSION['old_input'] = ['username' => $username];
    flash('Heslo musí mať aspoň 8 znakov.', 'error');
    header('Location: ' . url('register'));
    exit;
}

if ($password !== $password2) {
    $_SESSION['old_input'] = ['username' => $username];
    flash('Heslá sa nezhodujú.', 'error');
    header('Location: ' . url('register'));
    exit;
}

$db = getDB();

// ── Vyčisti expirované neoverené účty (staršie ako 1 hodina) ─────
// Umožní re-registráciu rovnakého e-mailu ak predchádzajúci účet nevyužil token
$db->prepare(
    "DELETE FROM users WHERE is_verified = 0 AND verify_token IS NOT NULL AND created_at < ?"
)->execute([date('Y-m-d\TH:i:s\Z', time() - 3600)]);

// ── Rate limit: max 3 registration attempts per IP per 15 min ────
$ip          = getRealIp();
$now         = time();
$windowStart = $now - 900;
$stCount     = $db->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip_address = ? AND timestamp >= ?");
$stCount->execute([$ip, $windowStart]);
if ((int)$stCount->fetchColumn() >= 3) {
    flash('Príliš veľa pokusov o registráciu. Skúste to znova o 15 minút.', 'error');
    header('Location: ' . url('register'));
    exit;
}
$db->prepare("INSERT INTO login_attempts (ip_address, timestamp) VALUES (?, ?)")->execute([$ip, $now]);

// Check uniqueness — do NOT reveal whether the address is already registered
$st = $db->prepare("SELECT id FROM users WHERE username = ?");
$st->execute([$username]);
if ($st->fetch()) {
    flash('Ak je tento e-mail voľný, bol naň odoslaný aktivačný odkaz. Skontrolujte si schránku.', 'success');
    header('Location: ' . url('login'));
    exit;
}

// First user in the system becomes admin
$count = (int)$db->query("SELECT COUNT(*) FROM users")->fetchColumn();
$role  = ($count === 0) ? 'admin' : 'user';

// Clear any existing session before creating the new account
session_unset();
session_destroy();
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'cookie_lifetime' => 0,
    'use_strict_mode' => true,
    'gc_maxlifetime'  => 7200,
]);

$hash  = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
$token = bin2hex(random_bytes(32));

$db->prepare("INSERT INTO users (username, password, role, is_verified, verify_token) VALUES (?, ?, ?, 0, ?)")
   ->execute([$username, $hash, $role, $token]);

$verifyLink = url('verify') . '?token=' . $token;
$emailSent  = sendEmail(
    $username,
    'Aktivujte svoj účet — GastroLink QR',
    '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="font-family:Inter,sans-serif;background:#f8fafc;margin:0;padding:40px 20px">'
    . '<div style="max-width:480px;margin:0 auto;background:#fff;border-radius:24px;padding:40px;box-shadow:0 4px 24px rgba(0,0,0,.06)">'
    . '<h1 style="font-size:22px;font-weight:800;color:#1e293b;margin:0 0 8px">Vitajte v GastroLink QR!</h1>'
    . '<p style="color:#64748b;margin:0 0 24px">Váš účet bol vytvorený. Kliknite na tlačidlo nižšie a aktivujte ho.</p>'
    . '<a href="' . e($verifyLink) . '" style="display:inline-block;padding:14px 28px;background:#4f46e5;color:#fff;border-radius:14px;text-decoration:none;font-weight:700;font-size:15px">Aktivovať účet →</a>'
    . '<p style="margin-top:24px;color:#94a3b8;font-size:13px">Ak ste si účet nevytvorili vy, tento e-mail ignorujte.</p>'
    . '</div></body></html>'
);

$_SESSION['registered_email'] = $username;
flash(
    $emailSent
        ? 'Registrácia úspešná! Skontrolujte e-mail a aktivujte účet.'
        : 'Účet bol vytvorený. Odoslanie aktivačného e-mailu zlyhalo — kontaktujte podporu.',
    $emailSent ? 'success' : 'error'
);
header('Location: ' . url('register?success=1'));
exit;
