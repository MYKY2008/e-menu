<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';

if (!isLoggedIn() || ($_SESSION['user_role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo 'Prístup zamietnutý.';
    exit;
}

if (!file_exists(DB_FILE)) {
    http_response_code(404);
    echo 'Databáza nenájdená.';
    exit;
}

$tmpFile  = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'gastrolink-backup-' . bin2hex(random_bytes(6)) . '.db';
$filename = 'gastrolink-backup-' . date('Y-m-d') . '.db';

try {
    // VACUUM INTO creates a consistent, defragmented copy — safe even during active writes
    getDB()->exec('VACUUM INTO ' . getDB()->quote($tmpFile));

    header('Content-Type: application/x-sqlite3');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($tmpFile));
    header('Cache-Control: no-store, no-cache');
    header('Pragma: no-cache');

    readfile($tmpFile);
} finally {
    if (file_exists($tmpFile)) {
        @unlink($tmpFile);
    }
}
