# PROMPT: Fix ceny Custom plánu a prémiový redizajn sliderov

**CIEĽ:** Opraviť cenovú logiku Custom plánu a vytvoriť vysoko kvalitné, vizuálne atraktívne ovládanie pomocou sliderov s oválnym úchopom.

---

### 🛠️ ÚLOHA 1: Nová cenotvorba pre Custom plán
**Súbor:** `views/plans_page.php` (funkcia `updateCustomPrice`)
**Problém:** Custom konfigurácia identická s Ultra plánom vychádza momentálne lacnejšie.
**Riešenie:** Nastav nové koeficienty (ceny bez DPH):
- **Základná mesačná réžia:** 5.00 €
- **Každá prevádzka:** 2.00 €
- **Každá kategória (limit):** 0.20 €
- **Každé jedlo (limit na kat.):** 0.10 €
- **DPH:** Ponechaj 23 %.

*Vysvetlenie:* Pri konfigurácii 1 prevádzka / 20 kat. / 50 jedál bude cena (5+2+4+5) * 1.23 = **19.68 €**. To je správne – fixný Ultra balík za 15 € je výhodnejší pre užívateľa, zatiaľ čo Custom je prémiová voľba pre tých, ktorí chcú presnú kontrolu.

---

### 🛠️ ÚLOHA 2: Redizajn Sliderov (UX & UI)
**Súbor:** `views/plans_page.php`
**Inštrukcie:**
1. **Oválne úchopy (Pill handle):** Prestaň používať natívny vzhľad sliderov. Pomocou CSS (napr. cez Tailwind triedy `[&::-webkit-slider-thumb]`, `[&::-moz-range-thumb]`) nastylovo úchop slidera ako **biely zaoblený ovál / pilulku** so stredovým tieňom a jemným borderom.
2. **Track (Dráha):** Dráha slidera musí byť hrubšia, zaoblená a mať farebný gradient alebo výraznú farbu (`violet-500`), ktorá sa mení podľa pozície úchopu (tzv. "fill" efekt).
3. **+/- Tlačidlá:** Po stranách každého slidera pridaj kruhové tlačidlá s ikonami mínus a plus pre presné krokovanie. Tieto tlačidlá musia:
   - Mať dostatočnú veľkosť na dotyk (min. `w-10 h-10`).
   - Mať vizuálnu odozvu pri kliknutí (`active:scale-90`).
   - Okamžite spustiť prepočet ceny.
4. **Zobrazenie hodnoty:** Číslo aktuálne vybratej hodnoty nad sliderom urob väčšie a výraznejšie (napr. `text-lg font-black text-violet-600`).

---

### 🛠️ ÚLOHA 3: Responzivita a Polish
1. Skontroluj, aby na mobile boli slidery a tlačidlá vedľa seba (ak je dostatok miesta) alebo v logickom stacku pod sebou, aby sa dali ľahko ovládať palcom.
2. Pridaj plynulé prechody (`transition`) pri zmenách farieb a stavov.

---
**VÝSTUP:** Upravený súbor `views/plans_page.php`.
