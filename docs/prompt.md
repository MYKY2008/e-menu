# Odstránenie horných kategórií z klientskeho zobrazenia (GastroLink QR)

Tento prompt obsahuje inštrukcie pre úpravu klientskeho zobrazenia menu. Claude AI musí vykonať všetky úlohy uvedené nižšie a dbať na to, aby sa zachoval Tailwind dizajn, dark mode a Inter font.

---

## Cieľ úpravy
V klientskom zobrazení lístka (`views/client_view.php`) momentálne zobrazujeme kategórie dvakrát na domovskej obrazovke – raz hore v podobe vodorovne scrollovateľných kapsulových tlačidiel (`#cat-nav`) a raz nižšie ako veľké dizajnové tlačidlá. Horné kapsuly sú zbytočné a zahlcujú mobilnú obrazovku.

Chceme úplne odstrániť tieto horné kapsuly (`#cat-nav`), čím ušetríme cenné vertikálne miesto. Tlačidlo pre vyhľadávanie (lupa), ktoré bolo doteraz súčasťou tejto lišty, presunieme do hlavičky (headeru) priamo vedľa prepínača tmavého režimu (vľavo od neho).

---

## Úlohy pre Claude AI

### 1. Úprava HTML v `views/client_view.php`

- **Presunúť tlačidlo vyhľadávania (lupa) do hlavičky:**
  - V súbore [client_view.php](file:///C:/Users/micha/Documents/Projects/EMENU/views/client_view.php) nájdite oba varianty headera:
    1. **Variant s fotkou na pozadí (Cover photo)** — okolo riadku 201.
    2. **Variant bez fotky (Avatar-style)** — okolo riadku 264.
  - V oboch variantoch umiestnite nové okrúhle tlačidlo pre vyhľadávanie (lupa) vedľa tlačidla na zmenu tmavého režimu.
  - Tlačidlo umiestnite vpravo hore s pozíciou `absolute top-3 right-[3.25rem]` (vľavo od tmavého prepínača, ktorý je na `right-3`).
  - **Dizajn tlačidla vyhľadávania:**
    - Vo variante **s cover fotkou** musí mať rovnaký štýl ako tmavý prepínač: okrúhle, `w-8 h-8 rounded-full bg-black/30 backdrop-blur-sm border border-white/20 shadow flex items-center justify-center text-white transition-all active:scale-90`.
    - Vo variante **bez cover fotky** musí mať tiež zodpovedajúci štýl: okrúhle, `w-8 h-8 rounded-full bg-gray-100 dark:bg-slate-800 border border-gray-200 dark:border-slate-600 shadow-sm flex items-center justify-center text-gray-500 dark:text-slate-400 transition-all active:scale-90`.
    - Tlačidlo po kliknutí vyvolá funkciu `openSearch()`.
    - Použite rovnakú SVG ikonu lupy ako predtým.

- **Odstrániť navigačnú lištu `#cat-nav`:**
  - Úplne vymažte element `<nav id="cat-nav" ...>` aj s celým jeho obsahom (približne riadky 320 až 353).

- **Odstrániť nepoužívané CSS štýly:**
  - Na začiatku HTML hlavičky vymažte CSS štýly pre triedu `.cat-pill` a `.cat-pill.active` (približne riadky 185 až 188).

### 2. Úprava JavaScriptu v `views/client_view.php`

- **Vyčistiť funkciu `showCategory()`:**
  - Odstráňte riadok skrývajúci `#cat-nav`: `document.getElementById('cat-nav')?.classList.add('hidden');`.
  - Odstráňte kód, ktorý prepína aktívnu triedu na `.cat-pill` (riadky 693 až 696).

- **Vyčistiť funkciu `showHome()`:**
  - Odstráňte riadok zobrazujúci `#cat-nav`: `document.getElementById('cat-nav')?.classList.remove('hidden');`.
  - Odstráňte kód, ktorý odoberá aktívnu triedu z `.cat-pill` (riadok 728).

- **Vyčistiť funkciu `openSearch()`:**
  - Odstráňte riadok: `document.getElementById('cat-nav')?.classList.remove('hidden');` (približne riadok 829).

---

## Overenie funkčnosti

1. Otvorte klientske zobrazenie menu a overte, že sa na domovskej obrazovke už nezobrazujú horné kapsulové kategórie.
2. Skontrolujte, že v hlavičke (s cover fotkou aj bez nej) pribudla vedľa mesiaca/slnka (prepínač tmavého režimu) ikona lupy.
3. Kliknite na lupu a overte, že vyhľadávanie funguje bez chýb a dá sa zavrieť.
4. Kliknite na ktorúkoľvek kategóriu na domovskej obrazovke a overte, že sa zobrazí zoznam jedál a tlačidlo "Späť", ktoré vás správne vráti na domovskú obrazovku.
