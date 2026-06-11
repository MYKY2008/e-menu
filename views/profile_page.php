<?php
$title  = 'Môj Profil — GastroLink QR';
$robots = 'noindex, nofollow';
require __DIR__ . '/partials/header.php';
?>
<style>
/* ── Custom select ───────────────────────────────────────────────── */
.gl-cs { position: relative; }

.gl-cs-btn {
  display: flex; align-items: center; justify-content: space-between; gap: 0.5rem;
  width: 100%; text-align: left; cursor: pointer;
  background: #f3f4f6; border: none;
  border-radius: 0.75rem;
  padding: 0.625rem 1rem;
  font-size: 0.875rem; line-height: 1.25rem;
  color: #0f172a;
  transition: background 0.15s, box-shadow 0.15s;
}
.dark .gl-cs-btn { background: #1e293b; color: #f1f5f9; }
.gl-cs-btn:focus { outline: none; box-shadow: 0 0 0 2px #6366f1; }

.gl-cs-arrow {
  flex-shrink: 0; width: 1rem; height: 1rem;
  color: #64748b; transition: transform 0.18s ease;
}
.dark .gl-cs-arrow { color: #94a3b8; }
.gl-cs-btn[aria-expanded="true"] .gl-cs-arrow { transform: rotate(180deg); }

.gl-cs-panel {
  display: none;
  position: absolute; top: calc(100% + 5px); left: 0; right: 0; z-index: 60;
  background: #ffffff;
  border-radius: 0.875rem;
  border: 1px solid #e2e8f0;
  box-shadow: 0 12px 28px -4px rgba(0,0,0,0.12), 0 4px 10px -3px rgba(0,0,0,0.07);
  overflow: hidden;
}
.dark .gl-cs-panel { background: #1e293b; border-color: #334155; }
.gl-cs-panel.open { display: block; }

.gl-cs-option {
  display: block; width: 100%; text-align: left;
  padding: 0.6rem 1rem;
  font-size: 0.875rem; color: #334155;
  background: transparent; border: none; cursor: pointer;
  transition: background 0.1s;
}
.dark .gl-cs-option { color: #cbd5e1; }
.gl-cs-option:hover { background: #f1f5f9; }
.dark .gl-cs-option:hover { background: #334155; }
.gl-cs-option[aria-selected="true"] { color: #4f46e5; font-weight: 700; }
.dark .gl-cs-option[aria-selected="true"] { color: #818cf8; }
</style>
<body class="bg-gray-50 dark:bg-slate-950 min-h-screen transition-colors duration-200">

<?php
$db     = getDB();
$userId = (int)$_SESSION['user_id'];
$email  = (string)($_SESSION['username'] ?? '');

// Load user plan
$stUser = $db->prepare("SELECT plan_name, max_categories, max_items_per_cat, plan_ends_at, next_plan_name, company_name, ico, dic, ic_dph, billing_street, billing_city, billing_zip, billing_country FROM users WHERE id = ?");
$stUser->execute([$userId]);
$planRow      = $stUser->fetch() ?: [];
$userPlan     = (string)($planRow['plan_name']        ?: 'free');
$maxCats      = (int)($planRow['max_categories']       ?: 3);
$maxItemsCat  = (int)($planRow['max_items_per_cat']    ?: 5);
$planEndsAt   = $planRow['plan_ends_at'] ?? null;
$nextPlanName = $planRow['next_plan_name'] ?? null;
$isPaidPlan   = in_array($userPlan, ['pro', 'ultra', 'custom'], true);
$companyName    = (string)($planRow['company_name']    ?? '');
$ico            = (string)($planRow['ico']            ?? '');
$dic            = (string)($planRow['dic']            ?? '');
$icDph          = (string)($planRow['ic_dph']         ?? '');
$billingStreet  = (string)($planRow['billing_street']  ?? '');
$billingCity    = (string)($planRow['billing_city']    ?? '');
$billingZip     = (string)($planRow['billing_zip']     ?? '');
$billingCountry = (string)($planRow['billing_country'] ?? 'Slovensko');
$planLabel   = match($userPlan) {
    'pro'    => 'Pro',
    'ultra'  => 'Ultra',
    'custom' => 'Custom',
    default  => 'Free',
};

// Load venue stats for plan section
$stVenueStats = $db->prepare("
    SELECT v.slug, v.name, COUNT(c.id) AS cat_count
    FROM venues v
    LEFT JOIN categories c ON c.venue_slug = v.slug
    WHERE v.user_id = ?
    GROUP BY v.slug, v.name
    ORDER BY v.name
");
$stVenueStats->execute([$userId]);
$venueStats = $stVenueStats->fetchAll();

// Load orders history
$stOrders = $db->prepare(
    "SELECT id, plan_name, amount, currency, status, invoice_id, created_at
     FROM orders WHERE user_id = ? ORDER BY created_at DESC"
);
$stOrders->execute([$userId]);
$orders = $stOrders->fetchAll();

$tabs = [
    ['id' => 'account',  'title' => 'Môj Účet',        'desc'  => 'Zmena e-mailu'],
    ['id' => 'security', 'title' => 'Zabezpečenie',     'desc'  => 'Zmena hesla'],
    ['id' => 'plan',     'title' => 'Môj Plán',         'desc'  => 'Free / Paid limity'],
    ['id' => 'billing',  'title' => 'Fakturačné údaje', 'desc'  => 'IČO, DIČ, fakturácia'],
    ['id' => 'invoices', 'title' => 'Faktúry',          'desc'  => 'História platieb'],
    ['id' => 'data',     'title' => 'Import / Export',  'desc'  => 'Záloha a prenos menu'],
    ['id' => 'danger',   'title' => 'Nebezpečná zóna',  'desc'  => 'Zmazanie účtu'],
];
?>

<!-- ── NAVBAR ─────────────────────────────────────────────────────────── -->
<nav class="bg-white/80 dark:bg-slate-950/80 backdrop-blur-lg shadow-sm px-5 py-3
            flex items-center justify-between sticky top-0 z-30
            border-b border-gray-100 dark:border-slate-800">
  <a href="<?= url() ?>" class="font-extrabold text-sm tracking-tight">
    <span class="text-indigo-600">GastroLink</span><span class="text-emerald-500">QR</span>
  </a>
  <div class="flex items-center gap-3">
    <a href="<?= url('dashboard') ?>"
       class="flex items-center gap-1.5 text-xs font-semibold px-3 py-1.5 rounded-xl
              text-slate-600 dark:text-slate-400 hover:bg-gray-100 dark:hover:bg-slate-800 transition">
      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
      </svg>
      Dashboard
    </a>
    <button id="dark-toggle" onclick="toggleDark()" aria-label="Prepnúť tmavý režim"
            class="w-8 h-8 rounded-xl bg-gray-100 dark:bg-slate-800
                   flex items-center justify-center text-slate-500 dark:text-slate-400
                   hover:bg-gray-200 dark:hover:bg-slate-700 transition-all duration-200">
      <span id="dark-icon" class="w-3.5 h-3.5 block pointer-events-none"></span>
    </button>
  </div>
</nav>

<!-- Toast -->
<div id="toast-wrap" class="fixed top-14 right-4 z-50 flex flex-col gap-2 pointer-events-none"></div>

<!-- ── MAIN ───────────────────────────────────────────────────────────── -->
<div class="max-w-5xl mx-auto px-4 py-8">
  <div class="flex flex-col md:flex-row gap-6 items-start">

    <!-- SIDEBAR -->
    <aside class="w-full md:w-64 flex-shrink-0">
      <div class="bg-white dark:bg-slate-900 rounded-[2rem] shadow-sm border border-gray-100 dark:border-slate-800 p-3 flex flex-col">
        <!-- User info -->
        <div class="px-3 pt-2 pb-4 border-b border-gray-100 dark:border-slate-800 mb-2">
          <div class="w-10 h-10 rounded-2xl bg-indigo-100 dark:bg-indigo-900/40 flex items-center justify-center mb-2">
            <svg class="w-5 h-5 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <circle cx="12" cy="8" r="4"/><path stroke-linecap="round" stroke-linejoin="round" d="M4 20c0-4 3.582-7 8-7s8 3 8 7"/>
            </svg>
          </div>
          <p class="font-extrabold text-slate-900 dark:text-white text-sm">Nastavenia účtu</p>
          <p class="text-[11px] text-slate-400 dark:text-slate-500 truncate mt-0.5"><?= e($email) ?></p>
          <span class="inline-block mt-2 px-2 py-0.5 rounded-full text-[10px] font-bold
                       <?= $userPlan === 'free' ? 'bg-gray-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400' : 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400' ?>">
            <?= e($planLabel) ?>
          </span>
        </div>

        <!-- Tabs -->
        <nav class="flex-1 space-y-0.5 mb-2">
          <?php foreach ($tabs as $i => $tab): ?>
          <button onclick="switchTab('<?= $tab['id'] ?>')"
                  id="tab-btn-<?= $tab['id'] ?>"
                  data-danger="<?= $tab['id'] === 'danger' ? '1' : '0' ?>"
                  class="w-full text-left px-3 py-2.5 rounded-xl transition-all duration-200
                         <?= $i === 0 ? 'bg-indigo-50 dark:bg-indigo-900/30' : 'hover:bg-gray-50 dark:hover:bg-slate-800' ?>">
            <p class="font-bold text-sm
                      <?= $tab['id'] === 'danger' ? 'text-red-600 dark:text-red-400' : 'text-slate-700 dark:text-slate-300' ?>
                      <?= $i === 0 ? 'text-indigo-700 dark:text-indigo-300' : '' ?>">
              <?= $tab['title'] ?>
            </p>
            <p class="text-[10px] text-slate-400 dark:text-slate-500 mt-0.5"><?= $tab['desc'] ?></p>
          </button>
          <?php endforeach; ?>
        </nav>

        <!-- Logout -->
        <div class="pt-3 border-t border-gray-100 dark:border-slate-800">
          <a href="<?= url('logout') ?>"
             class="flex items-center gap-2 w-full px-3 py-2.5 rounded-xl
                    bg-red-50 dark:bg-red-900/20 hover:bg-red-100 dark:hover:bg-red-900/30
                    text-red-600 dark:text-red-400 text-sm font-bold transition">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h6a2 2 0 012 2v1"/>
            </svg>
            Odhlásiť sa
          </a>
        </div>
      </div>
    </aside>

    <!-- CONTENT -->
    <div class="flex-1 min-w-0">

      <!-- ── Môj Účet ─────────────────────────────────────────────────── -->
      <div id="tab-account" class="space-y-4">
        <div class="bg-white dark:bg-slate-900 rounded-[2rem] shadow-sm border border-gray-100 dark:border-slate-800 p-6">
          <p class="text-[10px] font-black uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-5">Zmena e-mailu</p>
          <div class="space-y-3 max-w-md">
            <div>
              <label class="text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1.5 block">E-mail</label>
              <input id="up-email" type="email" value="<?= e($email) ?>"
                     class="w-full bg-gray-100 dark:bg-slate-800 border-none rounded-xl px-4 py-2.5 text-sm
                            text-slate-900 dark:text-slate-100
                            focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-all duration-200">
            </div>
            <div>
              <label class="text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1.5 block">Aktuálne heslo (potvrdenie)</label>
              <input id="up-current-password" type="password" placeholder="Vaše aktuálne heslo" autocomplete="current-password"
                     class="w-full bg-gray-100 dark:bg-slate-800 border-none rounded-xl px-4 py-2.5 text-sm
                            text-slate-900 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500
                            focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-all duration-200">
            </div>
            <button onclick="submitUpdateProfile()"
              class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold
                     rounded-2xl transition-all duration-200 active:scale-95">
              Uložiť e-mail
            </button>
          </div>
        </div>
      </div>

      <!-- ── Zabezpečenie ──────────────────────────────────────────────── -->
      <div id="tab-security" class="space-y-4 hidden">
        <div class="bg-white dark:bg-slate-900 rounded-[2rem] shadow-sm border border-gray-100 dark:border-slate-800 p-6">
          <p class="text-[10px] font-black uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-5">Zmena hesla</p>
          <div class="space-y-3 max-w-md">
            <div>
              <label class="text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1.5 block">Aktuálne heslo</label>
              <input id="cp-old" type="password" placeholder="Aktuálne heslo" autocomplete="current-password"
                class="w-full bg-gray-100 dark:bg-slate-800 border-none rounded-xl px-4 py-2.5 text-sm
                       text-slate-900 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500
                       focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-all duration-200">
            </div>
            <div>
              <label class="text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1.5 block">Nové heslo</label>
              <input id="cp-new" type="password" placeholder="Min. 8 znakov" autocomplete="new-password"
                class="w-full bg-gray-100 dark:bg-slate-800 border-none rounded-xl px-4 py-2.5 text-sm
                       text-slate-900 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500
                       focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-all duration-200">
            </div>
            <div>
              <label class="text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1.5 block">Zopakovať nové heslo</label>
              <input id="cp-new2" type="password" placeholder="Zopakovať nové heslo" autocomplete="new-password"
                class="w-full bg-gray-100 dark:bg-slate-800 border-none rounded-xl px-4 py-2.5 text-sm
                       text-slate-900 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500
                       focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-all duration-200">
            </div>
            <button onclick="submitPasswordChange()"
              class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold
                     rounded-2xl transition-all duration-200 active:scale-95">
              Zmeniť heslo
            </button>
          </div>
        </div>
      </div>

      <!-- ── Môj Plán ──────────────────────────────────────────────────── -->
      <div id="tab-plan" class="space-y-4 hidden">
        <div class="bg-white dark:bg-slate-900 rounded-[2rem] shadow-sm border border-gray-100 dark:border-slate-800 p-6">
          <p class="text-[10px] font-black uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-5">Aktuálny plán</p>

          <div class="flex items-center gap-3 mb-6">
            <span class="text-3xl font-extrabold text-slate-900 dark:text-white">
              <?= e($planLabel) ?>
            </span>
            <span class="px-3 py-1 rounded-full text-xs font-bold
                         <?= $userPlan === 'free' ? 'bg-gray-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400' : 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400' ?>">
              <?= $userPlan === 'free' ? 'Základný' : 'Aktívny' ?>
            </span>
          </div>

          <div class="mb-5 p-4 bg-slate-50 dark:bg-slate-800/50 rounded-2xl">
            <p class="text-[10px] font-black uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-2">Limity plánu</p>
            <div class="grid grid-cols-2 gap-3">
              <div>
                <p class="text-xs text-slate-500 dark:text-slate-400">Max. kategórie</p>
                <p class="text-lg font-bold text-slate-900 dark:text-white"><?= $maxCats >= 9999 ? '∞' : $maxCats ?></p>
              </div>
              <div>
                <p class="text-xs text-slate-500 dark:text-slate-400">Max. jedál / kat.</p>
                <p class="text-lg font-bold text-slate-900 dark:text-white"><?= $maxItemsCat >= 9999 ? '∞' : $maxItemsCat ?></p>
              </div>
            </div>
          </div>

          <a href="<?= url('plans') ?>"
             class="inline-flex items-center gap-2 px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700
                    text-white text-sm font-bold rounded-2xl transition-all duration-200 active:scale-95 mb-5">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M4 5a1 1 0 011-1h4a1 1 0 011 1v5a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM14 5a1 1 0 011-1h4a1 1 0 011 1v2a1 1 0 01-1 1h-4a1 1 0 01-1-1V5zM4 15a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1H5a1 1 0 01-1-1v-4zM14 13a1 1 0 011-1h4a1 1 0 011 1v6a1 1 0 01-1 1h-4a1 1 0 01-1-1v-6z"/>
            </svg>
            Zmeniť plán
          </a>

          <?php if ($nextPlanName !== null): ?>
          <?php $nextLabel = match($nextPlanName) { 'pro'=>'Pro','ultra'=>'Ultra','custom'=>'Custom',default=>'Free' }; ?>
          <div class="mb-5 p-4 bg-blue-50 dark:bg-blue-900/20 rounded-2xl border border-blue-100 dark:border-blue-800/40">
            <p class="text-xs font-bold text-blue-700 dark:text-blue-300 mb-1">Naplánovaná zmena plánu</p>
            <p class="text-xs text-blue-600 dark:text-blue-400 leading-relaxed">
              Váš súčasný plán <strong><?= e($planLabel) ?></strong> zostáva plne aktívny
              <?php if ($planEndsAt): ?>
                do <strong><?= e(date('d. m. Y', strtotime((string)$planEndsAt))) ?></strong>.
              <?php endif; ?>
              Potom sa prepne na <strong><?= e($nextLabel) ?></strong>.
            </p>
          </div>
          <?php endif; ?>

          <?php if ($userPlan === 'free'): ?>
          <div class="space-y-4">
            <?php if (!empty($venueStats)): ?>
            <p class="text-[10px] font-black uppercase tracking-wider text-slate-400 dark:text-slate-500">Využitie kategórií</p>
            <?php foreach ($venueStats as $vs):
              $catCount = (int)$vs['cat_count'];
              $pct      = min(100, (int)round($catCount / 3 * 100));
              $over     = $catCount >= 3;
            ?>
            <div class="bg-gray-50 dark:bg-slate-800/50 rounded-2xl p-4">
              <div class="flex justify-between items-center mb-2">
                <p class="text-xs font-bold text-slate-700 dark:text-slate-300 truncate">📍 <?= e($vs['name']) ?></p>
                <span class="text-xs font-bold ml-3 flex-shrink-0 <?= $over ? 'text-red-500' : 'text-slate-600 dark:text-slate-400' ?>">
                  <?= $catCount ?>/3
                </span>
              </div>
              <div class="w-full bg-gray-200 dark:bg-slate-700 rounded-full h-1.5">
                <div class="h-1.5 rounded-full transition-all <?= $over ? 'bg-red-500' : 'bg-indigo-500' ?>"
                     style="width:<?= $pct ?>%"></div>
              </div>
            </div>
            <?php endforeach; ?>
            <?php else: ?>
            <p class="text-xs text-slate-400 dark:text-slate-500">Zatiaľ nemáte žiadne prevádzky.</p>
            <?php endif; ?>

            <div class="p-4 bg-indigo-50 dark:bg-indigo-900/20 rounded-2xl border border-indigo-100 dark:border-indigo-800/50">
              <p class="text-xs font-bold text-indigo-700 dark:text-indigo-300 mb-2">Limity Free plánu</p>
              <ul class="text-xs text-indigo-600 dark:text-indigo-400 space-y-1">
                <li>• Max. <strong>3 kategórie</strong> na prevádzku</li>
                <li>• Max. <strong>5 jedál</strong> na kategóriu</li>
              </ul>
            </div>
          </div>

          <?php else: ?>
          <div class="space-y-4">
            <p class="text-sm text-slate-600 dark:text-slate-400 leading-relaxed">
              Váš <strong class="text-slate-900 dark:text-white"><?= e($planLabel) ?></strong> plán zahŕňa
              <strong class="text-slate-900 dark:text-white"><?= $maxCats >= 9999 ? 'neobmedzený' : $maxCats ?></strong> kategórií
              a <strong class="text-slate-900 dark:text-white"><?= $maxItemsCat >= 9999 ? 'neobmedzený' : $maxItemsCat ?></strong> jedál na kategóriu.
            </p>
            <?php if (!empty($venueStats)): ?>
            <div class="bg-gray-50 dark:bg-slate-800/50 rounded-2xl p-4">
              <p class="text-[10px] font-black uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-3">Prevádzky</p>
              <?php foreach ($venueStats as $vs): ?>
              <div class="flex justify-between items-center py-2 border-b border-gray-100 dark:border-slate-700 last:border-0">
                <span class="text-xs text-slate-700 dark:text-slate-300 truncate"><?= e($vs['name']) ?></span>
                <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 ml-3 flex-shrink-0">
                  <?= (int)$vs['cat_count'] ?> kat.
                </span>
              </div>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if ($isPaidPlan): ?>
            <div class="pt-2">
              <?php if ($nextPlanName === 'free'): ?>
              <p class="text-xs text-amber-600 dark:text-amber-400 leading-relaxed">
                Predplatné bude ukončené k
                <strong><?= $planEndsAt ? e(date('d. m. Y', strtotime((string)$planEndsAt))) : '—' ?></strong>.
                Opätovná obnova je vypnutá.
              </p>
              <?php else: ?>
              <button onclick="openCancelModal()"
                class="text-xs font-semibold text-red-500 hover:text-red-700 dark:hover:text-red-400
                       underline underline-offset-2 transition">
                Zrušiť predplatné
              </button>
              <?php endif; ?>
            </div>
            <?php endif; ?>
          </div>
          <?php endif; ?>
        </div>

      </div>

      <!-- ── Fakturačné údaje ───────────────────────────────────────────── -->
      <div id="tab-billing" class="space-y-4 hidden">
        <div class="bg-white dark:bg-slate-900 rounded-[2rem] shadow-sm border border-gray-100 dark:border-slate-800 p-6">
          <p class="text-[10px] font-black uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-5">Firemné a fakturačné údaje</p>
          <div class="space-y-3 max-w-md">
            <div>
              <label class="text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1.5 block">Obchodné meno / Názov firmy</label>
              <input id="bl-company" type="text" value="<?= e($companyName) ?>" placeholder="napr. Reštaurácia Novák s.r.o."
                     class="w-full bg-gray-100 dark:bg-slate-800 border-none rounded-xl px-4 py-2.5 text-sm
                            text-slate-900 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500
                            focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-all duration-200">
            </div>
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1.5 block">IČO</label>
                <input id="bl-ico" type="text" value="<?= e($ico) ?>" placeholder="napr. 12345678"
                       class="w-full bg-gray-100 dark:bg-slate-800 border-none rounded-xl px-4 py-2.5 text-sm
                              text-slate-900 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500
                              focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-all duration-200">
              </div>
              <div>
                <label class="text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1.5 block">DIČ</label>
                <input id="bl-dic" type="text" value="<?= e($dic) ?>" placeholder="napr. 2023456789"
                       class="w-full bg-gray-100 dark:bg-slate-800 border-none rounded-xl px-4 py-2.5 text-sm
                              text-slate-900 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500
                              focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-all duration-200">
              </div>
            </div>
            <div>
              <label class="text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1.5 block">
                IČ DPH <span class="font-normal text-slate-400">(voliteľné)</span>
              </label>
              <input id="bl-ic-dph" type="text" value="<?= e($icDph) ?>" placeholder="napr. SK2023456789"
                     class="w-full bg-gray-100 dark:bg-slate-800 border-none rounded-xl px-4 py-2.5 text-sm
                            text-slate-900 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500
                            focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-all duration-200">
            </div>
            <div>
              <label class="text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1.5 block">Ulica a číslo</label>
              <input id="bl-street" type="text" value="<?= e($billingStreet) ?>" placeholder="napr. Hlavná 12"
                     class="w-full bg-gray-100 dark:bg-slate-800 border-none rounded-xl px-4 py-2.5 text-sm
                            text-slate-900 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500
                            focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-all duration-200">
            </div>
            <div class="grid grid-cols-3 gap-3">
              <div class="col-span-2">
                <label class="text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1.5 block">Mesto</label>
                <input id="bl-city" type="text" value="<?= e($billingCity) ?>" placeholder="napr. Žilina"
                       class="w-full bg-gray-100 dark:bg-slate-800 border-none rounded-xl px-4 py-2.5 text-sm
                              text-slate-900 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500
                              focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-all duration-200">
              </div>
              <div>
                <label class="text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1.5 block">PSČ</label>
                <input id="bl-zip" type="text" value="<?= e($billingZip) ?>" placeholder="010 01"
                       class="w-full bg-gray-100 dark:bg-slate-800 border-none rounded-xl px-4 py-2.5 text-sm
                              text-slate-900 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500
                              focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-all duration-200">
              </div>
            </div>
            <div>
              <label class="text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1.5 block">Krajina</label>
              <div class="gl-cs" id="cs-country">
                <button type="button" class="gl-cs-btn" aria-haspopup="listbox" aria-expanded="false"
                        onclick="glCsToggle('cs-country')">
                  <span class="gl-cs-label"><?= e($billingCountry ?: 'Slovensko') ?></span>
                  <svg class="gl-cs-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                  </svg>
                </button>
                <div class="gl-cs-panel" role="listbox">
                  <button type="button" class="gl-cs-option" role="option"
                          aria-selected="<?= ($billingCountry === '' || $billingCountry === 'Slovensko') ? 'true' : 'false' ?>"
                          data-value="Slovensko" onclick="glCsSelect('cs-country', this)">Slovensko</button>
                  <button type="button" class="gl-cs-option" role="option"
                          aria-selected="<?= $billingCountry === 'Česko' ? 'true' : 'false' ?>"
                          data-value="Česko" onclick="glCsSelect('cs-country', this)">Česko</button>
                </div>
                <input type="hidden" id="bl-country" value="<?= e($billingCountry ?: 'Slovensko') ?>">
              </div>
            </div>
            <button onclick="submitBillingData()"
              class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold
                     rounded-2xl transition-all duration-200 active:scale-95">
              Uložiť fakturačné údaje
            </button>
          </div>
        </div>
      </div>

      <!-- ── Faktúry ───────────────────────────────────────────────────────── -->
      <div id="tab-invoices" class="space-y-4 hidden">
        <div class="bg-white dark:bg-slate-900 rounded-[2rem] shadow-sm border border-gray-100 dark:border-slate-800 p-6">
          <p class="text-[10px] font-black uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-5">História platieb</p>

          <?php if (empty($orders)): ?>
          <div class="flex flex-col items-center justify-center py-10 text-center">
            <span class="text-3xl mb-3">🧾</span>
            <p class="text-sm font-semibold text-slate-500 dark:text-slate-400">Zatiaľ ste nerealizovali žiadne platby.</p>
            <a href="<?= url('plans') ?>"
               class="mt-4 inline-flex items-center gap-1.5 px-4 py-2 bg-indigo-600 hover:bg-indigo-700
                      text-white text-xs font-bold rounded-2xl transition active:scale-95">
              Zobraziť plány
            </a>
          </div>

          <?php else: ?>
          <div class="overflow-x-auto -mx-6 px-6">
            <table class="w-full text-xs">
              <thead>
                <tr class="border-b border-gray-100 dark:border-slate-800">
                  <th class="text-left font-black uppercase tracking-wider text-slate-400 dark:text-slate-500 pb-3 pr-4">Dátum</th>
                  <th class="text-left font-black uppercase tracking-wider text-slate-400 dark:text-slate-500 pb-3 pr-4">Plán</th>
                  <th class="text-left font-black uppercase tracking-wider text-slate-400 dark:text-slate-500 pb-3 pr-4">Suma</th>
                  <th class="text-left font-black uppercase tracking-wider text-slate-400 dark:text-slate-500 pb-3 pr-4">Stav</th>
                  <th class="text-left font-black uppercase tracking-wider text-slate-400 dark:text-slate-500 pb-3">Akcia</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-50 dark:divide-slate-800/60">
                <?php foreach ($orders as $order):
                  $statusLabel = match($order['status']) {
                      'paid'    => ['text' => 'Zaplatené', 'cls' => 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400'],
                      'failed'  => ['text' => 'Zlyhalo',   'cls' => 'bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400'],
                      default   => ['text' => 'Čaká',      'cls' => 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400'],
                  };
                  $planLabel = match($order['plan_name']) { 'pro'=>'Pro','ultra'=>'Ultra','custom'=>'Custom',default=>'Free' };
                  $date = $order['created_at'] ? date('d. m. Y', strtotime((string)$order['created_at'])) : '—';
                ?>
                <tr class="hover:bg-gray-50 dark:hover:bg-slate-800/30 transition-colors">
                  <td class="py-3 pr-4 text-slate-600 dark:text-slate-400 whitespace-nowrap"><?= e($date) ?></td>
                  <td class="py-3 pr-4 font-semibold text-slate-800 dark:text-slate-200"><?= e($planLabel) ?></td>
                  <td class="py-3 pr-4 text-slate-700 dark:text-slate-300 whitespace-nowrap">
                    <?= $order['amount'] > 0 ? number_format((float)$order['amount'], 2, ',', ' ') . ' ' . e((string)$order['currency']) : '—' ?>
                  </td>
                  <td class="py-3 pr-4">
                    <span class="inline-block px-2 py-0.5 rounded-full text-[10px] font-bold <?= $statusLabel['cls'] ?>">
                      <?= $statusLabel['text'] ?>
                    </span>
                  </td>
                  <td class="py-3">
                    <?php if ($order['status'] === 'paid' && !empty($order['invoice_id'])): ?>
                    <a href="<?= url('api/payments/download_invoice.php') ?>?order_id=<?= (int)$order['id'] ?>"
                       class="text-indigo-600 dark:text-indigo-400 hover:underline font-semibold whitespace-nowrap"
                       target="_blank">
                      Stiahnuť PDF
                    </a>
                    <?php elseif ($order['status'] === 'paid'): ?>
                    <span class="text-xs text-amber-500 dark:text-amber-400 font-medium">Spracováva sa…</span>
                    <?php else: ?>
                    <span class="text-slate-300 dark:text-slate-600">—</span>
                    <?php endif; ?>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php endif; ?>

        </div>
      </div>

      <!-- ── Import / Export ─────────────────────────────────────────────── -->
      <div id="tab-data" class="space-y-4 hidden">
        <div class="bg-white dark:bg-slate-900 rounded-[2rem] shadow-sm border border-gray-100 dark:border-slate-800 p-6">
          <p class="text-[10px] font-black uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-5">Export</p>

          <?php if (!empty($venueStats)): ?>
          <div class="space-y-4">
            <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
              Stiahnite celý jedálny lístok prevádzky ako CSV. Súbor môžete neskôr importovať do rovnakej alebo inej prevádzky.
            </p>
            <div class="space-y-2">
              <?php foreach ($venueStats as $vs): ?>
              <div class="flex items-center justify-between py-2.5 px-4 bg-gray-50 dark:bg-slate-800/50 rounded-2xl">
                <span class="text-xs font-semibold text-slate-700 dark:text-slate-300 truncate mr-3">📍 <?= e($vs['name']) ?></span>
                <form method="POST" action="<?= url('api/export_full.php') ?>" class="flex-shrink-0">
                  <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
                  <input type="hidden" name="slug" value="<?= e($vs['slug']) ?>">
                  <button type="submit"
                          class="px-3 py-1.5 bg-indigo-50 dark:bg-indigo-900/30 hover:bg-indigo-100
                                 dark:hover:bg-indigo-900/50 text-indigo-700 dark:text-indigo-400
                                 text-xs font-bold rounded-xl transition">
                    Stiahnuť CSV
                  </button>
                </form>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
          <?php else: ?>
          <p class="text-xs text-slate-400 dark:text-slate-500">Zatiaľ nemáte žiadne prevádzky.</p>
          <?php endif; ?>
        </div>

        <div class="bg-white dark:bg-slate-900 rounded-[2rem] shadow-sm border border-gray-100 dark:border-slate-800 p-6">
          <p class="text-[10px] font-black uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-5">Import</p>

          <?php if (!empty($venueStats)): ?>
          <div class="space-y-3">
            <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
              Nahrajte CSV (v rovnakom formáte ako export) do zvolenej prevádzky. Limity plánu budú rešpektované.
            </p>
            <div>
              <label class="text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1.5 block">Cieľová prevádzka</label>
              <div class="gl-cs" id="cs-import-slug">
                <button type="button" class="gl-cs-btn" aria-haspopup="listbox" aria-expanded="false"
                        onclick="glCsToggle('cs-import-slug')">
                  <span class="gl-cs-label"><?= e(!empty($venueStats) ? $venueStats[0]['name'] : '—') ?></span>
                  <svg class="gl-cs-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                  </svg>
                </button>
                <div class="gl-cs-panel" role="listbox">
                  <?php foreach ($venueStats as $i => $vs): ?>
                  <button type="button" class="gl-cs-option" role="option"
                          aria-selected="<?= $i === 0 ? 'true' : 'false' ?>"
                          data-value="<?= e($vs['slug']) ?>"
                          onclick="glCsSelect('cs-import-slug', this)"><?= e($vs['name']) ?></button>
                  <?php endforeach; ?>
                </div>
                <input type="hidden" id="import-slug" value="<?= e(!empty($venueStats) ? $venueStats[0]['slug'] : '') ?>">
              </div>
            </div>
            <div>
              <label class="text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1.5 block">CSV súbor</label>
              <input id="import-file" type="file" accept=".csv,text/csv"
                class="text-xs text-slate-500 dark:text-slate-400 w-full
                       file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0
                       file:text-xs file:font-semibold
                       file:bg-indigo-50 dark:file:bg-indigo-900/30
                       file:text-indigo-700 dark:file:text-indigo-400
                       hover:file:bg-indigo-100 dark:hover:file:bg-indigo-900/50">
            </div>
            <button onclick="submitImport()"
              class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold
                     rounded-2xl transition-all duration-200 active:scale-95">
              Importovať CSV
            </button>
          </div>
          <?php else: ?>
          <p class="text-xs text-slate-400 dark:text-slate-500">Zatiaľ nemáte žiadne prevádzky.</p>
          <?php endif; ?>
        </div>
      </div>

      <!-- ── Nebezpečná zóna ───────────────────────────────────────────── -->
      <div id="tab-danger" class="space-y-4 hidden">
        <div class="bg-white dark:bg-slate-900 rounded-[2rem] shadow-sm border border-red-100 dark:border-red-900/30 p-6">
          <p class="text-[10px] font-black uppercase tracking-wider text-red-400 mb-5">⚠️ Nebezpečná zóna</p>
          <p class="text-sm text-slate-600 dark:text-slate-400 mb-6 leading-relaxed">
            <strong class="text-slate-800 dark:text-slate-200">VAROVANIE: Táto akcia je nevratná.</strong>
            Všetky vaše prevádzky, jedálne lístky a nahrané fotografie budú okamžite a natrvalo zmazané.
          </p>
          <div class="space-y-3 max-w-md">
            <div>
              <label class="text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1.5 block">Aktuálne heslo</label>
              <input id="da-password" type="password" placeholder="Vaše aktuálne heslo"
                class="w-full bg-gray-100 dark:bg-slate-800 border-none rounded-xl px-4 py-2.5 text-sm
                       text-slate-900 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500
                       focus:outline-none focus:ring-2 focus:ring-red-500 transition-all duration-200"
                oninput="checkDeleteReady()">
            </div>
            <div>
              <label class="text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1.5 block">Potvrďte zmazanie</label>
              <input id="da-confirm" type="text" placeholder="Napíšte: ano chcem odstranit ucet"
                class="w-full bg-gray-100 dark:bg-slate-800 border-none rounded-xl px-4 py-2.5 text-sm
                       text-slate-900 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500
                       focus:outline-none focus:ring-2 focus:ring-red-500 transition-all duration-200"
                oninput="checkDeleteReady()">
            </div>
            <button id="da-submit" onclick="submitDeleteAccount()" disabled
              class="w-full py-2.5 bg-red-600 hover:bg-red-700 text-white text-sm font-bold
                     rounded-2xl transition-all duration-200 active:scale-95
                     disabled:opacity-40 disabled:pointer-events-none">
              Definitívne zmazať môj účet
            </button>
          </div>
        </div>
      </div>

    </div><!-- /content -->
  </div>
</div>

<!-- ── Cancel subscription modal ─────────────────────────────────── -->
<div id="modal-cancel-plan"
     class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 backdrop-blur-sm p-4"
     onclick="if(event.target===this)closeCancelModal()">
  <div class="bg-white dark:bg-slate-900 rounded-[2rem] shadow-2xl max-w-sm w-full p-6">
    <p class="text-base font-extrabold text-slate-900 dark:text-white mb-3">Zrušiť obnovu predplatného?</p>
    <p class="text-sm text-slate-600 dark:text-slate-400 leading-relaxed mb-5" id="cancel-modal-text">
      Chcete zrušiť automatické obnovovanie predplatného?
      Váš prístup a všetky dáta zostanú zachované do konca aktívneho obdobia.
    </p>
    <div class="flex flex-col gap-2">
      <button onclick="cancelPlanConfirm()" id="cancel-plan-btn"
        class="w-full py-2.5 bg-red-600 hover:bg-red-700 text-white text-sm font-bold
               rounded-2xl transition-all active:scale-95 disabled:opacity-50 disabled:pointer-events-none">
        Potvrdiť zrušenie obnovy
      </button>
      <button onclick="closeCancelModal()"
        class="w-full py-2.5 bg-gray-100 dark:bg-slate-800 hover:bg-gray-200 dark:hover:bg-slate-700
               text-slate-700 dark:text-slate-300 text-sm font-semibold rounded-2xl transition">
        Ponechať predplatné
      </button>
    </div>
  </div>
</div>

<script>
const CSRF = <?= json_encode(csrfToken()) ?>;

// ── Custom select ─────────────────────────────────────────────────
function glCsToggle(id) {
  const wrap  = document.getElementById(id);
  const panel = wrap.querySelector('.gl-cs-panel');
  const btn   = wrap.querySelector('.gl-cs-btn');
  const isOpen = panel.classList.contains('open');
  glCsCloseAll();
  if (!isOpen) {
    panel.classList.add('open');
    btn.setAttribute('aria-expanded', 'true');
  }
}

function glCsSelect(id, opt) {
  const wrap  = document.getElementById(id);
  const panel = wrap.querySelector('.gl-cs-panel');
  const btn   = wrap.querySelector('.gl-cs-btn');
  const label = wrap.querySelector('.gl-cs-label');
  const input = wrap.querySelector('input[type="hidden"]');
  wrap.querySelectorAll('.gl-cs-option').forEach(o => o.setAttribute('aria-selected', 'false'));
  opt.setAttribute('aria-selected', 'true');
  label.textContent = opt.textContent;
  input.value = opt.dataset.value;
  panel.classList.remove('open');
  btn.setAttribute('aria-expanded', 'false');
}

function glCsCloseAll() {
  document.querySelectorAll('.gl-cs-panel.open').forEach(p => {
    p.classList.remove('open');
    p.closest('.gl-cs')?.querySelector('.gl-cs-btn')?.setAttribute('aria-expanded', 'false');
  });
}

document.addEventListener('click', e => {
  if (!e.target.closest('.gl-cs')) glCsCloseAll();
});

// ── Tab switching ──────────────────────────────────────────────────
const TAB_IDS = ['account', 'security', 'plan', 'billing', 'invoices', 'data', 'danger'];
function switchTab(id) {
  TAB_IDS.forEach(t => {
    document.getElementById('tab-' + t)?.classList.toggle('hidden', t !== id);
    const btn = document.getElementById('tab-btn-' + t);
    if (!btn) return;
    const active = t === id;
    const danger = btn.dataset.danger === '1';
    const base   = 'w-full text-left px-3 py-2.5 rounded-xl transition-all duration-200';
    btn.className = `${base} ${active
      ? (danger ? 'bg-red-50 dark:bg-red-900/20' : 'bg-indigo-50 dark:bg-indigo-900/30')
      : 'hover:bg-gray-50 dark:hover:bg-slate-800'}`;
  });
}

// ── Network helpers ────────────────────────────────────────────────
function fetchWithTimeout(url, options, ms = 10000) {
  const ctrl = new AbortController();
  const id   = setTimeout(() => ctrl.abort(), ms);
  return fetch(url, { ...options, signal: ctrl.signal }).finally(() => clearTimeout(id));
}

// ── Toast ──────────────────────────────────────────────────────────
function toast(msg, type = 'info') {
  const el = document.createElement('div');
  el.className = `pointer-events-auto px-4 py-2.5 rounded-2xl text-white text-sm font-semibold
    shadow-xl transition-all
    ${type==='success' ? 'bg-emerald-600' : type==='error' ? 'bg-red-600' : 'bg-slate-800'}`;
  el.textContent = msg;
  document.getElementById('toast-wrap').appendChild(el);
  setTimeout(() => { el.style.opacity = '0'; setTimeout(() => el.remove(), 300); }, 3200);
}

// ── E-mail ─────────────────────────────────────────────────────────
async function submitUpdateProfile() {
  const email   = (document.getElementById('up-email')?.value || '').trim();
  const current = document.getElementById('up-current-password')?.value || '';
  if (!email || !current) { toast('Vyplňte e-mail aj aktuálne heslo.', 'error'); return; }
  try {
    const res = await fetchWithTimeout(<?= json_encode(url('api/update_profile.php')) ?>, {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ csrf: CSRF, email, current_password: current })
    });
    const data = await res.json();
    if (data.ok) {
      toast('E-mail bol úspešne zmenený.', 'success');
      document.getElementById('up-current-password').value = '';
    } else {
      toast(data.error || 'Chyba.', 'error');
    }
  } catch { toast('Sieťová chyba.', 'error'); }
}

// ── Heslo ──────────────────────────────────────────────────────────
async function submitPasswordChange() {
  const oldPw  = document.getElementById('cp-old').value;
  const newPw  = document.getElementById('cp-new').value;
  const newPw2 = document.getElementById('cp-new2').value;
  if (!oldPw || !newPw || !newPw2) { toast('Vyplňte všetky polia.', 'error'); return; }
  try {
    const res = await fetchWithTimeout(<?= json_encode(url('api/change_password.php')) ?>, {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ csrf: CSRF, old_password: oldPw, new_password: newPw, new_password2: newPw2 })
    });
    const data = await res.json();
    if (data.ok) {
      toast('Heslo bolo úspešne zmenené.', 'success');
      ['cp-old','cp-new','cp-new2'].forEach(id => { document.getElementById(id).value = ''; });
    } else {
      toast(data.error || 'Chyba.', 'error');
    }
  } catch { toast('Sieťová chyba.', 'error'); }
}

// ── Zmazanie účtu ──────────────────────────────────────────────────
function checkDeleteReady() {
  const pw   = document.getElementById('da-password')?.value || '';
  const conf = document.getElementById('da-confirm')?.value || '';
  const btn  = document.getElementById('da-submit');
  if (btn) btn.disabled = !(pw && conf === 'ano chcem odstranit ucet');
}

async function submitDeleteAccount() {
  const password     = document.getElementById('da-password')?.value || '';
  const confirmation = document.getElementById('da-confirm')?.value || '';
  try {
    const res = await fetchWithTimeout(<?= json_encode(url('api/delete_account.php')) ?>, {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ csrf: CSRF, current_password: password, confirmation_text: confirmation })
    });
    const data = await res.json();
    if (data.ok) {
      window.location.href = <?= json_encode(url('login')) ?>;
    } else {
      toast(data.error || 'Chyba.', 'error');
    }
  } catch { toast('Sieťová chyba.', 'error'); }
}

// ── Billing data ──────────────────────────────────────────────────
async function submitBillingData() {
  const company = (document.getElementById('bl-company')?.value  || '').trim();
  const ico     = (document.getElementById('bl-ico')?.value      || '').trim();
  const dic     = (document.getElementById('bl-dic')?.value      || '').trim();
  const icDph   = (document.getElementById('bl-ic-dph')?.value   || '').trim();
  const street  = (document.getElementById('bl-street')?.value   || '').trim();
  const city    = (document.getElementById('bl-city')?.value     || '').trim();
  const zip     = (document.getElementById('bl-zip')?.value      || '').trim();
  const country = (document.getElementById('bl-country')?.value  || '').trim();
  try {
    const res = await fetchWithTimeout(<?= json_encode(url('api/update_profile.php')) ?>, {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        csrf: CSRF, action: 'billing',
        company_name: company, ico, dic, ic_dph: icDph,
        street, city, zip, country
      })
    });
    const data = await res.json();
    if (data.ok) {
      toast('Fakturačné údaje boli uložené.', 'success');
    } else {
      toast(data.error || 'Chyba.', 'error');
    }
  } catch { toast('Sieťová chyba.', 'error'); }
}

// ── Import CSV ─────────────────────────────────────────────────────
async function submitImport() {
  const slug = document.getElementById('import-slug')?.value;
  const file = document.getElementById('import-file')?.files?.[0];
  if (!slug) { toast('Vyberte prevádzku.', 'error'); return; }
  if (!file) { toast('Vyberte CSV súbor.', 'error'); return; }

  const fd = new FormData();
  fd.append('csrf', CSRF);
  fd.append('slug', slug);
  fd.append('file', file);

  try {
    const res  = await fetchWithTimeout(<?= json_encode(url('api/import_full.php')) ?>, { method: 'POST', body: fd });
    const data = await res.json();
    if (data.ok) {
      toast(`Importovaných: ${data.imported} jedál. Preskočených: ${data.skipped}.`, 'success');
      document.getElementById('import-file').value = '';
    } else {
      toast(data.error || 'Chyba importu.', 'error');
    }
  } catch { toast('Sieťová chyba.', 'error'); }
}

// ── Cancel plan modal ──────────────────────────────────────────────
function openCancelModal() {
  const m = document.getElementById('modal-cancel-plan');
  // Update modal text with actual end date if available
  const endsAt = <?= json_encode($planEndsAt) ?>;
  const textEl = document.getElementById('cancel-modal-text');
  if (textEl && endsAt) {
    const date = new Date(endsAt).toLocaleDateString('sk-SK');
    textEl.textContent = `Chcete zrušiť automatické obnovovanie predplatného? Váš prístup zostane zachovaný do ${date}.`;
  }
  m?.classList.remove('hidden');
  m?.classList.add('flex');
}
function closeCancelModal() {
  const m = document.getElementById('modal-cancel-plan');
  m?.classList.add('hidden');
  m?.classList.remove('flex');
}

async function cancelPlanConfirm() {
  const btn = document.getElementById('cancel-plan-btn');
  if (btn) btn.disabled = true;

  try {
    const res  = await fetchWithTimeout(<?= json_encode(url('api/user_actions.php')) ?>, {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ csrf: CSRF, action: 'cancel_plan' })
    });
    const data = await res.json();
    if (data.ok) {
      if (data.deferred) {
        const date = data.ends_at ? new Date(data.ends_at).toLocaleDateString('sk-SK') : '';
        toast('Obnova zrušená. Prístup zachovaný' + (date ? ' do ' + date : '') + '.', 'success');
      } else {
        toast('Predplatné bolo prepnuté na Free.', 'success');
      }
      closeCancelModal();
      setTimeout(() => { window.location.reload(); }, 1500);
    } else {
      toast(data.error || 'Chyba.', 'error');
      if (btn) btn.disabled = false;
    }
  } catch {
    toast('Sieťová chyba.', 'error');
    if (btn) btn.disabled = false;
  }
}

// Init
switchTab('account');
</script>
<?php require __DIR__ . '/partials/footer.php'; ?>
