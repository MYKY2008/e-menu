# Dynamické rýchle odkazy pre prevádzku (GastroLink QR)

Tento prompt obsahuje inštrukcie pre pridanie dynamických rýchlych odkazov pre prevádzky (Google Recenzie, Instagram, Facebook, TikTok). Claude AI musí vykonať všetky úlohy uvedené nižšie a dbať na zachovanie strict_types, Tailwind dizajnu a Inter fontu.

---

## Cieľ úpravy
Namiesto dvoch fixne zobrazených textových polí pre Google Recenzie a Instagram chceme dať majiteľovi prevádzky možnosť vybrať si pomocou checkboxov, ktoré odkazy chce na svojom menu zobraziť. Na výber budú štyri možnosti:
- **Google Recenzie** (stĺpec `google_url`)
- **Instagram** (stĺpec `instagram_url`)
- **Facebook** (stĺpec `facebook_url` — nový stĺpec)
- **TikTok** (stĺpec `tiktok_url` — nový stĺpec)

Keď majiteľ v admin paneli začiarkne príslušný checkbox, pod ním sa zobrazí textové pole pre zadanie danej URL. Ak checkbox odčiarkne, textové pole sa schová a jeho hodnota sa vymaže. Na verejnom menu zákazníka sa zobrazia iba tie rýchle odkazy, ktoré sú začiarknuté a vyplnené.

---

## Úlohy pre Claude AI

### 1. Databázová migrácia v `config.php`
- V súbore [config.php](file:///C:/Users/micha/Documents/Projects/EMENU/config.php) pridajte do poľa `$migrations` (okolo riadku 170) dva nové SQL príkazy na pridanie stĺpcov pre Facebook a TikTok:
  ```php
  "ALTER TABLE venues ADD COLUMN facebook_url TEXT DEFAULT NULL",
  "ALTER TABLE venues ADD COLUMN tiktok_url TEXT DEFAULT NULL",
  ```

### 2. Úprava backendu v `api/save_venue.php`
- V súbore [save_venue.php](file:///C:/Users/micha/Documents/Projects/EMENU/api/save_venue.php):
  - Rozšírte pole `$urlFields` (riadok 81) o `'facebook_url' => 'Facebook'` a `'tiktok_url' => 'TikTok'`.
  - Spracujte nové premenné z payloadu:
    ```php
    $fb = trim($payload['facebook_url'] ?? '') ?: null;
    $tt = trim($payload['tiktok_url'] ?? '') ?: null;
    ```
  - Upravte SQL UPDATE dopyt pre zmenu slugu (okolo riadku 192) a SQL INSERT/UPDATE dopyt (okolo riadku 217) tak, aby sa ukladali a aktualizovali aj stĺpce `facebook_url` a `tiktok_url`.

### 3. Úprava admin panelu v `views/dashboard.php`
- **Úprava HTML formulára (okolo riadku 307):**
  - Nahraďte pôvodný cyklus pre Google a Instagram novou sekciou pre rýchle odkazy.
  - Vytvorte grid s 2 stĺpcami pre checkboxy:
    - Kľúče a popis: `google` (Google Recenzie), `insta` (Instagram), `facebook` (Facebook), `tiktok` (TikTok).
    - Každý checkbox bude mať ID vo formáte `f-check-{key}` a bude volať `onchange="toggleLinkInput('{key}')"`.
    - Checkbox bude označený ako `checked`, ak príslušný stĺpec v `$selected` nie je prázdny.
  - Vytvorte kontajnery pre textové vstupy pod checkboxmi.
    - Každý kontajner bude mať ID `wrap-{key}` a triedu `hidden`, ak je hodnota prázdna.
    - Textové vstupy (inputs) budú mať ID `f-google`, `f-insta`, `f-facebook`, `f-tiktok`.
    - Použite moderný Tailwind vzhľad ladiaci s [docs/dizajn.md](file:///C:/Users/micha/Documents/Projects/EMENU/docs/dizajn.md).
- **Pridať JS funkciu `toggleLinkInput(key)`:**
  - Ak je checkbox `f-check-{key}` začiarknutý, odoberte triedu `hidden` z elementu `wrap-{key}`.
  - Ak je odčiarknutý, pridajte triedu `hidden` na `wrap-{key}` a vymažte hodnotu (value) príslušného textového poľa.
- **Úprava funkcie `saveVenue()`:**
  - Do odosielaného payloadu pridajte `facebook_url` a `tiktok_url`.
  - Hodnota URL sa pošle iba vtedy, ak je príslušný checkbox začiarknutý (inak pošlite prázdny reťazec).
- **Úprava funkcie `openNewVenue()`:**
  - Zabezpečte vyčistenie nových polí `f-facebook` a `f-tiktok`.
  - Odčiarknite všetky štyri checkboxy a skryte ich kontajnery pridaním triedy `hidden`.

### 4. Úprava verejného menu v `views/client_view.php`
- V súbore [client_view.php](file:///C:/Users/micha/Documents/Projects/EMENU/views/client_view.php) upravte plnenie poľa `$quickActions` (okolo riadku 130) tak, aby zahŕňalo aj Facebook a TikTok, ak sú vyplnené v DB:
  - Google: emoji `⭐`, label `Google`
  - Instagram: emoji `📷`, label `Instagram`
  - Facebook: emoji `👥`, label `Facebook`
  - TikTok: emoji `🎵`, label `TikTok`

---

## Overenie funkčnosti
1. Otvorte admin panel a v editácii prevádzky overte, že sa zobrazujú checkboxy pre Google, Instagram, Facebook a TikTok.
2. Začiarknite Facebook a TikTok a overte, že sa pod nimi zobrazia vstupné polia pre zadanie URL.
3. Vyplňte testovacie adresy a uložte prevádzku.
4. Overte v databáze alebo opätovným načítaním stránky, že sa hodnoty správne uložili.
5. Otvorte klientske menu prevádzky a skontrolujte, že sa v hlavičke zobrazia správne ikony rýchlych odkazov pre všetky štyri typy sietí.
