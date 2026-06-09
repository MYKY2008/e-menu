# PROMPT: Implementácia systému "Zabudnuté heslo" (PHPMailer)

**CIEĽ:** Vytvoriť bezpečný a profesionálny systém na obnovu hesla pomocou e-mailových tokenov.

---

### 🛠️ ÚLOHA 1: Príprava Databázy a Knižnice

1.  **Migrácia Databázy (`config.php`):**
    *   Pridaj SQL na vytvorenie tabuľky `password_resets`:
        ```sql
        CREATE TABLE IF NOT EXISTS password_resets (
            email      TEXT NOT NULL,
            token      TEXT NOT NULL,
            expires_at INTEGER NOT NULL
        )
        ```
2.  **Integrácia PHPMailer:**
    *   Priprav v koreňovom priečinku priečinok `libs/PHPMailer/`.
    *   Implementuj funkciu `sendEmail($to, $subject, $body)` v `config.php`, ktorá bude používať PHPMailer.
    *   Pre účely testovania nastav SMTP na "dummy" hodnoty (localhost), ktoré si užívateľ neskôr zmení.

---

### 🛠️ ÚLOHA 2: Proces žiadosti o reset (Forgot Password)

1.  **Nový View (`views/forgot_password.php`):**
    *   Jednoduchý, čistý formulár na zadanie e-mailu podľa `docs/dizajn.md`.
    *   Link "Späť na prihlásenie".
2.  **Logika odoslania (`auth/forgot_password_process.php`):**
    *   Skontroluj, či e-mail existuje v tabuľke `users`.
    *   Ak áno (alebo aj ak nie - kvôli bezpečnosti ukáž rovnakú správu):
        *   Vymaž staré tokeny pre tento e-mail.
        *   Vygeneruj bezpečný náhodný token: `bin2hex(random_bytes(32))`.
        *   Ulož e-mail, token a `time() + 3600` (platnosť 1 hodina) do `password_resets`.
        *   Pošli e-mail s linkom: `url('reset-password?token=' . $token)`.

---

### 🛠️ ÚLOHA 3: Proces zmeny hesla (Reset Password)

1.  **Nový View (`views/reset_password.php`):**
    *   Tento pohľad sa zobrazí len vtedy, ak je v URL platný token.
    *   Formulár: "Nové heslo" a "Potvrďte heslo".
2.  **Logika zmeny (`auth/reset_password_process.php`):**
    *   Over token v databáze a skontroluj `expires_at`.
    *   Ak je neplatný/expirovaný, vyhoď chybu a presmeruj na začiatok.
    *   Ak je OK:
        *   Zahašuj nové heslo (`password_hash`).
        *   Aktualizuj tabuľku `users` pre daný e-mail.
        *   **Dôležité:** Vymaž použitý token z `password_resets`.
        *   Prihlás používateľa alebo ho pošli na login so správou o úspechu.

---

### 🛠️ ÚLOHA 4: Integrácia do UI

1.  **Login Page:** Pridaj pod prihlasovacie tlačidlo link "Zabudli ste heslo?".
2.  **Routing:** Uprav `index.php`, aby spracovával nové trasy `/forgot-password` a `/reset-password`.

---
**VÝSTUP:** Dodaj kód pre všetky nové súbory a úpravy existujúcich. Zabezpeč, aby e-mail vyzeral pekne a profesionálne (HTML e-mail).
