<!DOCTYPE html>
<html lang="sk" class="">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Zabudnuté heslo — GastroLink QR</title>

<!-- Anti-flash dark mode -->
<script>(function(){if(localStorage.getItem('gl-dark')==='1')document.documentElement.classList.add('dark')})();</script>

<!-- Inter font -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<link rel="stylesheet" href="<?= asset('assets/css/style.css') ?>">
</head>
<body class="min-h-screen bg-gray-50 dark:bg-slate-950 flex flex-col items-center justify-center p-5 transition-colors duration-200">

<!-- Dark mode toggle -->
<button onclick="toggleDark()" aria-label="Prepnúť tmavý režim"
        class="fixed top-4 right-4 w-10 h-10 rounded-2xl bg-white dark:bg-slate-900
               border border-gray-100 dark:border-slate-800 shadow-sm
               flex items-center justify-center
               text-slate-500 dark:text-slate-400
               hover:bg-gray-50 dark:hover:bg-slate-800
               transition-all duration-200">
  <span id="dark-icon" class="w-4 h-4 block"></span>
</button>

<div class="w-full max-w-sm">

  <!-- Logo -->
  <div class="text-center mb-8">
    <a href="<?= url() ?>" class="inline-block font-extrabold text-2xl tracking-tight mb-1">
      <span class="text-indigo-600">GastroLink</span><span class="text-emerald-500">QR</span>
    </a>
    <p class="text-slate-500 dark:text-slate-400 text-sm">Obnova hesla</p>
  </div>

  <!-- Flash message -->
  <?php $flash = getFlash(); if ($flash): ?>
  <div class="mb-4 px-4 py-3 rounded-2xl text-sm font-medium
    <?= $flash['type'] === 'success'
        ? 'bg-emerald-50 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800'
        : 'bg-red-50 dark:bg-red-900/30 text-red-700 dark:text-red-400 border border-red-200 dark:border-red-800' ?>">
    <?= e($flash['msg']) ?>
  </div>
  <?php endif; ?>

  <!-- Form card -->
  <div class="bg-white dark:bg-slate-900 rounded-[2rem] shadow-sm border border-gray-100 dark:border-slate-800 p-8">
    <h1 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white mb-2">
      Zabudnuté heslo
    </h1>
    <p class="text-sm text-slate-500 dark:text-slate-400 mb-6">
      Zadajte váš e-mail a pošleme vám odkaz na vytvorenie nového hesla.
    </p>

    <form method="POST" action="<?= url('forgot-password') ?>">
      <input type="hidden" name="csrf" value="<?= csrfToken() ?>">

      <div class="mb-6">
        <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1.5 uppercase tracking-wide"
               for="email">E-mail</label>
        <input id="email" name="email" type="email" required autocomplete="email"
          class="w-full bg-gray-100 dark:bg-slate-800 border-none rounded-xl
                 px-4 py-3 text-sm text-slate-900 dark:text-slate-100
                 placeholder-slate-400 dark:placeholder-slate-500
                 focus:outline-none focus:ring-2 focus:ring-indigo-500
                 transition-all duration-200"
          placeholder="vas@email.sk">
      </div>

      <button type="submit"
        class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-semibold
               py-3 rounded-2xl transition-all duration-200 active:scale-95
               shadow-lg shadow-indigo-500/20 text-sm">
        Odoslať odkaz na reset
      </button>
    </form>

    <div class="mt-5 pt-5 border-t border-gray-100 dark:border-slate-800 text-center">
      <a href="<?= url('login') ?>" class="text-sm text-indigo-600 dark:text-indigo-400 font-semibold hover:underline">
        ← Späť na prihlásenie
      </a>
    </div>
  </div>

</div>

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
