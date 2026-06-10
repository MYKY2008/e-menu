# PROMPT: Finálne doladenie (Bezpečnosť, Údržba a Mobile UX)

**CIEĽ:** Zabezpečiť systém proti spamu, optimalizovať úložisko a dotiahnuť vizuálne detaily pre mobilné prehliadače.

---

### 🛠️ ÚLOHA 1: Rate Limiting (Ochrana proti spamu)
**Problém:** Registrácia a reset hesla sú momentálne bez ochrany.
**Inštrukcie:**
1. Uprav `auth/register.php` a `auth/forgot_password_process.php`.
2. Implementuj kontrolu pokusov z danej IP adresy pomocou existujúcej tabuľky `login_attempts` (podobne ako v `auth/login.php`).
3. **Limit:** Max 3 registrácie / 3 resety hesla za 15 minút z jednej IP.

---

### 🛠️ ÚLOHA 2: Image Cleanup (Úspora miesta)
**Problém:** Pri zmene alebo zmazaní jedla/loga ostávajú staré súbory na disku.
**Inštrukcie:**
1. V `api/manage_menu.php` zabezpeč, aby sa pri akcii `delete_item` fyzicky zmazal obrázok z priečinka `uploads/venues/` (použi funkciu `deleteImageFile` z `config.php`).
2. Pri akcii `save_item`, ak sa nahráva nová fotka a položka už mala starú fotku, starý súbor musí byť zmazaný.
3. Prever rovnakú logiku v `api/save_venue.php` pre logá a cover fotky.

---

### 🛠️ ÚLOHA 3: Mobile UX & SEO Polish
**Inštrukcie:**
1. **Theme Color:** Uprav `views/partials/header.php`, aby prijímal voliteľnú premennú `$themeColor`. Ak existuje, pridaj `<meta name="theme-color" content="...">`.
2. V `views/client_view.php` nastav túto premennú na primárnu farbu prevádzky. Týmto sa lišta mobilného prehliadača (Chrome/Safari) zafarbí podľa farby podniku.
3. **App Title:** Zabezpeč, aby v `header.php` bol názov stránky vždy v tvare `Názov | GastroLink QR`.

---

### 🛠️ ÚLOHA 4: Error Logging
**Inštrukcie:**
1. V `config.php` vytvor jednoduchú funkciu `gl_log($message)`.
2. Funkcia zapíše chybu s časovou pečiatkou do súboru (napr. `storage/error.log`). Zabezpeč, aby priečinok `storage` existoval a súbor nebol prístupný z webu (cez `.htaccess` alebo umiestnením).
3. Nahraď v kritických `catch` blokoch v API (najmä v `PHPMailer`) priame `error_log` touto novou funkciou.

---
**VÝSTUP:** Dodaj upravené súbory: `config.php`, `auth/register.php`, `auth/forgot_password_process.php`, `api/manage_menu.php`, `api/save_venue.php` a `views/partials/header.php`.
