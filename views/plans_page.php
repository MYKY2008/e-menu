<?php
$title  = 'Plány — GastroLink QR';
$robots = 'noindex, nofollow';
require __DIR__ . '/partials/header.php';
?>
<body class="bg-gray-50 dark:bg-slate-950 min-h-screen transition-colors duration-200">

<?php
$db       = getDB();
$userId   = (int)$_SESSION['user_id'];
$stUser   = $db->prepare("SELECT plan_name, max_venues, max_categories, max_items_per_cat, plan_ends_at, next_plan_name FROM users WHERE id = ?");
$stUser->execute([$userId]);
$planRow      = $stUser->fetch() ?: [];
$userPlan     = (string)($planRow['plan_name'] ?: 'free');
$planEndsAt   = $planRow['plan_ends_at']  ?? null;
$nextPlanName = $planRow['next_plan_name'] ?? null;
$csrf         = csrfToken();
?>

<style>
/* ── Premium slider ──────────────────────────────────────────────── */
.gl-slider {
  -webkit-appearance: none;
  appearance: none;
  height: 8px;
  border-radius: 999px;
  outline: none;
  cursor: pointer;
  background: linear-gradient(
    to right,
    #7c3aed var(--fill, 0%),
    #e5e7eb var(--fill, 0%)
  );
  transition: background 0.1s ease;
}
.dark .gl-slider {
  background: linear-gradient(
    to right,
    #7c3aed var(--fill, 0%),
    #334155 var(--fill, 0%)
  );
}

/* Webkit thumb — oval pill */
.gl-slider::-webkit-slider-thumb {
  -webkit-appearance: none;
  appearance: none;
  width: 30px;
  height: 20px;
  border-radius: 999px;
  background: #ffffff;
  border: 2.5px solid #7c3aed;
  box-shadow: 0 2px 10px rgba(124,58,237,0.30), 0 0 0 0 rgba(124,58,237,0);
  cursor: grab;
  transition: transform 0.15s ease, box-shadow 0.15s ease;
}
.gl-slider::-webkit-slider-thumb:hover {
  transform: scale(1.12);
  box-shadow: 0 4px 16px rgba(124,58,237,0.40);
}
.gl-slider::-webkit-slider-thumb:active {
  cursor: grabbing;
  transform: scale(0.94);
}
.dark .gl-slider::-webkit-slider-thumb {
  background: #1e1b4b;
  border-color: #a78bfa;
  box-shadow: 0 2px 10px rgba(167,139,250,0.35);
}

/* Firefox thumb */
.gl-slider::-moz-range-thumb {
  width: 30px;
  height: 20px;
  border-radius: 999px;
  background: #ffffff;
  border: 2.5px solid #7c3aed;
  box-shadow: 0 2px 10px rgba(124,58,237,0.30);
  cursor: grab;
  transition: transform 0.15s ease;
}
.gl-slider::-moz-range-thumb:active {
  cursor: grabbing;
  transform: scale(0.94);
}
.dark .gl-slider::-moz-range-thumb {
  background: #1e1b4b;
  border-color: #a78bfa;
}

