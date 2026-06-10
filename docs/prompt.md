# PROMPT: Fix - Striktne odložené zrušenie predplatného

**CIEĽ:** Odstrániť "šedú zónu" pri zrušení predplatného. Zrušenie NESMIE nikdy mazať dáta okamžite, ak má užívateľ aktívne obdobie. Musí iba nastaviť dátum zániku.

---

### 🛠️ ÚLOHA 1: Úprava API pre zrušenie
**Súbor:** `api/user_actions.php` (akcia `cancel_plan`)
**Inštrukcie:**
1. **Úplne odstráň** akúkoľvek logiku, ktorá volá `DELETE FROM categories` v rámci akcie `cancel_plan`.
2. **Logika:** 
   - Ak `plan_ends_at` je v budúcnosti -> **IBA** nastav `next_plan_name = 'free'`. Nič viac. Vráť úspešnú odpoveď s informáciou o dátume expirácie.
   - Ak `plan_ends_at` je v minulosti alebo NULL (napr. testovací účet bez dátumu) -> Nastav `plan_name = 'free'` a základné limity (1/3/5), ale **STÁLE NEMRAŽ DÁTA**. Dáta sa mazať nesmú v tomto kroku nikdy.

---

### 🛠️ ÚLOHA 2: Úprava UI v Profile
**Súbor:** `views/profile_page.php` (funkcia `cancelPlanWithExport` a modál)
**Inštrukcie:**
1. **Zmena textov:** Modál na zrušenie už nesmie strašiť okamžitým mazaním dát. 
   - Text: "Chcete zrušiť automatické obnovovanie predplatného? Váš prístup zostane zachovaný do [Dátum]."
2. **Odstránenie Exportu:** Pri obyčajnom zrušení obnovy (Cancellation) nie je potrebný povinný export ani sťahovanie CSV. Odstráň volanie `api/export_full.php` z tejto funkcie.
3. **Akcia:** Tlačidlo v modále premenuj na "Potvrdiť zrušenie obnovy". Po kliknutí zavolaj API a po úspechu reštartuj stránku.

---

### 🛠️ ÚLOHA 3: Jediné miesto pre mazanie dát (Lockdown v Dashboarde)
**Súbor:** `views/dashboard.php`
**Inštrukcie:**
1. **Len tu je povolený reset:** Mazanie dát (s povinnou zálohou) je povolené **IBA** vtedy, keď je dashboard v stave **Lockdown** (t.j. už nastal moment expirácie a užívateľ má v DB viac dát, než povoľuje jeho nový Free/nižší plán).
2. Tlačidlo v Lockdown karte "Zresetovať menu podľa limitov" (ktoré volá `reset_menu`) je jediné miesto, ktoré reálne maže kategórie a jedlá po stiahnutí CSV.

---

### 🎨 Kontrola logiky:
1. Užívateľ má Ultra, klikne "Zrušiť".
2. **Výsledok:** Stále má Ultra, v DB je zapísané, že po 30.6. bude Free. Žiadne CSV sa nesťahuje, nič sa nemaže.
3. Nastane 1.7. -> Systém ho automaticky prepne na Free (vďaka `applyPlanTransitionIfNeeded`).
4. Užívateľ má v DB stále 50 jedál, ale limit je 5.
5. **Výsledok:** Dashboard sa uzamkne (Lockdown).
6. Užívateľ klikne "Resetovať", stiahne CSV a až teraz systém zmaže menu.

---
**VÝSTUP:** Opravené súbory `api/user_actions.php`, `views/profile_page.php` a `views/dashboard.php`.
