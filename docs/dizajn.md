# Design System & Visual Guidelines (EMENU)

Tento dokument definuje striktné vizuálne pravidlá pre projekt EMENU. Claude AI musí tieto pravidlá rešpektovať pri každej úprave frontendu.

## 1. Základné Princípy
- **Minimalizmus:** Žiadny zbytočný vizuálny šum (žiadne ťažké okraje, žiadne prehnané prechody).
- **Vzdušnosť:** Používanie veľkorysého whitespace (paddingy, marginy).
- **Konzistencia:** Každé tlačidlo, input a karta v systéme musí vyzerať identicky.

## 2. Farebná Paleta
- **Svetlý režim (Light Mode):**
  - Pozadie: `bg-gray-50`
  - Karty/Kontajnery: `bg-white`
  - Text primárny: `text-slate-900`
  - Text sekundárny: `text-slate-500`
- **Tmavý režim (Dark Mode):**
  - Pozadie: `dark:bg-slate-950`
  - Karty/Kontajnery: `dark:bg-slate-900`
  - Text primárny: `dark:text-slate-50`
  - Text sekundárny: `dark:text-slate-400`
- **Akcentná farba (Brand):**
  - Primárna: `indigo-600` (hover: `indigo-700`)
  - Doplnková (Success): `emerald-500`

## 3. Komponenty (Tailwind Triedy)
- **Karty (Cards):** 
  - `rounded-[2rem]` (extrémne zaoblenie)
  - `shadow-sm` (veľmi jemný tieň)
  - `border border-gray-100 dark:border-slate-800`
- **Tlačidlá (Buttons):**
  - Primárne: `rounded-2xl px-6 py-3 font-semibold transition-all active:scale-95`
  - Štýl: Žiadne ostré hrany.
- **Vstupné polia (Inputs):**
  - `bg-gray-100 dark:bg-slate-800 border-none rounded-xl focus:ring-2 focus:ring-indigo-500`
- **Pill (Odznaky/Ceny):**
  - `rounded-full px-3 py-1 text-xs font-bold uppercase tracking-wider`

## 4. Typografia
- Font: **Inter** (sans-serif)
- Nadpisy: `font-bold tracking-tight text-slate-900 dark:text-white`
- Čitateľnosť: Line-height `leading-relaxed` pre dlhšie texty.

## 5. Špecifické UX Prvky
- **Sticky Nav:** Navigácia kategórií musí byť vždy dostupná na vrchu obrazovky pri scrolovaní.
- **Mobile-First:** Všetky zobrazenia sú primárne navrhnuté pre mobilné zariadenia (`max-w-md` na desktope pre klientske zobrazenie).
- **Interaktivita:** Každý klikateľný prvok musí mať vizuálnu odozvu (hover, active scale).
