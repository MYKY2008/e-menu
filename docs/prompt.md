# Oprava verifikácie účtov a ochrana proti robotom (Gmail/Outlook scannery)

## Problém
1. **Invalid Link / Automatická verifikácia:** Emailové scannery (Gmail, Outlook) klikajú na linky automaticky. Aktuálne `auth/verify.php` vykoná verifikáciu hneď pri GET požiadavke (kliknutí). To znamená, že účet sa aktivuje skôr, než naň klikne človek, a skutočný klik užívateľa potom hlási "neplatný odkaz".
2. **Bezpečnosť:** Chceme, aby verifikáciu potvrdil **človek**, nie jeho schránka.
3. **Priebeh:** Po aktivácii chceme, aby sa užívateľ **prihlásil manuálne**, čím potvrdí, že pozná svoje prihlasovacie údaje.

## Úlohy pre Claude AI

### 1. Úprava `auth/verify.php` (Human-in-the-loop)
- **Zmena na dvojfázové overenie:**
    - **GET požiadavka:** Nezmení stav v databáze. Iba overí, či token existuje a je platný. Ak áno, zobrazí jednoduchú, peknú stránku (v štýle projektu) s nápisom "Posledný krok k aktivácii" a veľkým tlačidlom **"Aktivovať môj účet"**.
    - **POST požiadavka (po kliknutí na tlačidlo):** 
        1. Vykoná sa skutočná verifikácia v DB (`is_verified = 1`, `verify_token = NULL`).
        2. **DÔLEŽITÉ:** Nenastavuj session (neprihlasuj užívateľa automaticky).
        3. Nastav flash správu "Účet bol úspešne aktivovaný. Teraz sa môžete prihlásiť."
        4. Presmeruj užívateľa na `/login`.
- **Resilience:** Ak je užívateľ už verifikovaný, presmeruj ho priamo na login s informáciou "Váš účet je už aktívny, môžete sa prihlásiť."

### 2. Hardening `auth/login.php`
- Striktná kontrola `is_verified` pred prihlásením. Ak účet nie je overený, zobraziť jasnú chybu a neprihlásiť.

### 3. Hardening `config.php` (requireLogin)
- V `requireLogin()` pridať poistku: Ak session tvrdí, že užívateľ je prihlásený, ale v DB má `is_verified = 0`, okamžite zničiť session (`session_destroy`) a presmerovať na login.

### 4. Úprava `auth/register.php`
- Pred vložením nového užívateľa do DB zavolať `session_unset()` a `session_destroy()`, aby sme začali s "čistým štítom".

## Technické detaily
- **Dizajn verifikačnej stránky:** Použi Tailwind CSS cez CDN, Inter font, indigo-600 farbu a zaoblené rohy (`rounded-2xl`). Stránka by mala byť responzívna a vycentrovaná.
- **Bezpečnosť:** Pre POST tlačidlo v `verify.php` stačí ako autorizácia samotný `token` (v URL alebo skrytom poli).

Po dokončení zmien by mal byť flow takýto:
1. Užívateľ klikne v maile na link.
2. Uvidí stránku "Aktivujte svoj účet". (Scanner tu skončí).
3. Užívateľ klikne na tlačidlo.
4. Účet sa aktivuje v DB.
5. Užívateľ je presmerovaný na prihlasovaciu stránku s úspešnou správou.
6. Užívateľ sa manuálne prihlási.


