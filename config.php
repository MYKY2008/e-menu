<?php
declare(strict_types=1);
/**
 * GastroLink QR  –  config.php
 * Shared bootstrap: session, DB, helper functions, guards.
 * Include this at the top of every entry-point file.
 */

ini_set('display_errors',         '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);

// ── .env loader ───────────────────────────────────────────────
(static function (): void {
    $file = __DIR__ . DIRECTORY_SEPARATOR . '.env';
    if (!is_file($file)) return;
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) continue;
        [$key, $val] = explode('=', $line, 2);
        $key = trim($key);
        $val = trim($val, " \t\n\r\0\x0B\"'");
        if ($key !== '' && !array_key_exists($key, $_ENV)) {
            $_ENV[$key] = $val;
            putenv("$key=$val");
        }
    }
})();

// ── Session ───────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'cookie_lifetime' => 0,
        'use_strict_mode' => true,
        'gc_maxlifetime'  => 7200,
    ]);
}

// ── Constants ─────────────────────────────────────────────────
define('BASE_DIR',        __DIR__);
define('DB_FILE',         __DIR__ . DIRECTORY_SEPARATOR . 'gastrolink.db');
define('SLUG_PATTERN',    '/^[a-z0-9][a-z0-9_-]{1,49}$/');
define('MAX_LOGO_BYTES',  700_000);   // ~512 KB base64
define('MAX_COVER_BYTES', 1_500_000); // ~1 MB base64
define('MAX_ITEM_BYTES',  1_000_000); // ~700 KB base64
define('ALLOWED_COLORS',  ['green', 'burgundy', 'coffee', 'black', 'orange']);
define('UPLOADS_DIR',     BASE_DIR . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'venues');

// ── Security headers ──────────────────────────────────────────
if (!headers_sent()) {
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), camera=(), microphone=()');
    header(
        "Content-Security-Policy: default-src 'self'; " .
        "script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://cdn.jsdelivr.net; " .
        "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; " .
        "font-src 'self' https://fonts.gstatic.com; " .
        "img-src 'self' data: blob:; " .
        "connect-src 'self'; " .
        "object-src 'none'; " .
        "base-uri 'self'; " .
        "frame-ancestors 'self';"
    );
}