/* Step buttons */
.step-btn {
  width: 2.5rem; height: 2.5rem;
  border-radius: 9999px;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
  user-select: none;
  cursor: pointer;
  transition: transform 0.12s ease, background 0.15s ease;
  background: rgba(124,58,237,0.10);
  color: #7c3aed;
  border: none;
}
.step-btn:hover  { background: rgba(124,58,237,0.18); }
.step-btn:active { transform: scale(0.88); }
.dark .step-btn  { background: rgba(167,139,250,0.12); color: #a78bfa; }
.dark .step-btn:hover { background: rgba(167,139,250,0.22); }
</style>

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
    <p class="text-sm text-slate-500 dark:text-slate-400">Ceny sú uvedené bez DPH</p>
  </div>

  <?php if ($nextPlanName !== null): ?>
  <?php $nextLabel = match($nextPlanName) { 'pro'=>'Pro','ultra'=>'Ultra','custom'=>'Custom',default=>'Free' }; ?>
  <div class="mb-8 p-4 bg-blue-50 dark:bg-blue-900/20 rounded-2xl border border-blue-100 dark:border-blue-800/40 flex items-start gap-3">
    <span class="text-xl flex-shrink-0">🔔</span>
    <p class="text-sm text-blue-700 dark:text-blue-300 leading-relaxed">
      Váš súčasný plán <strong><?= e(match($userPlan) { 'pro'=>'Pro','ultra'=>'Ultra','custom'=>'Custom',default=>'Free' }) ?></strong>
      zostáva plne aktívny<?php if ($planEndsAt): ?> do <strong><?= e(date('d. m. Y', strtotime((string)$planEndsAt))) ?></strong><?php endif; ?>.
      Potom sa automaticky prepne na <strong><?= e($nextLabel) ?></strong>.
    </p>
  </div>
  <?php endif; ?>

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
      $isCurrent = $userPlan === $plan['id'];
      $isIndigo  = $plan['color'] === 'indigo';
      $isEmerald = $plan['color'] === 'emerald';
    ?>
    <div class="relative bg-white dark:bg-slate-900 rounded-[2rem] shadow-sm p-6 flex flex-col
                transition-all duration-200
                border <?= $isCurrent
                          ? 'border-indigo-400 dark:border-indigo-600'
                          : ($plan['popular']
                            ? 'border-indigo-200 dark:border-indigo-800/50'
                            : 'border-gray-100 dark:border-slate-800') ?>">

      <?php if ($plan['popular']): ?>
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

    <!-- Header -->
    <div class="flex flex-wrap items-start justify-between gap-4 mb-8">
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
        <div class="text-4xl font-extrabold text-violet-600 dark:text-violet-400 transition-all duration-200" id="custom-price">8,40 €</div>
        <div class="text-xs text-slate-400 mt-0.5">mesačne bez DPH</div>
      </div>
    </div>

    <!-- Sliders grid -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-8 mb-8">

      <!-- Slider: Prevádzky -->
      <div>
        <div class="flex justify-between items-center mb-3">
          <label class="text-xs font-bold text-slate-600 dark:text-slate-400 uppercase tracking-wide">Prevádzky</label>
          <span class="text-lg font-black text-violet-600 dark:text-violet-400 tabular-nums transition-all duration-150" id="val-venues">1</span>
        </div>
        <div class="flex items-center gap-3">
          <button class="step-btn" onclick="stepSlider('slider-venues',-1)" aria-label="Menej">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14"/>
            </svg>
          </button>
          <div class="flex-1 py-3">
            <input type="range" id="slider-venues" class="gl-slider w-full" min="1" max="10" value="1"
                   oninput="updateCustomPrice()">
          </div>
          <button class="step-btn" onclick="stepSlider('slider-venues',1)" aria-label="Viac">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14"/>
            </svg>
          </button>
        </div>
        <div class="flex justify-between text-[10px] text-slate-400 mt-1 px-1">
          <span>1</span><span>10</span>
        </div>
      </div>

      <!-- Slider: Kategórie -->
      <div>
        <div class="flex justify-between items-center mb-3">
          <label class="text-xs font-bold text-slate-600 dark:text-slate-400 uppercase tracking-wide">Kategórie / prev.</label>
          <span class="text-lg font-black text-violet-600 dark:text-violet-400 tabular-nums transition-all duration-150" id="val-cats">4</span>
        </div>
        <div class="flex items-center gap-3">
          <button class="step-btn" onclick="stepSlider('slider-cats',-1)" aria-label="Menej">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14"/>
            </svg>
          </button>
          <div class="flex-1 py-3">
            <input type="range" id="slider-cats" class="gl-slider w-full" min="4" max="50" value="4"
                   oninput="updateCustomPrice()">
          </div>
          <button class="step-btn" onclick="stepSlider('slider-cats',1)" aria-label="Viac">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14"/>
            </svg>
          </button>
        </div>
        <div class="flex justify-between text-[10px] text-slate-400 mt-1 px-1">
          <span>4</span><span>50</span>
        </div>
      </div>

      <!-- Slider: Jedlá -->
      <div>
        <div class="flex justify-between items-center mb-3">
          <label class="text-xs font-bold text-slate-600 dark:text-slate-400 uppercase tracking-wide">Max jedál / kat.</label>
          <span class="text-lg font-black text-violet-600 dark:text-violet-400 tabular-nums transition-all duration-150" id="val-items">6</span>
        </div>
        <div class="flex items-center gap-3">
          <button class="step-btn" onclick="stepSlider('slider-items',-1)" aria-label="Menej">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14"/>
            </svg>
          </button>
          <div class="flex-1 py-3">
            <input type="range" id="slider-items" class="gl-slider w-full" min="6" max="100" value="6"
                   oninput="updateCustomPrice()">
          </div>
          <button class="step-btn" onclick="stepSlider('slider-items',1)" aria-label="Viac">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14"/>
            </svg>
          </button>
        </div>
        <div class="flex justify-between text-[10px] text-slate-400 mt-1 px-1">
          <span>6</span><span>100</span>
        </div>
      </div>
    </div>

    <!-- Price breakdown -->
    <div class="bg-slate-50 dark:bg-slate-800/50 rounded-2xl p-4 mb-6">
      <p class="text-[10px] font-black uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-3">Výpočet ceny (bez DPH)</p>
      <div class="space-y-1.5 text-xs text-slate-600 dark:text-slate-400">
        <div class="flex justify-between">
          <span>Základná réžia</span>
          <span class="font-semibold text-slate-700 dark:text-slate-300">5,00 €</span>
        </div>
        <div class="flex justify-between">
          <span id="breakdown-venues">Prevádzky (1 × 2,00 €)</span>
          <span class="font-semibold text-slate-700 dark:text-slate-300" id="breakdown-venues-val">2,00 €</span>
        </div>
        <div class="flex justify-between">
          <span id="breakdown-cats">Kategórie (4 × 0,20 €)</span>
          <span class="font-semibold text-slate-700 dark:text-slate-300" id="breakdown-cats-val">0,80 €</span>
        </div>
        <div class="flex justify-between">
          <span id="breakdown-items">Jedlá (6 × 0,10 €)</span>
          <span class="font-semibold text-slate-700 dark:text-slate-300" id="breakdown-items-val">0,60 €</span>
        </div>
        <div class="flex justify-between border-t border-slate-200 dark:border-slate-700 pt-2 mt-1">
          <span class="font-semibold text-violet-600 dark:text-violet-400">Celková cena bez DPH</span>
          <span class="font-bold text-violet-600 dark:text-violet-400" id="breakdown-subtotal">8,40 €</span>
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

// ── Plan select ───────────────────────────────────────────────────
const CSRF = <?= json_encode($csrf) ?>;

async function selectPlan(planId) {
  const current   = <?= json_encode($userPlan) ?>;
  const endsAt    = <?= json_encode($planEndsAt) ?>;
  const planOrder = { free: 0, pro: 1, ultra: 2, custom: 3 };
  if (planId === current) return;

  // Downgrade — spravuje profil
  const isDowngrade = (planOrder[planId] ?? 0) < (planOrder[current] ?? 0);
  if (isDowngrade && endsAt) {
    const date = new Date(endsAt).toLocaleDateString('sk-SK');
    toast(`Zmenu plánu môžete spravovať v profile. Downgrade sa aktivuje po uplynutí predplatného (${date}).`, 'info');
    return;
  }

  // Free plán — len info
  if (planId === 'free') {
    toast('Prejdite do profilu a zrušte predplatné pre prechod na Free.', 'info');
    return;
  }

  // Platené plány — Stripe Checkout
  const btn = document.querySelector(`button[onclick="selectPlan('${planId}')"]`);
  const origText = btn?.textContent ?? '';
  if (btn) { btn.disabled = true; btn.textContent = '⏳ Presmerovania…'; }

  try {
    const res = await fetch('<?= url('api/payments/create_session.php') ?>', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ csrf: CSRF, plan_id: planId }),
    });
    const data = await res.json();
    if (data.ok && data.url) {
      window.location.href = data.url;
    } else {
      toast(data.error || 'Chyba pri inicializácii platby.', 'error');
      if (btn) { btn.disabled = false; btn.textContent = origText; }
    }
  } catch (error) {
    console.error('Stripe Fetch Error:', error);
    toast('Chyba komunikácie so serverom. Skontrolujte konzolu (F12) alebo error.log.', 'error');
    if (btn) { btn.disabled = false; btn.textContent = origText; }
  }
}

