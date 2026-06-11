# TASKS FOR CLAUDE AI — DASHBOARD RESTRUCTURE (ANALYTICS TAB)

Cieľom je presunúť analytiku z bočného panelu do novej, samostatnej záložky (Tab) v hlavnej časti dashboardu.

## ÚLOHA 1: Úprava Tab Baru
**Súbor:** `views/dashboard.php`
1. V sekcii "Tab bar (segment control)" (okolo riadku 220) pridaj tretie tlačidlo: k existujúcim `⚙️ Nastavenia` a `🍽️ Jedálny lístok` pridaj `📊 Analytika`.
2. Uprav CSS triedy tak, aby sa tri tlačidlá pekne zmestili (použi napr. `grid-cols-3` na rodičovskom kontajneri namiesto `flex`).
3. Tlačidlo musí mať ID `tab-btn-analytics` a volať `switchTab('analytics')`.

## ÚLOHA 2: Vytvorenie obsahu novej záložky
**Súbor:** `views/dashboard.php`
1. Vytvor nový kontajner `<div id="tab-analytics" class="space-y-4 hidden">` (pod ostatnými tabmi).
2. Presuň doň logiku a HTML z pôvodnej karty "Analytika", ktorá bola v bočnom paneli (`aside`).
3. **Vylepšenie dizajnu:** Keďže je teraz analytika v hlavnom poli, urob štatistiky vizuálne atraktívnejšie:
   - Použi dve veľké karty vedľa seba (Zobrazenia tento mesiac vs. Celkovo).
   - Pridaj k nim ikony a výraznú typografiu podľa Design Systemu.
   - Ak nie je vybraná žiadna prevádzka, zobraz prázdny stav (rovnako ako pri menu).

## ÚLOHA 3: Odstránenie starého prvku
**Súbor:** `views/dashboard.php`
1. Odstráň pôvodnú kartu analytiky z bočného panelu (`aside`), aby tam zostal len zoznam prevádzok a tlačidlo na pridanie novej.

## ÚLOHA 4: Aktualizácia JavaScriptu
**Súbor:** `views/dashboard.php`
1. Uprav funkciu `switchTab(tab)` tak, aby korektne spracovávala aj hodnotu `'analytics'`.
2. Nezabudni na prepínanie aktívnych tried (biely background, tiene, farba textu) na všetkých troch tlačidlách v Tab bare.

---
**Postup:**
- Zachovaj minimalistický dizajn, zaoblené rohy `rounded-[2rem]` a farby `indigo-600` / `slate`.
- Skontroluj, či prepínanie funguje plynulo.
- Uisti sa, že ak nie je vybraná žiadna prevádzka, v záložke Analytika sa zobrazí informácia: *"Najprv vytvorte prevádzku v záložke Nastavenia."*
