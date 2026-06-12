<?php
$title = 'Registrácia — GastroLink QR';
$isSuccess = isset($_GET['success']) && $_GET['success'] === '1';
if ($isSuccess) {
    $registeredEmail = (string)($_SESSION['registered_email'] ?? '');
    unset($_SESSION['registered_email']);
}
require __DIR__ . '/partials/header.php';
?>
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
    <p class="text-slate-500 dark:text-slate-400 text-sm">
      <?= $isSuccess ? 'Skoro ste tam!' : 'Vytvorte si bezplatný účet' ?>
    </p>
  </div>

<?php if ($isSuccess): ?>

  <!-- Success card -->
  <div class="bg-white dark:bg-slate-900 rounded-[2rem] shadow-sm border border-gray-100 dark:border-slate-800 p-8 text-center">

    <div class="w-16 h-16 mx-auto mb-5 bg-emerald-50 dark:bg-emerald-900/30 rounded-2xl flex items-center justify-center">
      <svg class="w-8 h-8 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
        <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"/>
      </svg>
    </div>

    <h1 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white mb-2">
      Registrácia bola úspešná!
    </h1>

    <?php if ($registeredEmail !== ''): ?>
    <p class="text-slate-500 dark:text-slate-400 text-sm mb-1">
      Na adresu
    </p>
    <p class="font-semibold text-slate-800 dark:text-slate-200 text-sm mb-4 break-all">
      <?= e($registeredEmail) ?>
    </p>
    <?php else: ?>
    <p class="text-slate-500 dark:text-slate-400 text-sm mb-4">
      Na vašu e-mailovú adresu
    </p>
    <?php endif; ?>

    <p class="text-slate-500 dark:text-slate-400 text-sm mb-6">
      sme odoslali aktivačný odkaz.<br>
      Skontrolujte si e-mail <span class="font-medium text-slate-700 dark:text-slate-300">(aj priečinok SPAM)</span><br>
      a aktivujte svoj účet kliknutím na odkaz.
    </p>

    <a href="<?= url('login') ?>"
       class="block w-full py-3 px-6 bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800
              text-white font-bold rounded-2xl transition-colors duration-150 text-sm text-center">
      Späť na prihlásenie
    </a>

    <p class="text-xs text-slate-400 dark:text-slate-600 mt-5">
      Neprišiel vám e-mail? Skontrolujte priečinok SPAM<br>alebo sa skúste zaregistrovať znova.
    </p>

  </div>

<?php else: ?>

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
      Nový účet
    </h1>

    <form method="POST" action="<?= url('register') ?>">
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

      <div class="mb-4">
        <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1.5 uppercase tracking-wide"
               for="password">Heslo</label>
        <input id="password" name="password" type="password" required autocomplete="new-password" minlength="8"
          class="w-full bg-gray-100 dark:bg-slate-800 border-none rounded-xl
                 px-4 py-3 text-sm text-slate-900 dark:text-slate-100
                 placeholder-slate-400 dark:placeholder-slate-500
                 focus:outline-none focus:ring-2 focus:ring-indigo-500
                 transition-all duration-200"
          placeholder="Minimálne 8 znakov">
      </div>

      <div class="mb-6">
        <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1.5 uppercase tracking-wide"
               for="password2">Zopakujte heslo</label>
        <input id="password2" name="password2" type="password" required autocomplete="new-password" minlength="8"
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
        Vytvoriť účet
      </button>
    </form>

    <div class="mt-5 pt-5 border-t border-gray-100 dark:border-slate-800 text-center">
      <p class="text-sm text-slate-500 dark:text-slate-400">
        Už máte účet?
        <a href="<?= url('login') ?>" class="text-indigo-600 dark:text-indigo-400 font-semibold hover:underline">
          Prihláste sa
        </a>
      </p>
    </div>
  </div>

  <p class="text-center text-xs text-slate-400 dark:text-slate-600 mt-5">
    Registráciou súhlasíte s podmienkami používania.
  </p>

<?php endif; ?>

</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
