# PROMPT: Skrývanie kategórií a jedál (Visibility Toggle)

**CIEĽ:** Umožniť používateľom dočasne skryť celú kategóriu alebo konkrétne jedlo z ponuky bez nutnosti ich mazania.

---

### 🛠️ ÚLOHA 1: Databázová migrácia
**Súbor:** `config.php`
**Inštrukcie:**
1. Do tabuliek `categories` a `items` pridaj stĺpec `is_visible INTEGER NOT NULL DEFAULT 1`.
2. Pridaj príslušné `ALTER TABLE` príkazy do poľa `$migrations`, aby sa stĺpce pridali aj do existujúcich databáz.

---

### 🛠️ ÚLOHA 2: Backend Logika (Toggling)
**Súbor:** `api/manage_menu.php`
**Inštrukcie:**
1. Vytvor novú akciu `toggle_visibility`.
2. Akcia musí prijímať `type` ('category' alebo 'item') a `id`.
3. Over vlastníctvo daného prvku (využi `$getCategory` alebo `$getItem`).
4. Prepni hodnotu `is_visible` (0 -> 1, 1 -> 0).
5. Nezabudni zavolať `$touchVenue()` pre premazanie cache na klientskej strane.

---

### 🛠️ ÚLOHA 3: UI v Administrácii
**Súbor:** `views/dashboard.php`
**Inštrukcie:**
1. **Renderovanie:** V `renderMenuTree()` pridaj vedľa tlačidiel pre editáciu/zmazanie nové tlačidlo na prepínanie viditeľnosti (ikona oka).
   - Ak je prvok skrytý (`is_visible == 0`): Ikona prečiarknutého oka, prvok v zozname môže mať jemne zníženú opacitu (`opacity-50`).
   - Ak je prvok viditeľný: Normálna ikona oka.
2. **JavaScript:** Implementuj funkciu `toggleVisibility(type, id)`, ktorá zavolá nové API a aktualizuje `menuData`.
3. **Live Preview:** Uprav `updatePreview()`, aby sa v iPhone náhľade vôbec nezobrazovali skryté kategórie a skryté jedlá.

---

### 🛠️ ÚLOHA 4: Klientske zobrazenie
**Súbor:** `views/client_view.php`
**Inštrukcie:**
1. Uprav SQL dotazy tak, aby sa načítavali iba kategórie a jedlá, ktoré majú `is_visible = 1`.
2. Uisti sa, že ak je kategória viditeľná, ale neobsahuje žiadne viditeľné jedlá, tak sa tiež nezobrazí (toto by malo fungovať automaticky po úprave dotazov).

---
**VÝSTUP:** Upravené súbory `config.php`, `api/manage_menu.php`, `views/dashboard.php` a `views/client_view.php`.
