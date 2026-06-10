# PROMPT: Zjednotenie štýlu Scrollbarov (Visual Polish)

**CIEĽ:** Nahradiť systémové scrollbary za vlastné, minimalistické a zaoblené, ktoré ladia s dizajnom v `docs/dizajn.md`.

---

### 🛠️ ÚLOHA 1: Globálny CSS štýl pre Scrollbary
**Súbor:** `views/partials/header.php`

**Inštrukcie:**
1.  Pridaj do hlavičky blok `<style>`, ktorý bude obsahovať globálne pravidlá pre scrollbary.
2.  **Štýl:**
    - **Šírka:** Úzka (cca 5px - 6px), aby nezavadzala.
    - **Thumb (bežec):** Výrazne zaoblený (`rounded-full` štýl), farba `slate-300` v Light móde a `slate-700` v Dark móde.
    - **Track (dráha):** Transparentná (minimalizmus, aby "nešpatila" čisté biele/tmavé plochy).
    - **Hover:** Pri prejdení myšou sa farba thumbu mierne zvýrazní (napr. `slate-400` / `slate-600`).
3.  **Konzistencia:** Presuň sem aj definíciu triedy `.no-scrollbar`, aby bola dostupná globálne na všetkých stránkach.

---

### 🛠️ ÚLOHA 2: Čistenie duplicitných štýlov
**Súbory:** `views/dashboard.php`, `views/admin.php`

**Inštrukcie:**
1.  Odstráň lokálne `<style>` bloky, ktoré definujú `-webkit-tap-highlight-color` alebo `.no-scrollbar`.
2.  Tieto pravidlá budú teraz centrálne spravované v `header.php`.

---

### 🛠️ ÚLOHA 3: UX vylepšenie (Overscroll)
**Inštrukcie:**
1.  Pridaj do globálneho štýlu pravidlo pre `body`, ktoré zabráni "odrážaniu" (bounce effect) pri scrolovaní na vrchu a spodku stránky (najmä na mobiloch), aby web pôsobil ako natívna aplikácia.
    - `overscroll-behavior-y: none;`

---
**VÝSTUP:** Upravené súbory `views/partials/header.php`, `views/dashboard.php` a `views/admin.php`.
