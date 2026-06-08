<!DOCTYPE html>
<html lang="sk">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Registrácia — GastroLink QR</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gradient-to-br from-indigo-50 to-emerald-50 flex items-center justify-center p-4">

<div class="w-full max-w-sm">

  <!-- Logo -->
  <div class="text-center mb-8">
    <a href="<?= url() ?>" class="inline-block font-extrabold text-2xl text-indigo-700">
      GastroLink <span class="text-emerald-600">QR</span>
    </a>
    <p class="text-gray-500 text-sm mt-1">Vytvorte si bezplatný účet</p>
  </div>

  <!-- Flash message -->
  <?php $flash = getFlash(); if ($flash): ?>
  <div class="mb-4 px-4 py-3 rounded-lg text-sm font-medium
    <?= $flash['type'] === 'success' ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800' ?>">
    <?= htmlspecialchars($flash['msg']) ?>
  </div>
  <?php endif; ?>

  <!-- Form -->
  <div class="bg-white rounded-2xl shadow-md p-8">
    <form method="POST" action="<?= url('register') ?>">
      <input type="hidden" name="csrf" value="<?= csrfToken() ?>">

      <div class="mb-5">
        <label class="block text-sm font-medium text-gray-700 mb-1" for="username">E-mail</label>
        <input id="username" name="username" type="email" required autocomplete="email"
          class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400 transition"
          placeholder="vas@email.sk">
      </div>

      <div class="mb-4">
        <label class="block text-sm font-medium text-gray-700 mb-1" for="password">Heslo</label>
        <input id="password" name="password" type="password" required autocomplete="new-password" minlength="8"
          class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400 transition"
          placeholder="Minimálne 8 znakov">
      </div>

      <div class="mb-6">
        <label class="block text-sm font-medium text-gray-700 mb-1" for="password2">Zopakujte heslo</label>
        <input id="password2" name="password2" type="password" required autocomplete="new-password" minlength="8"
          class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400 transition"
          placeholder="••••••••">
      </div>

      <button type="submit"
        class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-2.5 rounded-lg transition text-sm">
        Vytvoriť účet
      </button>
    </form>

    <p class="text-center text-sm text-gray-500 mt-5">
      Už máte účet?
      <a href="<?= url('login') ?>" class="text-indigo-600 hover:underline font-medium">Prihláste sa</a>
    </p>
  </div>

</div>
</body>
</html>