// ── Database singleton ────────────────────────────────────────
function getDB(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $pdo = new PDO('sqlite:' . DB_FILE, null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    $pdo->exec('PRAGMA journal_mode  = WAL');
    $pdo->exec('PRAGMA synchronous   = NORMAL');
    $pdo->exec('PRAGMA foreign_keys  = ON');
    $pdo->exec('PRAGMA temp_store    = MEMORY');
    $pdo->exec('PRAGMA busy_timeout  = 5000');

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id                INTEGER PRIMARY KEY AUTOINCREMENT,
            username          TEXT    UNIQUE NOT NULL,
            password          TEXT    NOT NULL,
            role              TEXT    NOT NULL DEFAULT 'user',
            venue_limit       INTEGER NOT NULL DEFAULT 1,
            plan_name         TEXT    NOT NULL DEFAULT 'free',
            max_venues        INTEGER NOT NULL DEFAULT 1,
            max_categories    INTEGER NOT NULL DEFAULT 3,
            max_items_per_cat INTEGER NOT NULL DEFAULT 5,
            is_verified       INTEGER NOT NULL DEFAULT 0,
            verify_token      TEXT    DEFAULT NULL,
            created_at        TEXT    NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%SZ','now')),
            plan_ends_at      TEXT    DEFAULT NULL,
            next_plan_name    TEXT    DEFAULT NULL,
            last_login_at     TEXT    DEFAULT NULL,
            company_name      TEXT    DEFAULT NULL,
            ico               TEXT    DEFAULT NULL,
            dic               TEXT    DEFAULT NULL,
            ic_dph            TEXT    DEFAULT NULL,
            billing_address         TEXT    DEFAULT NULL,
            stripe_customer_id      TEXT    DEFAULT NULL,
            stripe_subscription_id  TEXT    DEFAULT NULL
        )
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS venues (
            slug          TEXT    PRIMARY KEY,
            user_id       INTEGER NOT NULL
                            REFERENCES users(id) ON DELETE CASCADE,
            name          TEXT    NOT NULL,
            menu_url      TEXT,
            google_url    TEXT,
            instagram_url TEXT,
            color         TEXT    NOT NULL DEFAULT 'black',
            logo          TEXT,
            cover_image   TEXT    DEFAULT NULL,
            created_at    TEXT    NOT NULL
                            DEFAULT (strftime('%Y-%m-%dT%H:%M:%SZ','now')),
            updated_at    TEXT    NOT NULL
                            DEFAULT (strftime('%Y-%m-%dT%H:%M:%SZ','now'))
        )
    ");
    // Non-destructive migrations for older DB files
    $migrations = [
        "ALTER TABLE venues ADD COLUMN logo         TEXT    DEFAULT NULL",
        "ALTER TABLE venues ADD COLUMN cover_image  TEXT    DEFAULT NULL",
        "ALTER TABLE venues ADD COLUMN user_id      INTEGER DEFAULT 1",
        "ALTER TABLE venues ADD COLUMN updated_at   TEXT    DEFAULT (strftime('%Y-%m-%dT%H:%M:%SZ','now'))",
        "ALTER TABLE venue_settings ADD COLUMN dark_mode_default INTEGER NOT NULL DEFAULT 0",
        "ALTER TABLE items ADD COLUMN image TEXT DEFAULT NULL",
        "ALTER TABLE users ADD COLUMN is_verified  INTEGER NOT NULL DEFAULT 0",
        "ALTER TABLE users ADD COLUMN verify_token TEXT    DEFAULT NULL",
        "UPDATE users SET is_verified = 1 WHERE is_verified = 0",
        "ALTER TABLE categories ADD COLUMN is_visible INTEGER NOT NULL DEFAULT 1",
        "ALTER TABLE items      ADD COLUMN is_visible INTEGER NOT NULL DEFAULT 1",
        "ALTER TABLE users      ADD COLUMN plan TEXT NOT NULL DEFAULT 'free'",
        "ALTER TABLE users      ADD COLUMN plan_name TEXT NOT NULL DEFAULT 'free'",
        "ALTER TABLE users      ADD COLUMN max_venues INTEGER NOT NULL DEFAULT 1",
        "ALTER TABLE users      ADD COLUMN max_categories INTEGER NOT NULL DEFAULT 3",
        "ALTER TABLE users      ADD COLUMN max_items_per_cat INTEGER NOT NULL DEFAULT 5",
        "UPDATE users SET max_venues = venue_limit WHERE venue_limit > 1 AND max_venues = 1",
        "UPDATE users SET plan_name = 'pro', max_categories = 10, max_items_per_cat = 25 WHERE plan = 'paid' AND plan_name = 'free'",
        "ALTER TABLE users ADD COLUMN plan_ends_at TEXT DEFAULT NULL",
        "ALTER TABLE users ADD COLUMN next_plan_name TEXT DEFAULT NULL",
        "ALTER TABLE users ADD COLUMN last_login_at TEXT DEFAULT NULL",
        "ALTER TABLE users ADD COLUMN company_name TEXT DEFAULT NULL",
        "ALTER TABLE users ADD COLUMN ico TEXT DEFAULT NULL",
        "ALTER TABLE users ADD COLUMN dic TEXT DEFAULT NULL",
        "ALTER TABLE users ADD COLUMN ic_dph TEXT DEFAULT NULL",
        "ALTER TABLE users ADD COLUMN billing_address TEXT DEFAULT NULL",
        "ALTER TABLE users ADD COLUMN billing_street TEXT DEFAULT NULL",
        "ALTER TABLE users ADD COLUMN billing_city TEXT DEFAULT NULL",
        "ALTER TABLE users ADD COLUMN billing_zip TEXT DEFAULT NULL",
        "ALTER TABLE users ADD COLUMN billing_country TEXT DEFAULT NULL",
        "ALTER TABLE users ADD COLUMN stripe_customer_id TEXT DEFAULT NULL",
        "ALTER TABLE users ADD COLUMN stripe_subscription_id TEXT DEFAULT NULL",
    ];
    foreach ($migrations as $sql) {
        try { $pdo->exec($sql); } catch (PDOException $ignored) {}
    }

    // ── Menu tables ───────────────────────────────────────────
    $pdo->exec("CREATE TABLE IF NOT EXISTS categories (
        id         INTEGER PRIMARY KEY AUTOINCREMENT,
        venue_slug TEXT    NOT NULL REFERENCES venues(slug) ON DELETE CASCADE ON UPDATE CASCADE,
        name       TEXT    NOT NULL,
        icon       TEXT    NOT NULL DEFAULT '',
        bg_color   TEXT    DEFAULT NULL,
        sort_order INTEGER NOT NULL DEFAULT 0
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS items (
        id                 INTEGER PRIMARY KEY AUTOINCREMENT,
        category_id        INTEGER NOT NULL REFERENCES categories(id) ON DELETE CASCADE,
        name               TEXT    NOT NULL,
        description        TEXT    NOT NULL DEFAULT '',
        detail_description TEXT    NOT NULL DEFAULT '',
        price              REAL    NOT NULL DEFAULT 0,
        weight             TEXT    NOT NULL DEFAULT '',
        allergens          TEXT    NOT NULL DEFAULT '',
        bg_color           TEXT    DEFAULT NULL,
        is_featured        INTEGER NOT NULL DEFAULT 0,
        sort_order         INTEGER NOT NULL DEFAULT 0
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS venue_settings (
        venue_slug             TEXT    PRIMARY KEY REFERENCES venues(slug) ON DELETE CASCADE ON UPDATE CASCADE,
        show_allergens         INTEGER NOT NULL DEFAULT 1,
        show_featured          INTEGER NOT NULL DEFAULT 1,
        default_category_color TEXT    NOT NULL DEFAULT '#1E3A5F',
        default_item_color     TEXT    NOT NULL DEFAULT '#FFFFFF',
        dark_mode_default      INTEGER NOT NULL DEFAULT 0
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS scans (
        id         INTEGER PRIMARY KEY AUTOINCREMENT,
        venue_slug TEXT    NOT NULL REFERENCES venues(slug) ON DELETE CASCADE ON UPDATE CASCADE,
        user_agent TEXT    NOT NULL DEFAULT '',
        created_at TEXT    NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%SZ','now'))
    )");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_scans_venue_date ON scans(venue_slug, created_at)");

    $pdo->exec("CREATE TABLE IF NOT EXISTS login_attempts (
        ip_address TEXT    NOT NULL,
        timestamp  INTEGER NOT NULL
    )");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_login_attempts_ip ON login_attempts(ip_address)");

    // ── Performance indexes ───────────────────────────────────
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_venues_user_id        ON venues(user_id)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_categories_venue_slug ON categories(venue_slug)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_items_category_id     ON items(category_id)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_venue_settings_slug   ON venue_settings(venue_slug)');

    $pdo->exec("CREATE TABLE IF NOT EXISTS password_resets (
        email      TEXT    NOT NULL,
        token      TEXT    NOT NULL,
        expires_at INTEGER NOT NULL
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS orders (
        id                INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id           INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        stripe_session_id TEXT    DEFAULT NULL,
        plan_name         TEXT    NOT NULL DEFAULT 'pro',
        amount            REAL    NOT NULL DEFAULT 0,
        currency          TEXT    NOT NULL DEFAULT 'EUR',
        status            TEXT    NOT NULL DEFAULT 'pending',
        invoice_id        TEXT    DEFAULT NULL,
        created_at        TEXT    NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%SZ','now'))
    )");

    return $pdo;
}

// ── Menu colour helpers (YIQ contrast, threshold 140) ────────
function menuTextColor(string $hex): string {
    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $hex)) return '#1e293b';
    $r = hexdec(substr($hex, 1, 2));
    $g = hexdec(substr($hex, 3, 2));
    $b = hexdec(substr($hex, 5, 2));
    return (0.299 * $r + 0.587 * $g + 0.114 * $b) >= 140 ? '#1e293b' : '#ffffff';
}

