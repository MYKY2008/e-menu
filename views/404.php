<?php $title = '404 — Stránka nenájdená · GastroLink QR'; require __DIR__ . '/partials/header.php'; ?>
<body class="min-h-screen bg-gray-50 dark:bg-slate-950 flex flex-col items-center justify-center p-5 transition-colors duration-200">

<div class="w-full max-w-sm text-center">

  <a href="<?= url() ?>" class="inline-block font-extrabold text-2xl tracking-tight mb-10">
    <span class="text-indigo-600">GastroLink</span><span class="text-emerald-500">QR</span>
  </a>

  <div class="bg-white dark:bg-slate-900 rounded-[2rem] shadow-sm border border-gray-100 dark:border-slate-800 p-10">
    <p class="text-7xl font-black text-indigo-100 dark:text-slate-800 leading-none mb-4">404</p>
    <h1 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white mb-2">
      Stránka nenájdená
    </h1>
    <p class="text-sm text-slate-500 dark:text-slate-400 leading-relaxed mb-8">
      Hľadaná stránka neexistuje alebo bola presunutá.
    </p>
    <a href="<?= url() ?>"
       class="inline-block w-full bg-indigo-600 hover:bg-indigo-700 text-white font-semibold
              py-3 rounded-2xl transition-all duration-200 active:scale-95
              shadow-lg shadow-indigo-500/20 text-sm">
      ← Späť na úvod
    </a>
  </div>

</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
