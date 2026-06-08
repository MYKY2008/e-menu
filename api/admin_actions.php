<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

ob_start();
try {
    if (!isLoggedIn() || ($_SESSION['user_role'] ?? '') !== 'admin') {
        http_response_code(403);
        throw new RuntimeException('Prístup zamietnutý.');
    }

    $raw     = (string)(file_get_contents('php://input') ?: '{}');
    $payload = json_decode($raw, true);
    if (!is_array($payload)) throw new InvalidArgumentException('Neplatný formát dát.');

    if (!csrfValid((string)($payload['csrf'] ?? ''))) {
        http_response_code(403);
        throw new RuntimeException('Bezpečnostná chyba (CSRF).');
    }

    $db     = getDB();
    $action = (string)($payload['action'] ?? '');

    switch ($action) {

        // ── Update venue_limit ────────────────────────────────
        case 'update_limit': {
            $uid   = (int)($payload['user_id']     ?? 0);
            $limit = (int)($payload['venue_limit'] ?? 1);
            if ($uid < 1) throw new InvalidArgumentException('Neplatné ID.');
            if ($limit < 0 || $limit > 9999) throw new InvalidArgumentException('Neplatný limit.');
            $db->prepare("UPDATE users SET venue_limit = ? WHERE id = ?")->execute([$limit, $uid]);
            ob_end_clean();
            echo json_encode(['ok' => true]);
            exit;
        }

        // ── Reset password ────────────────────────────────────
        case 'reset_password': {
            $uid  = (int)($payload['user_id'] ?? 0);
            $pass = (string)($payload['password'] ?? '');
            if ($uid < 1)        throw new InvalidArgumentException('Neplatné ID.');
            if (strlen($pass) < 8) throw new InvalidArgumentException('Heslo musí mať aspoň 8 znakov.');
            $hash = password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12]);
            $db->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hash, $uid]);
            ob_end_clean();
            echo json_encode(['ok' => true]);
            exit;
        }

        // ── Update username (e-mail) ──────────────────────────
        case 'update_username': {
            $uid      = (int)($payload['user_id']  ?? 0);
            $username = strtolower(trim($payload['username'] ?? ''));
            if ($uid < 1) throw new InvalidArgumentException('Neplatné ID.');
            if (!filter_var($username, FILTER_VALIDATE_EMAIL)) {
                throw new InvalidArgumentException('Neplatný e-mail.');
            }
            try {
                $db->prepare("UPDATE users SET username = ? WHERE id = ?")->execute([$username, $uid]);
            } catch (PDOException $ex) {
                if (str_contains($ex->getMessage(), 'UNIQUE')) {
                    throw new InvalidArgumentException("E-mail \"$username\" je už zaregistrovaný.");
                }
                throw $ex;
            }
            ob_end_clean();
            echo json_encode(['ok' => true]);
            exit;
        }

        // ── Delete user (CASCADE deletes venues too) ──────────
        case 'delete_user': {
            $uid = (int)($payload['user_id'] ?? 0);
            if ($uid < 1) throw new InvalidArgumentException('Neplatné ID.');
            // Prevent admin from deleting themselves
            if ($uid === (int)$_SESSION['user_id']) {
                throw new InvalidArgumentException('Nemôžete zmazať vlastný účet.');
            }
            $db->prepare("DELETE FROM users WHERE id = ?")->execute([$uid]);
            ob_end_clean();
            echo json_encode(['ok' => true]);
            exit;
        }

        // ── Delete venue ──────────────────────────────────────
        case 'delete_venue': {
            $slug = sanitizeSlug((string)($payload['slug'] ?? ''));
            if (!preg_match(SLUG_PATTERN, $slug)) throw new InvalidArgumentException('Neplatný slug.');
            $db->prepare("DELETE FROM venues WHERE slug = ?")->execute([$slug]);
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
