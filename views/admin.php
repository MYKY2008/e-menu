<?php
$title  = 'Admin — GastroLink QR';
$robots = 'noindex, nofollow';
require __DIR__ . '/partials/header.php';
?>
<style>
.adm-select {
  -webkit-appearance: none; -moz-appearance: none; appearance: none;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2.5'%3E%3Cpath d='M19 9l-7 7-7-7'/%3E%3C/svg%3E");
  background-repeat: no-repeat; background-position: right 0.75rem center; background-size: 0.9rem;
  padding-right: 2.5rem;
}
.dark .adm-select {
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='2.5'%3E%3Cpath d='M19 9l-7 7-7-7'/%3E%3C/svg%3E");
}
</style>
<body class="bg-gray-50 dark:bg-slate-950 min-h-screen text-slate-900 dark:text-slate-100 transition-colors duration-200">

<?php
requireAdmin();
$db   = getDB();
$csrf = csrfToken();

/* ── Stats ─────────────────────────────────────────────────────── */
$cut30          = date('Y-m-d\TH:i:s\Z', strtotime('-30 days'));
$statTotalUsers = (int)$db->query("SELECT COUNT(*) FROM users")->fetchColumn();
$stAct = $db->prepare("SELECT COUNT(*) FROM users WHERE last_login_at IS NOT NULL AND last_login_at >= ?");
$stAct->execute([$cut30]); $statActiveUsers = (int)$stAct->fetchColumn();
try {
    $stMrr = $db->prepare("SELECT COALESCE(SUM(amount),0) FROM orders WHERE status='paid' AND created_at >= ?");
    $stMrr->execute([$cut30]); $statMrr = (float)$stMrr->fetchColumn();
} catch (Throwable) { $statMrr = 0.0; }
$statVenues = (int)$db->query("SELECT COUNT(*) FROM venues")->fetchColumn();
try {
    $stSc = $db->prepare("SELECT COUNT(*) FROM scans WHERE created_at >= ?");
    $stSc->execute([$cut30]); $statScans = (int)$stSc->fetchColumn();
} catch (Throwable) { $statScans = 0; }