function menuMutedColor(string $hex): string {
    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $hex)) return '#64748b';
    $r = hexdec(substr($hex, 1, 2));
    $g = hexdec(substr($hex, 3, 2));
    $b = hexdec(substr($hex, 5, 2));
    return (0.299 * $r + 0.587 * $g + 0.114 * $b) >= 140 ? '#64748b' : '#cbd5e1';
}

// ── Output helpers ────────────────────────────────────────────
function e(mixed $v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

// ── Input sanitization ────────────────────────────────────────
function purify(mixed $data): mixed {
    if (is_array($data)) {
        return array_map('purify', $data);
    }
    if (is_string($data)) {
        return strip_tags(trim($data));
    }
    return $data;
}

// ── URL helpers ───────────────────────────────────────────────
/**
 * Build an absolute app URL.
 * Detects whether the app is installed at root or in a subdirectory
 * by comparing BASE_DIR with the document root.
 */
function url(string $path = ''): string {
    static $base = null;
    if ($base === null) {
        $docRoot = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? ''));
        $appDir  = str_replace('\\', '/', realpath(BASE_DIR));
        if ($docRoot && str_starts_with($appDir, $docRoot)) {
            $base = rtrim(substr($appDir, strlen($docRoot)), '/');
        } else {
            $base = '';
        }
    }
    return $base . '/' . ltrim($path, '/');
}

