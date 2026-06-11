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
    $payload = purify($payload);

    if (!csrfValid((string)($payload['csrf'] ?? ''))) {
        http_response_code(403);
        throw new RuntimeException('Bezpečnostná chyba (CSRF).');
    }

    $db     = getDB();
    $action = (string)($payload['action'] ?? '');

    switch ($action) {

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
            if ($uid === (int)$_SESSION['user_id']) {
                throw new InvalidArgumentException('Nemôžete zmazať vlastný účet.');
            }
            // Delete uploaded files for every venue owned by this user
            $stVenues = $db->prepare("SELECT slug FROM venues WHERE user_id = ?");
            $stVenues->execute([$uid]);
            foreach ($stVenues->fetchAll() as $v) {
                deleteVenueFiles($v['slug']);
            }
            // CASCADE removes venues, categories, items, scans
            $db->prepare("DELETE FROM users WHERE id = ?")->execute([$uid]);
            ob_end_clean();
            echo json_encode(['ok' => true]);
            exit;
        }

        // ── Create user (admin-created, auto-verified) ────────
        case 'create_user': {
            $email = strtolower(trim($payload['username'] ?? ''));
            $pass  = (string)($payload['password']    ?? '');
            $role  = in_array($payload['role'] ?? '', ['admin', 'user'], true)
                     ? $payload['role'] : 'user';
            $limit = max(0, min(9999, (int)($payload['venue_limit'] ?? 1)));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new InvalidArgumentException('Neplatný e-mail.');
            }
            if (strlen($pass) < 8) {
                throw new InvalidArgumentException('Heslo musí mať aspoň 8 znakov.');
            }
            $hash = password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12]);
            try {
                $db->prepare(
                    "INSERT INTO users (username, password, role, venue_limit, max_venues, is_verified)
                     VALUES (?, ?, ?, ?, ?, 1)"
                )->execute([$email, $hash, $role, $limit, $limit]);
            } catch (PDOException $ex) {
                if (str_contains($ex->getMessage(), 'UNIQUE')) {
                    throw new InvalidArgumentException("E-mail \"$email\" je už zaregistrovaný.");
                }
                throw $ex;
            }
            $newId = (int)$db->lastInsertId();
            ob_end_clean();
            echo json_encode(['ok' => true, 'id' => $newId, 'email' => $email, 'role' => $role, 'limit' => $limit]);
            exit;
        }

        // ── Delete venue ──────────────────────────────────────
        case 'delete_venue': {
            $slug = sanitizeSlug((string)($payload['slug'] ?? ''));
            if (!preg_match(SLUG_PATTERN, $slug)) throw new InvalidArgumentException('Neplatný slug.');
            deleteVenueFiles($slug);
            $db->prepare("DELETE FROM venues WHERE slug = ?")->execute([$slug]);
            ob_end_clean();
            echo json_encode(['ok' => true]);
            exit;
        }

        // ── Update user: plan + ends_at + limits + role ──────
        case 'update_user_admin': {
            $uid     = (int)($payload['user_id'] ?? 0);
            $plan    = in_array($payload['plan_name'] ?? '', ['free','pro','ultra','custom'], true)
                       ? $payload['plan_name'] : 'free';
            $maxV    = max(0, min(9999, (int)($payload['max_venues']        ?? 1)));
            $maxC    = max(0, min(9999, (int)($payload['max_categories']    ?? 3)));
            $maxI    = max(0, min(9999, (int)($payload['max_items_per_cat'] ?? 5)));
            $endsAt  = $payload['plan_ends_at'] ?? null;
            $role    = in_array($payload['role'] ?? '', ['user','admin'], true)
                       ? $payload['role'] : 'user';
            if ($uid < 1) throw new InvalidArgumentException('Neplatné ID.');
            if ($endsAt !== null && $endsAt !== '') {
                if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', (string)$endsAt)) {
                    throw new InvalidArgumentException('Neplatný formát dátumu.');
                }
            } else {
                $endsAt = null;
            }
            if ($uid === (int)$_SESSION['user_id'] && $role !== 'admin') {
                throw new InvalidArgumentException('Nemôžete si sám odobrať admin rolu.');
            }
            $db->prepare(
                "UPDATE users SET plan_name=?, max_venues=?, max_categories=?,
                 max_items_per_cat=?, venue_limit=?, plan_ends_at=?, role=? WHERE id=?"
            )->execute([$plan, $maxV, $maxC, $maxI, $maxV, $endsAt, $role, $uid]);
            $stU = $db->prepare(
                "SELECT id, plan_name, plan_ends_at, max_venues, max_categories, max_items_per_cat, role FROM users WHERE id = ?"
            );
            $stU->execute([$uid]);
            $upd = $stU->fetch();
            ob_end_clean();
            echo json_encode(['ok' => true, 'user' => [
                'id'               => (int)$upd['id'],
                'plan_name'        => $upd['plan_name'],
                'plan_ends_at'     => $upd['plan_ends_at'],
                'max_venues'       => (int)$upd['max_venues'],
                'max_categories'   => (int)$upd['max_categories'],
                'max_items_per_cat'=> (int)$upd['max_items_per_cat'],
                'role'             => $upd['role'],
            ]]);
            exit;
        }

        // ── Delete / truncate error log ───────────────────────
        case 'delete_log': {
            $logFile = BASE_DIR . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'error.log';
            if (is_file($logFile)) {
                file_put_contents($logFile, '');
            }
            ob_end_clean();
            echo json_encode(['ok' => true]);
            exit;
        }

        // ── Auto-cleanup inactive free users ──────────────────
        case 'cleanup_inactive': {
            $cutoff     = date('Y-m-d\TH:i:s\Z', strtotime('-90 days'));
            $stInactive = $db->prepare(
                "SELECT id FROM users
                 WHERE plan_name = 'free' AND role != 'admin'
                   AND last_login_at IS NOT NULL AND last_login_at < ?"
            );
            $stInactive->execute([$cutoff]);
            $inactive = $stInactive->fetchAll();

            $deleted = 0;
            foreach ($inactive as $u) {
                $uid = (int)$u['id'];
                if ($uid === (int)$_SESSION['user_id']) continue;
                $stVenues = $db->prepare("SELECT slug FROM venues WHERE user_id = ?");
                $stVenues->execute([$uid]);
                foreach ($stVenues->fetchAll() as $v) {
                    deleteVenueFiles($v['slug']);
                }
                $db->prepare("DELETE FROM users WHERE id = ?")->execute([$uid]);
                $deleted++;
            }
            ob_end_clean();
            echo json_encode(['ok' => true, 'deleted' => $deleted]);
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
