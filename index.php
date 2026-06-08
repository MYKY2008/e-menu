<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

// ── PHP built-in server: serve real files directly ────────────
if (PHP_SAPI === 'cli-server') {
    $file = __DIR__ . parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    if (is_file($file)) {
        if (pathinfo($file, PATHINFO_EXTENSION) !== 'php') {
            return false; // serve CSS, JS, images, etc. as-is
        }
        // Execute api/*.php files directly (they bootstrap themselves)
        $realFile = str_replace('\\', '/', (string)realpath($file));
        $realApi  = str_replace('\\', '/', (string)realpath(BASE_DIR . '/api'));
        if ($realApi && str_starts_with($realFile, $realApi . '/')) {
            require $file;
            exit;
        }
    }
}

// ── Parse path (strip base if app is in a subdirectory) ───────
$reqUri  = $_SERVER['REQUEST_URI'] ?? '/';
$reqPath = parse_url($reqUri, PHP_URL_PATH) ?? '/';

$docRoot = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? BASE_DIR));
$appDir  = str_replace('\\', '/', realpath(BASE_DIR));
$appBase = ($docRoot && str_starts_with($appDir, $docRoot))
    ? rtrim(substr($appDir, strlen($docRoot)), '/')
    : '';

// Strip the app base prefix to get the local path
if ($appBase !== '' && str_starts_with($reqPath, $appBase)) {
    $path = substr($reqPath, strlen($appBase));
} else {
    $path = $reqPath;
}
$path = '/' . ltrim($path, '/');

// Remove trailing slash (except root)
if ($path !== '/' && str_ends_with($path, '/')) {
    $path = rtrim($path, '/');
}

// ── Handle POST for auth routes ───────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    match ($path) {
        '/login'    => (static function () { require BASE_DIR . '/auth/login.php'; })(),
        '/register' => (static function () { require BASE_DIR . '/auth/register.php'; })(),
        default     => null,
    };
    // If a POST route was matched, auth files redirect and exit.
    // Fall through to GET rendering otherwise (e.g. validation failures re-render form).
}

// ── GET routing ───────────────────────────────────────────────
switch (true) {

    case $path === '/':
        require BASE_DIR . '/views/landing.php';
        break;

    case $path === '/login':
        if (isLoggedIn()) {
            header('Location: ' . url('dashboard'));
            exit;
        }
        require BASE_DIR . '/views/login_page.php';
        break;

    case $path === '/register':
        if (isLoggedIn()) {
            header('Location: ' . url('dashboard'));
            exit;
        }
        require BASE_DIR . '/views/register_page.php';
        break;

    case $path === '/logout':
        require BASE_DIR . '/auth/logout.php';
        break;

    case $path === '/dashboard':
        requireLogin();
        require BASE_DIR . '/views/dashboard.php';
        break;

    case $path === '/admin':
        requireAdmin();
        require BASE_DIR . '/views/admin.php';
        break;

    // Clean URL  /r/{slug}
    case (bool) preg_match('~^/r/([a-z0-9_-]+)$~i', $path, $m):
        $routeSlug = sanitizeSlug($m[1]);
        require BASE_DIR . '/views/client_view.php';
        break;

    // Query-string fallback  ?r={slug}  (Apache mod_rewrite target)
    case !empty($_GET['r']):
        $routeSlug = sanitizeSlug((string)$_GET['r']);
        require BASE_DIR . '/views/client_view.php';
        break;

    default:
        http_response_code(404);
        echo '<!DOCTYPE html><html lang="sk"><head><meta charset="UTF-8">'
           . '<title>404</title><script src="https://cdn.tailwindcss.com"></script></head>'
           . '<body class="min-h-screen flex items-center justify-center bg-slate-50">'
           . '<div class="text-center"><p class="text-6xl mb-4">&#128269;</p>'
           . '<h1 class="text-2xl font-bold text-slate-800">Stránka nenájdená</h1>'
           . '<a href="' . e(url()) . '" class="mt-4 inline-block text-blue-600 hover:underline">'
           . '← Späť na úvod</a></div></body></html>';
        break;
}