function baseUrl(): string {
    if (!empty($_ENV['APP_URL'])) return rtrim($_ENV['APP_URL'], '/');
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
             || (($_SERVER['SERVER_PORT'] ?? 80) == 443);
    $host  = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return ($https ? 'https' : 'http') . '://' . $host . rtrim(
        str_replace('\\', '/', realpath(BASE_DIR))
        ? substr(
            str_replace('\\', '/', realpath(BASE_DIR)),
            strlen(str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? BASE_DIR)))
          )
        : '',
        '/'
    );
}

function asset(string $path): string {
    $abs = BASE_DIR . DIRECTORY_SEPARATOR . ltrim(str_replace('/', DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);
    $ver = is_file($abs) ? (string)filemtime($abs) : '0';
    return url($path) . '?v=' . $ver;
}

// ── Slug & validation ─────────────────────────────────────────
function sanitizeSlug(string $raw): string {
    $s = strtolower(trim($raw));
    $s = preg_replace('/[^a-z0-9_-]/', '', $s);
    return substr($s, 0, 50);
}

function isValidUrl(?string $url): bool {
    if ($url === null || $url === '') return true;
    $url = trim($url);
    return (bool) filter_var($url, FILTER_VALIDATE_URL)
        && (bool) preg_match('/^https?:\/\//i', $url);
}

function isValidColor(string $c): bool {
    return in_array($c, ALLOWED_COLORS, true)
        || (bool) preg_match('/^#[0-9a-fA-F]{6}$/', $c);
}

// ── Image file storage ────────────────────────────────────────
function ensureUploadsDir(): void {
    if (!is_dir(UPLOADS_DIR)) {
        mkdir(UPLOADS_DIR, 0755, true);
    }
}

function imageMimeFromBytes(string $data): ?string {
    if (strlen($data) < 12) return null;
    $b = $data;
    if (str_starts_with($b, "\xFF\xD8\xFF"))                                     return 'image/jpeg';
    if (str_starts_with($b, "\x89PNG\r\n\x1a\n"))                                return 'image/png';
    if (str_starts_with($b, 'GIF87a') || str_starts_with($b, 'GIF89a'))          return 'image/gif';
    if (str_starts_with($b, 'RIFF') && substr($b, 8, 4) === 'WEBP')              return 'image/webp';
    return null;
}

function saveImageFile(string $base64, string $type): ?string {
    if (!preg_match('/^data:image\/(jpeg|jpg|png|webp|gif);base64,/i', $base64, $m)) return null;
    $ext  = strtolower($m[1] === 'jpeg' ? 'jpg' : $m[1]);
    $data = base64_decode(preg_replace('/^data:image\/[a-zA-Z+]+;base64,/', '', $base64));
    if (!$data) return null;

    // Verify actual MIME type via magic bytes — prevents disguised PHP files
    if (!imageMimeFromBytes($data)) return null;

    ensureUploadsDir();
    $name = $type . '_' . uniqid('', true) . '.' . $ext;
    $abs  = UPLOADS_DIR . DIRECTORY_SEPARATOR . $name;
    if (file_put_contents($abs, $data) === false) return null;
    return 'uploads/venues/' . $name;
}

function deleteImageFile(?string $relPath): void {
    if (!$relPath || str_starts_with($relPath, 'data:')) return;
    $abs = BASE_DIR . DIRECTORY_SEPARATOR . ltrim(str_replace('/', DIRECTORY_SEPARATOR, $relPath), DIRECTORY_SEPARATOR);
    if (is_file($abs)) @unlink($abs);
}

function deleteVenueFiles(string $slug): void {
    $db = getDB();
    $st = $db->prepare("SELECT logo, cover_image FROM venues WHERE slug = ?");
    $st->execute([$slug]);
    $r = $st->fetch();
    if ($r) {
        deleteImageFile($r['logo']        ?? null);
        deleteImageFile($r['cover_image'] ?? null);
    }
    // Delete all item images belonging to this venue
    $imgSt = $db->prepare("
        SELECT i.image FROM items i
        JOIN categories c ON c.id = i.category_id
        WHERE c.venue_slug = ? AND i.image IS NOT NULL
    ");
    $imgSt->execute([$slug]);
    foreach ($imgSt->fetchAll() as $row) {
        deleteImageFile($row['image']);
    }
}

function imgUrl(?string $val): string {
    if (!$val) return '';
    if (str_starts_with($val, 'data:')) return $val;
    return url($val);
}

// ── Colour helpers ────────────────────────────────────────────
function lightColorHex(string $hex): string {
    $r = hexdec(substr($hex, 1, 2));
    $g = hexdec(substr($hex, 3, 2));
    $b = hexdec(substr($hex, 5, 2));
    return sprintf('rgb(%d,%d,%d)',
        (int) round($r * 0.12 + 255 * 0.88),
        (int) round($g * 0.12 + 255 * 0.88),
        (int) round($b * 0.12 + 255 * 0.88)
    );
}

function resolveColor(string $color): array {
    $pal = getPalette();
    if (isset($pal[$color])) {
        return ['hex' => $pal[$color]['hex'], 'light' => $pal[$color]['light']];
    }
    if (preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
        return ['hex' => $color, 'light' => lightColorHex($color)];
    }
    return ['hex' => $pal['black']['hex'], 'light' => $pal['black']['light']];
}

function getGastroThemes(): array {
    return [
        ['bg' => '#FFFFFF', 'name' => 'Biela porcelán'],
        ['bg' => '#FFF8E7', 'name' => 'Krémová vanilka'],
        ['bg' => '#F2ECD9', 'name' => 'Slonovina'],
        ['bg' => '#C8A882', 'name' => 'Cappuccino'],
        ['bg' => '#7C5C3E', 'name' => 'Mocha hnedá'],
        ['bg' => '#3D2015', 'name' => 'Espresso'],
        ['bg' => '#3D5229', 'name' => 'Olivová zelená'],
        ['bg' => '#1E3A5F', 'name' => 'Polnočná modrá'],
        ['bg' => '#1B4332', 'name' => 'Tmavá zelená'],
        ['bg' => '#7D1128', 'name' => 'Bordová'],
        ['bg' => '#111827', 'name' => 'Moderná čierna'],
        ['bg' => '#EA580C', 'name' => 'Teplá oranžová'],
    ];
}

function getPalette(): array {
    return [
        'green'    => ['key'=>'green',    'label'=>'Tmavá zelená',   'hex'=>'#1B4332','light'=>'#D8F3DC','swatch'=>'#1B4332'],
        'burgundy' => ['key'=>'burgundy', 'label'=>'Bordová',         'hex'=>'#6B0F1A','light'=>'#FFE5E8','swatch'=>'#7D1128'],
        'coffee'   => ['key'=>'coffee',   'label'=>'Kávová hnedá',   'hex'=>'#4A2C17','light'=>'#F4E6D3','swatch'=>'#7C4A1E'],
        'black'    => ['key'=>'black',    'label'=>'Moderná čierna', 'hex'=>'#111827','light'=>'#F8FAFC','swatch'=>'#111827'],
        'orange'   => ['key'=>'orange',   'label'=>'Teplá oranžová', 'hex'=>'#B84008','light'=>'#FFF0E6','swatch'=>'#EA580C'],
    ];
}

// ── Plan transition ───────────────────────────────────────────
function applyPlanTransitionIfNeeded(PDO $db, int $userId): void {
    $st = $db->prepare("SELECT plan_ends_at, next_plan_name FROM users WHERE id = ?");
    $st->execute([$userId]);
    $row = $st->fetch();
    if (!$row || $row['plan_ends_at'] === null) return;
    if (strtotime((string)$row['plan_ends_at']) >= time()) return;

    $next = (isset($row['next_plan_name']) && $row['next_plan_name'] !== '')
        ? $row['next_plan_name'] : 'free';

    if ($next === 'custom') {
        $db->prepare(
            "UPDATE users SET plan_name='custom', plan_ends_at=NULL, next_plan_name=NULL WHERE id=?"
        )->execute([$userId]);
    } else {
        [$maxV, $maxC, $maxI] = match($next) {
            'pro'   => [1, 10, 25],
            'ultra' => [1, 20, 50],
            default => [1, 3, 5],
        };
        $db->prepare(
            "UPDATE users SET plan_name=?, max_venues=?, max_categories=?, max_items_per_cat=?,
             venue_limit=?, plan_ends_at=NULL, next_plan_name=NULL WHERE id=?"
        )->execute([$next, $maxV, $maxC, $maxI, $maxV, $userId]);
    }
}

// ── CSRF ──────────────────────────────────────────────────────
function csrfToken(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrfValid(string $token): bool {
    return !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
}

// ── Flash messages ────────────────────────────────────────────
function flash(string $msg, string $type = 'info'): void {
    $_SESSION['flash'] = ['msg' => $msg, 'type' => $type];
}

function getFlash(): ?array {
    if (!empty($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}

// ── Auth guards ───────────────────────────────────────────────
function isLoggedIn(): bool {
    return !empty($_SESSION['user_id']);
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        flash('Pre prístup sa musíte prihlásiť.', 'error');
        header('Location: ' . url('login'));
        exit;
    }
}

function requireAdmin(): void {
    if (!isLoggedIn() || ($_SESSION['user_role'] ?? '') !== 'admin') {
        flash('Prístup zamietnutý.', 'error');
        header('Location: ' . url());
        exit;
    }
}

// ── Error logging ─────────────────────────────────────────────
function gl_log(string $message): void {
    static $dir = null;
    if ($dir === null) {
        $dir = BASE_DIR . DIRECTORY_SEPARATOR . 'storage';
        if (!is_dir($dir)) @mkdir($dir, 0750, true);
    }
    @file_put_contents(
        $dir . DIRECTORY_SEPARATOR . 'error.log',
        '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL,
        FILE_APPEND | LOCK_EX
    );
}

// ── E-mail (PHPMailer) ────────────────────────────────────────
function sendEmail(string $to, string $subject, string $htmlBody): bool {
    require_once BASE_DIR . '/libs/PHPMailer/src/Exception.php';
    require_once BASE_DIR . '/libs/PHPMailer/src/PHPMailer.php';
    require_once BASE_DIR . '/libs/PHPMailer/src/SMTP.php';

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    try {
        // ── SMTP — configure via .env or PHP constants ───────
        $mail->isSMTP();
        $mail->Host       = $_ENV['SMTP_HOST']     ?? (defined('SMTP_HOST')     ? SMTP_HOST     : 'localhost');
        $mail->Port       = (int)($_ENV['SMTP_PORT']   ?? (defined('SMTP_PORT')     ? SMTP_PORT     : 25));
        $mail->Username   = $_ENV['SMTP_USER']     ?? (defined('SMTP_USER')     ? SMTP_USER     : '');
        $mail->Password   = $_ENV['SMTP_PASS']     ?? (defined('SMTP_PASS')     ? SMTP_PASS     : '');
        $mail->SMTPSecure = $_ENV['SMTP_SECURE']   ?? (defined('SMTP_SECURE')   ? SMTP_SECURE   : '');
        $mail->SMTPAuth   = $mail->Username !== '';

        $fromEmail = $_ENV['MAIL_FROM']      ?? (defined('MAIL_FROM')      ? MAIL_FROM      : 'noreply@gastrolink.sk');
        $fromName  = $_ENV['MAIL_FROM_NAME'] ?? (defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : 'GastroLink QR');

        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->CharSet  = 'UTF-8';
        $mail->Subject  = $subject;
        $mail->Body     = $htmlBody;
        $mail->AltBody  = strip_tags(str_replace(['<br>', '<br/>'], "\n", $htmlBody));
        $mail->send();
        return true;
    } catch (\Exception $e) {
        gl_log('sendEmail error: ' . $e->getMessage());
        return false;
    }
}

// ── Real IP (proxy-aware) ────────────────────────────────────
function getRealIp(): string {
    $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
    if ($forwarded !== '') {
        return trim(explode(',', $forwarded)[0]);
    }
    return (string)($_SERVER['REMOTE_ADDR'] ?? '');
}

// ── Session IP guard (anti-hijacking) ────────────────────────
if (!empty($_SESSION['user_id']) && !empty($_SESSION['login_ip']) &&
    $_SESSION['login_ip'] !== getRealIp()) {
    session_destroy();
    header('Location: ' . url('login'));
    exit;
}

// ── Garbage collector (1 % lottery) ──────────────────────────
if (mt_rand(1, 100) === 1) {
    try {
        $gcDb = getDB();
        $gcDb->prepare("DELETE FROM password_resets WHERE expires_at < ?")->execute([time()]);
        $gcDb->prepare("DELETE FROM login_attempts  WHERE timestamp  < ?")->execute([time() - 86400]);
    } catch (\Throwable $ignored) {}
}
