# ÚLOHA: Security Hardening (Bezpečnostné posilnenie)

Cieľom je implementovať pokročilé bezpečnostné mechanizmy a odstrániť potenciálne zraniteľnosti v celom projekte.

## 1. Globálne bezpečnostné hlavičky (config.php)
- Na začiatok `config.php` pridaj odosielanie bezpečnostných hlavičiek cez `header()`:
    - `X-Frame-Options: SAMEORIGIN`
    - `X-Content-Type-Options: nosniff`
    - `Referrer-Policy: strict-origin-when-cross-origin`
    - `Permissions-Policy: geolocation=(), camera=(), microphone=()`
- **Implementuj Content Security Policy (CSP):**
    - Povoľ skripty len z 'self' a dôveryhodných CDN (cdnjs, cdn.jsdelivr.net).
    - Povoľ štýly z 'self', 'unsafe-inline' (kvôli dynamickým farbám) a Google Fonts.
    - Zakáž `object-src 'none'`.

## 2. Ochrana súborov a priečinkov
- Do každého súboru, ktorý sa len inkluduje (napr. v `libs/`, `views/partials/`, `config.php`), pridaj na začiatok kontrolu:
  `defined('BASE_DIR') or die('Access denied');`
- Vytvor súbor `uploads/.htaccess`, ktorý zakáže spúšťanie PHP skriptov:
  `php_flag engine off` (ak to server podporuje) a `AddHandler cgi-script .php .phtml .php3`.

## 3. Refaktoring klientskeho menu (views/client_view.php)
- Odstráň inline JSON objekty z atribútov `onclick` pri jedlách.
- Namiesto toho ulož dáta o jedle do `data-item='...'` atribútu (zakódované cez `e(json_encode(...))`).
- Uprav JavaScript tak, aby pri kliknutí načítal dáta z `dataset.item`.
- Týmto sa eliminujú problémy s úvodzovkami a potenciálny XSS.

## 4. Validácia vstupov a Sanizácia
- V `api/manage_menu.php` a `api/save_venue.php` prever, či sú všetky textové vstupy prehnané cez `purify()`.
- Zabezpeč, aby sa pri nahrávaní súborov v `saveImageFile` (v `config.php`) vždy vygeneroval nový názov súboru (už sa deje, ale over to).

## 5. Logout Security (auth/logout.php)
- Zabezpeč, aby odhlásenie prebehlo úplne: `$_SESSION = [];`, `session_destroy();` a vymazanie session cookie.
