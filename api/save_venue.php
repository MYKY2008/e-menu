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

    if (!csrfValid((string)($payload['csrf'] ?? ''))) {
        http_response_code(403);
        throw new RuntimeException('Bezpečnostná chyba (CSRF).');
    }

    $userId = (int)$_SESSION['user_id'];
    $role   = (string)($_SESSION['user_role'] ?? 'user');
    $db     = getDB();

    $action = $payload['action'] ?? 'save';

    // ── DELETE venue ──────────────────────────────────────────
    if ($action === 'delete') {
        $slug = sanitizeSlug((string)($payload['slug'] ?? ''));
        if (!preg_match(SLUG_PATTERN, $slug)) throw new InvalidArgumentException('Neplatný slug.');

        // Admin may delete any venue; user only their own
        if ($role === 'admin') {
            $db->prepare("DELETE FROM venues WHERE slug = ?")->execute([$slug]);
        } else {
            $db->prepare("DELETE FROM venues WHERE slug = ? AND user_id = ?")
               ->execute([$slug, $userId]);
        }
        ob_end_clean();
        echo json_encode(['ok' => true]);
        exit;
    }

    // ── SAVE (insert / update) venue ──────────────────────────
    $slug         = sanitizeSlug((string)($payload['slug']          ?? ''));
    $originalSlug = sanitizeSlug((string)($payload['original_slug'] ?? ''));
    $name         = trim($payload['name'] ?? '');
    $color        = trim($payload['color'] ?? 'black');

    if (!preg_match(SLUG_PATTERN, $slug)) {
        throw new InvalidArgumentException('Neplatný slug. Povolené: a–z, 0–9, pomlčka (min. 2 znaky).');
    }
    if ($name === '' || strlen($name) > 800) {
        throw new InvalidArgumentException('Názov podniku musí mať 1–200 znakov.');
    }

    $urlFields = ['menu_url' => 'Jedálny lístok', 'google_url' => 'Google Recenzie', 'instagram_url' => 'Instagram'];
    foreach ($urlFields as $field => $label) {
        $u = trim($payload[$field] ?? '');
        if ($u !== '' && !isValidUrl($u)) {
            throw new InvalidArgumentException("Pole \"$label\" obsahuje neplatnú URL adresu.");
        }
    }

    if (!isValidColor($color)) $color = 'black';

    $menu  = trim($payload['menu_url']      ?? '') ?: null;
    $goog  = trim($payload['google_url']    ?? '') ?: null;
    $insta = trim($payload['instagram_url'] ?? '') ?: null;

    // Logo validation
    $rawLogo = $payload['logo'] ?? null;
    if (!is_string($rawLogo) || $rawLogo === '') {
        $logo = null;
    } elseif (!preg_match('/^data:image\/(jpeg|png|webp|gif);base64,/i', $rawLogo)) {
        $logo = null;
    } elseif (strlen($rawLogo) > MAX_LOGO_BYTES) {
        throw new InvalidArgumentException('Logo je príliš veľké (max. 512 KB).');
    } else {
        $logo = $rawLogo;
    }

    $isNew = ($originalSlug === '');

    // ── Venue limit check for new venues (non-admin users) ────
    if ($isNew && $role !== 'admin') {
        $userRow = $db->prepare("SELECT venue_limit FROM users WHERE id = ?");
        $userRow->execute([$userId]);
        $limit = (int)($userRow->fetchColumn() ?? 1);

        $cnt = $db->prepare("SELECT COUNT(*) FROM venues WHERE user_id = ?");
        $cnt->execute([$userId]);
        $count = (int)$cnt->fetchColumn();

        if ($count >= $limit) {
            throw new InvalidArgumentException(
                "Dosiahli ste váš limit {$limit} " . ($limit === 1 ? 'prevádzky' : 'prevádzok')
                . ". Kontaktujte administrátora pre navýšenie."
            );
        }
    }

    // ── Slug ownership check ───────────────────────────────────
    $checkSt = $db->prepare("SELECT user_id FROM venues WHERE slug = ?");
    $checkSt->execute([$slug]);
    $existing = $checkSt->fetch();

    if ($existing) {
        $ownerOk = ((int)$existing['user_id'] === $userId) || $role === 'admin';
        if (!$ownerOk) {
            throw new InvalidArgumentException("Slug \"$slug\" je už obsadený iným podnikom.");
        }
    }

    // ── Perform DB write ──────────────────────────────────────
    if (!$isNew && $originalSlug !== $slug) {
        // Slug rename: verify ownership, then UPDATE PK
        $ownCheck = $db->prepare("SELECT user_id FROM venues WHERE slug = ?");
        $ownCheck->execute([$originalSlug]);
        $orig = $ownCheck->fetch();
        if (!$orig || ((int)$orig['user_id'] !== $userId && $role !== 'admin')) {
            throw new RuntimeException('Prevádzka nebola nájdená.');
        }
        $db->prepare("UPDATE venues
            SET slug=:slug, name=:name, menu_url=:menu, google_url=:goog,
                instagram_url=:insta, color=:color, logo=:logo,
                updated_at=strftime('%Y-%m-%dT%H:%M:%SZ','now')
            WHERE slug=:orig")
           ->execute([
               ':slug'=>$slug, ':name'=>$name, ':menu'=>$menu,
               ':goog'=>$goog, ':insta'=>$insta, ':color'=>$color,
               ':logo'=>$logo, ':orig'=>$originalSlug,
           ]);
    } else {
        // Upsert (insert new or update same-slug)
        $ownerId = $isNew ? $userId : (int)($existing['user_id'] ?? $userId);
        $db->prepare("
            INSERT INTO venues (slug, user_id, name, menu_url, google_url, instagram_url, color, logo)
            VALUES (:slug, :uid, :name, :menu, :goog, :insta, :color, :logo)
            ON CONFLICT(slug) DO UPDATE SET
                name=excluded.name, menu_url=excluded.menu_url,
                google_url=excluded.google_url, instagram_url=excluded.instagram_url,
                color=excluded.color, logo=excluded.logo,
                updated_at=strftime('%Y-%m-%dT%H:%M:%SZ','now')
        ")->execute([
            ':slug'=>$slug, ':uid'=>$ownerId, ':name'=>$name,
            ':menu'=>$menu, ':goog'=>$goog, ':insta'=>$insta,
            ':color'=>$color, ':logo'=>$logo,
        ]);
    }

    ob_end_clean();
    echo json_encode(['ok' => true, 'slug' => $slug]);

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
