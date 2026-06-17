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

    // Purify text fields; preserve raw image data — it is validated by saveImageFile()
    $rawLogo  = $payload['logo']        ?? null;
    $rawCover = $payload['cover_image'] ?? null;

    // Early size pre-check before any processing to save memory
    if (is_string($rawLogo)  && strlen($rawLogo)  > (int)(MAX_LOGO_BYTES  * 1.4)) {
        throw new InvalidArgumentException('Logo je príliš veľké (max. 512 KB).');
    }
    if (is_string($rawCover) && strlen($rawCover) > (int)(MAX_COVER_BYTES * 1.4)) {
        throw new InvalidArgumentException('Cover fotka je príliš veľká (max. 1 MB).');
    }

    $payload  = purify($payload);
    $payload['logo']        = $rawLogo;
    $payload['cover_image'] = $rawCover;

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

        deleteVenueFiles($slug);

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
    if ($name === '') {
        throw new InvalidArgumentException('Zadajte názov podniku.');
    }
    if (mb_strlen($name) > 100) {
        throw new InvalidArgumentException('Názov podniku je príliš dlhý (max 100 znakov).');
    }

    $urlFields = [
        'menu_url'      => 'Jedálny lístok',
        'google_url'    => 'Google Recenzie',
        'instagram_url' => 'Instagram',
        'facebook_url'  => 'Facebook',
        'tiktok_url'    => 'TikTok',
    ];
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
    $fb    = trim($payload['facebook_url']  ?? '') ?: null;
    $tt    = trim($payload['tiktok_url']    ?? '') ?: null;

    $isNew = ($originalSlug === '');

    // Fetch current image paths for cleanup on update
    $currentLogo  = null;
    $currentCover = null;
    if (!$isNew) {
        $stCur = $db->prepare("SELECT logo, cover_image FROM venues WHERE slug = ?");
        $stCur->execute([$originalSlug ?: $slug]);
        $cur = $stCur->fetch();
        if ($cur) {
            $currentLogo  = $cur['logo']        ?? null;
            $currentCover = $cur['cover_image'] ?? null;
        }
    }

    // ── Logo processing ───────────────────────────────────────
    $rawLogo = $payload['logo'] ?? null;
    if (!is_string($rawLogo) || $rawLogo === '') {
        deleteImageFile($currentLogo);
        $logo = null;
    } elseif (str_starts_with($rawLogo, 'data:image')) {
        if (strlen($rawLogo) > MAX_LOGO_BYTES) {
            throw new InvalidArgumentException('Logo je príliš veľké (max. 512 KB).');
        }
        $savedPath = saveImageFile($rawLogo, 'logo');
        if (!$savedPath) throw new InvalidArgumentException('Neplatný formát loga.');
        deleteImageFile($currentLogo);
        $logo = $savedPath;
    } else {
        // Existing file path — keep as is
        $logo = $rawLogo;
    }

    // ── Cover image processing ────────────────────────────────
    $rawCover = $payload['cover_image'] ?? null;
    if (!is_string($rawCover) || $rawCover === '') {
        deleteImageFile($currentCover);
        $cover = null;
    } elseif (str_starts_with($rawCover, 'data:image')) {
        if (strlen($rawCover) > MAX_COVER_BYTES) {
            throw new InvalidArgumentException('Cover fotka je príliš veľká (max. 1 MB).');
        }
        $savedPath = saveImageFile($rawCover, 'cover');
        if (!$savedPath) throw new InvalidArgumentException('Neplatný formát cover fotky.');
        deleteImageFile($currentCover);
        $cover = $savedPath;
    } else {
        // Existing file path — keep as is
        $cover = $rawCover;
    }

    // ── New-venue race guard: transaction spans limit check + INSERT ──
    if ($isNew) $db->beginTransaction();
    try {
        // ── Venue limit check for new venues (non-admin users) ────
        if ($isNew && $role !== 'admin') {
            $userRow = $db->prepare("SELECT max_venues FROM users WHERE id = ?");
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
            // Slug rename: verify ownership, then UPDATE PK + all FK references
            $ownCheck = $db->prepare("SELECT user_id FROM venues WHERE slug = ?");
            $ownCheck->execute([$originalSlug]);
            $orig = $ownCheck->fetch();
            if (!$orig || ((int)$orig['user_id'] !== $userId && $role !== 'admin')) {
                throw new RuntimeException('Prevádzka nebola nájdená.');
            }
            // PRAGMA foreign_keys cannot be changed inside a transaction — must go before beginTransaction
            $db->exec('PRAGMA foreign_keys = OFF');
            $db->beginTransaction();
            try {
                $db->prepare("UPDATE venues
                    SET slug=:slug, name=:name, menu_url=:menu, google_url=:goog,
                        instagram_url=:insta, facebook_url=:fb, tiktok_url=:tt,
                        color=:color, logo=:logo, cover_image=:cover,
                        updated_at=strftime('%Y-%m-%dT%H:%M:%SZ','now')
                    WHERE slug=:orig")
                   ->execute([
                       ':slug'=>$slug, ':name'=>$name, ':menu'=>$menu,
                       ':goog'=>$goog, ':insta'=>$insta, ':fb'=>$fb, ':tt'=>$tt,
                       ':color'=>$color, ':logo'=>$logo, ':cover'=>$cover, ':orig'=>$originalSlug,
                   ]);
                foreach (['categories', 'venue_settings', 'scans'] as $tbl) {
                    $db->prepare("UPDATE $tbl SET venue_slug = :new WHERE venue_slug = :old")
                       ->execute([':new' => $slug, ':old' => $originalSlug]);
                }
                $db->commit();
            } catch (Throwable $ex) {
                $db->rollBack();
                throw $ex;
            } finally {
                $db->exec('PRAGMA foreign_keys = ON');
            }
        } else {
            // Upsert (insert new or update same-slug)
            $ownerId = $isNew ? $userId : (int)($existing['user_id'] ?? $userId);
            $db->prepare("
                INSERT INTO venues (slug, user_id, name, menu_url, google_url, instagram_url, facebook_url, tiktok_url, color, logo, cover_image)
                VALUES (:slug, :uid, :name, :menu, :goog, :insta, :fb, :tt, :color, :logo, :cover)
                ON CONFLICT(slug) DO UPDATE SET
                    name=excluded.name, menu_url=excluded.menu_url,
                    google_url=excluded.google_url, instagram_url=excluded.instagram_url,
                    facebook_url=excluded.facebook_url, tiktok_url=excluded.tiktok_url,
                    color=excluded.color, logo=excluded.logo, cover_image=excluded.cover_image,
                    updated_at=strftime('%Y-%m-%dT%H:%M:%SZ','now')
            ")->execute([
                ':slug'=>$slug, ':uid'=>$ownerId, ':name'=>$name,
                ':menu'=>$menu, ':goog'=>$goog, ':insta'=>$insta, ':fb'=>$fb, ':tt'=>$tt,
                ':color'=>$color, ':logo'=>$logo, ':cover'=>$cover,
            ]);
            if ($isNew) $db->commit();
        }
    } catch (Throwable $ex) {
        if ($isNew && $db->inTransaction()) $db->rollBack();
        throw $ex;
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
