<!DOCTYPE html>
<html lang="sk" class="">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>GastroLink QR — Digitálny jedálny lístok pre váš podnik</title>
<meta name="description" content="Vytvorte krásny digitálny jedálny lístok s QR kódom za 2 minúty. Menu, Google recenzie, Instagram — na jednom mieste.">

<!-- Open Graph -->
<meta property="og:type"        content="website">
<meta property="og:url"         content="<?= e(baseUrl()) ?>">
<meta property="og:title"       content="GastroLink QR — Digitálny jedálny lístok pre váš podnik">
<meta property="og:description" content="Vytvorte krásny digitálny jedálny lístok s QR kódom za 2 minúty. Menu, Google recenzie, Instagram — na jednom mieste.">
<meta property="og:image"       content="<?= e(baseUrl()) ?>/assets/img/og-image.jpg">
<meta property="og:locale"      content="sk_SK">
<meta property="og:site_name"   content="GastroLink QR">

<!-- Twitter Card -->
<meta name="twitter:card"        content="summary_large_image">
<meta name="twitter:title"       content="GastroLink QR — Digitálny jedálny lístok pre váš podnik">
<meta name="twitter:description" content="Vytvorte krásny digitálny jedálny lístok s QR kódom za 2 minúty. Menu, Google recenzie, Instagram — na jednom mieste.">
<meta name="twitter:image"       content="<?= e(baseUrl()) ?>/assets/img/og-image.jpg">

<!-- Anti-flash dark mode -->
<script>(function(){if(localStorage.getItem('gl-dark')==='1')document.documentElement.classList.add('dark')})();</script>

<!-- Inter font -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

<link rel="stylesheet" href="<?= asset('assets/css/style.css') ?>">
</head>
<body class="bg-gray-50 dark:bg-slate-950 text-slate-900 dark:text-slate-50 antialiased">

<!-- ── NAVBAR ──────────────────────────────────────────────────────── -->
<nav class="fixed top-0 w-full z-50 bg-white/80 dark:bg-slate-950/80 backdrop-blur-lg
            border-b border-gray-100 dark:border-slate-800">
  <div class="max-w-6xl mx-auto px-5 py-3.5 flex items-center justify-between">
    <a href="<?= url() ?>" class="font-extrabold text-xl tracking-tight">
      <span class="text-indigo-600">GastroLink</span><span class="text-emerald-500">QR</span>
    </a>
    <div class="flex items-center gap-2">
      <!-- Dark mode toggle -->
      <button onclick="toggleDark()" aria-label="Prepnúť tmavý režim"
              class="w-9 h-9 rounded-xl bg-gray-100 dark:bg-slate-800
                     flex items-center justify-center
                     text-slate-500 dark:text-slate-400
                     hover:bg-gray-200 dark:hover:bg-slate-700
                     transition-all duration-200">
        <span id="dark-icon" class="w-4 h-4 block"></span>
      </button>
      <a href="<?= url('login') ?>"
         class="hidden sm:inline-flex px-4 py-2 rounded-2xl text-sm font-semibold
                text-slate-600 dark:text-slate-300
                hover:bg-gray-100 dark:hover:bg-slate-800
                transition-all duration-200">
        Prihlásiť
      </a>
      <a href="<?= url('register') ?>"
         class="px-5 py-2 rounded-2xl bg-indigo-600 hover:bg-indigo-700 text-white
                text-sm font-semibold transition-all duration-200 active:scale-95
                shadow-md shadow-indigo-500/20">
        Začať zadarmo
      </a>
    </div>
  </div>
</nav>

