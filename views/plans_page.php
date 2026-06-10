<?php
$title  = 'Plány — GastroLink QR';
$robots = 'noindex, nofollow';
require __DIR__ . '/partials/header.php';
?>
<body class="bg-gray-50 dark:bg-slate-950 min-h-screen transition-colors duration-200">

<?php
$db       = getDB();
$userId   = (int)$_SESSION['user_id'];
$stUser   = $db->prepare("SELECT plan_name, max_venues, max_categories, max_items_per_cat FROM users WHERE id = ?");
$stUser->execute([$userId]);
$planRow  = $stUser->fetch() ?: [];
$userPlan = (string)($planRow['plan_name'] ?: 'free');
?>

<!-- ── NAVBAR ─────────────────────────────────────────────────────── -->
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

<!-- ── MAIN ───────────────────────────────────────────────────────── -->
<div class="max-w-5xl mx-auto px-4 py-10">

  <!-- Header -->
  <div class="text-center mb-10">
    <h1 class="text-3xl font-extrabold text-slate-900 dark:text-white mb-2">Vyberte si váš plán</h1>
    <p class="text-sm text-slate-500 dark:text-slate-400">Všetky ceny sú vrátane DPH 23 %</p>
  </div>

  <!-- ── Fixed Plans ───────────────────────────────────────────────── -->
  <div class="grid grid-cols-1 md:grid-cols-3 gap-5 mb-10">

    <?php
    $plans = [
      [
        'id'       => 'free',
        'name'     => 'Free',
        'price'    => '0 €',
        'period'   => 'navždy',
        'popular'  => false,
        'color'    => 'slate',
        'features' => ['1 prevádzka', '3 kategórie', '5 jedál / kategóriu', 'QR kód & živé menu'],
      ],
      [
        'id'       => 'pro',
        'name'     => 'Pro',
        'price'    => '8 €',
        'period'   => 'mesačne',
        'popular'  => true,
        'color'    => 'indigo',
        'features' => ['1 prevádzka', '10 kategórií', '25 jedál / kategóriu', 'QR kód & živé menu', 'Prioritná podpora'],
      ],
      [
        'id'       => 'ultra',
        'name'     => 'Ultra',
        'price'    => '15 €',
        'period'   => 'mesačne',
        'popular'  => false,
        'color'    => 'emerald',
        'features' => ['1 prevádzka', '20 kategórií', '50 jedál / kategóriu', 'QR kód & živé menu', 'Prioritná podpora', 'Vlastná doména'],
      ],
    ];
    foreach ($plans as $plan):
      $isCurrent  = $userPlan === $plan['id'];
      $isPopular  = $plan['popular'];
      $isIndigo   = $plan['color'] === 'indigo';
      $isEmerald  = $plan['color'] === 'emerald';
    ?>
    <div class="relative bg-white dark:bg-slate-900 rounded-[2rem] shadow-sm
                border <?= $isCurrent ? 'border-indigo-400 dark:border-indigo-600' : ($isPopular ? 'border-indigo-200 dark:border-indigo-800/50' : 'border-gray-100 dark:border-slate-800') ?>
                p-6 flex flex-col transition-all duration-200">

      <?php if ($isPopular): ?>
      <div class="absolute -top-3 left-1/2 -translate-x-1/2">
        <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest bg-indigo-600 text-white shadow-md">
          Najpopulárnejší
        </span>
      </div>
      <?php endif; ?>

      <?php if ($isCurrent): ?>
      <div class="absolute -top-3 right-5">
        <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest bg-emerald-500 text-white shadow-md">
          Aktuálny
        </span>
      </div>
      <?php endif; ?>

      <div class="mb-5">
        <p class="font-extrabold text-xl text-slate-900 dark:text-white mb-1"><?= $plan['name'] ?></p>
        <div class="flex items-baseline gap-1">
          <span class="text-4xl font-extrabold <?= $isIndigo ? 'text-indigo-600' : ($isEmerald ? 'text-emerald-600' : 'text-slate-700 dark:text-slate-300') ?>">
            <?= $plan['price'] ?>
          </span>
          <span class="text-xs text-slate-400">/ <?= $plan['period'] ?></span>
        </div>
      </div>

      <ul class="space-y-2 flex-1 mb-6">
        <?php foreach ($plan['features'] as $feat): ?>
        <li class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-400">
          <svg class="w-4 h-4 flex-shrink-0 <?= $isIndigo ? 'text-indigo-500' : ($isEmerald ? 'text-emerald-500' : 'text-slate-400') ?>"
               fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
          </svg>
          <?= e($feat) ?>
        </li>
        <?php endforeach; ?>
      </ul>

      <button onclick="selectPlan('<?= $plan['id'] ?>')"
              class="w-full py-3 rounded-2xl text-sm font-bold transition-all duration-200 active:scale-95
                     <?= $isCurrent
                       ? 'bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-400 border-2 border-emerald-300 dark:border-emerald-700 cursor-default'
                       : ($isIndigo
                         ? 'bg-indigo-600 hover:bg-indigo-700 text-white shadow-lg shadow-indigo-500/20'
                         : ($isEmerald
                           ? 'bg-emerald-600 hover:bg-emerald-700 text-white shadow-lg shadow-emerald-500/20'
                           : 'bg-gray-100 dark:bg-slate-800 hover:bg-gray-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300')) ?>">
        <?= $isCurrent ? '✓ Váš aktuálny plán' : 'Vybrať plán' ?>
      </button>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- ── Custom Plan ──────────────────────────────────────────────── -->
  <div class="bg-white dark:bg-slate-900 rounded-[2rem] shadow-sm border border-gray-100 dark:border-slate-800 p-6 md:p-8">
    <div class="flex items-start justify-between mb-6">
      <div>
        <div class="flex items-center gap-2 mb-1">
          <p class="font-extrabold text-xl text-slate-900 dark:text-white">Custom</p>
          <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-widest
                       bg-violet-100 dark:bg-violet-900/30 text-violet-600 dark:text-violet-400">
            Na mieru
          </span>
        </div>
        <p class="text-sm text-slate-500 dark:text-slate-400">Nastavte si presne to, čo potrebujete.</p>
      </div>
      <div class="text-right">
        <div class="text-4xl font-extrabold text-violet-600 dark:text-violet-400" id="custom-price">4,92 €</div>
        <div class="text-xs text-slate-400">mesačne s DPH</div>
      </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">

      <!-- Slider: Prevádzky -->
      <div>
        <div class="flex justify-between items-center mb-2">
          <label class="text-xs font-bold text-slate-700 dark:text-slate-300">Prevádzky</label>
          <span class="text-sm font-extrabold text-violet-600 dark:text-violet-400" id="val-venues">1</span>
        </div>
        <input type="range" id="slider-venues" min="1" max="10" value="1"
               oninput="updateCustomPrice()"
               class="w-full h-2 bg-gray-200 dark:bg-slate-700 rounded-full appearance-none cursor-pointer
                      accent-violet-600 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:ring-offset-2
                      dark:focus:ring-offset-slate-900">
        <div class="flex justify-between text-[10px] text-slate-400 mt-1">
          <span>1</span><span>10</span>
        </div>
      </div>

      <!-- Slider: Kategórie -->
      <div>
        <div class="flex justify-between items-center mb-2">
          <label class="text-xs font-bold text-slate-700 dark:text-slate-300">Kategórie / prev.</label>
          <span class="text-sm font-extrabold text-violet-600 dark:text-violet-400" id="val-cats">4</span>
        </div>
        <input type="range" id="slider-cats" min="4" max="50" value="4"
               oninput="updateCustomPrice()"
               class="w-full h-2 bg-gray-200 dark:bg-slate-700 rounded-full appearance-none cursor-pointer
                      accent-violet-600 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:ring-offset-2
                      dark:focus:ring-offset-slate-900">
        <div class="flex justify-between text-[10px] text-slate-400 mt-1">
          <span>4</span><span>50</span>
        </div>
      </div>

      <!-- Slider: Jedlá -->
      <div>
        <div class="flex justify-between items-center mb-2">
          <label class="text-xs font-bold text-slate-700 dark:text-slate-300">Max jedál / kat.</label>
          <span class="text-sm font-extrabold text-violet-600 dark:text-violet-400" id="val-items">6</span>
        </div>
        <input type="range" id="slider-items" min="6" max="100" value="6"
               oninput="updateCustomPrice()"
               class="w-full h-2 bg-gray-200 dark:bg-slate-700 rounded-full appearance-none cursor-pointer
                      accent-violet-600 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:ring-offset-2
                      dark:focus:ring-offset-slate-900">
        <div class="flex justify-between text-[10px] text-slate-400 mt-1">
          <span>6</span><span>100</span>
        </div>
      </div>
    </div>

    <!-- Price breakdown -->
    <div class="bg-slate-50 dark:bg-slate-800/50 rounded-2xl p-4 mb-6">
      <p class="text-[10px] font-black uppercase tracking-wider text-slate-400 mb-3">Výpočet ceny (bez DPH)</p>
      <div class="space-y-1 text-xs text-slate-600 dark:text-slate-400">
        <div class="flex justify-between"><span>Základný poplatok</span><span class="font-semibold">4,00 €</span></div>
        <div class="flex justify-between"><span id="breakdown-venues">Prevádzky (1 × 1,00 €)</span><span class="font-semibold" id="breakdown-venues-val">1,00 €</span></div>
        <div class="flex justify-between"><span id="breakdown-cats">Kategórie (4 × 0,10 €)</span><span class="font-semibold" id="breakdown-cats-val">0,40 €</span></div>
        <div class="flex justify-between"><span id="breakdown-items">Jedlá (6 × 0,05 €)</span><span class="font-semibold" id="breakdown-items-val">0,30 €</span></div>
        <div class="flex justify-between border-t border-slate-200 dark:border-slate-700 pt-1 mt-1">
          <span class="font-semibold text-slate-700 dark:text-slate-300">Celkom bez DPH</span>
          <span class="font-bold text-slate-700 dark:text-slate-300" id="breakdown-subtotal">5,70 €</span>
        </div>
        <div class="flex justify-between text-emerald-600 dark:text-emerald-400">
          <span>DPH 23 %</span>
          <span id="breakdown-vat">1,31 €</span>
        </div>
      </div>
    </div>

    <button onclick="selectPlan('custom')"
            class="w-full py-3 bg-violet-600 hover:bg-violet-700 text-white rounded-2xl
                   text-sm font-bold transition-all duration-200 active:scale-95
                   shadow-lg shadow-violet-500/20">
      Vybrať Custom plán
    </button>
  </div>