// ── Slider fill ───────────────────────────────────────────────────
function updateFill(el) {
  const min = parseFloat(el.min);
  const max = parseFloat(el.max);
  const val = parseFloat(el.value);
  const pct = ((val - min) / (max - min)) * 100;
  el.style.setProperty('--fill', pct.toFixed(2) + '%');
}

// ── Step buttons ──────────────────────────────────────────────────
function stepSlider(id, delta) {
  const el = document.getElementById(id);
  el.value = Math.min(parseInt(el.max), Math.max(parseInt(el.min), parseInt(el.value) + delta));
  updateCustomPrice();
}

// ── Format number ─────────────────────────────────────────────────
function fmt(n) {
  return n.toFixed(2).replace('.', ',') + ' €';
}

// ── Custom price calculator ───────────────────────────────────────
// Coefficients (bez DPH): base=5€, venue=2€, cat=0.20€, item=0.10€
function updateCustomPrice() {
  const sV = document.getElementById('slider-venues');
  const sC = document.getElementById('slider-cats');
  const sI = document.getElementById('slider-items');

  updateFill(sV);
  updateFill(sC);
  updateFill(sI);

  const venues = parseInt(sV.value);
  const cats   = parseInt(sC.value);
  const items  = parseInt(sI.value);

  document.getElementById('val-venues').textContent = venues;
  document.getElementById('val-cats').textContent   = cats;
  document.getElementById('val-items').textContent  = items;

  const venueCost = venues * 2.00;
  const catCost   = cats   * 0.20;
  const itemCost  = items  * 0.10;
  const base      = 5.00;
  const subtotal  = base + venueCost + catCost + itemCost;

  document.getElementById('custom-price').textContent         = fmt(subtotal);
  document.getElementById('breakdown-venues').textContent     = `Prevádzky (${venues} × 2,00 €)`;
  document.getElementById('breakdown-venues-val').textContent = fmt(venueCost);
  document.getElementById('breakdown-cats').textContent       = `Kategórie (${cats} × 0,20 €)`;
  document.getElementById('breakdown-cats-val').textContent   = fmt(catCost);
  document.getElementById('breakdown-items').textContent      = `Jedlá (${items} × 0,10 €)`;
  document.getElementById('breakdown-items-val').textContent  = fmt(itemCost);
  document.getElementById('breakdown-subtotal').textContent   = fmt(subtotal);
}

// Init
updateCustomPrice();
</script>
<?php require __DIR__ . '/partials/footer.php'; ?>
