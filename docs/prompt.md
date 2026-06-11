# TASKS FOR CLAUDE AI — SECURITY HARDENING (FINAL PHASE)

Tento prompt obsahuje finálnu sadu bezpečnostných a stabilizačných vylepšení.

## ÚLOHA 1: Mazanie súborov pri reset_menu
**Súbor:** `api/user_actions.php`
1. V `case 'reset_menu'` pred vymazaním kategórií z databázy pridaj logiku na vymazanie fyzických súborov (obrázkov jedál).
2. Použi pomocnú funkciu `deleteVenueFiles($slug)`, ktorú už máme v `config.php`, alebo implementuj podobnú logiku (nájsť všetky jedlá cez JOIN s kategóriami danej prevádzky a pre každé zavolať `deleteImageFile()`).

## ÚLOHA 2: CSRF ochrana pre backup.php
**Súbor:** `api/backup.php`
1. Pridaj kontrolu CSRF tokenu hneď na začiatku súboru: `if (!csrfValid($_GET['csrf'] ?? '')) { die('CSRF invalid'); }`.
**Súbor:** `views/admin.php` (alebo kde je odkaz na zálohu)
2. Uisti sa, že odkaz na stiahnutie zálohy obsahuje `?csrf=<?= csrfToken() ?>`.

## ÚLOHA 3: Zjednotenie API hlavičiek
**Súbory:** `api/delete_account.php`, `api/change_password.php`, `api/update_profile.php`
1. Pridaj/uprav hlavičky na začiatku súborov tak, aby boli konzistentné s ostatnými API:
   - `header('Content-Type: application/json; charset=utf-8');`
   - `header('X-Content-Type-Options: nosniff');`
2. Uisti sa, že všetky chybové správy sú vracané ako JSON.

## ÚLOHA 4: Validácia dĺžky polí pri importe
**Súbor:** `api/import_full.php`
1. Pri spracovaní riadkov CSV pridaj validáciu dĺžky reťazcov (mb_substr):
   - Názov kategórie: max 100 znakov.
   - Názov jedla: max 100 znakov.
   - Popis jedla: max 255 znakov.
2. Ak je cena neplatná alebo záporná, nastav ju na 0 alebo preskoč danú položku.

## ÚLOHA 5: Robustnejší import (Error handling)
**Súbor:** `api/import_full.php`
1. Celú logiku čítania CSV a vkladania do DB zabaľ do `try-catch` bloku.
2. Ak nastane chyba, vráť JSON s `ok: false` a chybovou správou.
3. Použi `getDB()->beginTransaction()` a `commit()`, aby sa nestalo, že sa importuje len polovica menu pri chybe uprostred súboru.

---
**Postup:**
- PHP súbory musia mať `declare(strict_types=1);`.
- Dodržiavaj Design System z `docs/dizajn.md`.
- Po úpravách otestuj Reset Menu a CSV Import, či fungujú správne.
