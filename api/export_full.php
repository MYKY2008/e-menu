<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';

if (!isLoggedIn()) {
    http_response_code(401);
    exit('Unauthorized');
}

$slug = sanitizeSlug((string)($_GET['slug'] ?? ''));
if (!preg_match(SLUG_PATTERN, $slug)) {
    http_response_code(400);
    exit('Invalid slug');
}

if (!csrfValid((string)($_GET['csrf'] ?? ''))) {
    http_response_code(403);
    exit('CSRF invalid');
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

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel

fputcsv($out, ['CategoryName', 'CategoryIcon', 'ItemName', 'ItemDesc', 'ItemPrice', 'ItemAllergens', 'ItemFeatured']);

foreach ($categories as $cat) {
    $iSt = $db->prepare("SELECT * FROM items WHERE category_id = ? ORDER BY sort_order, id");
    $iSt->execute([(int)$cat['id']]);
    $items = $iSt->fetchAll();

    if (empty($items)) {
        fputcsv($out, [$cat['name'], $cat['icon'], '', '', '', '', '']);
    } else {
        foreach ($items as $item) {
            fputcsv($out, [
                $cat['name'],
                $cat['icon'],
                $item['name'],
                $item['description'],
                number_format((float)$item['price'], 2, '.', ''),
                $item['allergens'],
                $item['is_featured'] ? '1' : '0',
            ]);
        }
    }
}

fclose($out);
exit;
