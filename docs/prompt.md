# PROMPT: Hĺbkové Mazanie Údajov (Disk Cleanup & GDPR)

**CIEĽ:** Zabezpečiť, aby pri vymazaní prevádzky (venue) alebo celého používateľa nezostali na serveri žiadne "sirotské" súbory (logá, cover fotky). Musíme fyzicky odstrániť súbory z priečinka `uploads/` pred tým, než sa vymažú záznamy z databázy.

---

### 🛠️ ÚLOHA 1: Čistenie pri zmazaní prevádzky (Single Venue Cleanup)

**Kde:** `api/save_venue.php` (akcia `delete`) a `api/admin_actions.php` (akcia `delete_venue`)

**Inštrukcie:**
1.  Pred samotným SQL príkazom `DELETE FROM venues...` urob nasledovné:
    *   Vytiahni z databázy cesty k súborom pre daný slug (`logo`, `cover_image`).
    *   Ak stĺpec obsahuje cestu k súboru (napr. `/uploads/venues/abc.png`) a tento súbor na disku reálne existuje, vymaž ho pomocou PHP funkcie `unlink()`.
    *   Uisti sa, že nevymažeš súbor, ak je v DB uložený ako Base64 (kontrola na prefix `data:image`).

---

### 🛠️ ÚLOHA 2: Čistenie pri zmazaní používateľa (Mass Cleanup)

**Kde:** `api/admin_actions.php` (akcia `delete_user`)

**Inštrukcie:**
1.  Aktuálne mazanie používateľa spolieha na `ON DELETE CASCADE` v databáze. To však neodstráni súbory na disku.
2.  **Nová logika pred zmazaním používateľa:**
    *   Nájdi v databáze všetky prevádzky (`slug`), ktoré patria danému `user_id`.
    *   Pre každú jednu prevádzku vykonaj "čistenie disku" (vymaž jej logo a cover fotku z priečinka `uploads/`).
    *   Až po fyzickom vymazaní všetkých súborov všetkých jeho prevádzok spusti SQL príkaz `DELETE FROM users WHERE id = ?`.

---

### 🛠️ ÚLOHA 3: Ošetrenie chýb a bezpečnosť

1.  **Cesty:** Pri mazaní súborov sa uisti, že cesty sú správne relatívne k `BASE_DIR`.
2.  **Prevencia:** Ak súbor z nejakého dôvodu na disku chýba (bol už zmazaný manuálne), skript nesmie skončiť chybou (použi `@unlink()` alebo kontrolu `file_exists()`).
3.  **Povolenia:** Skontroluj, či má webový server oprávnenie na mazanie v priečinku `uploads/`.

---
**VÝSTUP:** Dodaj upravený kód pre `api/save_venue.php` a `api/admin_actions.php`. Zameraj sa na to, aby v systéme nezostal po zmazaní žiadny bordel na disku.
