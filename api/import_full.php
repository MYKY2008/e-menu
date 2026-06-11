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

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        throw new RuntimeException('Metóda nie je povolená.');
    }

    if (!csrfValid((string)($_POST['csrf'] ?? ''))) {
        http_response_code(403);
        throw new RuntimeException('Bezpečnostná chyba (CSRF).');
    }

    $slug = sanitizeSlug((string)($_POST['slug'] ?? ''));
    if (!preg_match(SLUG_PATTERN, $slug)) {
        throw new InvalidArgumentException('Neplatný slug prevádzky.');
    }

    if (!isset($_FILES['file']) || (int)$_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        throw new InvalidArgumentException('Nebolo odovzdané žiadne CSV alebo nastala chyba pri nahrávaní.');
    }

    $userId = (int)$_SESSION['user_id'];
    $role   = (string)($_SESSION['user_role'] ?? 'user');
    $db     = getDB();

    $vSt = $db->prepare("SELECT user_id FROM venues WHERE slug = ?");
    $vSt->execute([$slug]);
    $venueRow = $vSt->fetch();

    if (!$venueRow || ((int)$venueRow['user_id'] !== $userId && $role !== 'admin')) {
        http_response_code(403);
        throw new RuntimeException('Prístup zamietnutý.');
    }

    $maxCats     = PHP_INT_MAX;
    $maxItemsCat = PHP_INT_MAX;
    if ($role !== 'admin') {
        $planSt = $db->prepare("SELECT max_categories, max_items_per_cat FROM users WHERE id = ?");
        $planSt->execute([$userId]);
        $planRow     = $planSt->fetch() ?: [];
        $maxCats     = max(1, (int)($planRow['max_categories']    ?: 3));
        $maxItemsCat = max(1, (int)($planRow['max_items_per_cat'] ?: 5));
    }

    $tmpFile = $_FILES['file']['tmp_name'];
    $handle  = fopen($tmpFile, 'r');
    if (!$handle) throw new RuntimeException('Chyba pri čítaní súboru.');

    // Strip UTF-8 BOM if present
    $bom = fread($handle, 3);
    if ($bom !== "\xEF\xBB\xBF") {
        fseek($handle, 0);
    }

    $headerRow = fgetcsv($handle);
    if (!$headerRow || count($headerRow) < 7) {
        fclose($handle);
        throw new InvalidArgumentException('Neplatná štruktúra CSV — musí mať 7 stĺpcov.');
    }

    // Load existing categories
    $existCatSt = $db->prepare("SELECT id, name FROM categories WHERE venue_slug = ?");
    $existCatSt->execute([$slug]);
    $existCats = [];
    foreach ($existCatSt->fetchAll() as $c) {
        $existCats[strtolower((string)$c['name'])] = (int)$c['id'];
    }

    // Pre-load item counts per category
    $catItemCounts = [];
    foreach ($existCats as $catId) {
        $cntSt = $db->prepare("SELECT COUNT(*) FROM items WHERE category_id = ?");
        $cntSt->execute([$catId]);
        $catItemCounts[$catId] = (int)$cntSt->fetchColumn();
    }

    $newCatCount = count($existCats);
    $imported    = 0;
    $skipped     = 0;

    $db->beginTransaction();
    try {
        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < 2) continue;
            try {
                $catName  = mb_substr(trim($row[0] ?? ''), 0, 100);
                $catIcon  = mb_substr(trim($row[1] ?? ''), 0, 50);
                $itemName = mb_substr(trim($row[2] ?? ''), 0, 100);
                $itemDesc = mb_substr(trim($row[3] ?? ''), 0, 255);

                // Price — must be a valid non-negative number
                $rawPrice = str_replace(',', '.', trim($row[4] ?? '0'));
                if ($rawPrice !== '' && !is_numeric($rawPrice)) {
                    throw new InvalidArgumentException("Neplatná cena: '{$rawPrice}'");
                }
                $itemPrice = max(0.0, (float)($rawPrice === '' ? '0' : $rawPrice));

                $itemAllergens = mb_substr(trim($row[5] ?? ''), 0, 255);
                // Is Featured — accept only '1' as true, everything else = 0
                $itemFeatured  = (trim($row[6] ?? '') === '1') ? 1 : 0;

                // Skip rows without a category name (would create orphan items)
                if ($catName === '') continue;

                $catKey = strtolower($catName);

                if (!isset($existCats[$catKey])) {
                    if ($newCatCount >= $maxCats) { $skipped++; continue; }
                    $db->prepare("INSERT INTO categories (venue_slug, name, icon, sort_order) VALUES (?, ?, ?, ?)")
                       ->execute([$slug, $catName, $catIcon, $newCatCount]);
                    $catId = (int)$db->lastInsertId();
                    $existCats[$catKey]    = $catId;
                    $catItemCounts[$catId] = 0;
                    $newCatCount++;
                } else {
                    $catId = $existCats[$catKey];
                }

                // Skip rows with no item name — category was created/found but no item to insert
                if ($itemName === '') continue;

                if (($catItemCounts[$catId] ?? 0) >= $maxItemsCat) { $skipped++; continue; }

                $db->prepare(
                    "INSERT INTO items (category_id, name, description, price, allergens, is_featured, sort_order)
                     VALUES (?, ?, ?, ?, ?, ?, ?)"
                )->execute([
                    $catId, $itemName, $itemDesc, $itemPrice, $itemAllergens,
                    $itemFeatured, (int)($catItemCounts[$catId] ?? 0),
                ]);
                $catItemCounts[$catId]++;
                $imported++;

            } catch (Throwable $rowEx) {
                // Log bad rows but continue importing the rest
                gl_log('Import row skip: ' . $rowEx->getMessage() . ' | ' . implode(' | ', array_map('trim', $row)));
                $skipped++;
            }
        }
        fclose($handle);
        $db->commit();
    } catch (Throwable $ex) {
        if ($db->inTransaction()) $db->rollBack();
        @fclose($handle);
        throw $ex;
    }

    ob_end_clean();
    echo json_encode(['ok' => true, 'imported' => $imported, 'skipped' => $skipped]);

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
