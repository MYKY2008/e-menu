# PROMPT: Odstránenie Tailwind CDN a Prechod na Produkčné CSS

**CIEĽ:** Optimalizovať výkon celého projektu EMENU odstránením závislosti na `cdn.tailwindcss.com` a implementáciou statického, kompilovaného CSS súboru. Toto riešenie je nevyhnutné pre produkčné nasadenie (rýchlejšie načítanie, žiadne FOUC).

---

### 🛠️ ÚLOHA: Implementácia Tailwind CLI Build Procesu

**1. Príprava Súborovej Štruktúry:**
*   Vytvor priečinok `assets/css/` v koreňovom adresári.
*   Vytvor súbor `assets/css/input.css`, ktorý bude obsahovať základné Tailwind direktívy:
    ```css
    @tailwind base;
    @tailwind components;
    @tailwind utilities;
    /* Tu môžeš pridať vlastné globálne štýly z config.php alebo šablón */
    ```

**2. Konfigurácia Tailwindu (`tailwind.config.js`):**
*   Vytvor súbor `tailwind.config.js` v koreňovom adresári.
*   Nastav parameter `content` tak, aby sledoval všetky PHP súbory v priečinkoch `views/`, `api/`, `auth/` a v koreňovom priečinku.
*   Zahrň do konfigurácie aj `darkMode: 'class'`.

**3. Úprava Šablón (Views):**
*   Prejdi všetky súbory: `views/landing.php`, `views/dashboard.php`, `views/client_view.php`, `views/login_page.php`, `views/register_page.php` a ďalšie.
*   **Odstráň** riadok `<script src="https://cdn.tailwindcss.com"></script>`.
*   **Nahraď** ho odkazom na statický súbor: `<link rel="stylesheet" href="<?= url('assets/css/style.css') ?>">`.

**4. Automatizácia pre Vývojára (Build Skripty & Integrácia):**
*   Vytvor súbor `package.json` s nasledujúcimi skriptami pre npm:
    *   `"build": "npx tailwindcss -i ./assets/css/input.css -o ./assets/css/style.css --minify"`
    *   `"watch": "npx tailwindcss -i ./assets/css/input.css -o ./assets/css/style.css --watch"`
*   **Úprava `run.bat` (Windows):**
    *   Pred spustením PHP servera pridaj príkaz na **jednorazový build** (aby CSS existovalo).
    *   Následne pridaj príkaz na spustenie **watchera na pozadí** (cez `start /b npx tailwindcss...`), aby sa CSS aktualizovalo pri každej zmene počas behu servera.
*   **Úprava `start.sh` (Linux/Mac):**
    *   Rovnaká logika: Pridaj jednorazový build a následne watcher na pozadí (použitím `&` na konci príkazu).

**5. Clean-up:**
*   Odstráň akékoľvek inline `<style>` bloky zo šablón, ktoré obsahovali Tailwind konfiguráciu (napr. `tailwind.config = ...`) a presuň ich do nového `tailwind.config.js`.

---
**VÝSTUP:** Dodaj upravený kód pre všetky dotknuté PHP súbory, obsah `tailwind.config.js`, `input.css`, `package.json` a inštrukcie, ako si má užívateľ vygenerovať prvý `style.css`.