</div>

<script>
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

// ── Plan select (visual feedback only) ────────────────────────────
function selectPlan(planId) {
  const current = <?= json_encode($userPlan) ?>;
  if (planId === current) return;
  toast('Kontaktujte nás na info@gastrolink.sk pre aktiváciu plánu.', 'info');
}

// ── Custom price calculator ────────────────────────────────────────
function fmt(n) {
  return n.toFixed(2).replace('.', ',') + ' €';
}

function updateCustomPrice() {
  const venues = parseInt(document.getElementById('slider-venues').value);
  const cats   = parseInt(document.getElementById('slider-cats').value);
  const items  = parseInt(document.getElementById('slider-items').value);

  document.getElementById('val-venues').textContent = venues;
  document.getElementById('val-cats').textContent   = cats;
  document.getElementById('val-items').textContent  = items;

  const venueCost = venues * 1.00;
  const catCost   = cats   * 0.10;
  const itemCost  = items  * 0.05;
  const base      = 4.00;
  const subtotal  = base + venueCost + catCost + itemCost;
  const vat       = subtotal * 0.23;
  const total     = subtotal * 1.23;

  document.getElementById('custom-price').textContent = fmt(total);
  document.getElementById('breakdown-venues').textContent      = `Prevádzky (${venues} × 1,00 €)`;
  document.getElementById('breakdown-venues-val').textContent  = fmt(venueCost);
  document.getElementById('breakdown-cats').textContent        = `Kategórie (${cats} × 0,10 €)`;
  document.getElementById('breakdown-cats-val').textContent    = fmt(catCost);
  document.getElementById('breakdown-items').textContent       = `Jedlá (${items} × 0,05 €)`;
  document.getElementById('breakdown-items-val').textContent   = fmt(itemCost);
  document.getElementById('breakdown-subtotal').textContent    = fmt(subtotal);
  document.getElementById('breakdown-vat').textContent         = fmt(vat);
}

// Init
updateCustomPrice();
</script>
<?php require __DIR__ . '/partials/footer.php'; ?>
