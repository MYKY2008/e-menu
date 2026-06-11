<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

$phpIni = php_ini_loaded_file() ?: 'Nepodarilo sa zistiť';
$phpVer = PHP_VERSION;
$exts   = get_loaded_extensions();
sort($exts, SORT_STRING | SORT_FLAG_CASE);

$criticalExts = [
    'openssl'    => 'OpenSSL — maily, Stripe',
    'curl'       => 'cURL — externé API',
    'gd'         => 'GD — spracovanie obrázkov',
    'pdo_sqlite' => 'PDO SQLite — databáza',
    'mbstring'   => 'Multibyte String — UTF-8',
    'json'       => 'JSON',
    'fileinfo'   => 'FileInfo — MIME detekcia',
];

$title  = 'Diagnostika — GastroLink QR';
$robots = 'noindex, nofollow';
require __DIR__ . '/views/partials/header.php';
?>
<body class="bg-gray-50 dark:bg-slate-950 min-h-screen text-slate-900 dark:text-slate-100 transition-colors duration-200">

<!-- NAVBAR -->
<nav class="bg-white/80 dark:bg-slate-950/80 backdrop-blur-lg sticky top-0 z-30
            border-b border-gray-100 dark:border-slate-800 px-5 py-3 flex items-center justify-between">
  <div class="flex items-center gap-3">
    <a href="<?= url() ?>" class="font-extrabold text-sm tracking-tight">
      <span class="text-indigo-600">GastroLink</span><span class="text-emerald-500">QR</span>
    </a>
    <span class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase tracking-widest
                 bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400">Admin</span>
  </div>
  <div class="flex items-center gap-3">
    <a href="<?= url('views/admin.php') ?>"
       class="text-xs font-semibold text-slate-500 dark:text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition">
      ← Admin
    </a>
    <button id="dark-toggle" onclick="toggleDark()" aria-label="Prepnúť tmavý režim"
            class="w-8 h-8 flex items-center justify-center rounded-xl text-slate-500 dark:text-slate-400
                   hover:bg-gray-100 dark:hover:bg-slate-800 transition">
      <span id="dark-icon" class="w-4 h-4 block"></span>
    </button>
  </div>
</nav>

<main class="max-w-3xl mx-auto px-4 py-8 space-y-5">

  <h1 class="text-2xl font-black text-slate-900 dark:text-white">Diagnostika systému</h1>

  <!-- PHP environment -->
  <div class="bg-white dark:bg-slate-900 rounded-[2rem] shadow-sm border border-gray-100 dark:border-slate-800 p-6">
    <p class="text-[10px] font-black uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-4">Prostredie PHP</p>
    <div class="space-y-3">
      <div class="flex items-center gap-3">
        <span class="text-xs font-bold text-slate-500 dark:text-slate-400 w-28 flex-shrink-0">PHP verzia</span>
        <code class="text-sm font-mono font-bold text-indigo-600 dark:text-indigo-400"><?= e($phpVer) ?></code>
      </div>
      <div class="flex items-start gap-3">
        <span class="text-xs font-bold text-slate-500 dark:text-slate-400 w-28 flex-shrink-0">php.ini</span>
        <code class="text-xs font-mono text-slate-700 dark:text-slate-300 break-all select-all"><?= e($phpIni) ?></code>
      </div>
    </div>
  </div>

  <!-- Critical extensions -->
  <div class="bg-white dark:bg-slate-900 rounded-[2rem] shadow-sm border border-gray-100 dark:border-slate-800 p-6">
    <p class="text-[10px] font-black uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-4">Kritické rozšírenia</p>
    <div class="grid sm:grid-cols-2 gap-3">
      <?php foreach ($criticalExts as $extKey => $extDesc):
        $loaded = extension_loaded($extKey);
      ?>
      <div class="flex items-center gap-3 px-4 py-3 rounded-2xl
                  <?= $loaded ? 'bg-emerald-50 dark:bg-emerald-900/20' : 'bg-red-50 dark:bg-red-900/20' ?>">
        <svg class="w-4 h-4 flex-shrink-0 <?= $loaded ? 'text-emerald-500' : 'text-red-500' ?>"
             fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
          <?php if ($loaded): ?>
            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
          <?php else: ?>
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
          <?php endif; ?>
        </svg>
        <div>
          <p class="text-xs font-bold font-mono <?= $loaded ? 'text-emerald-700 dark:text-emerald-300' : 'text-red-700 dark:text-red-300' ?>">
            <?= e($extKey) ?>
          </p>
          <p class="text-[11px] text-slate-500 dark:text-slate-400"><?= e($extDesc) ?></p>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php $hasMissing = array_filter(array_keys($criticalExts), fn($k) => !extension_loaded($k)); ?>
    <?php if ($hasMissing): ?>
    <div class="mt-4 p-4 rounded-2xl bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800">
      <p class="text-xs font-bold text-amber-700 dark:text-amber-300 mb-1">Ako povoliť chýbajúce rozšírenia</p>
      <p class="text-xs text-amber-700 dark:text-amber-400">
        Otvorte súbor <code class="font-mono"><?= e($phpIni) ?></code>, nájdite riadok
        <code class="font-mono">;extension=nazov</code> a odstráňte bodkočiarku na začiatku.
        Potom reštartujte váš server (Apache / Nginx / PHP-FPM).
      </p>
    </div>
    <?php endif; ?>
  </div>

  <!-- All loaded extensions -->
  <div class="bg-white dark:bg-slate-900 rounded-[2rem] shadow-sm border border-gray-100 dark:border-slate-800 p-6">
    <p class="text-[10px] font-black uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-4">
      Všetky načítané rozšírenia
      <span class="ml-1 text-slate-300 dark:text-slate-600">(<?= count($exts) ?>)</span>
    </p>
    <div class="flex flex-wrap gap-1.5">
      <?php foreach ($exts as $ext): ?>
      <span class="px-2.5 py-1 rounded-xl text-xs font-mono
                   <?= isset($criticalExts[$ext])
                       ? 'bg-indigo-100 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 font-bold'
                       : 'bg-gray-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400' ?>">
        <?= e($ext) ?>
      </span>
      <?php endforeach; ?>
    </div>
  </div>

</main>
<?php require __DIR__ . '/views/partials/footer.php'; ?>
