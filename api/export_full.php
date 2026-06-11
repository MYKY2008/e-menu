<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';

if (!isLoggedIn()) {
    http_response_code(401);
    exit('Unauthorized');
}

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
    exit('Invalid slug');
}

$userId = (int)$_SESSION['user_id'];
$role   = (string)($_SESSION['user_role'] ?? 'user');
$db     = getDB();

$vSt = $db->prepare("SELECT user_id FROM venues WHERE slug = ?");
$vSt->execute([$slug]);
$venueRow = $vSt->fetch();

if (!$venueRow || ((int)$venueRow['user_id'] !== $userId && $role !== 'admin')) {
    http_response_code(403);
    exit('Access denied');
}

$catSt = $db->prepare("SELECT * FROM categories WHERE venue_slug = ? ORDER BY sort_order, id");
$catSt->execute([$slug]);
$categories = $catSt->fetchAll();

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="menu-' . $slug . '-' . date('Y-m-d') . '.csv"');
header('Pragma: no-cache');
header('Cache-Control: no-store');

function escapeCsv(mixed $value): string {
    $s = (string)($value ?? '');
    return ($s !== '' && in_array($s[0], ['=', '+', '-', '@'], true)) ? "'" . $s : $s;
}

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel

fputcsv($out, ['CategoryName', 'CategoryIcon', 'ItemName', 'ItemDesc', 'ItemPrice', 'ItemAllergens', 'ItemFeatured']);

foreach ($categories as $cat) {
    $iSt = $db->prepare("SELECT * FROM items WHERE category_id = ? ORDER BY sort_order, id");
    $iSt->execute([(int)$cat['id']]);
    $items = $iSt->fetchAll();

    if (empty($items)) {
        fputcsv($out, [escapeCsv($cat['name']), escapeCsv($cat['icon']), '', '', '', '', '']);
    } else {
        foreach ($items as $item) {
            fputcsv($out, [
                escapeCsv($cat['name']),
                escapeCsv($cat['icon']),
                escapeCsv($item['name']),
                escapeCsv($item['description']),
                number_format((float)$item['price'], 2, '.', ''),
                escapeCsv($item['allergens']),
                $item['is_featured'] ? '1' : '0',
            ]);
        }
    }
}

fclose($out);
exit;
