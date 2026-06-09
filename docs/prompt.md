# MASTER PROMPT: Finálne Prémiové Vylepšenia (v1.0)

**CIEĽ:** Posunúť projekt EMENU na úroveň prémiovej služby pridaním vizuálnych fotiek jedál, zlepšením interaktivity pre zákazníka a zabezpečením dát pre administrátora.

---

### 📷 ÚLOHA 1: Obrázky k jednotlivým jedlám

**1. Databáza (`config.php`):**
*   Pridaj SQL migráciu: `ALTER TABLE items ADD COLUMN image TEXT DEFAULT NULL`.
**2. API (`api/manage_menu.php`):**
*   V akcii `save_item` spracuj prichádzajúci Base64 obrázok.
*   Použi existujúcu logiku `saveImageFile($base64, 'item')` na uloženie na disk a do DB ulož cestu.
*   Pri zmazaní jedla (`delete_item`) zabezpeč vymazanie obrázku z disku cez `deleteImageFile()`.
**3. Dashboard (`views/dashboard.php`):**
*   Pridaj do formulára jedla možnosť nahrať fotku (s kompresiou na max 600px šírku cez canvas, podobne ako pri logu).
**4. Client View (`views/client_view.php`):**
*   Zobraz malú, vkusnú fotku (náhľad) vedľa názvu jedla. Po kliknutí na fotku/jedlo sa môže otvoriť modálne okno s veľkou fotkou a detailným popisom.

---

### 🧪 ÚLOHA 2: Interaktívna Legenda Alergénov

**Kde:** `views/client_view.php`

**Inštrukcie:**
1.  Namiesto statického zoznamu čísel urob čísla alergénov pri jedle klikateľné.
2.  **UX:** Po kliknutí na číslo alergénu (napr. "7") sa zobrazí elegantné malé modálne okno alebo Tailwind "popover", ktorý vysvetlí, o aký alergén ide (napr. *"7 - Mlieko a mliečne výrobky"*).
3.  Zabezpeč, aby zákazník nemusel scrolovať na koniec stránky kvôli legende.

---

### 🔍 ÚLOHA 3: Vyhľadávanie v menu pre zákazníka

**Kde:** `views/client_view.php`

**Inštrukcie:**
1.  Pridaj do hlavičky menu (vedľa loga alebo pod názov) ikonu lupy.
2.  Po kliknutí sa vysunie/zobrazí vyhľadávacie pole.
3.  Implementuj **Live Search** (Javascript): Pri písaní sa v reálnom čase filtrujú jedlá. Ak kategória neobsahuje žiadne jedlo zodpovedajúce hľadaniu, celá kategória sa skryje.

---

### 💾 ÚLOHA 4: Zálohovanie Databázy (Admin)

**Kde:** `views/admin.php` a `api/admin_actions.php`

**Inštrukcie:**
1.  V admin paneli pridaj tlačidlo **"Stiahnuť zálohu databázy (.db)"**.
2.  Vytvor novú akciu v `admin_actions.php` (alebo samostatný malý skript), ktorý:
    *   Overí, či je používateľ admin.
    *   Nastaví HTTP hlavičky pre sťahovanie súboru (`Content-Type: application/x-sqlite3`, `Content-Disposition: attachment`).
    *   Použije `readfile()` na odoslanie súboru `gastrolink.db` používateľovi.

---

### 🎨 DIZAJNOVÁ POŽIADAVKA
Všetky nové prvky (modálne okná, vyhľadávacie pole, tlačidlá) musia **STRIKTNE** dodržiavať `docs/dizajn.md` (zaoblenie `rounded-[2rem]`, Inter font, jemné tiene).

---
**VÝSTUP:** Kompletný upravený kód pre `config.php`, `api/manage_menu.php`, `views/dashboard.php`, `views/client_view.php`, `views/admin.php` a `api/admin_actions.php`.
