# PROMPT: Finálne technické "Hardening" (Výkon a Bezpečnosť)

**CIEĽ:** Optimalizovať databázu pre rýchlosť, zvýšiť bezpečnosť sedení (sessions), zabezpečiť hĺbkové čistenie disku a zlepšiť stabilitu frontendu.

---

### 🛠️ ÚLOHA 1: Databázová Optimalizácia (Indexy)
**Súbor:** `config.php` (funkcia `getDB`)
**Inštrukcie:**
Pridaj nasledujúce SQL príkazy pre vytvorenie indexov na cudzích kľúčoch (Foreign Keys), aby sa zrýchlilo načítanie menu a admina:
1. `CREATE INDEX IF NOT EXISTS idx_venues_user_id ON venues(user_id)`
2. `CREATE INDEX IF NOT EXISTS idx_categories_venue_slug ON categories(venue_slug)`
3. `CREATE INDEX IF NOT EXISTS idx_items_category_id ON items(category_id)`
4. `CREATE INDEX IF NOT EXISTS idx_venue_settings_slug ON venue_settings(venue_slug)`

---

### 🛠️ ÚLOHA 2: Session Hardening (IP Validation)
**Súbor:** `config.php`
**Inštrukcie:**
1. V `session_start` nastaveniach pridaj `'cookie_secure' => true` (iba ak je web na HTTPS) a `'cookie_lifetime' => 0`.
2. Implementuj ochranu proti Session Hijacking:
   - Po prihlásení v `auth/login.php` ulož do `$_SESSION['login_ip'] = $_SERVER['REMOTE_ADDR']`.
   - V `config.php` (v hlavnom bloku, nie vo funkcii) pridaj kontrolu: Ak je používateľ prihlásený a jeho aktuálna IP sa nezhoduje s `$_SESSION['login_ip']`, okamžite znič session (`session_destroy()`) a odhlás ho.

---

### 🛠️ ÚLOHA 3: Hĺbkové čistenie disku (Deep Cleanup)
**Súbor:** `api/admin_actions.php` (akcia `delete_user`)
**Inštrukcie:**
Pri mazaní používateľa momentálne SQLite zmaže dáta v DB (cascade), ale súbory na disku ostanú.
1. Pred samotným `DELETE FROM users` pridaj cyklus, ktorý vyhľadá všetky prevádzky (`venues`) daného používateľa.
2. Pre každú prevádzku zavolaj:
   - Fyzické zmazanie loga a cover fotky.
   - Fyzické zmazanie všetkých fotiek jedál (items) patriacich do tejto prevádzky.
   - *Tip:* Využi existujúcu funkciu `deleteVenueFiles($slug)` a rozšír ju, aby čistila aj obrázky jedál.

---

### 🛠️ ÚLOHA 4: AJAX Resilience (Timeouty)
**Súbory:** `views/dashboard.php`, `views/admin.php`
**Inštrukcie:**
1. Vytvor pomocnú JavaScript funkciu `fetchWithTimeout(url, options, timeout = 10000)`.
2. Táto funkcia musí použiť `AbortController` na prerušenie požiadavky po 10 sekundách.
3. Nahraď kritické `fetch()` volania (uloženie menu, zmena hesla, zmena limitov) touto novou funkciou, aby aplikácia nezamrzla pri slabom pripojení.

---
**VÝSTUP:** Upravené súbory `config.php`, `auth/login.php`, `api/admin_actions.php`, `views/dashboard.php` a `views/admin.php`.
