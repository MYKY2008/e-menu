# PROMPT: Implementácia plánov (Free vs. Paid) a limitov pre menu

**CIEĽ:** Zaviesť systém plánov pre používateľov. Používatelia s plánom "free" budú mať obmedzený počet kategórií (max 3) a jedál (max 5 na kategóriu). Administrátor bude môcť v admin paneli vidieť a meniť plán používateľa.

---

### 🛠️ ÚLOHA 1: Databázová migrácia
**Súbor:** `config.php`
**Inštrukcie:**
1. Do tabuľky `users` pridaj stĺpec `plan TEXT NOT NULL DEFAULT 'free'`.
2. Pridaj príslušný `ALTER TABLE` príkaz do poľa `$migrations`.

---

### 🛠️ ÚLOHA 2: Administrátorské rozhranie (Admin Panel)
**Súbor:** `views/admin.php`
**Inštrukcie:**
1. V tabuľke používateľov pridaj stĺpec "Plán".
2. Zobraz aktuálny plán (Free/Paid) pomocou farebného odznaku (Pill).
   - Free: `bg-gray-100 text-slate-600`
   - Paid: `bg-emerald-100 text-emerald-700` (alebo indigo)
3. Umožni administrátorovi zmeniť plán používateľa (napr. cez jednoduchý select v riadku tabuľky, ktorý okamžite po zmene zavolá API).
4. V `api/admin_actions.php` implementuj akciu `update_plan`.

---

### 🛠️ ÚLOHA 3: Vynucovanie limitov na Backende
**Súbor:** `api/manage_menu.php`
**Inštrukcie:**
1. Pri pridávaní kategórie (`add_category`) over, či má používateľ plán `free`. Ak áno, skontroluj, či už nemá 3 kategórie. Ak má, vráť chybu: "Dosiahli ste limit 3 kategórií pre Free plán. Prejdite na Paid pre neobmedzený počet."
2. Pri pridávaní jedla (`add_item`) over, či má používateľ plán `free`. Ak áno, skontroluj, či daná kategória už nemá 5 jedál. Ak má, vráť chybu: "Dosiahli ste limit 5 jedál na kategóriu pre Free plán."
3. Potrebuješ načítať `plan` používateľa z tabuľky `users` (pripoj k dotazu v `verifyVenue` alebo načítaj samostatne).

---

### 🛠️ ÚLOHA 4: Spätná väzba v Dashboarde
**Súbor:** `views/dashboard.php`
**Inštrukcie:**
1. Do dashboardu pridaj informáciu o aktuálnom pláne (napr. v sidebare alebo pri názve prevádzky).
2. Ak je používateľ Free a dosiahol limit kategórií, tlačidlo "+ Kategória" by malo byť vizuálne odlišné (napr. disabled s tooltipom alebo po kliknutí zobraziť toast s vysvetlením).
3. Podobne ošetri tlačidlo "+ Pridať jedlo" v rámci kategórie, ak je dosiahnutý limit 5 jedál.

---

### 🎨 Dizajnové pravidlá:
- Zachovaj minimalistický vizuál.
- Toast správy pre chyby limitov by mali byť jasné a motivovať k upgradeu.

---
**VÝSTUP:** Upravené súbory `config.php`, `views/admin.php`, `api/admin_actions.php`, `api/manage_menu.php` a `views/dashboard.php`.
