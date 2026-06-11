<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Nie ste prihlásený.']);
    exit;
}

$payload = json_decode(file_get_contents('php://input'), true) ?? [];

if (!csrfValid((string)($payload['csrf'] ?? ''))) {
    echo json_encode(['ok' => false, 'error' => 'Bezpečnostná chyba.']);
    exit;
}

$currentPassword = (string)($payload['current_password'] ?? '');
$email           = strtolower(trim((string)($payload['email'] ?? '')));

$db     = getDB();
$userId = (int)$_SESSION['user_id'];

$userRow = $db->prepare("SELECT password FROM users WHERE id = ?");
$userRow->execute([$userId]);
$userRow = $userRow->fetch();

if (!$userRow || !password_verify($currentPassword, $userRow['password'])) {
    echo json_encode(['ok' => false, 'error' => 'Zadané heslo je nesprávne.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['ok' => false, 'error' => 'Neplatný formát e-mailu.']);
    exit;
}

$st = $db->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
$st->execute([$email, $userId]);
if ($st->fetch()) {
    echo json_encode(['ok' => false, 'error' => 'Tento e-mail je už zaregistrovaný.']);
    exit;
}

$db->prepare("UPDATE users SET username = ? WHERE id = ?")
   ->execute([$email, $userId]);

$_SESSION['username'] = $email;

echo json_encode(['ok' => true]);
