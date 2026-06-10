---
name: project-overview
description: GastroLink QR — PHP/SQLite SaaS aplikácia na digitálne jedálne lístky s QR kódom
metadata:
  type: project
---

**GastroLink QR** je PHP/SQLite SaaS pre digitálne jedálne lístky.

**Stack:** PHP 8.x (strict_types), SQLite (WAL mode), Tailwind CSS (CDN), vanilla JS.
**Routing:** single-entry `index.php`, verejné menu na `/r/{slug}`.

**Kľúčové súbory:**
- `index.php` — router
- `config.php` — DB init, migrácie, helper funkcie, session hardening
- `views/dashboard.php` — hlavná admin stránka (~1900 riadkov, Tailwind + vanilla JS)
- `views/client_view.php` — verejné menu zákazníka
- `views/landing.php` — landing page
- `views/partials/header.php` / `footer.php`
- `api/manage_menu.php` — AJAX API pre kategórie, jedlá, reorder, toggle_visibility
- `api/save_venue.php` — uloženie/rename prevádzky (slug rename s FK cascade)

**DB tabuľky:** users, venues, categories, items, venue_settings, scans, login_attempts

**Bezpečnosť:** CSRF tokeny, rate limiting (3/15min/IP), session IP guard, bcrypt heslá, email enumeration prevention.

**JS v dashboarde:**
- `fetchWithTimeout()`, `menuApi()`, `renderMenuTree()`, `updatePreview()`, `toggleVisibility()`, `reorderApi()`
- SortableJS (drag&drop), QRCode.js, dark mode cez `html.dark` (localStorage `gl-dark`)

**História sessions (10 dokončených):** slug change modal, DB integrita, rate limiting, image cleanup, DB indexy, price comma support, drag&drop jedál medzi kategóriami, profilový modál, delete account, visibility toggle.

**Nové úlohy:** zapisujú sa do `docs/prompt.md`.

**Why:** Pochopiť architektúru pre budúce tasky.
**How to apply:** Všetky zmeny musí dodržiavať design system z `docs/dizajn.md` (minimalizmus, Tailwind, Inter font, rounded-[2rem] karty, indigo-600 akcentná farba).
