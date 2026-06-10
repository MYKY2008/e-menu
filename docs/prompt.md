# PROMPT: Unifikácia dizajnu v rámci Dashboardu (Nastavenia vs. Menu)

**CIEĽ:** Dosiahnuť úplnú vizuálnu a štrukturálnu konzistenciu medzi záložkou "Nastavenia" (správa prevádzky) a záložkou "Menu" (správa kategórií a jedál) v rámci dashboardu. Obe sekcie musia zdieľať rovnaký vizuálny jazyk, paddingy a štýly komponentov.

---

### 🛠️ ÚLOHA 1: Unifikácia tabu "Nastavenia" (Venue Settings)
**Súbor:** `views/dashboard.php`
**Inštrukcie:**
1. **Layout Kariet:** Záložka Nastavenia (`#tab-settings`) musí používať rovnaký systém kariet ako Menu.
   - Všetky hlavné sekcie (Základné info, Logo/Cover, Farebná téma) obal do samostatných kariet s `rounded-[2rem]`, `p-5` a `border border-gray-100 dark:border-slate-800`.
2. **Nadpisy:** Použi unifikovaný štýl nadpisov sekcií: `text-[10px] font-black uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-5`.
3. **Tlačidlá:** Hlavné tlačidlo "Uložiť nastavenia" musí byť `rounded-2xl`, `px-5`, `py-2.5`, `font-bold` (rovnako ako "Uložiť nastavenia menu").
4. **Inputy:** Zjednoť všetky inputy v tejto sekcii na `rounded-xl`, `px-4`, `py-2.5` (rovnako ako vyhľadávacie pole v menu).

---

### 🛠️ ÚLOHA 2: Farebná paleta prevádzky
**Súbor:** `views/dashboard.php`
**Inštrukcie:**
1. Výber farby prevádzky (`currentVenueColor`) musí vizuálne ladiť s paletou v menu. 
   - Použi rovnaký grid a zaoblené kruhy (`w-8 h-8 rounded-full`) s `ring-offset-1`.
   - Pridaj aj sem **Custom Color Picker** (paletka 🎨), aby bolo možné nastaviť ľubovoľnú farbu prevádzky, nielen tie z palety.

---

### 🛠️ ÚLOHA 3: Zjednotenie detailov v "Menu" tabe
**Súbor:** `views/dashboard.php`
**Inštrukcie:**
1. **Empty States:** Keď nie je vybraná prevádzka, zobrazenie prázdneho stavu musí byť vizuálne identické v oboch taboch.
2. **Spacing:** Skontroluj `gap` medzi kartami v oboch taboch, musí byť identický (`space-y-4` alebo `space-y-6`).

---

### 🛠️ ÚLOHA 4: Responzivita a Dark Mode
1. Uisti sa, že oba taby vyzerajú konzistentne aj na mobilných zariadeniach.
2. Skontroluj, či sú všetky nové prvky (najmä custom color picker v nastaveniach prevádzky) správne ostylované pre dark mode.

---
**VÝSTUP:** Upravený súbor `views/dashboard.php`.
