---
name: design-system
description: Striktné vizuálne pravidlá pre EMENU — Tailwind triedy, farby, komponenty
metadata:
  type: project
---

**Zdroj:** `docs/dizajn.md` — tieto pravidlá sú záväzné pri každej úprave frontendu.

**Princípy:** Minimalizmus, vzdušnosť (whitespace), konzistencia.

**Farby:**
- Light: bg-gray-50, bg-white, text-slate-900, text-slate-500
- Dark: dark:bg-slate-950, dark:bg-slate-900, dark:text-slate-50, dark:text-slate-400
- Akcent: indigo-600 (hover: indigo-700), emerald-500

**Komponenty:**
- Karty: `rounded-[2rem] shadow-sm border border-gray-100 dark:border-slate-800`
- Tlačidlá: `rounded-2xl px-6 py-3 font-semibold transition-all active:scale-95`
- Inputy: `bg-gray-100 dark:bg-slate-800 border-none rounded-xl focus:ring-2 focus:ring-indigo-500`
- Pill: `rounded-full px-3 py-1 text-xs font-bold uppercase tracking-wider`

**Typografia:** Inter, font-bold tracking-tight, leading-relaxed pre dlhé texty.

**UX:** Sticky nav kategórií, mobile-first (max-w-md na desktope), vizuálna odozva na hover/active.

**Why:** Zachovať konzistentný vzhľad naprieč celou aplikáciou.
**How to apply:** Pri každej UI zmene overiť triedy podľa tohto dokumentu.
