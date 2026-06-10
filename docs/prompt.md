# PROMPT: Posledné kroky k ostrej produkcii (Final Polish)

**CIEĽ:** Zabezpečiť maximálnu stabilitu databázy pri záťaži a zjednotiť bezpečnostné mechanizmy.

---

### 🛠️ ÚLOHA 1: SQLite Busy Timeout
**Súbor:** `config.php` (funkcia `getDB`)
**Inštrukcie:**
1. V bloku, kde sa nastavujú `PRAGMA` príkazy pre PDO (WAL, synchronous, foreign_keys...), pridaj riadok:
   `$pdo->exec('PRAGMA busy_timeout = 5000');`
   *Tento krok je kritický pre produkciu, aby systém nepadal pri súbežnom zápise (napr. počas sťahovania zálohy).*

---

### 🛠️ ÚLOHA 2: Konzistentná Session Ochrana
**Súbor:** `auth/verify.php`
**Inštrukcie:**
1. V časti, kde sa po úspešnej aktivácii nastavujú session premenné (`user_id`, `username`, `user_role`, `venue_limit`), pridaj chýbajúci IP Guard:
   `$_SESSION['login_ip'] = (string)($_SERVER['REMOTE_ADDR'] ?? '');`
   *Zabezpečíme tým, že ochrana proti ukradnutiu session (Hijacking) bude fungovať okamžite po aktivácii účtu.*

---

### 🛠️ ÚLOHA 3: Mobile Viewport & CSS Polish
**Súbor:** `views/partials/header.php`
**Inštrukcie:**
1. Uprav meta tag `viewport`. Pridaj do neho `viewport-fit=cover`. Celý tag by mal vyzerať takto:
   `<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">`
   *Toto zabezpečí správne zobrazenie na moderných iPhonoch s výrezom (notch).*
2. Skontroluj, či sú importy fontov (Inter) a CSS štýlov v tomto súbore čisté a bez duplicít.

---

### 🛠️ ÚLOHA 4: Čistenie logov pri zálohovaní
**Súbor:** `api/backup.php`
**Inštrukcie:**
1. Pridaj do `catch` bloku v zálohovaní volanie `gl_log()`, ak by proces zlyhal.
2. Uisti sa, že dočasný súbor `.db` sa v každom prípade zmaže cez `finally`.

---
**VÝSTUP:** Upravené súbory `config.php`, `auth/verify.php`, `views/partials/header.php` a `api/backup.php`.