/* ── Users ─────────────────────────────────────────────────────── */
$allUsers = $db->query("
    SELECT u.id, u.username, u.plan_name, u.plan_ends_at, u.role,
           u.max_venues, u.max_categories, u.max_items_per_cat,
           u.created_at, u.last_login_at, u.is_verified,
           COUNT(v.slug) AS venue_count
    FROM users u LEFT JOIN venues v ON v.user_id = u.id
    GROUP BY u.id ORDER BY u.id DESC
")->fetchAll();

/* ── Orders ────────────────────────────────────────────────────── */
try {
    $allOrders = $db->query("
        SELECT o.id, o.plan_name, o.amount, o.currency, o.status, o.invoice_id, o.created_at, u.username
        FROM orders o JOIN users u ON u.id = o.user_id ORDER BY o.created_at DESC
    ")->fetchAll();
} catch (Throwable) { $allOrders = []; }

/* ── Recent venues ─────────────────────────────────────────────── */
$recentVenues = $db->query("
    SELECT v.slug, v.name, v.color, v.created_at, u.username AS owner
    FROM venues v JOIN users u ON u.id = v.user_id
    ORDER BY v.created_at DESC LIMIT 20
")->fetchAll();

/* ── Plan distribution ─────────────────────────────────────────── */
$planCounts = ['free' => 0, 'pro' => 0, 'ultra' => 0, 'custom' => 0];
foreach ($allUsers as $u) {
    $p = $u['plan_name'] ?? 'free';
    if (!isset($planCounts[$p])) $planCounts[$p] = 0;
    $planCounts[$p]++;
}

/* ── Logs ──────────────────────────────────────────────────────── */
$logFile  = BASE_DIR . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'error.log';
$logLines = [];
if (is_file($logFile)) {
    $raw = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    $logLines = array_slice($raw, -50);
}

/* ── System health ──────────────────────────────────────────────── */
$sysHealth = [
    ['label' => 'OpenSSL',    'ext' => 'openssl',    'note' => 'Potrebné pre maily a Stripe'],
    ['label' => 'cURL',       'ext' => 'curl',        'note' => 'Potrebné pre API volania'],
    ['label' => 'GD',         'ext' => 'gd',          'note' => 'Potrebné pre prácu s obrázkami'],
    ['label' => 'PDO SQLite', 'ext' => 'pdo_sqlite',  'note' => 'Databáza'],
];
foreach ($sysHealth as &$_sh) { $_sh['ok'] = extension_loaded($_sh['ext']); }
unset($_sh);
$sysAllOk = !in_array(false, array_column($sysHealth, 'ok'), true);
?>

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
    <a href="<?= url('dashboard') ?>"
       class="flex items-center gap-1.5 text-xs font-semibold px-3 py-1.5 rounded-xl
              text-slate-600 dark:text-slate-400 hover:bg-gray-100 dark:hover:bg-slate-800 transition">
      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
      </svg>
      Dashboard
    </a>
    <button id="dark-toggle" onclick="toggleDark()" aria-label="Prepnúť tmavý režim"
            class="w-8 h-8 rounded-xl bg-gray-100 dark:bg-slate-800 flex items-center justify-center
                   text-slate-500 dark:text-slate-400 hover:bg-gray-200 dark:hover:bg-slate-700 transition-all duration-200">
      <span id="dark-icon" class="w-3.5 h-3.5 block pointer-events-none"></span>
    </button>
  </div>
</nav>

<!-- Toast -->
<div id="toast-container" class="fixed top-16 right-4 z-50 flex flex-col gap-2 pointer-events-none"></div>

<!-- MAIN -->
<div class="max-w-7xl mx-auto px-4 py-8">
  <div class="flex flex-col lg:flex-row gap-6 items-start">

    <!-- SIDEBAR -->
    <aside class="w-full lg:w-60 flex-shrink-0">
      <div class="bg-white dark:bg-slate-900 rounded-[2rem] shadow-sm border border-gray-100 dark:border-slate-800 p-3">
        <div class="px-3 pt-2 pb-4 border-b border-gray-100 dark:border-slate-800 mb-2">
          <div class="w-10 h-10 rounded-2xl bg-red-100 dark:bg-red-900/30 flex items-center justify-center mb-2">
            <svg class="w-5 h-5 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
            </svg>
          </div>
          <p class="font-extrabold text-slate-900 dark:text-white text-sm">Admin Panel</p>
          <p class="text-[11px] text-slate-400 truncate mt-0.5"><?= e((string)($_SESSION['username'] ?? '')) ?></p>
        </div>
        <nav class="space-y-0.5 mb-3">
          <?php
          $admTabs = [
            ['overview',  'Prehľad',     '<path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>'],
            ['users',     'Užívatelia',  '<path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>'],
            ['payments',  'Platby',      '<path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>'],
            ['logs',      'Logy',        '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>'],
          ];
          foreach ($admTabs as $i => [$tid, $tlabel, $ticon]):
          ?>
          <button onclick="admSwitchTab('<?= $tid ?>')"
                  id="adm-tab-btn-<?= $tid ?>"
                  class="w-full text-left px-3 py-2.5 rounded-xl transition-all duration-200 flex items-center gap-2.5
                         <?= $i === 0 ? 'bg-indigo-50 dark:bg-indigo-900/30' : 'hover:bg-gray-50 dark:hover:bg-slate-800' ?>">
            <svg id="adm-tab-icon-<?= $tid ?>"
                 class="w-4 h-4 flex-shrink-0 <?= $i === 0 ? 'text-indigo-600 dark:text-indigo-400' : 'text-slate-400 dark:text-slate-500' ?>"
                 fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><?= $ticon ?></svg>
            <span id="adm-tab-label-<?= $tid ?>"
                  class="font-bold text-sm <?= $i === 0 ? 'text-indigo-700 dark:text-indigo-300' : 'text-slate-700 dark:text-slate-300' ?>">
              <?= $tlabel ?>
            </span>
          </button>
          <?php endforeach; ?>
        </nav>
        <div class="pt-2 border-t border-gray-100 dark:border-slate-800">
          <button onclick="doCleanup()"
                  class="w-full text-left px-3 py-2 rounded-xl text-xs font-semibold transition flex items-center gap-2
                         text-amber-600 dark:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-900/20">
            <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
            </svg>
            Cleanup neaktívnych
          </button>
        </div>
      </div>
    </aside>

    <!-- CONTENT -->
    <div class="flex-1 min-w-0 space-y-5">

      <!-- ─── TAB: PREHĽAD ──────────────────────────────────────── -->
      <div id="adm-tab-overview">

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-5">
          <?php
          $statCards = [
            [
              'val'   => $statTotalUsers,
              'label' => 'Užívatelia',
              'sub'   => $statActiveUsers . ' aktívnych / 30 dní',
              'color' => 'indigo',
              'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>',
            ],
            [
              'val'   => number_format($statMrr, 2, ',', ' ') . ' €',
              'label' => 'MRR',
              'sub'   => 'Posledných 30 dní',
              'color' => 'emerald',
              'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>',
            ],
            [
              'val'   => $statVenues,
              'label' => 'Prevádzky',
              'sub'   => 'Celkovo v systéme',
              'color' => 'violet',
              'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>',
            ],
            [
              'val'   => number_format($statScans),
              'label' => 'Skenovania',
              'sub'   => 'Posledných 30 dní',
              'color' => 'amber',
              'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/>',
            ],
          ];
          foreach ($statCards as $card):
            $c = $card['color'];
          ?>
          <div class="bg-white dark:bg-slate-900 rounded-[2rem] shadow-sm border border-gray-100 dark:border-slate-800 p-5">
            <div class="w-9 h-9 rounded-2xl bg-<?= $c ?>-100 dark:bg-<?= $c ?>-900/30 flex items-center justify-center mb-3">
              <svg class="w-5 h-5 text-<?= $c ?>-600 dark:text-<?= $c ?>-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <?= $card['icon'] ?>
              </svg>
            </div>
            <p class="text-2xl font-black text-slate-900 dark:text-white leading-none"><?= $card['val'] ?></p>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1"><?= $card['label'] ?></p>
            <p class="text-[10px] font-bold text-<?= $c ?>-500 dark:text-<?= $c ?>-400 mt-0.5"><?= $card['sub'] ?></p>
          </div>
          <?php endforeach; ?>
        </div>

        <div class="grid lg:grid-cols-2 gap-5">
          <!-- Plan distribution -->
          <div class="bg-white dark:bg-slate-900 rounded-[2rem] shadow-sm border border-gray-100 dark:border-slate-800 p-6">
            <p class="text-[10px] font-black uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-4">Distribúcia plánov</p>
            <?php
            $planMeta = [
              'free'   => ['bg-slate-400',   'text-slate-600 dark:text-slate-400'],
              'pro'    => ['bg-indigo-500',   'text-indigo-600 dark:text-indigo-400'],
              'ultra'  => ['bg-emerald-500',  'text-emerald-600 dark:text-emerald-400'],
              'custom' => ['bg-violet-500',   'text-violet-600 dark:text-violet-400'],
            ];
            ?>
            <div class="space-y-3.5">
              <?php foreach ($planCounts as $plan => $cnt):
                $pct = $statTotalUsers > 0 ? round($cnt / $statTotalUsers * 100) : 0;
                [$bar, $txt] = $planMeta[$plan] ?? ['bg-gray-400', 'text-gray-600'];
              ?>
              <div>
                <div class="flex justify-between items-baseline mb-1">
                  <span class="text-xs font-bold <?= $txt ?>"><?= ucfirst($plan) ?></span>
                  <span class="text-xs text-slate-400"><?= $cnt ?> (<?= $pct ?>%)</span>
                </div>
                <div class="w-full bg-gray-100 dark:bg-slate-800 rounded-full h-1.5">
                  <div class="<?= $bar ?> h-1.5 rounded-full" style="width:<?= $pct ?>%"></div>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
          </div>

          <!-- DB backup -->
          <div class="bg-white dark:bg-slate-900 rounded-[2rem] shadow-sm border border-gray-100 dark:border-slate-800 p-6 flex flex-col justify-between">
            <div>
              <p class="text-[10px] font-black uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-3">Záloha databázy</p>
              <p class="text-sm text-slate-500 dark:text-slate-400">
                Stiahnuť aktuálny stav databázy ako
                <code class="text-xs bg-gray-100 dark:bg-slate-800 px-1.5 py-0.5 rounded font-mono">.db</code> súbor.
              </p>
            </div>
            <a href="<?= url('api/backup.php') ?>?csrf=<?= $csrf ?>"
               class="mt-5 inline-flex items-center justify-center gap-2 px-5 py-2.5
                      bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-sm
                      rounded-2xl transition active:scale-95 shadow-lg shadow-indigo-500/20">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
              </svg>
              Stiahnuť zálohu
            </a>
          </div>
        </div>

        <!-- Recent venues -->
        <?php if (!empty($recentVenues)): ?>
        <div class="mt-5 bg-white dark:bg-slate-900 rounded-[2rem] shadow-sm border border-gray-100 dark:border-slate-800 p-6">
          <p class="text-[10px] font-black uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-4">
            Najnovšie prevádzky
          </p>
          <div class="overflow-x-auto -mx-2 px-2">
            <table class="w-full text-xs min-w-[480px]">
              <thead>
                <tr class="border-b border-gray-100 dark:border-slate-800">
                  <th class="text-left font-black uppercase tracking-wider text-slate-400 pb-3 pr-4">Slug</th>
                  <th class="text-left font-black uppercase tracking-wider text-slate-400 pb-3 pr-4">Názov</th>
                  <th class="text-left font-black uppercase tracking-wider text-slate-400 pb-3 pr-4">Vlastník</th>
                  <th class="text-left font-black uppercase tracking-wider text-slate-400 pb-3 pr-4">Vytvorená</th>
                  <th class="text-right font-black uppercase tracking-wider text-slate-400 pb-3">Akcia</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-50 dark:divide-slate-800/60">
              <?php foreach ($recentVenues as $v):
                $vc = resolveColor($v['color']);
              ?>
              <tr class="hover:bg-gray-50 dark:hover:bg-slate-800/30 transition" id="venue-row-<?= e($v['slug']) ?>">
                <td class="py-3 pr-4">
                  <span class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full flex-shrink-0" style="background:<?= e($vc['hex']) ?>"></span>
                    <a href="<?= url('r/' . $v['slug']) ?>" target="_blank"
                       class="text-indigo-600 dark:text-indigo-400 hover:underline font-mono">/r/<?= e($v['slug']) ?></a>
                  </span>
                </td>
                <td class="py-3 pr-4 font-medium text-slate-800 dark:text-slate-200"><?= e($v['name']) ?></td>
                <td class="py-3 pr-4 text-slate-500 dark:text-slate-400"><?= e($v['owner']) ?></td>
                <td class="py-3 pr-4 text-slate-400"><?= substr($v['created_at'], 0, 10) ?></td>
                <td class="py-3 text-right">
                  <button onclick="adminDeleteVenue('<?= e($v['slug']) ?>','<?= e(addslashes($v['name'])) ?>')"
                          class="px-2.5 py-1 text-[11px] font-bold rounded-xl transition
                                 bg-red-100 dark:bg-red-900/30 hover:bg-red-200 dark:hover:bg-red-900/50
                                 text-red-700 dark:text-red-400">Zmazať</button>
                </td>
              </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
        <?php endif; ?>

        <!-- ─── Zdravie systému ────────────────────────────────── -->
        <div class="mt-5 bg-white dark:bg-slate-900 rounded-[2rem] shadow-sm border border-gray-100 dark:border-slate-800 p-6">
          <div class="flex items-center justify-between mb-4">
            <p class="text-[10px] font-black uppercase tracking-wider text-slate-400 dark:text-slate-500">
              Zdravie systému
              <?php if (!$sysAllOk): ?>
              <span class="ml-2 px-2 py-0.5 rounded-full bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400">Problém</span>
              <?php endif; ?>
            </p>
            <a href="<?= url('diagnose') ?>" target="_blank"
               class="flex items-center gap-1 text-xs font-bold text-indigo-600 dark:text-indigo-400 hover:underline">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
              </svg>
              Zobraziť diagnostiku
            </a>
          </div>
          <div class="grid sm:grid-cols-2 gap-3">
            <?php foreach ($sysHealth as $sh): ?>
            <div class="flex items-start gap-3 px-4 py-3 rounded-2xl
                        <?= $sh['ok'] ? 'bg-emerald-50 dark:bg-emerald-900/20' : 'bg-red-50 dark:bg-red-900/20' ?>">
              <svg class="w-5 h-5 flex-shrink-0 mt-0.5 <?= $sh['ok'] ? 'text-emerald-500' : 'text-red-500' ?>"
                   fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <?php if ($sh['ok']): ?>
                  <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                <?php else: ?>
                  <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                <?php endif; ?>
              </svg>
              <div>
                <p class="text-sm font-bold <?= $sh['ok'] ? 'text-emerald-700 dark:text-emerald-300' : 'text-red-700 dark:text-red-300' ?>">
                  <?= e($sh['label']) ?>
                </p>
                <p class="text-xs text-slate-500 dark:text-slate-400"><?= e($sh['note']) ?></p>
                <?php if (!$sh['ok']): ?>
                <p class="text-[11px] font-semibold text-red-600 dark:text-red-400 mt-1 leading-snug">
                  Povoľte toto rozšírenie v php.ini a reštartujte server.
                </p>
                <?php endif; ?>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>

      </div><!-- /tab-overview -->

      <!-- ─── TAB: UŽÍVATELIA ───────────────────────────────────── -->
      <div id="adm-tab-users" class="hidden">
        <div class="bg-white dark:bg-slate-900 rounded-[2rem] shadow-sm border border-gray-100 dark:border-slate-800 p-6">
          <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
            <p class="text-[10px] font-black uppercase tracking-wider text-slate-400 dark:text-slate-500">
              Všetci užívatelia (<?= $statTotalUsers ?>)
            </p>
            <div class="flex items-center gap-2">
              <input id="user-search" type="search" placeholder="Hľadať podľa e-mailu…"
                     class="bg-gray-100 dark:bg-slate-800 border-none rounded-xl px-4 py-2 text-sm
                            text-slate-900 dark:text-slate-100 placeholder-slate-400
                            focus:outline-none focus:ring-2 focus:ring-indigo-500 transition w-52">
              <button onclick="openCreateUser()"
                      class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold
                             rounded-xl transition active:scale-95 shadow-sm shadow-indigo-500/20 whitespace-nowrap">
                + Nový účet
              </button>
            </div>
          </div>
          <div class="overflow-x-auto -mx-6 px-6">
            <table class="w-full text-xs min-w-[600px]">
              <thead>
                <tr class="border-b border-gray-100 dark:border-slate-800">
                  <th class="text-left font-black uppercase tracking-wider text-slate-400 pb-3 pr-4">E-mail</th>
                  <th class="text-left font-black uppercase tracking-wider text-slate-400 pb-3 pr-4">Plán</th>
                  <th class="text-left font-black uppercase tracking-wider text-slate-400 pb-3 pr-4">Koniec</th>
                  <th class="text-left font-black uppercase tracking-wider text-slate-400 pb-3 pr-4">Prev.</th>
                  <th class="text-left font-black uppercase tracking-wider text-slate-400 pb-3 pr-4">Rola</th>
                  <th class="text-left font-black uppercase tracking-wider text-slate-400 pb-3">Posl. login</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-50 dark:divide-slate-800/60">
              <?php foreach ($allUsers as $u):
                $isSelf = ((int)$u['id'] === (int)$_SESSION['user_id']);
                $planBadge = match($u['plan_name'] ?? 'free') {
                  'pro'    => 'bg-indigo-100 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-400',
                  'ultra'  => 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400',
                  'custom' => 'bg-violet-100 dark:bg-violet-900/30 text-violet-700 dark:text-violet-400',
                  default  => 'bg-gray-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400',
                };
                $endsAt    = $u['plan_ends_at']  ? date('d.m.Y', strtotime((string)$u['plan_ends_at']))  : '—';
                $lastLogin = $u['last_login_at'] ? date('d.m.Y', strtotime((string)$u['last_login_at'])) : '—';
                $ud = htmlspecialchars(json_encode([
                  'id'               => (int)$u['id'],
                  'email'            => (string)$u['username'],
                  'plan_name'        => (string)$u['plan_name'],
                  'plan_ends_at'     => $u['plan_ends_at'] ? substr((string)$u['plan_ends_at'], 0, 10) : '',
                  'max_venues'       => (int)$u['max_venues'],
                  'max_categories'   => (int)$u['max_categories'],
                  'max_items_per_cat'=> (int)$u['max_items_per_cat'],
                  'role'             => (string)$u['role'],
                  'is_self'          => $isSelf,
                  'created_at'       => $u['created_at'] ? substr((string)$u['created_at'], 0, 10) : '',
                ]), ENT_QUOTES, 'UTF-8');
              ?>
              <tr id="user-row-<?= (int)$u['id'] ?>"
                  class="user-row hover:bg-indigo-50/40 dark:hover:bg-indigo-900/10 cursor-pointer transition
                         <?= $u['role'] === 'admin' ? 'border-l-2 border-red-400 dark:border-red-500' : '' ?>"
                  data-id="<?= (int)$u['id'] ?>"
                  data-email="<?= e(strtolower((string)$u['username'])) ?>"
                  data-user="<?= $ud ?>"
                  onclick="openEditModal(JSON.parse(this.dataset.user))">
                <td class="py-3 pr-4 font-medium text-slate-800 dark:text-slate-200">
                  <?= e((string)$u['username']) ?>
                  <?php if ($isSelf): ?><span class="text-[10px] text-indigo-400 ml-1">(vy)</span><?php endif; ?>
                  <?php if (!(int)($u['is_verified'] ?? 1)): ?>
                  <span class="ml-1 px-1.5 py-0.5 rounded text-[9px] font-black uppercase
                               bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-400">overenie</span>
                  <?php endif; ?>
                </td>
                <td class="py-3 pr-4">
                  <span class="adm-plan-badge inline-block px-2 py-0.5 rounded-full text-[10px] font-bold <?= $planBadge ?>">
                    <?= ucfirst((string)$u['plan_name']) ?>
                  </span>
                </td>
                <td class="adm-ends-at py-3 pr-4 text-slate-500 dark:text-slate-400"><?= $endsAt ?></td>
                <td class="py-3 pr-4 text-slate-500 dark:text-slate-400"><?= (int)$u['venue_count'] ?></td>
                <td class="adm-role-cell py-3 pr-4">
                  <?php if ($u['role'] === 'admin'): ?>
                  <span class="inline-block px-2 py-0.5 rounded-full text-[10px] font-bold
                               bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400">Admin</span>
                  <?php else: ?>
                  <span class="text-slate-400 dark:text-slate-500">User</span>
                  <?php endif; ?>
                </td>
                <td class="py-3 text-slate-400 dark:text-slate-500"><?= $lastLogin ?></td>
              </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div><!-- /tab-users -->

      <!-- ─── TAB: PLATBY ───────────────────────────────────────── -->
      <div id="adm-tab-payments" class="hidden">
        <div class="bg-white dark:bg-slate-900 rounded-[2rem] shadow-sm border border-gray-100 dark:border-slate-800 p-6">
          <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
            <p class="text-[10px] font-black uppercase tracking-wider text-slate-400 dark:text-slate-500">
              Všetky objednávky (<?= count($allOrders) ?>)
            </p>
            <div class="flex gap-2 flex-wrap">
              <?php foreach (['all' => 'Všetky', 'paid' => 'Zaplatené', 'pending' => 'Čaká', 'failed' => 'Zlyhalo'] as $s => $l): ?>
              <button id="ord-filter-<?= $s ?>" onclick="filterOrders('<?= $s ?>')"
                      class="px-3 py-1.5 rounded-xl text-xs font-bold transition
                             <?= $s === 'all' ? 'bg-indigo-600 text-white' : 'bg-gray-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 hover:bg-gray-200 dark:hover:bg-slate-700' ?>">
                <?= $l ?>
              </button>
              <?php endforeach; ?>
            </div>
          </div>
          <?php if (empty($allOrders)): ?>
          <p class="text-sm text-slate-400 text-center py-8">Zatiaľ žiadne objednávky.</p>
          <?php else: ?>
          <div class="overflow-x-auto -mx-6 px-6">
            <table class="w-full text-xs min-w-[560px]">
              <thead>
                <tr class="border-b border-gray-100 dark:border-slate-800">
                  <th class="text-left font-black uppercase tracking-wider text-slate-400 pb-3 pr-4">Dátum</th>
                  <th class="text-left font-black uppercase tracking-wider text-slate-400 pb-3 pr-4">Užívateľ</th>
                  <th class="text-left font-black uppercase tracking-wider text-slate-400 pb-3 pr-4">Plán</th>
                  <th class="text-left font-black uppercase tracking-wider text-slate-400 pb-3 pr-4">Suma</th>
                  <th class="text-left font-black uppercase tracking-wider text-slate-400 pb-3 pr-4">Stav</th>
                  <th class="text-left font-black uppercase tracking-wider text-slate-400 pb-3">SF Invoice</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-50 dark:divide-slate-800/60">
              <?php foreach ($allOrders as $o):
                $sMap = [
                  'paid'    => ['Zaplatené', 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400'],
                  'pending' => ['Čaká',      'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400'],
                  'failed'  => ['Zlyhalo',   'bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400'],
                ];
                [$sLabel, $sCls] = $sMap[$o['status']] ?? ['?', 'bg-gray-100 text-gray-600'];
                $oDate = $o['created_at'] ? date('d.m.Y H:i', strtotime((string)$o['created_at'])) : '—';
              ?>
              <tr class="ord-row hover:bg-gray-50 dark:hover:bg-slate-800/30 transition" data-status="<?= e($o['status']) ?>">
                <td class="py-3 pr-4 text-slate-500 dark:text-slate-400 whitespace-nowrap"><?= e($oDate) ?></td>
                <td class="py-3 pr-4 text-slate-700 dark:text-slate-300 max-w-[160px] truncate"><?= e((string)$o['username']) ?></td>
                <td class="py-3 pr-4 font-semibold text-slate-800 dark:text-slate-200"><?= ucfirst((string)$o['plan_name']) ?></td>
                <td class="py-3 pr-4 text-slate-700 dark:text-slate-300 whitespace-nowrap">
                  <?= $o['amount'] > 0 ? number_format((float)$o['amount'], 2, ',', ' ') . ' €' : '—' ?>
                </td>
                <td class="py-3 pr-4">
                  <span class="inline-block px-2 py-0.5 rounded-full text-[10px] font-bold <?= $sCls ?>"><?= $sLabel ?></span>
                </td>
                <td class="py-3 font-mono text-[10px] text-slate-400 dark:text-slate-500">
                  <?= $o['invoice_id'] ? e((string)$o['invoice_id']) : '—' ?>
                </td>
              </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php endif; ?>
        </div>
      </div><!-- /tab-payments -->

      <!-- ─── TAB: LOGY ─────────────────────────────────────────── -->
      <div id="adm-tab-logs" class="hidden">
        <div class="bg-white dark:bg-slate-900 rounded-[2rem] shadow-sm border border-gray-100 dark:border-slate-800 p-6">
          <div class="flex items-center justify-between mb-4">
            <p class="text-[10px] font-black uppercase tracking-wider text-slate-400 dark:text-slate-500">
              Posledných <?= count($logLines) ?> riadkov — storage/error.log
            </p>
            <button onclick="doClearLogs()"
                    class="px-3 py-1.5 text-xs font-bold rounded-xl transition
                           bg-red-50 dark:bg-red-900/20 hover:bg-red-100 dark:hover:bg-red-900/30
                           text-red-600 dark:text-red-400">
              Vymazať logy
            </button>
          </div>
          <?php if (empty($logLines)): ?>
          <p class="text-xs text-slate-400 dark:text-slate-500 text-center py-6">(žiadne záznamy)</p>
          <?php else: ?>
          <pre id="log-content"
               class="bg-slate-950 text-emerald-400 text-[11px] font-mono leading-relaxed
                      rounded-2xl p-4 overflow-x-auto whitespace-pre-wrap break-words
                      max-h-[480px] overflow-y-auto"><?php
            foreach ($logLines as $line) {
                echo htmlspecialchars($line, ENT_QUOTES | ENT_HTML5, 'UTF-8') . "\n";
            }
          ?></pre>
          <?php endif; ?>
        </div>
      </div><!-- /tab-logs -->

    </div><!-- /content -->
  </div>
</div><!-- /main -->


<!-- ── EDIT USER MODAL ────────────────────────────────────────────── -->
<div id="eu-modal"
     class="hidden fixed inset-0 z-50 items-center justify-center bg-black/50 backdrop-blur-sm p-4"
     onclick="if(event.target===this)closeEditModal()">
  <div class="bg-white dark:bg-slate-900 rounded-[2rem] shadow-2xl w-full max-w-md p-6">
    <div class="flex items-start justify-between mb-5">
      <div>
        <p class="text-base font-extrabold text-slate-900 dark:text-white">Upraviť užívateľa</p>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5" id="eu-email"></p>
      </div>
      <a id="eu-mailto" href="#"
         class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold rounded-xl transition
                bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400
                hover:bg-indigo-100 dark:hover:bg-indigo-900/50">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
        </svg>
        E-mail
      </a>
    </div>
    <div class="grid grid-cols-2 gap-2 mb-3 rounded-xl bg-gray-50 dark:bg-slate-800/60 px-3 py-2.5 text-xs">
      <div>
        <span class="text-slate-400 dark:text-slate-500">ID</span>
        <p class="font-mono font-bold text-slate-700 dark:text-slate-300 mt-0.5" id="eu-id-display"></p>
      </div>
      <div>
        <span class="text-slate-400 dark:text-slate-500">Registrácia</span>
        <p class="font-semibold text-slate-700 dark:text-slate-300 mt-0.5" id="eu-created-at-display"></p>
      </div>
    </div>
    <div class="space-y-3">
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1.5 block">Plán</label>
          <select id="eu-plan" onchange="euAutoFill(this.value)"
                  class="adm-select w-full bg-gray-100 dark:bg-slate-800 border-none rounded-xl
                         px-4 py-2.5 text-sm text-slate-900 dark:text-slate-100
                         focus:outline-none focus:ring-2 focus:ring-indigo-500 transition">
            <option value="free">Free</option>
            <option value="pro">Pro</option>
            <option value="ultra">Ultra</option>
            <option value="custom">Custom</option>
          </select>
        </div>
        <div>
          <label class="text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1.5 block">Rola</label>
          <select id="eu-role"
                  class="adm-select w-full bg-gray-100 dark:bg-slate-800 border-none rounded-xl
                         px-4 py-2.5 text-sm text-slate-900 dark:text-slate-100
                         focus:outline-none focus:ring-2 focus:ring-indigo-500 transition">
            <option value="user">User</option>
            <option value="admin">Admin</option>
          </select>
        </div>
      </div>
      <div>
        <label class="text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1.5 block">Koniec predplatného</label>
        <input id="eu-ends-at" type="date" style="color-scheme:light dark;"
               class="w-full bg-gray-100 dark:bg-slate-800 border-none rounded-xl px-4 py-2.5 text-sm
                      text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition">
      </div>
      <div class="grid grid-cols-3 gap-3">
        <div>
          <label class="text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1.5 block">Max prev.</label>
          <input id="eu-max-venues" type="number" min="0" max="9999"
                 class="w-full bg-gray-100 dark:bg-slate-800 border-none rounded-xl px-3 py-2.5 text-sm
                        text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition">
        </div>
        <div>
          <label class="text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1.5 block">Max kat.</label>
          <input id="eu-max-cats" type="number" min="0" max="9999"
                 class="w-full bg-gray-100 dark:bg-slate-800 border-none rounded-xl px-3 py-2.5 text-sm
                        text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition">
        </div>
        <div>
          <label class="text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1.5 block">Max jedál</label>
          <input id="eu-max-items" type="number" min="0" max="9999"
                 class="w-full bg-gray-100 dark:bg-slate-800 border-none rounded-xl px-3 py-2.5 text-sm
                        text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition">
        </div>
      </div>
      <div class="flex gap-2 pt-1">
        <button id="eu-save-btn" onclick="submitEditModal()"
                class="flex-1 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold
                       rounded-2xl transition active:scale-95 disabled:opacity-50 disabled:pointer-events-none">
          Uložiť zmeny
        </button>
        <button onclick="euTriggerReset()" title="Reset hesla"
                class="px-3.5 py-2.5 bg-amber-100 dark:bg-amber-900/30 hover:bg-amber-200 dark:hover:bg-amber-900/50
                       text-amber-700 dark:text-amber-400 rounded-2xl transition">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
          </svg>
        </button>
        <button id="eu-del-btn" onclick="euDeleteUser()" title="Zmazať účet"
                class="px-3.5 py-2.5 bg-red-100 dark:bg-red-900/30 hover:bg-red-200 dark:hover:bg-red-900/50
                       text-red-700 dark:text-red-400 rounded-2xl transition">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
          </svg>
        </button>
        <button onclick="closeEditModal()" title="Zrušiť"
                class="px-3.5 py-2.5 bg-gray-100 dark:bg-slate-800 hover:bg-gray-200 dark:hover:bg-slate-700
                       text-slate-500 dark:text-slate-400 rounded-2xl transition">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
          </svg>
        </button>
      </div>
    </div>
  </div>
</div>

<!-- ── CREATE USER MODAL ──────────────────────────────────────────── -->
<div id="cu-modal" class="hidden fixed inset-0 bg-black/50 dark:bg-black/70 z-50 flex items-center justify-center p-4">
  <div class="bg-white dark:bg-slate-900 rounded-[2rem] shadow-2xl border border-gray-100 dark:border-slate-800 p-7 w-full max-w-sm">
    <h3 class="font-bold text-lg text-slate-900 dark:text-white mb-5">Vytvoriť účet</h3>
    <div class="space-y-3 mb-5">
      <input id="cu-email" type="email" placeholder="E-mail" autocomplete="off"
        class="w-full bg-gray-100 dark:bg-slate-800 border-none rounded-xl px-4 py-3 text-sm
               text-slate-900 dark:text-slate-100 placeholder-slate-400
               focus:outline-none focus:ring-2 focus:ring-indigo-500 transition">
      <input id="cu-pass" type="password" placeholder="Heslo (min. 8 znakov)" minlength="8"
        class="w-full bg-gray-100 dark:bg-slate-800 border-none rounded-xl px-4 py-3 text-sm
               text-slate-900 dark:text-slate-100 placeholder-slate-400
               focus:outline-none focus:ring-2 focus:ring-indigo-500 transition">
      <div class="flex gap-3">
        <select id="cu-role"
          class="adm-select flex-1 bg-gray-100 dark:bg-slate-800 border-none rounded-xl px-4 py-3 text-sm
                 text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition">
          <option value="user">Používateľ</option>
          <option value="admin">Admin</option>
        </select>
        <input id="cu-limit" type="number" min="0" max="9999" value="1" placeholder="Limit prev."
          class="w-28 bg-gray-100 dark:bg-slate-800 border-none rounded-xl px-4 py-3 text-sm
                 text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition">
      </div>
    </div>
    <div class="flex gap-3">
      <button onclick="submitCreateUser()"
        class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 rounded-2xl text-sm transition active:scale-95">
        Vytvoriť
      </button>
      <button onclick="closeCreateUser()"
        class="px-5 bg-gray-100 dark:bg-slate-800 hover:bg-gray-200 dark:hover:bg-slate-700
               text-slate-700 dark:text-slate-300 font-semibold py-3 rounded-2xl text-sm transition">
        Zrušiť
      </button>
    </div>
  </div>
</div>

<!-- ── PASSWORD RESET MODAL ───────────────────────────────────────── -->
<div id="pw-modal" class="hidden fixed inset-0 bg-black/50 dark:bg-black/70 z-50 flex items-center justify-center p-4">
  <div class="bg-white dark:bg-slate-900 rounded-[2rem] shadow-2xl border border-gray-100 dark:border-slate-800 p-7 w-full max-w-sm">
    <h3 class="font-bold text-lg text-slate-900 dark:text-white mb-1">Reset hesla</h3>
    <p class="text-sm text-slate-500 dark:text-slate-400 mb-5">
      Účet: <span id="pw-username" class="font-semibold text-slate-800 dark:text-slate-200"></span>
    </p>
    <input id="pw-new" type="password" placeholder="Nové heslo (min. 8 znakov)" minlength="8"
      class="w-full bg-gray-100 dark:bg-slate-800 border-none rounded-xl px-4 py-3 text-sm
             text-slate-900 dark:text-slate-100 placeholder-slate-400
             focus:outline-none focus:ring-2 focus:ring-indigo-500 transition mb-5">
    <div class="flex gap-3">
      <button onclick="submitReset()"
        class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 rounded-2xl text-sm transition active:scale-95">
        Nastaviť heslo
      </button>
      <button onclick="closePwModal()"
        class="px-5 bg-gray-100 dark:bg-slate-800 hover:bg-gray-200 dark:hover:bg-slate-700
               text-slate-700 dark:text-slate-300 font-semibold py-3 rounded-2xl text-sm transition">
        Zrušiť
      </button>
    </div>
  </div>
</div>

<script>
const CSRF    = <?= json_encode($csrf) ?>;
const API_URL = <?= json_encode(url('api/admin_actions.php')) ?>;

function fetchWithTimeout(url, options, ms = 10000) {
  const ctrl = new AbortController();
  const id   = setTimeout(() => ctrl.abort(), ms);
  return fetch(url, { ...options, signal: ctrl.signal }).finally(() => clearTimeout(id));
}
async function adminApi(payload) {
  const res = await fetchWithTimeout(API_URL, {
    method: 'POST', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ ...payload, csrf: CSRF }),
  });
  return res.json();
}
function toast(msg, type = 'info') {
  const el = document.createElement('div');
  const bg = type === 'success' ? 'bg-emerald-600' : type === 'error' ? 'bg-red-600' : 'bg-slate-800';
  el.className = `pointer-events-auto px-5 py-3 rounded-2xl text-white text-sm font-semibold shadow-xl ${bg}`;
  el.textContent = msg;
  document.getElementById('toast-container').appendChild(el);
  setTimeout(() => { el.style.opacity = '0'; el.style.transition = 'opacity .3s'; setTimeout(() => el.remove(), 300); }, 3200);
}

/* ── Tab switching ──────────────────────────────────────────────── */
const ADM_TABS = ['overview', 'users', 'payments', 'logs'];
function admSwitchTab(id) {
  ADM_TABS.forEach(t => {
    document.getElementById('adm-tab-' + t)?.classList.toggle('hidden', t !== id);
    const btn   = document.getElementById('adm-tab-btn-'   + t);
    const icon  = document.getElementById('adm-tab-icon-'  + t);
    const label = document.getElementById('adm-tab-label-' + t);
    if (!btn) return;
    const a = t === id;
    btn.className   = `w-full text-left px-3 py-2.5 rounded-xl transition-all duration-200 flex items-center gap-2.5 ${a ? 'bg-indigo-50 dark:bg-indigo-900/30' : 'hover:bg-gray-50 dark:hover:bg-slate-800'}`;
    if (icon)  icon.className  = `w-4 h-4 flex-shrink-0 ${a ? 'text-indigo-600 dark:text-indigo-400' : 'text-slate-400 dark:text-slate-500'}`;
    if (label) label.className = `font-bold text-sm ${a ? 'text-indigo-700 dark:text-indigo-300' : 'text-slate-700 dark:text-slate-300'}`;
  });
}

/* ── User search ────────────────────────────────────────────────── */
document.getElementById('user-search')?.addEventListener('input', function () {
  const q = this.value.toLowerCase();
  document.querySelectorAll('.user-row').forEach(r => {
    r.style.display = (r.dataset.email ?? '').includes(q) ? '' : 'none';
  });
});

/* ── Orders filter ──────────────────────────────────────────────── */
function filterOrders(status) {
  document.querySelectorAll('.ord-row').forEach(r => {
    r.style.display = (status === 'all' || r.dataset.status === status) ? '' : 'none';
  });
  ['all', 'paid', 'pending', 'failed'].forEach(s => {
    const btn = document.getElementById('ord-filter-' + s);
    if (!btn) return;
    const a = s === status;
    btn.className = `px-3 py-1.5 rounded-xl text-xs font-bold transition ${a ? 'bg-indigo-600 text-white' : 'bg-gray-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 hover:bg-gray-200 dark:hover:bg-slate-700'}`;
  });
}

/* ── Edit user modal ────────────────────────────────────────────── */
let _euId = null, _euEmail = '', _euIsSelf = false;
function openEditModal(d) {
  _euId = d.id; _euEmail = d.email; _euIsSelf = !!d.is_self;
  document.getElementById('eu-email').textContent             = d.email;
  document.getElementById('eu-mailto').href                   = 'mailto:' + d.email;
  document.getElementById('eu-id-display').textContent        = '#' + d.id;
  document.getElementById('eu-created-at-display').textContent = d.created_at || '—';
  document.getElementById('eu-plan').value                    = d.plan_name;
  document.getElementById('eu-ends-at').value                 = d.plan_ends_at || '';
  document.getElementById('eu-max-venues').value              = d.max_venues;
  document.getElementById('eu-max-cats').value                = d.max_categories;
  document.getElementById('eu-max-items').value               = d.max_items_per_cat;
  document.getElementById('eu-role').value                    = d.role;
  document.getElementById('eu-role').disabled                 = _euIsSelf;
  document.getElementById('eu-save-btn').disabled             = false;
  const del = document.getElementById('eu-del-btn');
  if (del) del.style.display = _euIsSelf ? 'none' : '';
  const m = document.getElementById('eu-modal');
  m.classList.remove('hidden'); m.classList.add('flex');
}
function closeEditModal() {
  const m = document.getElementById('eu-modal');
  m.classList.add('hidden'); m.classList.remove('flex');
}
function euAutoFill(plan) {
  const d = { free:[1,3,5], pro:[1,10,25], ultra:[1,20,50] };
  if (d[plan]) {
    document.getElementById('eu-max-venues').value = d[plan][0];
    document.getElementById('eu-max-cats').value   = d[plan][1];
    document.getElementById('eu-max-items').value  = d[plan][2];
  }
}
async function submitEditModal() {
  const btn = document.getElementById('eu-save-btn');
  if (btn) btn.disabled = true;
  const endsAt = document.getElementById('eu-ends-at').value;
  try {
    const data = await adminApi({
      action: 'update_user_admin', user_id: _euId,
      plan_name:         document.getElementById('eu-plan').value,
      plan_ends_at:      endsAt ? endsAt + 'T23:59:59Z' : null,
      max_venues:        parseInt(document.getElementById('eu-max-venues').value) || 1,
      max_categories:    parseInt(document.getElementById('eu-max-cats').value)   || 3,
      max_items_per_cat: parseInt(document.getElementById('eu-max-items').value)  || 5,
      role:              document.getElementById('eu-role').value,
    });
    if (data.ok) {
      toast('Uložené.', 'success');
      if (data.user) updateUserRow(_euId, data.user);
      closeEditModal();
      setTimeout(() => location.reload(), 500);
    } else { toast(data.error || 'Chyba.', 'error'); if (btn) btn.disabled = false; }
  } catch { toast('Sieťová chyba.', 'error'); if (btn) btn.disabled = false; }
}
function updateUserRow(userId, u) {
  const row = document.getElementById('user-row-' + userId);
  if (!row) return;
  try {
    const ex = JSON.parse(row.dataset.user || '{}');
    Object.assign(ex, { plan_name: u.plan_name, plan_ends_at: u.plan_ends_at ? u.plan_ends_at.substring(0,10) : '', max_venues: u.max_venues, max_categories: u.max_categories, max_items_per_cat: u.max_items_per_cat, role: u.role });
    row.dataset.user = JSON.stringify(ex);
  } catch {}
  const PLAN_CLS = {
    pro:    'adm-plan-badge inline-block px-2 py-0.5 rounded-full text-[10px] font-bold bg-indigo-100 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-400',
    ultra:  'adm-plan-badge inline-block px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400',
    custom: 'adm-plan-badge inline-block px-2 py-0.5 rounded-full text-[10px] font-bold bg-violet-100 dark:bg-violet-900/30 text-violet-700 dark:text-violet-400',
    free:   'adm-plan-badge inline-block px-2 py-0.5 rounded-full text-[10px] font-bold bg-gray-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400',
  };
  const badge = row.querySelector('.adm-plan-badge');
  if (badge) { badge.className = PLAN_CLS[u.plan_name] || PLAN_CLS.free; badge.textContent = u.plan_name.charAt(0).toUpperCase() + u.plan_name.slice(1); }
  const endsEl = row.querySelector('.adm-ends-at');
  if (endsEl) endsEl.textContent = u.plan_ends_at ? u.plan_ends_at.substring(0,10).split('-').reverse().join('.') : '—';
  const roleCell = row.querySelector('.adm-role-cell');
  if (roleCell) roleCell.innerHTML = u.role === 'admin'
    ? '<span class="inline-block px-2 py-0.5 rounded-full text-[10px] font-bold bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400">Admin</span>'
    : '<span class="text-slate-400 dark:text-slate-500">User</span>';
  if (u.role === 'admin') { row.classList.add('border-l-2','border-red-400','dark:border-red-500'); }
  else { row.classList.remove('border-l-2','border-red-400','dark:border-red-500'); }
}
function euTriggerReset() { closeEditModal(); resetPassword(_euId, _euEmail); }
async function euDeleteUser() {
  if (_euIsSelf || !confirm(`Naozaj zmazať účet "${_euEmail}" a všetky jeho prevádzky?`)) return;
  try {
    const data = await adminApi({ action: 'delete_user', user_id: _euId });
    if (data.ok) { toast('Účet zmazaný.', 'success'); closeEditModal(); setTimeout(() => location.reload(), 1200); }
    else toast(data.error || 'Chyba.', 'error');
  } catch { toast('Sieťová chyba.', 'error'); }
}

/* ── Venue delete ───────────────────────────────────────────────── */
async function adminDeleteVenue(slug, name) {
  if (!confirm(`Naozaj zmazať prevádzku "${name}"?`)) return;
  try {
    const data = await adminApi({ action: 'delete_venue', slug });
    if (data.ok) { toast('Prevádzka zmazaná.', 'success'); document.getElementById('venue-row-' + slug)?.remove(); }
    else toast(data.error || 'Chyba.', 'error');
  } catch { toast('Sieťová chyba.', 'error'); }
}

/* ── Cleanup inactive ───────────────────────────────────────────── */
async function doCleanup() {
  if (!confirm('Zmazať všetkých Free užívateľov neaktívnych 90+ dní?')) return;
  try {
    const data = await adminApi({ action: 'cleanup_inactive' });
    if (data.ok) { toast(`Vymazaných: ${data.deleted} užívateľov.`, 'success'); setTimeout(() => location.reload(), 1500); }
    else toast(data.error || 'Chyba.', 'error');
  } catch { toast('Sieťová chyba.', 'error'); }
}

/* ── Clear logs ─────────────────────────────────────────────────── */
async function doClearLogs() {
  if (!confirm('Naozaj vymazať všetky logy?')) return;
  try {
    const data = await adminApi({ action: 'delete_log' });
    if (data.ok) {
      const pre = document.getElementById('log-content');
      if (pre) pre.textContent = '';
      toast('Logy vymazané.', 'success');
    } else toast(data.error || 'Chyba.', 'error');
  } catch { toast('Sieťová chyba.', 'error'); }
}

/* ── Password reset modal ───────────────────────────────────────── */
let _pwUserId = null;
function resetPassword(userId, username) {
  _pwUserId = userId;
  document.getElementById('pw-username').textContent = username;
  document.getElementById('pw-new').value = '';
  document.getElementById('pw-modal').classList.remove('hidden');
  setTimeout(() => document.getElementById('pw-new').focus(), 50);
}
function closePwModal() { document.getElementById('pw-modal').classList.add('hidden'); _pwUserId = null; }
async function submitReset() {
  const pass = document.getElementById('pw-new').value;
  if (pass.length < 8) { toast('Heslo musí mať aspoň 8 znakov.', 'error'); return; }
  try {
    const data = await adminApi({ action: 'reset_password', user_id: _pwUserId, password: pass });
    if (data.ok) { toast('Heslo zmenené.', 'success'); closePwModal(); }
    else toast(data.error || 'Chyba.', 'error');
  } catch { toast('Sieťová chyba.', 'error'); }
}

/* ── Create user modal ──────────────────────────────────────────── */
function openCreateUser() {
  document.getElementById('cu-email').value = '';
  document.getElementById('cu-pass').value  = '';
  document.getElementById('cu-role').value  = 'user';
  document.getElementById('cu-limit').value = '1';
  document.getElementById('cu-modal').classList.remove('hidden');
  setTimeout(() => document.getElementById('cu-email').focus(), 50);
}
function closeCreateUser() { document.getElementById('cu-modal').classList.add('hidden'); }
async function submitCreateUser() {
  const email = document.getElementById('cu-email').value.trim();
  const pass  = document.getElementById('cu-pass').value;
  const role  = document.getElementById('cu-role').value;
  const limit = parseInt(document.getElementById('cu-limit').value) || 1;
  if (!email) { toast('Zadajte e-mail.', 'error'); return; }
  if (pass.length < 8) { toast('Heslo musí mať aspoň 8 znakov.', 'error'); return; }
  try {
    const data = await adminApi({ action: 'create_user', username: email, password: pass, role, venue_limit: limit });
    if (data.ok) { toast('Účet bol vytvorený.', 'success'); closeCreateUser(); setTimeout(() => location.reload(), 1200); }
    else toast(data.error || 'Chyba.', 'error');
  } catch { toast('Sieťová chyba.', 'error'); }
}

/* ── Modal backdrop & Escape ────────────────────────────────────── */
[['pw-modal', closePwModal], ['cu-modal', closeCreateUser]].forEach(([id, fn]) => {
  const el = document.getElementById(id);
  if (!el) return;
  let _d = false;
  el.addEventListener('mousedown', e => { _d = e.target === el; });
  el.addEventListener('mouseup',   e => { if (_d && e.target === el) fn(); });
});
document.addEventListener('keydown', e => {
  if (e.key !== 'Escape') return;
  if (!document.getElementById('eu-modal')?.classList.contains('hidden')) closeEditModal();
  else if (!document.getElementById('pw-modal')?.classList.contains('hidden')) closePwModal();
  else if (!document.getElementById('cu-modal')?.classList.contains('hidden')) closeCreateUser();
});
</script>
<?php require __DIR__ . '/partials/footer.php'; ?>