<!-- ── HERO ────────────────────────────────────────────────────────── -->
<section class="pt-32 pb-20 px-5 bg-gray-50 dark:bg-slate-950">
  <div class="max-w-5xl mx-auto">
    <div class="lg:grid lg:grid-cols-2 lg:gap-16 lg:items-center">

      <!-- Left: Copy -->
      <div class="text-center lg:text-left">
        <!-- Badge -->
        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-bold
                     bg-indigo-100 dark:bg-indigo-900/40 text-indigo-600 dark:text-indigo-400 mb-6">
          ✨ Digitálne menu pre gastronómiu
        </span>

        <h1 class="text-5xl md:text-6xl font-extrabold tracking-tight leading-[1.08] mb-6
                   text-slate-900 dark:text-white">
          Jeden<br>
          <span class="text-indigo-600">QR kód.</span><br>
          Celé menu.
        </h1>

        <p class="text-lg text-slate-500 dark:text-slate-400 leading-relaxed mb-8 max-w-md mx-auto lg:mx-0">
          Vytvorte krásny digitálny jedálny lístok za 2 minúty. Zákazníci naskenujú a ihneď vidia vaše menu priamo na mobile.
        </p>

        <div class="flex flex-col sm:flex-row gap-3 justify-center lg:justify-start">
          <a href="<?= url('register') ?>"
             class="px-8 py-3.5 rounded-2xl bg-indigo-600 hover:bg-indigo-700 text-white
                    font-semibold text-base transition-all duration-200 active:scale-95
                    shadow-lg shadow-indigo-500/25">
            Vytvoriť menu zadarmo →
          </a>
          <a href="<?= url('login') ?>"
             class="px-8 py-3.5 rounded-2xl bg-gray-100 dark:bg-slate-800
                    hover:bg-gray-200 dark:hover:bg-slate-700
                    text-slate-700 dark:text-slate-200
                    font-semibold text-base transition-all duration-200">
            Prihlásiť sa
          </a>
        </div>
        <p class="mt-3 text-sm text-slate-400 dark:text-slate-500">
          Bez kreditnej karty &middot; Vždy zadarmo
        </p>
      </div>

      <!-- Right: Phone mockup -->
      <div class="hidden lg:flex justify-center items-center animate-float">
        <div class="relative">

          <!-- Phone frame — relative so notch anchors here; flex-col stacks content -->
          <div class="relative w-[13rem] rounded-[2.5rem] border-[5px] border-slate-800 dark:border-slate-700
                      shadow-2xl overflow-hidden flex flex-col bg-gray-50"
               style="height:430px">

            <!-- Notch (absolute inside the relative frame) -->
            <div class="absolute top-0 left-1/2 -translate-x-1/2 z-10
                        w-20 h-5 bg-slate-800 dark:bg-slate-700 rounded-b-2xl"></div>

            <!-- Notch spacer — pushes real content below the notch -->
            <div class="shrink-0 h-5"></div>

            <!-- Header section -->
            <div class="shrink-0 px-4 pt-4 pb-3 text-center bg-indigo-600">
              <div class="w-10 h-10 rounded-full bg-white/20 mx-auto mb-2
                          flex items-center justify-center text-base font-black text-white">K</div>
              <p class="text-white font-bold text-[11px] leading-tight">Kaviareň Central</p>
              <div class="flex justify-center gap-1.5 mt-2">
                <span class="px-2 py-0.5 rounded-full bg-white/20 text-white text-[8px] font-semibold">⭐ Google</span>
                <span class="px-2 py-0.5 rounded-full bg-white/20 text-white text-[8px] font-semibold">📷 Insta</span>
              </div>
            </div>

            <!-- Sticky nav -->
            <div class="shrink-0 px-3 py-2 bg-white border-b border-gray-100 flex gap-1.5">
              <span class="px-2.5 py-1 rounded-full bg-indigo-600 text-white text-[7px] font-bold whitespace-nowrap">☕ Kávy</span>
              <span class="px-2.5 py-1 rounded-full bg-gray-100 text-gray-500 text-[7px] font-semibold whitespace-nowrap">🥐 Raňajky</span>
              <span class="px-2.5 py-1 rounded-full bg-gray-100 text-gray-500 text-[7px] font-semibold whitespace-nowrap">🍰 Torty</span>
            </div>

            <!-- Menu items (fills remaining space, clips overflow) -->
            <div class="flex-1 overflow-hidden bg-gray-50 p-3 space-y-2">
              <?php foreach ([['Flat White','3,80'],['Kapučíno','3,20'],['Croissant','2,90'],['Cheesecake','4,50']] as [$n,$p]): ?>
              <div class="bg-white rounded-xl p-2.5 flex items-center justify-between shadow-sm">
                <span class="text-[9px] font-semibold text-gray-800"><?= $n ?></span>
                <span class="px-2 py-0.5 rounded-full bg-gray-100 text-gray-700 text-[8px] font-bold"><?= $p ?> €</span>
              </div>
              <?php endforeach; ?>
            </div>

          </div><!-- /phone frame -->

          <!-- Floating QR badge -->
          <div class="absolute -bottom-3 -right-7 bg-white dark:bg-slate-900 rounded-[1.25rem]
                      shadow-xl border border-gray-100 dark:border-slate-800 p-3 text-center">
            <div class="w-12 h-12 bg-slate-900 dark:bg-white rounded-lg mx-auto mb-1.5
                        grid grid-cols-3 gap-0.5 p-1">
              <?php
              $qr = [1,0,1, 0,0,0, 1,0,1];
              foreach ($qr as $cell):
              ?>
              <div class="rounded-[1px] <?= $cell ? 'bg-white dark:bg-slate-900' : 'bg-slate-900 dark:bg-white' ?>"></div>
              <?php endforeach; ?>
            </div>
            <p class="text-[8px] font-bold text-slate-600 dark:text-slate-400">QR kód</p>
          </div>

        </div>
      </div>

    </div>
  </div>
