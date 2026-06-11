<?php
declare(strict_types=1);
// Webhook nesmie spúšťať session (volá ho Stripe, nie prehliadač)
define('SKIP_SESSION', true);
require_once __DIR__ . '/../../config.php';

header('Content-Type: application/json; charset=utf-8');

$stripeKey     = $_ENV['STRIPE_SECRET_KEY']     ?? '';
$webhookSecret = $_ENV['STRIPE_WEBHOOK_SECRET'] ?? '';

$rawPayload = (string)file_get_contents('php://input');
$sigHeader  = (string)($_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '');

// ── Stripe Signature Verification ────────────────────────────────
if ($webhookSecret !== '') {
    $parts = [];
    foreach (explode(',', $sigHeader) as $part) {
        $kv = explode('=', $part, 2);
        if (count($kv) === 2) $parts[$kv[0]][] = $kv[1];
    }
    $timestamp = (int)($parts['t'][0] ?? 0);
    if (abs(time() - $timestamp) > 300) {
        http_response_code(400);
        echo json_encode(['error' => 'Timestamp expired.']);
        exit;
    }
    $expected   = hash_hmac('sha256', $timestamp . '.' . $rawPayload, $webhookSecret);
    $signatures = $parts['v1'] ?? [];
    $valid = false;
    foreach ($signatures as $sig) {
        if (hash_equals($expected, $sig)) { $valid = true; break; }
    }
    if (!$valid) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid signature.']);
        exit;
    }
}

$event = json_decode($rawPayload, true);
if (!is_array($event)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON.']);
    exit;
}

$db = getDB();

switch ($event['type'] ?? '') {

    // ── Platba úspešná (prvé predplatné) ─────────────────────────
    case 'checkout.session.completed': {
        $session   = $event['data']['object'] ?? [];
        $sessionId = (string)($session['id']                       ?? '');
        $userId    = (int)($session['metadata']['user_id']         ?? 0);
        $planId    = (string)($session['metadata']['plan_id']      ?? '');
        $amount    = (float)(($session['amount_total']             ?? 0) / 100);
        $subId     = (string)($session['subscription']             ?? '');
        $custId    = (string)($session['customer']                 ?? '');

        if (!$userId || !in_array($planId, ['pro', 'ultra', 'custom'], true)) break;

        [$maxV, $maxC, $maxI] = match($planId) {
            'ultra'  => [1, 20, 50],
            'pro'    => [1, 10, 25],
            default  => [1,  3,  5],
        };
        $planEndsAt = date('Y-m-d\TH:i:s\Z', strtotime('+1 month'));

        $db->prepare(
            "UPDATE users SET plan_name=?, max_venues=?, max_categories=?, max_items_per_cat=?,
             venue_limit=?, plan_ends_at=?, next_plan_name=NULL,
             stripe_customer_id=?, stripe_subscription_id=? WHERE id=?"
        )->execute([$planId, $maxV, $maxC, $maxI, $maxV, $planEndsAt,
                    $custId ?: null, $subId ?: null, $userId]);

        $db->prepare(
            "UPDATE orders SET status='paid', amount=? WHERE stripe_session_id=?"
        )->execute([$amount, $sessionId]);

        break;
    }

    // ── Obnova predplatného ───────────────────────────────────────
    case 'invoice.paid': {
        $invoice = $event['data']['object'] ?? [];
        $subId   = (string)($invoice['subscription'] ?? '');
        $amount  = (float)(($invoice['amount_paid']  ?? 0) / 100);
        $invId   = (string)($invoice['id']           ?? '');

        if (!$subId) break;

        $st = $db->prepare("SELECT id, plan_name FROM users WHERE stripe_subscription_id = ?");
        $st->execute([$subId]);
        $user = $st->fetch();
        if (!$user) break;

        $planEndsAt = date('Y-m-d\TH:i:s\Z', strtotime('+1 month'));
        $db->prepare("UPDATE users SET plan_ends_at=? WHERE id=?")->execute([$planEndsAt, (int)$user['id']]);

        $db->prepare(
            "INSERT INTO orders (user_id, plan_name, amount, currency, status, invoice_id, created_at)
             VALUES (?, ?, ?, 'EUR', 'paid', ?, strftime('%Y-%m-%dT%H:%M:%SZ','now'))"
        )->execute([(int)$user['id'], $user['plan_name'], $amount, $invId ?: null]);

        break;
    }

    // ── Neúspešná platba ──────────────────────────────────────────
    case 'invoice.payment_failed': {
        $invoice = $event['data']['object'] ?? [];
        $subId   = (string)($invoice['subscription'] ?? '');
        if (!$subId) break;

        $st = $db->prepare("SELECT id, plan_name FROM users WHERE stripe_subscription_id = ?");
        $st->execute([$subId]);
        $user = $st->fetch();
        if (!$user) break;

        $db->prepare(
            "INSERT INTO orders (user_id, plan_name, amount, currency, status, created_at)
             VALUES (?, ?, 0, 'EUR', 'failed', strftime('%Y-%m-%dT%H:%M:%SZ','now'))"
        )->execute([(int)$user['id'], $user['plan_name']]);

        break;
    }

    // Ostatné eventy ignorujeme
    default: break;
}

http_response_code(200);
echo json_encode(['ok' => true]);
