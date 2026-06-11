<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

ob_start();
try {
    if (!isLoggedIn()) {
        http_response_code(401);
        throw new RuntimeException('Nie ste prihlásený.');
    }

    $raw     = (string)(file_get_contents('php://input') ?: '{}');
    $payload = json_decode($raw, true);
    if (!is_array($payload)) throw new InvalidArgumentException('Neplatný formát dát.');
    $payload = purify($payload);

    if (!csrfValid((string)($payload['csrf'] ?? ''))) {
        http_response_code(403);
        throw new RuntimeException('Bezpečnostná chyba (CSRF).');
    }

    $db     = getDB();
    $userId = (int)$_SESSION['user_id'];
    $action = (string)($payload['action'] ?? '');

    switch ($action) {

        case 'cancel_plan': {
            $planSt = $db->prepare(
                "SELECT plan_name, plan_ends_at, stripe_subscription_id FROM users WHERE id = ?"
            );
            $planSt->execute([$userId]);
            $planRow    = $planSt->fetch();
            $planName   = (string)($planRow['plan_name']             ?? 'free');
            $planEndsAt = $planRow['plan_ends_at']                   ?? null;
            $subId      = (string)($planRow['stripe_subscription_id'] ?? '');

            if (!in_array($planName, ['pro', 'ultra', 'custom'], true)) {
                throw new InvalidArgumentException('Nemáte aktívny platený plán.');
            }

            // Tell Stripe to stop renewing at period end
            if ($subId !== '' && extension_loaded('curl')) {
                $stripeKey = $_ENV['STRIPE_SECRET_KEY'] ?? '';
                if ($stripeKey !== '') {
                    $ch = curl_init('https://api.stripe.com/v1/subscriptions/' . rawurlencode($subId));
                    curl_setopt_array($ch, [
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_CUSTOMREQUEST  => 'POST',
                        CURLOPT_POSTFIELDS     => 'cancel_at_period_end=true',
                        CURLOPT_USERPWD        => $stripeKey . ':',
                        CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
                        CURLOPT_TIMEOUT        => 10,
                        CURLOPT_SSL_VERIFYPEER => false,
                    ]);
                    $sResp = curl_exec($ch);
                    $sCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    $sErrN = curl_errno($ch);
                    $sErrM = curl_error($ch);
                    if ($sErrN !== 0) {
                        gl_log("cancel_plan Stripe cURL #{$sErrN}: {$sErrM}");
                    } elseif ($sCode !== 200) {
                        $sErrData = json_decode((string)$sResp, true);
                        gl_log('cancel_plan Stripe HTTP ' . $sCode . ': '
                            . ($sErrData['error']['message'] ?? '?'));
                    }
                }
            }

            if ($planEndsAt !== null && strtotime((string)$planEndsAt) > time()) {
                // Active billing period: schedule downgrade, keep data untouched
                $db->prepare("UPDATE users SET next_plan_name='free' WHERE id=?")
                   ->execute([$userId]);
                ob_end_clean();
                echo json_encode(['ok' => true, 'deferred' => true, 'ends_at' => $planEndsAt]);
                exit;
            }

            // No billing period: switch to free limits immediately
            $db->prepare(
                "UPDATE users SET plan_name='free', max_venues=1, max_categories=3,
                 max_items_per_cat=5, venue_limit=1, plan_ends_at=NULL, next_plan_name=NULL WHERE id=?"
            )->execute([$userId]);

            ob_end_clean();
            echo json_encode(['ok' => true, 'deferred' => false]);
            exit;
        }

        case 'reset_menu': {
            $slug = sanitizeSlug((string)($payload['slug'] ?? ''));
            if (!preg_match(SLUG_PATTERN, $slug)) {
                throw new InvalidArgumentException('Neplatný slug.');
            }
            $vSt = $db->prepare("SELECT user_id FROM venues WHERE slug = ?");
            $vSt->execute([$slug]);
            $vRow = $vSt->fetch();
            if (!$vRow || (int)$vRow['user_id'] !== $userId) {
                throw new RuntimeException('Prístup zamietnutý.');
            }
            // Delete physical image files before removing DB records
            $imgSt = $db->prepare(
                "SELECT i.image FROM items i
                 JOIN categories c ON c.id = i.category_id
                 WHERE c.venue_slug = ? AND i.image IS NOT NULL"
            );
            $imgSt->execute([$slug]);
            foreach ($imgSt->fetchAll() as $row) {
                deleteImageFile($row['image']);
            }
            $db->prepare("DELETE FROM categories WHERE venue_slug = ?")
               ->execute([$slug]);
            ob_end_clean();
            echo json_encode(['ok' => true]);
            exit;
        }

        default:
            throw new InvalidArgumentException('Neznáma akcia.');
    }

} catch (InvalidArgumentException $e) {
    ob_end_clean();
    if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
} catch (Throwable $e) {
    ob_end_clean();
    if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
