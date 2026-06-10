# AI Kontext — GastroLink QR

## Čo to je

**GastroLink QR** je PHP/SQLite SaaS aplikácia na digitálne jedálne lístky s QR kódom.
Majiteľ reštaurácie sa zaregistruje, vytvorí prevádzku, pridá kategórie a jedlá,
stiahne QR kód a zákazníci cez neho otvoria live menu v prehliadači.

Beží ako **PHP 8.x single-entry-point app** (`index.php` robí routing).
Databáza je **SQLite** (jeden `.db` súbor v `storage/`).
Frontend je **Tailwind CSS** (CDN build v `assets/css/style.css`) + vanilla JS.

---

## Štruktúra projektu

```
index.php               — router (PHP built-in server + Apache/Nginx)
config.php              — DB init, migrácie, helper funkcie, session hardening
views/
  dashboard.php         — hlavná admin stránka (Tailwind + vanilla JS, ~1900 riadkov)
  admin.php             — super-admin panel (správa všetkých používateľov)
  client_view.php       — verejné menu zákazníka (/r/{slug})
  landing.php           — landing page
  partials/
    header.php          — HTML <head>, globálny CSS (scrollbar, tap, overscroll), $extraHead
    footer.php          — dark mode JS, </body></html>
  login_page.php / register_page.php / forgot_password.php / reset_password.php / 404.php
auth/
  login.php / register.php / verify.php / logout.php
  forgot_password_process.php / reset_password_process.php
api/
  manage_menu.php       — AJAX API pre dashboard (kategórie, jedlá, reorder, toggle_visibility)
  save_venue.php        — uloženie/rename prevádzky (slug rename s FK cascade)
  admin_actions.php     — admin akcie (create_user, delete_user, reset_password, ...)
  change_password.php   — zmena hesla prihláseného užívateľa
  update_profile.php    — zmena e-mailu (vyžaduje current_password)
  delete_account.php    — vymazanie účtu + všetkých súborov (vyžaduje heslo + potvrdzovaciu vetu)
  export_csv.php        — export menu do CSV
  backup.php            — SQLite VACUUM INTO záloha
storage/
  *.db                  — SQLite databáza
  error.log             — chybový log (gl_log())
  .htaccess             — Deny from all
docs/
  prompt.md             — TODO pre ďalšiu AI session (sem píš nové úlohy)
  dizajn.md             — dizajnové rozhodnutia
```

---

## Technické detaily

### PHP / Backend
- **PHP 8.x**, `declare(strict_types=1)` všade
- **SQLite PDO**: WAL mode, `synchronous=NORMAL`, `busy_timeout=5000`, `foreign_keys=ON`, `temp_store=MEMORY`
- **Migrácie**: pole `$migrations` v `config.php` — `ALTER TABLE ... ADD COLUMN` príkazy, chyby sa ignorujú (idempotentné)
- **Session hardening**: `cookie_httponly`, `cookie_samesite=Lax`, `cookie_secure` (HTTPS), `use_strict_mode`, `gc_maxlifetime=7200`
- **Session IP guard**: po prihlásení sa uloží `$_SESSION['login_ip']`; každý request ho overí — ak sa nezhoduje, session sa zničí
- **CSRF**: `csrfToken()` + `csrfValid()` z `config.php`, tokeny v každom AJAX payloade
- **Rate limiting**: tabuľka `login_attempts` — max 3 pokusy / 15 min / IP (login, register, forgot password)
- **Email enumeration prevention**: register a forgot-password vracajú neutrálnu správu bez ohľadu na to, či email existuje
- **Slug rename**: `api/save_venue.php` — `PRAGMA foreign_keys = OFF` PRED `beginTransaction()` (SQLite ignoruje PRAGMA vo vnútri transakcie!), manuálny CASCADE update na `categories`, `venue_settings`, `scans`, potom `foreign_keys = ON` v `finally`
- **Backup**: `VACUUM INTO` do dočasného súboru, `finally` ho zmaže
- **Chybový log**: `gl_log(string $msg)` — píše do `storage/error.log`
- **Mazanie súborov**: `deleteVenueFiles($slug)` maže logo, cover image a všetky obrázky jedál cez JOIN

### Databázové tabuľky (hlavné)
```
users          — id, username (email), password (bcrypt), role, is_verified, verify_token, venue_limit
venues         — slug (PK), name, color, logo, cover_image, user_id, updated_at
categories     — id, venue_slug (FK), name, icon, bg_color, sort_order, is_visible
items          — id, category_id (FK), name, description, detail_description, price, image,
                 bg_color, sort_order, is_featured, allergens, is_visible
venue_settings — venue_slug (FK), show_allergens, show_featured, default_category_color,
                 default_item_color (dark_mode_default deprecated/removed)
scans          — id, venue_slug (FK), timestamp
login_attempts — id, ip_address, timestamp
```

**Indexy**: `idx_venues_user_id`, `idx_categories_venue_slug`, `idx_items_category_id`, `idx_venue_settings_slug`

### Frontend / Dashboard
- **Tailwind CSS** dark mode via `html.dark` class (localStorage `gl-dark`)
- **Anti-flash** script v `<head>` (v `header.php`)
- **QRCode.js** (CDN) — generovanie QR kódu v dashboarde
- **SortableJS 1.15.2** (CDN) — drag & drop kategórií a jedál
  - Kategórie: `#sortable-cats`, handle `.drag-cat-handle`
  - Jedlá: `.sortable-items`, handle `.drag-item-handle`, `group: 'shared-items'` (cross-category drag)
