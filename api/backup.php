<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';

if (!isLoggedIn() || ($_SESSION['user_role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo 'Prístup zamietnutý.';
    exit;
}

$file = DB_FILE;
if (!file_exists($file)) {
    http_response_code(404);
    echo 'Databáza nenájdená.';
    exit;
}

$filename = 'gastrolink-backup-' . date('Y-m-d') . '.db';

header('Content-Type: application/x-sqlite3');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($file));
header('Cache-Control: no-store, no-cache');
header('Pragma: no-cache');

readfile($file);
