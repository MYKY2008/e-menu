<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

if (!extension_loaded('curl')) {
    http_response_code(503);
    echo json_encode(['ok' => false, 'error' => 'Chýba PHP rozšírenie cURL. Povoľte ho v php.ini.']);
    exit;
}

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Nie ste prihlásený.']);
    exit;
}

$raw     = (string)(file_get_contents('php://input') ?: '{}');
$payload = json_decode($raw, true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Neplatný formát dát.']);
    exit;
}

if (!csrfValid((string)($payload['csrf'] ?? ''))) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Bezpečnostná chyba (CSRF).']);
    exit;
}

$stripeKey = $_ENV['STRIPE_SECRET_KEY'] ?? '';
if ($stripeKey === '') {
    http_response_code(503);
    echo json_encode(['ok' => false, 'error' => 'Platobný systém nie je nakonfigurovaný.']);
    exit;
}

$db     = getDB();
$userId = (int)$_SESSION['user_id'];

$st = $db->prepare("SELECT stripe_customer_id FROM users WHERE id = ?");
$st->execute([$userId]);
$row        = $st->fetch();
$customerId = (string)($row['stripe_customer_id'] ?? '');

if ($customerId === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Pre váš účet neexistuje Stripe zákazník. Kontaktujte podporu.']);
    exit;
}

$returnUrl = baseUrl() . '/profile';

$ch = curl_init('https://api.stripe.com/v1/billing_portal/sessions');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => http_build_query(['customer' => $customerId, 'return_url' => $returnUrl]),
    CURLOPT_USERPWD        => $stripeKey . ':',
    CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_SSL_VERIFYPEER => false,
]);
$response  = curl_exec($ch);
$httpCode  = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErrNo = curl_errno($ch);
$curlError = curl_error($ch);

if ($response === false || $curlErrNo !== 0) {
    $isSsl = stripos($curlError, 'ssl') !== false || stripos($curlError, 'certificate') !== false;
    gl_log('create_portal_session cURL #' . $curlErrNo . ': ' . $curlError);
    http_response_code(502);
    echo json_encode(['ok' => false, 'error' => $isSsl
        ? 'SSL chyba pri spojení so Stripe.'
        : 'Chyba spojenia so Stripe. Skontrolujte error.log.']);
    exit;
}

if ($httpCode !== 200) {
    $errData   = json_decode((string)$response, true);
    $stripeErr = $errData['error']['message'] ?? 'Stripe chyba. Skúste neskôr.';
    gl_log('create_portal_session Stripe HTTP ' . $httpCode . ': ' . $stripeErr);
    http_response_code(502);
    echo json_encode(['ok' => false, 'error' => $stripeErr]);
    exit;
}

$session = json_decode((string)$response, true);
if (empty($session['url'])) {
    http_response_code(502);
    echo json_encode(['ok' => false, 'error' => 'Neplatná odpoveď od Stripe.']);
    exit;
}

echo json_encode(['ok' => true, 'url' => $session['url']]);
