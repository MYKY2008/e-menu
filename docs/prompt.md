# PROMPT: Finálne "Production-Ready" Úpravy

**CIEĽ:** Zabezpečiť bezproblémové komerčné nasadenie aplikácie. Úloha zahŕňa bezpečnú konfiguráciu, ochranu pred cache problémami, údržbu databázy, funkciu exportu a možnosť zmeny hesla pre bežného používateľa.

---

### ⚙️ ÚLOHA 1: Environment Konfigurácia (`.env`)

**Kde:** Vytvorenie nového súboru `.env` a úprava `config.php`

**Inštrukcie:**
1.  Napíš malú funkciu v `config.php` na parsovanie `.env` súboru (nepotrebujeme veľkú knižnicu, stačí prečítať riadky a naplniť `$_ENV`).
2.  Presuň všetky citlivé a konfiguračné dáta (SMTP prihlasovacie údaje, URL domény) z `config.php` do nového vzorového súboru `.env.example`.
3.  V `config.php` používaj na tieto hodnoty `$_ENV['KLUC']` (s defaultnými fallbackmi).

---

### 🔄 ÚLOHA 2: Verzovanie Assetov (Cache Busting)

**Kde:** `config.php` (funkcia `url()`) a šablóny

**Inštrukcie:**
1.  Uprav logiku alebo vytvor novú funkciu `asset(string $path)`, ktorá k ceste na súbor pridá query parameter s časom poslednej modifikácie súboru.
    *   *Príklad:* Namiesto `style.css` vráti `style.css?v=1715342100` (hodnota z funkcie `filemtime()`).
2.  Prejdi všetky šablóny (Views) a nahraď volanie `url('assets/css/style.css')` za `asset('assets/css/style.css')`. Toto zabezpečí, že po nahratí nových štýlov sa zákazníkom okamžite prejaví zmena bez nutnosti mazať cache v prehliadači.

---

### 🧹 ÚLOHA 3: Automatické čistenie Databázy (Garbage Collector)

**Kde:** `config.php`

**Inštrukcie:**
1.  Na koniec súboru `config.php` (alebo do vhodnej funkcie, ktorá sa volá často) pridaj jednoduchý "Lottery" systém.
2.  Napríklad `if (mt_rand(1, 100) === 1)` (1% šanca pri každom načítaní).
3.  Ak podmienka prejde, spusti SQL dotaz, ktorý vymaže:
    *   Všetky záznamy z `password_resets`, kde je `expires_at < time()`.
    *   Všetky záznamy z `login_attempts` staršie ako 24 hodín.
    *   (Zabezpeč, aby táto funkcia nespomaľovala kritické dopyty, prípadne použi `@` pre potlačenie chýb, ak by sa náhodou zosypala).

---

### 📊 ÚLOHA 4: Export Menu do CSV pre Majiteľa

**Kde:** `views/dashboard.php` a `api/manage_menu.php`

**Inštrukcie:**
1.  Do Dashboardu pridaj tlačidlo **"Exportovať Menu (CSV)"**.
2.  V `manage_menu.php` (alebo v novom endpoint) vytvor akciu `export_csv`.
3.  Logika:
    *   Vytiahni všetky kategórie a jedlá pre vybranú prevádzku.
    *   Vygeneruj CSV súbor so stĺpcami: `Kategória`, `Názov jedla`, `Popis`, `Cena`, `Alergény`.
    *   Pošli správne HTTP hlavičky (`Content-Type: text/csv`, `Content-Disposition: attachment; filename="menu.csv"`) a vypíš obsah.

---

### 🛡️ ÚLOHA 5: Zmena hesla v Profile (Dashboard)

**Kde:** `views/dashboard.php` a nová akcia v API

**Inštrukcie:**
1.  V Dashboarde (napr. pod zoznamom prevádzok alebo v novej sekcii "Môj Profil") vytvor jednoduchý formulár na zmenu hesla.
2.  **Polia:** Staré heslo, Nové heslo, Zopakovať nové heslo.
3.  **Logika:**
    *   Endpoint musí najprv overiť `Staré heslo` (cez `password_verify` s aktuálnym heslom z DB pre dané `$_SESSION['user_id']`).
    *   Ak sedí a "Nové heslo" spĺňa dĺžku (8 znakov) a zhoduje sa s kontrolou, zahašuj ho a urob `UPDATE` v DB.
    *   Zobraz používateľovi peknú Toast/Flash správu o úspechu.

---
**VÝSTUP:** Dodaj kód pre `.env.example`, úpravy v `config.php`, aktualizované šablóny s `asset()` funkciou a kód pre CSV export a zmenu hesla.
