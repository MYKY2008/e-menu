<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

if (!extension_loaded('curl')) {
    http_response_code(503);
    echo json_encode(['ok' => false, 'error' => 'Chýba PHP rozšírenie cURL. Povoľte ho v php.ini a reštartujte server.']);
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

$planId = (string)($payload['plan_id'] ?? '');
if (!in_array($planId, ['pro', 'ultra', 'custom'], true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Neplatný plán.']);
    exit;
}

$stripeKey = $_ENV['STRIPE_SECRET_KEY'] ?? '';
if ($stripeKey === '') {
    http_response_code(503);
    echo json_encode(['ok' => false, 'error' => 'Platobný systém nie je nakonfigurovaný.']);
    exit;
}

$priceIds = [
    'pro'    => $_ENV['STRIPE_PRICE_PRO']    ?? '',
    'ultra'  => $_ENV['STRIPE_PRICE_ULTRA']  ?? '',
    'custom' => $_ENV['STRIPE_PRICE_CUSTOM'] ?? '',
];
$priceId = $priceIds[$planId] ?? '';
if ($priceId === '') {
    http_response_code(503);
    echo json_encode(['ok' => false, 'error' => 'Cena pre tento plán nie je nastavená.']);
    exit;
}

$db     = getDB();
$userId = (int)$_SESSION['user_id'];
$email  = (string)($_SESSION['username'] ?? '');

// If user already has a Stripe subscription, redirect to Customer Portal for plan changes
$stUser = $db->prepare("SELECT stripe_customer_id, stripe_subscription_id FROM users WHERE id = ?");
$stUser->execute([$userId]);
$uRow       = $stUser->fetch();
$existSubId = (string)($uRow['stripe_subscription_id'] ?? '');
$existCustId = (string)($uRow['stripe_customer_id']    ?? '');

if ($existSubId !== '' && $existCustId !== '') {
    $portalCh = curl_init('https://api.stripe.com/v1/billing_portal/sessions');
    curl_setopt_array($portalCh, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'customer'   => $existCustId,
            'return_url' => baseUrl() . '/plans',
        ]),
        CURLOPT_USERPWD        => $stripeKey . ':',
        CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $portalResp = curl_exec($portalCh);
    $portalCode = (int)curl_getinfo($portalCh, CURLINFO_HTTP_CODE);
    $portalErr  = curl_errno($portalCh);

    if ($portalResp !== false && $portalErr === 0 && $portalCode === 200) {
        $portal = json_decode((string)$portalResp, true);
        if (!empty($portal['url'])) {
            echo json_encode(['ok' => true, 'url' => $portal['url']]);
            exit;
        }
    }
    gl_log('create_session: portal fallback failed (code=' . $portalCode . ', errno=' . $portalErr . '), proceeding to checkout');
}

$appUrl     = baseUrl();
$successUrl = $appUrl . '/dashboard?payment=success&plan=' . urlencode($planId);
$cancelUrl  = $appUrl . '/plans';

$params = http_build_query([
    'mode'                                  => 'subscription',
    'line_items[0][price]'                  => $priceId,
    'line_items[0][quantity]'               => 1,
    'customer_email'                        => $email,
    'metadata[user_id]'                     => $userId,
    'metadata[plan_id]'                     => $planId,
    'subscription_data[metadata][user_id]'  => $userId,
    'subscription_data[metadata][plan_id]'  => $planId,
    'success_url'                           => $successUrl,
    'cancel_url'                            => $cancelUrl,
]);

$ch = curl_init('https://api.stripe.com/v1/checkout/sessions');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $params,
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
    gl_log('create_session cURL #' . $curlErrNo . ': ' . $curlError
        . ($isSsl ? ' → nastavte curl.cainfo v php.ini na cestu k cacert.pem' : ''));
    http_response_code(502);
    echo json_encode(['ok' => false, 'error' => $isSsl
        ? 'SSL chyba pri spojení so Stripe. Nastavte curl.cainfo v php.ini (pozri error.log).'
        : 'Chyba spojenia so Stripe. Skontrolujte error.log.']);
    exit;
}

if ($httpCode !== 200) {
    $errData   = json_decode((string)$response, true);
    $stripeErr = $errData['error']['message'] ?? 'Stripe chyba. Skúste neskôr.';
    gl_log('create_session Stripe HTTP ' . $httpCode . ': ' . $stripeErr);
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

// Uložiť pending objednávku
$db->prepare(
    "INSERT INTO orders (user_id, stripe_session_id, plan_name, amount, currency, status, created_at)
     VALUES (?, ?, ?, 0, 'EUR', 'pending', strftime('%Y-%m-%dT%H:%M:%SZ','now'))"
)->execute([$userId, $session['id'], $planId]);

echo json_encode(['ok' => true, 'url' => $session['url']]);
