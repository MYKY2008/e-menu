# PROMPT: Správa životného cyklu predplatného a dátová integrita

**CIEĽ:** Implementovať logiku expirácie plánu, povinnú zálohu pred zrušením, import/export dát a automatické čistenie neaktívnych účtov.

---

### 🛠️ ÚLOHA 1: Databáza a Sledovanie času
**Súbor:** `config.php`
1. Pridaj stĺpce do tabuľky `users`:
   - `plan_ends_at` (DATETIME, default NULL)
   - `last_login_at` (DATETIME, default teraz)
2. Pridaj migráciu do `$migrations`.
3. V `auth/login.php` (alebo v mieste, kde sa overuje login) pridaj update `last_login_at = strftime('%Y-%m-%dT%H:%M:%SZ','now')`.

---

### 🛠️ ÚLOHA 2: Expirácia a "Locked" Dashboard
**Súbor:** `views/dashboard.php` a `views/client_view.php`
1. **Logika:** Ak `plan_ends_at` uplynul (teraz > `plan_ends_at`) a nie je to admin:
   - **Klientske menu:** Zobraz jednoduchú čistú stránku s oznamom: "Tento jedálny lístok je momentálne nedostupný (expirácia predplatného)."
   - **Dashboard:** Ak užívateľ vstúpi do správy menu, nahraď celý stredový obsah dashboardu (sekcie kategórií) veľkým varovaním (Card s `bg-amber-50`). 
     - Text: "Váš plán expiroval. Vaše dáta sú u nás v bezpečí, ale menu nie je verejne dostupné."
     - Akcia: Ponúkni dve tlačidlá: "Obnoviť predplatné" (link na /plans) a "Stiahnuť zálohu dát (CSV)" (volanie exportu).
2. Ponechaj prístup k sidebaru (zoznamu prevádzok) a k profilu.

---

### 🛠️ ÚLOHA 3: Export a Import (Full Data Portability)
**Súbory:** `api/export_full.php`, `api/import_full.php` (nové) a UI v `views/profile_page.php`
1. **Export:** Vytvor skript, ktorý vygeneruje CSV so všetkými kategóriami a jedlami pre zadaný slug prevádzky. 
   - Štruktúra CSV: `CategoryName, CategoryIcon, ItemName, ItemDesc, ItemPrice, ItemAllergens, ItemFeatured`.
2. **Import:** Vytvor skript, ktorý prijme toto CSV a nahrá ho do zvolenej prevádzky. 
   - **Validácia:** Musí rešpektovať aktuálne limity kategórií a jedál užívateľa (ak má v pláne limit 5 jedál, šieste z CSV ignoruj a vráť info).
3. **UI v Profile:** V sekcii "Môj Plán" pridaj prehľadnú podsekciu "Záloha a prenos dát" s tlačidlami pre Export a Import.

---

### 🛠️ ÚLOHA 4: Proces zrušenia plánu
**Súbor:** `views/profile_page.php` a nové API v `api/admin_actions.php`
1. Ak má užívateľ aktívny platený plán (pro/ultra/custom), zobraz v sekcii "Môj Plán" tlačidlo "Zrušiť predplatné".
2. **Workflow:** 
   - Klik na tlačidlo -> Varovanie v modále: "Zrušením plánu sa vaše menu vymaže a účet sa prepne na Free. Chcete pokračovať?"
   - **Povinný krok:** Systém automaticky spustí stiahnutie `export_full.php`.
   - Až po stiahnutí (alebo v rámci rovnakého procesu) odošli request na prepnutie plánu na `free`, nastavenie limitov na základné (1/3/5) a vymazanie všetkých kategórií a jedál z DB.

---

### 🛠️ ÚLOHA 5: Auto-Cleanup neaktívnych užívateľov
**Súbor:** `api/admin_actions.php`
1. Implementuj logiku (možno v rámci existujúcej akcie alebo pri načítaní panelu), ktorá vymaže užívateľov:
   - Ktorí sú na `free` pláne.
   - Neprihlásili sa viac ako 90 dní (`last_login_at`).
2. Vymazanie musí byť kompletné (použi `deleteVenueFiles` pre každú ich prevádzku a potom zmaž užívateľa z DB - kaskáda sa postará o zvyšok).

---

### 🎨 Dizajnové pravidlá:
- Zachovaj minimalistický systém (`rounded-[2rem]`, `rounded-xl`, `indigo-600`).
- Locked state v dashboarde by mal pôsobiť ako "jemné upozornenie", nie ako technická chyba.

---
**VÝSTUP:** Upravené súbory `config.php`, `views/dashboard.php`, `views/client_view.php`, `views/profile_page.php`, `api/admin_actions.php` a nové API súbory.