- **`fetchWithTimeout(url, options, ms=10000)`** — všetky AJAX volania, `AbortController`
- **`menuApi(payload)`** — centrálna funkcia pre `api/manage_menu.php`
- **`renderMenuTree()`** — vykresľuje strom kategórií/jedál, zachováva accordion stav
- **`updatePreview()`** — live iPhone preview, filtruje skryté kategórie a jedlá (`is_visible`)
- **`toggleVisibility(type, id)`** — prepína viditeľnosť, volá API, aktualizuje `menuData` + re-render
- **`reorderApi(type, ids, venueSlug, targetCatId, snapshot)`** — ukladá poradie; pri chybe rollback z `snapshot`
- **Modály**: `#modal-cat`, `#modal-item`, `#modal-confirm-slug`, `#modal-profile`, `#modal-delete-account`
  - `openModal(id)` / `closeModal(id)` — generické funkcie
  - Klik mimo modálu ho zatvára (forEach na všetkých ID)
- **Profilový modál** (`#modal-profile`): zmena e-mailu (vyžaduje heslo), zmena hesla, logout, sekcia zrušenia účtu
- **Delete account modál** (`#modal-delete-account`): heslo + presná potvrdzovacia veta `"ano chcem odstranit ucet"`, tlačidlo enabled až po vyplnení oboch polí

### Globálny CSS (v `header.php`)
```css
* { -webkit-tap-highlight-color: transparent }
body { overscroll-behavior-y: none }
::-webkit-scrollbar { width: 5px; height: 5px }
::-webkit-scrollbar-track { background: transparent }
::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 9999px }
html.dark ::-webkit-scrollbar-thumb { background: #334155 }
.no-scrollbar { scrollbar-width: none } /* + ::-webkit-scrollbar { display: none } */
```

---

## Čo bolo urobené (história sessions)

| # | Čo sa urobilo |
|---|---------------|
| 1 | Slug change warning modal (`#modal-confirm-slug`), FK constraint fix (PRAGMA pred transakciou) |
| 2 | DB integrita (WAL, PRAGMA), layout partials (`header.php`, `footer.php`), backup (VACUUM INTO) |
| 3 | Rate limiting (login_attempts), image cleanup (deleteVenueFiles kaskáda), session hardening (IP guard) |
| 4 | DB indexy, gl_log(), AJAX timeout (fetchWithTimeout + AbortController), email enumeration prevention |
| 5 | Price comma support (`str_replace(',','.')` v save_item), CSV export |
| 6 | **Drag & drop jedál medzi kategóriami**: SortableJS `group: 'shared-items'`, cross-category `onEnd`, `target_category_id` v API |
| 7 | **Dashboard refaktoring**: odstránenie "Rýchly odkaz" a "Zmena hesla" zo sidebaru, profilový modál (`#modal-profile`) s ikonou v navbare, `api/update_profile.php` |
| 8 | **Zabezpečenie profilu**: `current_password` povinné pri zmene e-mailu, `api/delete_account.php` (deep cleanup + session_destroy), delete account modál s potvrdením |
| 9 | **Scrollbar polish**: globálny CSS v `header.php` (5px, zaoblené, slate-300/700), `body { overscroll-behavior-y: none }`, `.no-scrollbar` globálne, odstránenie duplicít z dashboard/admin |
| 10 | **Visibility toggle**: `is_visible` stĺpce v `categories` a `items` (migrácia), `toggle_visibility` akcia v API, eye/eye-off ikony v `renderMenuTree`, `opacity-50` pre skryté, filter v `updatePreview`, filter v `client_view.php` SQL |

---

## Ako spustiť lokálne

```bash
php -S localhost:8000 index.php
```

`.env` súbor (pozri `.env.example`):
```
DB_FILE=storage/gastrolink.db
BASE_URL=http://localhost:8000
MAIL_HOST=...
MAIL_USER=...
MAIL_PASS=...
MAIL_FROM=...
MAIL_FROM_NAME=GastroLink QR
```

---

## Ako písať nové tasky

Edituj `docs/prompt.md` a napíš úlohy vo formáte ktorý sa tu používa (ÚLOHA 1, ÚLOHA 2...).
Potom v novom chate napíš: `@/docs/prompt.md vykonaj vsetko v prompt.md`

---

## Dôležité gotchas

1. **SQLite FK + rename**: `PRAGMA foreign_keys = OFF` MUSÍ byť pred `beginTransaction()`, nie vnútri
2. **`renderMenuTree` + `is_visible`**: hodnota môže byť `0` alebo `1` (integer z JSON), nie boolean — používaj `=== 0` nie `=== false`
3. **`reorderApi` rollback**: pri chybe sa obnoví `menuData.categories = snapshot` (JSON.parse deep clone) a zavolá `renderMenuTree()`
4. **Modál close**: všetky modály sú v poli `['modal-cat', 'modal-item', 'modal-confirm-slug', 'modal-profile', 'modal-delete-account']` pre klik-mimo-close
5. **`$extraHead`** v dashboard.php obsahuje iba CDN skripty (QRCode.js, SortableJS) — globálne štýly sú v `header.php`
6. **`deleteVenueFiles`** v `config.php` maže fyzické súbory; `DELETE FROM users` potom kaskádu zvyšok DB
