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
    flash('Zadajte platný e-mail.', 'error');
    header('Location: ' . url('register'));
    exit;
}

if (strlen($password) < 8) {
    flash('Heslo musí mať aspoň 8 znakov.', 'error');
    header('Location: ' . url('register'));
    exit;
}

if ($password !== $password2) {
    flash('Heslá sa nezhodujú.', 'error');
    header('Location: ' . url('register'));
    exit;
}

$db = getDB();

// Check uniqueness
$st = $db->prepare("SELECT id FROM users WHERE username = ?");
$st->execute([$username]);
if ($st->fetch()) {
    flash('Tento e-mail je už zaregistrovaný.', 'error');
    header('Location: ' . url('register'));
    exit;
}

// First user in the system becomes admin
$count = (int)$db->query("SELECT COUNT(*) FROM users")->fetchColumn();
$role  = ($count === 0) ? 'admin' : 'user';

$hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

$db->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)")
   ->execute([$username, $hash, $role]);

$userId = (int)$db->lastInsertId();

session_regenerate_id(true);
$_SESSION['user_id']     = $userId;
$_SESSION['username']    = $username;
$_SESSION['user_role']   = $role;
$_SESSION['venue_limit'] = 1;

flash('Vitajte v GastroLink QR! Účet bol vytvorený.', 'success');
$target = ($role === 'admin') ? url('admin') : url('dashboard');
header('Location: ' . $target);
exit;