</section>

<!-- ── HOW IT WORKS ─────────────────────────────────────────────────── -->
<section class="py-24 px-5 bg-white dark:bg-slate-900">
  <div class="max-w-5xl mx-auto">
    <div class="text-center mb-16">
      <p class="text-xs font-bold uppercase tracking-[.2em] text-indigo-600 dark:text-indigo-400 mb-3">
        Jednoduché kroky
      </p>
      <h2 class="text-4xl font-extrabold tracking-tight text-slate-900 dark:text-white">
        Ako to funguje?
      </h2>
    </div>

    <div class="grid md:grid-cols-3 gap-5">
      <?php
      $steps = [
        ['📝', '01', 'Zaregistrujte sa',   'Stačí e-mail a heslo. Žiadne platobné údaje ani záväzky.'],
        ['⚙️', '02', 'Nastavte menu',       'Pridajte kategórie a jedlá. Nahrajte logo, vyberte farby.'],
        ['🖨️', '03', 'Zdieľajte QR kód',    'Stiahnite QR kód a umiestnite ho na stoly alebo vitráž.'],
      ];
      foreach ($steps as [$icon, $num, $title, $desc]):
      ?>
      <div class="group bg-gray-50 dark:bg-slate-800 rounded-[2rem]
                  border border-gray-100 dark:border-slate-700
                  p-8 shadow-sm hover:shadow-md hover:-translate-y-1
                  transition-all duration-200">
        <div class="flex items-start justify-between mb-5">
          <span class="text-3xl"><?= $icon ?></span>
          <span class="text-4xl font-black text-gray-100 dark:text-slate-700 leading-none">
            <?= $num ?>
          </span>
        </div>
        <h3 class="font-bold text-lg text-slate-900 dark:text-white mb-2"><?= $title ?></h3>
        <p class="text-slate-500 dark:text-slate-400 text-sm leading-relaxed"><?= $desc ?></p>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ── FEATURES ─────────────────────────────────────────────────────── -->
