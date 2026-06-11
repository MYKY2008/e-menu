# ÚLOHA: Fix Forbidden chyby a assetov na Localhoste

Cieľom je opraviť bezpečnostnú kontrolu v `index.php`, ktorá na Windowse blokuje prístup k štýlom a skriptom (Forbidden).

## 1. Oprava kontroly v index.php
- Uprav sekciu `if (PHP_SAPI === 'cli-server')`:
- Zmeň logiku blokovania `storage/` tak, aby bola odolná voči Windows cestám:
    ```php
    $realStorage = str_replace('\\', '/', (string)realpath(BASE_DIR . '/storage'));
    $realFile    = str_replace('\\', '/', (string)realpath($file));
    
    // Blokuj LEN ak ide o priečinok storage a LEN ak realpath uspel
    if ($realStorage !== '' && $realFile !== '' && str_starts_with($realFile, $realStorage)) {
        http_response_code(403);
        die('Access denied to storage.');
    }
    ```
- Zabezpeč, aby sa pred touto kontrolou overilo, či sa užívateľ nepokúša pristúpiť k `assets/` alebo `uploads/`, ktoré musia byť povolené.

## 2. Povolenie statických súborov
- Uisti sa, že ak je `$file` existujúci súbor a končí na `.css`, `.js`, `.png`, `.jpg`, `.webp`, tak skript vráti `false`, čím povie PHP serveru, aby ho normálne odoslal.

## 3. Double-check
- Po tejto zmene by sa po reštarte `run.bat` mala aplikácia zobraziť so správnymi štýlmi.
