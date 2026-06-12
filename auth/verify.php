<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';

$token = trim($_GET['token'] ?? '');

if ($token === '') {
    flash('Neplatný aktivačný odkaz.', 'error');
    header('Location: ' . url('login'));
    exit;
}

$db = getDB();

// Lookup without is_verified filter — lets us distinguish "already verified" from "bad token"
$st = $db->prepare(
    "SELECT id, username, created_at, is_verified FROM users WHERE verify_token = ?"
);
$st->execute([$token]);
$user = $st->fetch();

if (!$user) {
    // Token cleared (already used) or never existed
    flash('Aktivačný odkaz je neplatný alebo bol už použitý. Môžete sa prihlásiť.', 'error');
    header('Location: ' . url('login'));
    exit;
}

if ((int)$user['is_verified'] === 1) {
    flash('Váš účet je už aktívny, môžete sa prihlásiť.', 'success');
    header('Location: ' . url('login'));
    exit;
}

// Token expiry check — gmdate() matches SQLite's UTC strftime output
$cutoff = gmdate('Y-m-d\TH:i:s\Z', time() - 3600);
if (($user['created_at'] ?? '') < $cutoff) {
    $db->prepare("DELETE FROM users WHERE id = ?")->execute([(int)$user['id']]);
    flash('Aktivačný odkaz vypršal (platnosť 1 hodina). Zaregistrujte sa prosím znova.', 'error');
    header('Location: ' . url('register'));
    exit;
}

// ── POST: human confirmed → perform actual verification ──────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postToken = trim($_POST['token'] ?? '');
    if ($postToken !== $token) {
        flash('Bezpečnostná chyba. Skúste znova.', 'error');
        header('Location: ' . url('login'));
        exit;
    }

    $db->prepare("UPDATE users SET is_verified = 1, verify_token = NULL WHERE id = ?")
       ->execute([(int)$user['id']]);

    flash('Účet bol úspešne aktivovaný. Teraz sa môžete prihlásiť.', 'success');
    header('Location: ' . url('login'));
    exit;
}

// ── GET: show confirmation page ───────────────────────────────────
$title = 'Aktivácia účtu — GastroLink QR';
require __DIR__ . '/../views/partials/header.php';
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
    <p class="text-slate-500 dark:text-slate-400 text-sm">Aktivácia účtu</p>
  </div>

  <!-- Card -->
  <div class="bg-white dark:bg-slate-900 rounded-[2rem] shadow-sm border border-gray-100 dark:border-slate-800 p-8 text-center">

    <!-- Icon -->
    <div class="w-16 h-16 mx-auto mb-5 bg-indigo-50 dark:bg-indigo-900/30 rounded-2xl flex items-center justify-center">
      <svg class="w-8 h-8 text-indigo-600 dark:text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
        <path stroke-linecap="round" stroke-linejoin="round"
              d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
      </svg>
    </div>

    <h1 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white mb-2">
      Posledný krok k aktivácii
    </h1>
    <p class="text-slate-500 dark:text-slate-400 text-sm mb-6">
      Kliknite na tlačidlo nižšie pre aktiváciu účtu<br>
      <span class="font-semibold text-slate-700 dark:text-slate-300"><?= e($user['username']) ?></span>.
    </p>

    <form method="POST" action="<?= url('verify') ?>?token=<?= urlencode($token) ?>">
      <input type="hidden" name="token" value="<?= e($token) ?>">
      <button type="submit"
              class="w-full py-3 px-6 bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800
                     text-white font-bold rounded-2xl transition-colors duration-150 text-sm">
        Aktivovať môj účet →
      </button>
    </form>

    <p class="text-xs text-slate-400 dark:text-slate-600 mt-5 leading-relaxed">
      Tento krok overuje, že ide o skutočného človeka,<br>nie automatický e-mailový skener.
    </p>

  </div>

  <p class="text-center mt-6 text-xs text-slate-400 dark:text-slate-600">
    Vrátite sa?
    <a href="<?= url('login') ?>" class="text-indigo-600 hover:underline font-medium">Prihlásiť sa</a>
  </p>

</div>

<?php require __DIR__ . '/../views/partials/footer.php'; ?>