<section class="py-24 px-5 bg-gray-50 dark:bg-slate-950">
  <div class="max-w-5xl mx-auto">
    <div class="text-center mb-16">
      <p class="text-xs font-bold uppercase tracking-[.2em] text-indigo-600 dark:text-indigo-400 mb-3">
        Funkcie
      </p>
      <h2 class="text-4xl font-extrabold tracking-tight text-slate-900 dark:text-white">
        Čo dostanete?
      </h2>
    </div>

    <div class="grid md:grid-cols-2 gap-4">
      <?php
      $features = [
        ['🍽️', 'Plnohodnotné digitálne menu',    'Kategórie, jedlá, gramáže, alergény, fotky. Všetko prehľadne na mobile.'],
        ['🎨', 'Vlastné farby a logo',            'Prispôsobte vzhľad podniku — 8 gastro tém alebo vlastná farba a logo.'],
        ['📱', 'QR kódy vo vysokej kvalite',      'Stiahnite si PNG kód priamo z dashboardu a vytlačte ho kdekoľvek.'],
        ['⭐', 'Google & Instagram linky',        'Zákazník jedným kliknutím napíše recenziu alebo navštívi váš Instagram.'],
        ['🌙', 'Plný Dark Mode',                  'Aplikácia sa prispôsobí preferencii zákazníka — svetlý aj tmavý režim.'],
        ['🔒', 'Bezpečnosť na prvom mieste',     'CSRF ochrana, bcrypt heslá, SQLite WAL — vaše dáta sú v bezpečí.'],
      ];
      foreach ($features as [$icon, $title, $desc]):
      ?>
      <div class="group bg-white dark:bg-slate-900 rounded-[2rem]
                  border border-gray-100 dark:border-slate-800
                  p-6 shadow-sm hover:shadow-md hover:-translate-y-0.5
                  transition-all duration-200 flex gap-5 items-start">
        <div class="w-12 h-12 rounded-2xl bg-gray-50 dark:bg-slate-800
                    flex items-center justify-center text-2xl flex-shrink-0">
          <?= $icon ?>
        </div>
        <div>
          <h3 class="font-bold text-slate-900 dark:text-white mb-1.5"><?= $title ?></h3>
          <p class="text-slate-500 dark:text-slate-400 text-sm leading-relaxed"><?= $desc ?></p>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ── CTA SECTION ──────────────────────────────────────────────────── -->
<section class="py-24 px-5 bg-white dark:bg-slate-900">
  <div class="max-w-2xl mx-auto text-center">
    <div class="bg-indigo-600 rounded-[2rem] px-10 py-14 shadow-xl shadow-indigo-500/20">
      <h2 class="text-3xl font-extrabold text-white mb-3 tracking-tight">
        Pripravení začať?
      </h2>
      <p class="text-indigo-200 mb-8 leading-relaxed">
        Prvá prevádzka je vždy zadarmo.<br>Žiadna kreditná karta, žiadne záväzky.
      </p>
      <a href="<?= url('register') ?>"
         class="inline-block px-8 py-4 rounded-2xl bg-white hover:bg-indigo-50
                text-indigo-600 font-bold text-base
                transition-all duration-200 active:scale-95 shadow-lg">
        Vytvoriť účet zadarmo
      </a>
    </div>
  </div>
</section>

<!-- ── FOOTER ────────────────────────────────────────────────────────── -->
<footer class="bg-slate-950 text-slate-500 px-5 py-10">
  <div class="max-w-5xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-4">
    <span class="font-extrabold text-slate-300">
      GastroLink<span class="text-emerald-500">QR</span>
    </span>
    <p class="text-sm text-center">
      &copy; <?= date('Y') ?> GastroLink QR &mdash; Digitálny jedálny lístok pre gastronómiu
    </p>
    <a href="<?= url('login') ?>" class="text-sm hover:text-slate-300 transition-colors">
      Prihlásiť sa
    </a>
  </div>
</footer>

<!-- Dark mode script -->
<script>
const SVG_SUN = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/></svg>`;
const SVG_MOON = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>`;

function toggleDark() {
  const on = document.documentElement.classList.toggle('dark');
  localStorage.setItem('gl-dark', on ? '1' : '0');
  document.getElementById('dark-icon').innerHTML = on ? SVG_MOON : SVG_SUN;
}
(function(){
  const on = document.documentElement.classList.contains('dark');
  document.getElementById('dark-icon').innerHTML = on ? SVG_MOON : SVG_SUN;
})();
</script>
</body>
</html>
