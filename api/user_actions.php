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
            $planSt = $db->prepare("SELECT plan_name, plan_ends_at FROM users WHERE id = ?");
            $planSt->execute([$userId]);
            $planRow    = $planSt->fetch();
            $planName   = (string)($planRow['plan_name']   ?? 'free');
            $planEndsAt = $planRow['plan_ends_at'] ?? null;

            if (!in_array($planName, ['pro', 'ultra', 'custom'], true)) {
                throw new InvalidArgumentException('Nemáte aktívny platený plán.');
            }

            if ($planEndsAt !== null && strtotime((string)$planEndsAt) > time()) {
                // Active billing period: schedule downgrade only, data untouched
                $db->prepare("UPDATE users SET next_plan_name='free' WHERE id=?")
                   ->execute([$userId]);
                ob_end_clean();
                echo json_encode(['ok' => true, 'deferred' => true, 'ends_at' => $planEndsAt]);
                exit;
            }

            // No billing period: switch to free limits, data untouched
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
