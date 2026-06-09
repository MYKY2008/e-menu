# PROMPT: Finálne doladenie zobrazenia popisov (Bottom Sheet)

**CIEĽ:** Upraviť modálne okno detailu jedla (Bottom Sheet) v klientskom menu tak, aby vedelo naraz zobraziť krátky aj detailný popis.

---

### 🛠️ ÚLOHA: Úprava HTML a JS v Client View

**Kde:** `views/client_view.php`

**Inštrukcie:**
1.  **Úprava HTML štruktúry Bottom Sheetu:**
    *   V sekcii `<article id="sheet"...>` pridaj pod nadpis jedla (alebo pod cenu) nový element pre **krátky popis** (napr. `<p id="sheet-short-desc" class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"></p>`).
    *   Pôvodný element `<p id="sheet-desc">` ponechaj pre **detailný popis** (napr. s menším fontom alebo kurzívou, aby bol vizuálne odlíšený).
2.  **Úprava JavaScript funkcie `openSheet(item)`:**
    *   Zmeň doterajšiu logiku, ktorá vyberala *buď* `detail_description` *alebo* `description`.
    *   **Nová logika:**
        *   Ak existuje `item.description`, vlož ho do `sheet-short-desc` a zobraz tento element (inak ho skry).
        *   Ak existuje `item.detail_description`, vlož ho do `sheet-desc` a zobraz tento element (inak ho skry).
3.  **Dizajn:** Obe textové polia musia ladiť s celkovým dizajnom (správne medzery, farby textu pre Dark Mode).

---
**VÝSTUP:** Dodaj upravený HTML kód sekcie `#sheet` a upravenú JS funkciu `openSheet(item)` zo súboru `views/client_view.php`.
