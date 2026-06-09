# PROMPT: UX a Stabilita (Formuláre, 404 a Limity Textov)

**CIEĽ:** Zlepšiť používateľskú skúsenosť pri chybách, zabezpečiť správne fungovanie odkazov v podpriečinkoch a ochrániť databázu pred nadmerne dlhými textami.

---

### 🛠️ ÚLOHA 1: Perzistencia dát vo formulároch (UX)

**Problém:** Pri chybe (napr. zlé heslo) sa používateľ vráti na prázdny formulár a musí znova vypisovať e-mail.

**Inštrukcie:**
1.  **Auth spracovanie (`auth/login.php`, `auth/register.php`):**
    *   Pred presmerovaním späť na formulár ulož prijaté dáta (okrem hesla!) do session, napr. `$_SESSION['old_input'] = $_POST;`.
2.  **Zobrazenie formulára (`views/login_page.php`, `views/register_page.php`):**
    *   Pre políčko e-mailu nastav `value` z tejto session (ak existuje).
    *   **Dôležité:** Po úspešnom vykreslení/použití túto session vymaž, aby tam nezostala pri ďalšom čistom otvorení stránky.

---

### 🛠️ ÚLOHA 2: Zjednotenie 404 a Oprava URL adries

**Problém:** Viaceré 404 stránky a statické odkazy (`href="/"`) nefungujú, ak je aplikácia v podpriečinku.

**Inštrukcie:**
1.  **Redizajn 404 (`views/404.php`):**
    *   Prepracuj tento súbor podľa štandardov v `docs/dizajn.md`. Musí ladiť so zvyškom webu (minimalizmus, Inter font, centrovaný obsah).
2.  **Router (`index.php`):**
    *   V `default` prípade (nenájdená trasa) odstráň inline HTML a namiesto neho načítaj `require BASE_DIR . '/views/404.php';`.
3.  **Audit Odkazov:**
    *   Prejdi všetky súbory (`landing.php`, `404.php`, `client_view.php`, atď.).
    *   Nahraď všetky statické odkazy typu `<a href="/">` za dynamické `<a href="<?= url() ?>">`. Používaj poctivo funkciu `url()` pre všetky interné prepojenia.

---

### 🛠️ ÚLOHA 3: Striktná validácia dĺžky textov

**Problém:** Chýbajú limity na dĺžku popisov, čo môže rozbiť databázu alebo UI.

**Inštrukcie:**
1.  **API (`api/manage_menu.php` a `api/save_venue.php`):**
    *   Zaveď striktné kontroly dĺžky reťazcov (`mb_strlen`) pri ukladaní a úprave.
    *   **Limity:**
        *   Názov prevádzky / kategórie / jedla: **max 100 znakov**.
        *   Krátky popis jedla: **max 255 znakov**.
        *   Dlhý/Detailný popis jedla: **max 1000 znakov**.
    *   Ak používateľ prekročí limit, API musí vrátiť zrozumiteľnú chybovú správu (napr. *"Názov je príliš dlhý (max 100 znakov)"*).

---
**VÝSTUP:** Dodaj upravený kód pre dotknuté súbory (`index.php`, `auth/*.php`, `views/*.php`, `api/*.php`). Sústreď sa na čistotu kódu a perfektné UX.
