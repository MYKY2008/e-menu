<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../libs/superfaktura.php';

if (!isLoggedIn()) {
    http_response_code(401);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Pre stiahnutie faktúry musíte byť prihlásený.';
    exit;
}

$orderId = (int)($_GET['order_id'] ?? 0);
if ($orderId <= 0) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Chýbajúci alebo neplatný order_id.';
    exit;
}

$db     = getDB();
$userId = (int)$_SESSION['user_id'];

$st = $db->prepare(
    "SELECT id, invoice_id, status, plan_name FROM orders WHERE id = ? AND user_id = ?"
);
$st->execute([$orderId, $userId]);
$order = $st->fetch();

if (!$order) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Objednávka nenájdená.';
    exit;
}

$invoiceId = trim((string)($order['invoice_id'] ?? ''));
if ($invoiceId === '') {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Faktúra sa ešte spracováva. Skúste neskôr.';
    exit;
}

$pdf = sfGetInvoicePdf($invoiceId);
if ($pdf === null) {
    http_response_code(502);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Nepodarilo sa načítať PDF. Skúste neskôr alebo kontaktujte podporu.';
    exit;
}

$filename = 'faktura-gl-' . $invoiceId . '.pdf';
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($pdf));
header('Cache-Control: private, no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
echo $pdf;
