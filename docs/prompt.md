# PROMPT: Drag & Drop jedál medzi kategóriami

**CIEĽ:** Umožniť používateľovi v administrácii presúvať jedlá z jednej kategórie do druhej pomocou myši (drag & drop).

---

### 🛠️ ÚLOHA 1: Prepojenie SortableJS skupín
**Súbor:** `views/dashboard.php` (funkcia `initSortable`)

**Inštrukcie:**
1. Pri inicializácii `.sortable-items` pridaj vlastnosť `group: 'shared-items'`.
2. To isté meno skupiny zabezpečí, že knižnica dovolí presun položky medzi rôznymi kontajnermi `.sortable-items`.
3. Uprav `onEnd` udalosť pre položky:
   - Zisti ID kategórie, do ktorej bolo jedlo pustené (z `evt.to.dataset.catId`).
   - Odošli toto `catId` v AJAX požiadavke na backend spolu so zoznamom ID jedál v novom poradí.
   - Po úspešnom uložení aktualizuj lokálny stav `menuData.categories`, aby sa zmena prejavila aj v živom náhľade.

---

### 🛠️ ÚLOHA 2: Rozšírenie API pre zmenu kategórie
**Súbor:** `api/manage_menu.php` (akcia `reorder`)

**Inštrukcie:**
1. Uprav vetvu `if ($type === 'items')`.
2. API musí prijímať voliteľný parameter `target_category_id`.
3. Ak je `target_category_id` zadané:
   - Over, či táto kategória patrí aktuálnemu používateľovi (rovnaká logika ako v `getCategory`).
   - V SQL dotaze vnútri cyklu neaktualizuj len `sort_order`, ale aj `category_id = :cat_id`.
   - `UPDATE items SET sort_order = :so, category_id = :cat_id WHERE id = :id`
4. Zabezpeč, aby sa po presune správne zavolala funkcia `touchVenue($slug)`, aby sa klientom vymazala cache.

---

### 🛠️ ÚLOHA 3: UX a Vizuálna spätná väzba
**Inštrukcie:**
1. Počas ťahania pridaj položke jemnú polopriehľadnosť (v SortableJS cez `ghostClass: 'opacity-40'`).
2. Ak presun zlyhá (napr. chyba siete), vráť položku na pôvodné miesto alebo refreshni zoznam z `menuData`.

---
**VÝSTUP:** Upravené súbory `views/dashboard.php` (sekcia JavaScript) a `api/manage_menu.php`.
