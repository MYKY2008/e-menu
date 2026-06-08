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
    flash('Vyplňte e-mail aj heslo.', 'error');
    header('Location: ' . url('login'));
    exit;
}

$db = getDB();
$st = $db->prepare("SELECT id, username, password, role, venue_limit FROM users WHERE username = ?");
$st->execute([$username]);
$user = $st->fetch();

if (!$user || !password_verify($password, $user['password'])) {
    flash('Nesprávny e-mail alebo heslo.', 'error');
    header('Location: ' . url('login'));
    exit;
}

// Regenerate session ID to prevent fixation
session_regenerate_id(true);

$_SESSION['user_id']     = (int)$user['id'];
$_SESSION['username']    = $user['username'];
$_SESSION['user_role']   = $user['role'];
$_SESSION['venue_limit'] = (int)$user['venue_limit'];

$target = ($user['role'] === 'admin') ? url('admin') : url('dashboard');
header('Location: ' . $target);
exit;
