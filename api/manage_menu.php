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
    $role   = (string)($_SESSION['user_role'] ?? 'user');
    $action = (string)($payload['action'] ?? '');

    // ── Ownership helpers ─────────────────────────────────────
    $verifyVenue = function(string $slug) use ($db, $userId, $role): void {
        if ($role === 'admin') return;
        $st = $db->prepare("SELECT user_id FROM venues WHERE slug = ?");
        $st->execute([$slug]);
        $row = $st->fetch();
        if (!$row || (int)$row['user_id'] !== $userId) {
            throw new RuntimeException('Prístup zamietnutý.');
        }
    };

    $getCategory = function(int $catId) use ($db, $userId, $role): array {
        $st = $db->prepare("
            SELECT c.*, v.user_id AS owner_id
            FROM categories c
            JOIN venues v ON v.slug = c.venue_slug
            WHERE c.id = ?
        ");
        $st->execute([$catId]);
        $row = $st->fetch();
        if (!$row) throw new RuntimeException('Kategória nenájdená.');
        if ($role !== 'admin' && (int)$row['owner_id'] !== $userId) {
            throw new RuntimeException('Prístup zamietnutý.');
        }
        return $row;
    };

    $getItem = function(int $itemId) use ($db, $userId, $role): array {
        $st = $db->prepare("
            SELECT i.*, v.user_id AS owner_id
            FROM items i
            JOIN categories c ON c.id = i.category_id
            JOIN venues v ON v.slug = c.venue_slug
            WHERE i.id = ?
        ");
        $st->execute([$itemId]);
        $row = $st->fetch();
        if (!$row) throw new RuntimeException('Jedlo nenájdené.');
        if ($role !== 'admin' && (int)$row['owner_id'] !== $userId) {
            throw new RuntimeException('Prístup zamietnutý.');
        }
        return $row;
    };

    $validColor = function(mixed $c): ?string {
        if (!is_string($c) || $c === '') return null;
        return preg_match('/^#[0-9a-fA-F]{6}$/', $c) ? $c : null;
    };

    // Cache invalidation: bump updated_at so client ETags expire
    $touchVenue = function(string $slug) use ($db): void {
        $db->prepare("UPDATE venues SET updated_at = strftime('%Y-%m-%dT%H:%M:%SZ','now') WHERE slug = ?")
           ->execute([$slug]);
    };

    // ── Helper: load full menu for a slug ─────────────────────
    $loadMenu = function(string $slug) use ($db): array {
        $catSt = $db->prepare("SELECT * FROM categories WHERE venue_slug = ? ORDER BY sort_order, id");
        $catSt->execute([$slug]);
        $categories = [];
        foreach ($catSt->fetchAll() as $cat) {
            $itemSt = $db->prepare("SELECT * FROM items WHERE category_id = ? ORDER BY sort_order, id");
            $itemSt->execute([(int)$cat['id']]);
            $cat['items'] = $itemSt->fetchAll();
            $categories[] = $cat;
        }
        $setsSt = $db->prepare("SELECT * FROM venue_settings WHERE venue_slug = ?");
        $setsSt->execute([$slug]);
        $settings = $setsSt->fetch() ?: [
            'venue_slug'             => $slug,
            'show_allergens'         => 1,
            'show_featured'          => 1,
            'default_category_color' => '#1e3a5f',
            'default_item_color'     => '#ffffff',
        ];
        return ['categories' => $categories, 'settings' => $settings];
    };

    switch ($action) {

        // ── Get full menu ─────────────────────────────────────
        case 'get_menu': {
            $slug = sanitizeSlug((string)($payload['venue_slug'] ?? ''));
            if (!$slug) throw new InvalidArgumentException('Chýba slug prevádzky.');
            $verifyVenue($slug);
            $menu = $loadMenu($slug);
            ob_end_clean();
            echo json_encode(array_merge(['ok' => true], $menu));
            exit;
        }

        // ── Add category ──────────────────────────────────────
        case 'add_category': {
            $slug  = sanitizeSlug((string)($payload['venue_slug'] ?? ''));
            $name  = trim((string)($payload['name'] ?? ''));
            $icon  = mb_substr(trim((string)($payload['icon'] ?? '')), 0, 10);
            $color = $validColor($payload['bg_color'] ?? null);

            if (!$slug) throw new InvalidArgumentException('Chýba slug prevádzky.');
            if ($name === '') throw new InvalidArgumentException('Zadajte názov kategórie.');
            if (mb_strlen($name) > 100) throw new InvalidArgumentException('Názov kategórie je príliš dlhý (max 100 znakov).');
            $verifyVenue($slug);

            $maxSt = $db->prepare("SELECT COALESCE(MAX(sort_order), -1) + 1 FROM categories WHERE venue_slug = ?");
            $maxSt->execute([$slug]);
            $sortOrder = (int)$maxSt->fetchColumn();

            $db->prepare("INSERT INTO categories (venue_slug, name, icon, bg_color, sort_order) VALUES (?,?,?,?,?)")
               ->execute([$slug, $name, $icon, $color, $sortOrder]);
            $id = (int)$db->lastInsertId();

            $touchVenue($slug);
            $menu = $loadMenu($slug);
            ob_end_clean();
            echo json_encode(array_merge(['ok' => true, 'id' => $id], $menu));
            exit;
        }

        // ── Edit category ─────────────────────────────────────
        case 'edit_category': {
            $id    = (int)($payload['id'] ?? 0);
            $name  = trim((string)($payload['name'] ?? ''));
            $icon  = mb_substr(trim((string)($payload['icon'] ?? '')), 0, 10);
            $color = $validColor($payload['bg_color'] ?? null);

            if ($id < 1) throw new InvalidArgumentException('Neplatné ID.');
            if ($name === '') throw new InvalidArgumentException('Zadajte názov kategórie.');
            if (mb_strlen($name) > 100) throw new InvalidArgumentException('Názov kategórie je príliš dlhý (max 100 znakov).');
            $cat = $getCategory($id);

            $db->prepare("UPDATE categories SET name=?, icon=?, bg_color=? WHERE id=?")
               ->execute([$name, $icon, $color, $id]);

            $touchVenue($cat['venue_slug']);
            $menu = $loadMenu($cat['venue_slug']);
            ob_end_clean();
            echo json_encode(array_merge(['ok' => true], $menu));
            exit;
        }

        // ── Delete category ───────────────────────────────────
        case 'delete_category': {
            $id = (int)($payload['id'] ?? 0);
            if ($id < 1) throw new InvalidArgumentException('Neplatné ID.');
            $cat = $getCategory($id);
            $db->prepare("DELETE FROM categories WHERE id = ?")->execute([$id]);
            $touchVenue($cat['venue_slug']);
            $menu = $loadMenu($cat['venue_slug']);
            ob_end_clean();
            echo json_encode(array_merge(['ok' => true], $menu));
            exit;
        }

        // ── Save item (insert or update) ──────────────────────
        case 'save_item': {
            $itemId   = (int)($payload['id']          ?? 0);
            $catId    = (int)($payload['category_id'] ?? 0);
            $name     = trim((string)($payload['name']               ?? ''));
            $desc     = trim((string)($payload['description']        ?? ''));
            $detail   = trim((string)($payload['detail_description'] ?? ''));
            $weight   = trim((string)($payload['weight']             ?? ''));
            $featured = ((int)($payload['is_featured'] ?? 0)) === 1 ? 1 : 0;
            $color    = $validColor($payload['bg_color'] ?? null);

            if ($name === '') throw new InvalidArgumentException('Zadajte názov jedla.');
            if (mb_strlen($name) > 100) throw new InvalidArgumentException('Názov jedla je príliš dlhý (max 100 znakov).');
            if (mb_strlen($desc) > 255) throw new InvalidArgumentException('Krátky popis je príliš dlhý (max 255 znakov).');
            if (mb_strlen($detail) > 1000) throw new InvalidArgumentException('Detailný popis je príliš dlhý (max 1000 znakov).');

            $price = filter_var($payload['price'] ?? '', FILTER_VALIDATE_FLOAT);
            if ($price === false || $price < 0) {
                throw new InvalidArgumentException('Cena musí byť kladné číslo (napr. 4.50).');
            }

            // Sanitize allergens — only integers 1–14 separated by commas
            $rawAllergens = (string)($payload['allergens'] ?? '');
            $allergenNums = array_filter(
                array_unique(array_map('intval', array_filter(explode(',', $rawAllergens), 'strlen'))),
                fn($n) => $n >= 1 && $n <= 14
            );
            sort($allergenNums);
            $allergens = implode(',', $allergenNums);

            if ($itemId > 0) {
                $item = $getItem($itemId);
                $db->prepare("UPDATE items SET name=?, description=?, detail_description=?, price=?,
                    weight=?, allergens=?, bg_color=?, is_featured=? WHERE id=?")
                   ->execute([$name, $desc, $detail, $price, $weight, $allergens, $color, $featured, $itemId]);
                // Reload venue slug via category
                $catRow = $db->prepare("SELECT venue_slug FROM categories WHERE id = ?");
                $catRow->execute([$item['category_id']]);
                $slug = (string)$catRow->fetchColumn();
            } else {
                if ($catId < 1) throw new InvalidArgumentException('Chýba ID kategórie.');
                $cat = $getCategory($catId);
                $db->prepare("INSERT INTO items (category_id,name,description,detail_description,
                    price,weight,allergens,bg_color,is_featured) VALUES (?,?,?,?,?,?,?,?,?)")
                   ->execute([$catId, $name, $desc, $detail, $price, $weight, $allergens, $color, $featured]);
                $itemId = (int)$db->lastInsertId();
                $slug   = $cat['venue_slug'];
            }

            $touchVenue($slug);
            $menu = $loadMenu($slug);
            ob_end_clean();
            echo json_encode(array_merge(['ok' => true, 'id' => $itemId], $menu));
            exit;
        }

        // ── Delete item ───────────────────────────────────────
        case 'delete_item': {
            $id = (int)($payload['id'] ?? 0);
            if ($id < 1) throw new InvalidArgumentException('Neplatné ID.');
            $item = $getItem($id);
            $db->prepare("DELETE FROM items WHERE id = ?")->execute([$id]);
            $catRow = $db->prepare("SELECT venue_slug FROM categories WHERE id = ?");
            $catRow->execute([$item['category_id']]);
            $slug = (string)$catRow->fetchColumn();
            $touchVenue($slug);
            $menu = $loadMenu($slug);
            ob_end_clean();
            echo json_encode(array_merge(['ok' => true], $menu));
            exit;
        }

        // ── Update venue settings ─────────────────────────────
        case 'update_settings': {
            $slug = sanitizeSlug((string)($payload['venue_slug'] ?? ''));
            if (!$slug) throw new InvalidArgumentException('Chýba slug prevádzky.');
            $verifyVenue($slug);

            $showAllergens = ((int)($payload['show_allergens'] ?? 1)) ? 1 : 0;
            $showFeatured  = ((int)($payload['show_featured']  ?? 1)) ? 1 : 0;
            $darkDefault   = ((int)($payload['dark_mode_default'] ?? 0)) ? 1 : 0;

            $validThemeBgs = array_column(getGastroThemes(), 'bg');
            $catColor  = in_array($payload['default_category_color'] ?? '', $validThemeBgs, true)
                         ? $payload['default_category_color'] : '#1E3A5F';
            $itemColor = in_array($payload['default_item_color'] ?? '', $validThemeBgs, true)
                         ? $payload['default_item_color'] : '#FFFFFF';

            $db->prepare("
                INSERT INTO venue_settings
                    (venue_slug, show_allergens, show_featured, default_category_color, default_item_color, dark_mode_default)
                VALUES (?,?,?,?,?,?)
                ON CONFLICT(venue_slug) DO UPDATE SET
                    show_allergens=excluded.show_allergens,
                    show_featured=excluded.show_featured,
                    default_category_color=excluded.default_category_color,
                    default_item_color=excluded.default_item_color,
                    dark_mode_default=excluded.dark_mode_default
            ")->execute([$slug, $showAllergens, $showFeatured, $catColor, $itemColor, $darkDefault]);

            $touchVenue($slug);
            ob_end_clean();
            echo json_encode(['ok' => true]);
            exit;
        }

        // ── Reorder categories or items ───────────────────────
        case 'reorder': {
            $type = (string)($payload['type'] ?? '');
            $ids  = array_values(array_filter(
                array_map('intval', (array)($payload['ids'] ?? [])),
                fn($id) => $id > 0
            ));

            if (!in_array($type, ['categories', 'items'], true)) {
                throw new InvalidArgumentException('Neplatný typ radenia.');
            }
            if (empty($ids)) {
                ob_end_clean();
                echo json_encode(['ok' => true]);
                exit;
            }

            if ($type === 'categories') {
                $slug = sanitizeSlug((string)($payload['venue_slug'] ?? ''));
                if (!$slug) throw new InvalidArgumentException('Chýba slug prevádzky.');
                $verifyVenue($slug);

                $db->beginTransaction();
                $st = $db->prepare("UPDATE categories SET sort_order = ? WHERE id = ? AND venue_slug = ?");
                foreach ($ids as $i => $id) { $st->execute([$i, $id, $slug]); }
                $db->commit();
                $touchVenue($slug);
            } else {
                $firstItem = $getItem($ids[0]);
                $catRow    = $db->prepare("SELECT venue_slug FROM categories WHERE id = ?");
                $catRow->execute([$firstItem['category_id']]);
                $slug = (string)$catRow->fetchColumn();

                $db->beginTransaction();
                $st = $db->prepare(
                    "UPDATE items SET sort_order = ? WHERE id = ? AND category_id = ?"
                );
                foreach ($ids as $i => $id) { $st->execute([$i, $id, $firstItem['category_id']]); }
                $db->commit();
                $touchVenue($slug);
            }

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
