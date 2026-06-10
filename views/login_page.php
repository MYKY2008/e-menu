<?php $title = 'Prihlásenie — GastroLink QR'; require __DIR__ . '/partials/header.php'; ?>
<body class="min-h-screen bg-gray-50 dark:bg-slate-950 flex flex-col items-center justify-center p-5 transition-colors duration-200">

<!-- Dark mode toggle (top right) -->
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
    <p class="text-slate-500 dark:text-slate-400 text-sm">Prihlásenie do panela</p>
  </div>

  <?php $oldInput = $_SESSION['old_input'] ?? []; unset($_SESSION['old_input']); ?>

  <!-- Flash message -->
  <?php $flash = getFlash(); if ($flash): ?>
  <div class="mb-4 px-4 py-3 rounded-2xl text-sm font-medium
    <?= $flash['type'] === 'success'
        ? 'bg-emerald-50 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800'
        : 'bg-red-50 dark:bg-red-900/30 text-red-700 dark:text-red-400 border border-red-200 dark:border-red-800' ?>">
    <?= htmlspecialchars($flash['msg']) ?>
  </div>
  <?php endif; ?>

  <!-- Form card -->
  <div class="bg-white dark:bg-slate-900 rounded-[2rem] shadow-sm border border-gray-100 dark:border-slate-800 p-8">
    <h1 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white mb-6">
      Vitajte späť
    </h1>

    <form method="POST" action="<?= url('login') ?>">
      <input type="hidden" name="csrf" value="<?= csrfToken() ?>">

      <div class="mb-4">
        <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1.5 uppercase tracking-wide"
               for="username">E-mail</label>
        <input id="username" name="username" type="email" required autocomplete="email"
          class="w-full bg-gray-100 dark:bg-slate-800 border-none rounded-xl
                 px-4 py-3 text-sm text-slate-900 dark:text-slate-100
                 placeholder-slate-400 dark:placeholder-slate-500
                 focus:outline-none focus:ring-2 focus:ring-indigo-500
                 transition-all duration-200"
          placeholder="vas@email.sk"
          value="<?= e($oldInput['username'] ?? '') ?>">
      </div>

      <div class="mb-6">
        <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1.5 uppercase tracking-wide"
               for="password">Heslo</label>
        <input id="password" name="password" type="password" required autocomplete="current-password"
          class="w-full bg-gray-100 dark:bg-slate-800 border-none rounded-xl
                 px-4 py-3 text-sm text-slate-900 dark:text-slate-100
                 placeholder-slate-400 dark:placeholder-slate-500
                 focus:outline-none focus:ring-2 focus:ring-indigo-500
                 transition-all duration-200"
          placeholder="••••••••">
      </div>

      <button type="submit"
        class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-semibold
               py-3 rounded-2xl transition-all duration-200 active:scale-95
               shadow-lg shadow-indigo-500/20 text-sm">
        Prihlásiť sa
      </button>

      <div class="mt-3 text-center">
        <a href="<?= url('forgot-password') ?>" class="text-xs text-slate-400 dark:text-slate-500 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">
          Zabudli ste heslo?
        </a>
      </div>
    </form>

    <div class="mt-1 text-center">
      <div class="mt-5 pt-5 border-t border-gray-100 dark:border-slate-800">
        <p class="text-sm text-slate-500 dark:text-slate-400">
          Nemáte účet?
          <a href="<?= url('register') ?>" class="text-indigo-600 dark:text-indigo-400 font-semibold hover:underline">
            Zaregistrujte sa
          </a>
        </p>
      </div>
    </div>
  </div>

</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
