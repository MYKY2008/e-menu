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

$token     = trim((string)($_POST['token']     ?? ''));
$password  = (string)($_POST['password']  ?? '');
$password2 = (string)($_POST['password2'] ?? '');

if ($token === '') {
    flash('Neplatný token.', 'error');
    header('Location: ' . url('forgot-password'));
    exit;
}

if (strlen($password) < 8) {
    flash('Heslo musí mať aspoň 8 znakov.', 'error');
    header('Location: ' . url('reset-password') . '?token=' . urlencode($token));
    exit;
}

if (!hash_equals($password, $password2)) {
    flash('Heslá sa nezhodujú.', 'error');
    header('Location: ' . url('reset-password') . '?token=' . urlencode($token));
    exit;
}

$db  = getDB();
$now = time();
$st  = $db->prepare("SELECT * FROM password_resets WHERE token = ? AND expires_at > ? LIMIT 1");
$st->execute([$token, $now]);
$reset = $st->fetch();

if (!$reset) {
    flash('Odkaz na reset hesla je neplatný alebo vypršal. Skúste znova.', 'error');
    header('Location: ' . url('forgot-password'));
    exit;
}

$hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
$db->prepare("UPDATE users SET password = ? WHERE username = ?")->execute([$hash, $reset['email']]);

// Remove used token (and any duplicates for this email)
$db->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$reset['email']]);

flash('Heslo bolo úspešne zmenené. Môžete sa prihlásiť.', 'success');
header('Location: ' . url('login'));
exit;
