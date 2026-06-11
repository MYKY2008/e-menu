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

$currentPassword  = (string)($payload['current_password'] ?? '');
$confirmationText = (string)($payload['confirmation_text'] ?? '');

if ($confirmationText !== 'ano chcem odstranit ucet') {
    echo json_encode(['ok' => false, 'error' => 'Potvrdzovacia veta nesúhlasí.']);
    exit;
}

$db     = getDB();
$userId = (int)$_SESSION['user_id'];

$userRow = $db->prepare("SELECT password FROM users WHERE id = ?");
$userRow->execute([$userId]);
$userRow = $userRow->fetch();

if (!$userRow || !password_verify($currentPassword, $userRow['password'])) {
    echo json_encode(['ok' => false, 'error' => 'Zadané heslo je nesprávne.']);
    exit;
}

// Delete all files for each venue before removing DB records
$slugs = $db->prepare("SELECT slug FROM venues WHERE user_id = ?");
$slugs->execute([$userId]);
foreach ($slugs->fetchAll() as $row) {
    deleteVenueFiles($row['slug']);
}

// SQLite CASCADE removes categories, items, scans, venue_settings
$db->prepare("DELETE FROM users WHERE id = ?")->execute([$userId]);

session_destroy();

echo json_encode(['ok' => true]);
