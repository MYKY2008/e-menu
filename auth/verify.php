<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';

$token = trim($_GET['token'] ?? '');

if ($token === '') {
    flash('Neplatný aktivačný odkaz.', 'error');
    header('Location: ' . url('login'));
    exit;
}

$db = getDB();
$st = $db->prepare(
    "SELECT id, username, role, venue_limit FROM users WHERE verify_token = ? AND is_verified = 0"
);
$st->execute([$token]);
$user = $st->fetch();

if (!$user) {
    flash('Aktivačný odkaz je neplatný alebo bol už použitý.', 'error');
    header('Location: ' . url('login'));
    exit;
}

$db->prepare("UPDATE users SET is_verified = 1, verify_token = NULL WHERE id = ?")
   ->execute([(int)$user['id']]);

session_regenerate_id(true);
$_SESSION['user_id']     = (int)$user['id'];
$_SESSION['username']    = $user['username'];
$_SESSION['user_role']   = $user['role'];
$_SESSION['venue_limit'] = (int)$user['venue_limit'];
$_SESSION['login_ip']    = (string)($_SERVER['REMOTE_ADDR'] ?? '');

flash('Účet bol úspešne aktivovaný. Vitajte!', 'success');
$target = ($user['role'] === 'admin') ? url('admin') : url('dashboard');
header('Location: ' . $target);
exit;
