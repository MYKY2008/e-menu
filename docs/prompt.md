# PROMPT: Absolútna dokonalosť (Security & UX Polish)

**CIEĽ:** Zvýšiť úroveň súkromia pri registrácii a zabezpečiť "blbuvzdornosť" pri zadávaní cien v slovenskom formáte.

---

### 🛠️ ÚLOHA 1: Prevencia enumerácie e-mailov
**Súbor:** `auth/register.php`
**Inštrukcie:**
1. Uprav časť, kde sa kontroluje, či e-mail už existuje.
2. Namiesto chyby "Tento e-mail je už zaregistrovaný" nastav správu, ktorá neprezradí, či účet existuje alebo nie.
3. **Navrhovaný text:** "Ak je tento e-mail voľný, bol naň odoslaný aktivačný odkaz. Skontrolujte si schránku." (Uisti sa, že sa používateľ aj tak presmeruje na login, aby to vyzeralo ako úspech).

---

### 🛠️ ÚLOHA 2: Podpora čiarky v cene (Slovak UX)
**Súbor:** `api/manage_menu.php`
**Inštrukcie:**
1. V akcii `save_item` nájdi riadok, kde sa spracováva `$price`.
2. Predtým, ako cena prejde cez `filter_var` alebo `floatval`, pridaj náhradu čiarky za bodku:
   `$rawPrice = str_replace(',', '.', (string)($payload['price'] ?? ''));`
3. Týmto umožníme používateľom zadávať ceny prirodzene (napr. "4,50" aj "4.50").

---

### 🛠️ ÚLOHA 3: Finálna kontrola `.env.example`
**Súbor:** `.env.example`
**Inštrukcie:**
1. Skontroluj, či `.env.example` obsahuje všetky potrebné premenné, ktoré sme pridali (SMTP nastavenia, MAIL_FROM atď.), aby bol projekt ľahko nasaditeľný pre iných.

---
**VÝSTUP:** Upravené súbory `auth/register.php`, `api/manage_menu.php` a `.env.example`.
