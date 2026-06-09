<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';

requireLogin();

$slug = sanitizeSlug((string)($_GET['slug'] ?? ''));
if (!preg_match(SLUG_PATTERN, $slug)) {
    http_response_code(400);
    echo 'Neplatný slug.';
    exit;
}

$db = getDB();

// Verify ownership (admin can export any venue)
$st = $db->prepare("SELECT slug FROM venues WHERE slug = ? AND user_id = ?");
$st->execute([$slug, (int)$_SESSION['user_id']]);
if (!$st->fetch() && ($_SESSION['user_role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo 'Prístup zamietnutý.';
    exit;
}

$catSt = $db->prepare("SELECT * FROM categories WHERE venue_slug = ? ORDER BY sort_order, id");
$catSt->execute([$slug]);
$categories = $catSt->fetchAll();

$filename = 'menu-' . $slug . '-' . date('Y-m-d') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store, no-cache');
header('Pragma: no-cache');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM — Excel compatibility

fputcsv($out, ['Kategória', 'Názov jedla', 'Popis', 'Gramáž', 'Cena (€)', 'Alergény'], ';');

foreach ($categories as $cat) {
    $iSt = $db->prepare("SELECT * FROM items WHERE category_id = ? ORDER BY sort_order, id");
    $iSt->execute([(int)$cat['id']]);
    foreach ($iSt->fetchAll() as $item) {
        fputcsv($out, [
            $cat['name'],
            $item['name'],
            $item['description'],
            $item['weight'],
            number_format((float)$item['price'], 2, '.', ''),
            $item['allergens'],
        ], ';');
    }
}

fclose($out);
exit;
