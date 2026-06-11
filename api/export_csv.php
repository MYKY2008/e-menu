<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

if (!csrfValid((string)($_POST['csrf'] ?? ''))) {
    http_response_code(403);
    exit('CSRF invalid');
}

$slug = sanitizeSlug((string)($_POST['slug'] ?? ''));
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

function escapeCsv(mixed $value): string {
    $s = (string)($value ?? '');
    return ($s !== '' && in_array($s[0], ['=', '+', '-', '@'], true)) ? "'" . $s : $s;
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
            escapeCsv($cat['name']),
            escapeCsv($item['name']),
            escapeCsv($item['description']),
            escapeCsv($item['weight']),
            number_format((float)$item['price'], 2, '.', ''),
            escapeCsv($item['allergens']),
        ], ';');
    }
}

fclose($out);
exit;
