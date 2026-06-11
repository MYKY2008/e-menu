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

$db     = getDB();
$userId = (int)$_SESSION['user_id'];
$action = (string)($payload['action'] ?? '');

// ── Billing data (no password required) ──────────────────────────
if ($action === 'billing') {
    $companyName = trim((string)($payload['company_name'] ?? ''));
    $ico         = trim((string)($payload['ico']         ?? ''));
    $dic         = trim((string)($payload['dic']         ?? ''));
    $icDph       = trim((string)($payload['ic_dph']      ?? ''));
    $street      = trim((string)($payload['street']      ?? ''));
    $city        = trim((string)($payload['city']        ?? ''));
    $zip         = trim((string)($payload['zip']         ?? ''));
    $country     = trim((string)($payload['country']     ?? ''));

    if (strlen($companyName) > 200 || strlen($ico) > 20 || strlen($dic) > 20 ||
        strlen($icDph) > 20 || strlen($street) > 200 || strlen($city) > 100 ||
        strlen($zip) > 20 || strlen($country) > 50) {
        echo json_encode(['ok' => false, 'error' => 'Niektoré polia sú príliš dlhé.']);
        exit;
    }

    if ($country !== '' && !in_array($country, ['Slovensko', 'Česko'], true)) {
        echo json_encode(['ok' => false, 'error' => 'Neplatná krajina.']);
        exit;
    }

    $db->prepare(
        "UPDATE users SET company_name=?, ico=?, dic=?, ic_dph=?,
         billing_street=?, billing_city=?, billing_zip=?, billing_country=? WHERE id=?"
    )->execute([
        $companyName !== '' ? $companyName : null,
        $ico         !== '' ? $ico         : null,
        $dic         !== '' ? $dic         : null,
        $icDph       !== '' ? $icDph       : null,
        $street      !== '' ? $street      : null,
        $city        !== '' ? $city        : null,
        $zip         !== '' ? $zip         : null,
        $country     !== '' ? $country     : null,
        $userId,
    ]);

    echo json_encode(['ok' => true]);
    exit;
}

// ── E-mail change (password required) ────────────────────────────
$currentPassword = (string)($payload['current_password'] ?? '');
$email           = strtolower(trim((string)($payload['email'] ?? '')));

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
