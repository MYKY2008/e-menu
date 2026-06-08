<?php
/** GastroLink QR — Client Menu View
 *  Requires: $routeSlug (set by index.php), config.php already loaded
 */
$db   = getDB();
$slug = $routeSlug ?? '';

if (!$slug) { http_response_code(404); exit; }

$vSt = $db->prepare("SELECT * FROM venues WHERE slug = ?");
$vSt->execute([$slug]);
$venue = $vSt->fetch();

if (!$venue) {
    http_response_code(404);
    echo '<!DOCTYPE html><html lang="sk"><head><meta charset="UTF-8"><title>Nenájdené</title></head>'
       . '<body style="font-family:system-ui;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;background:#f8fafc">'
       . '<div style="text-align:center"><p style="font-size:3rem">🔍</p>'
       . '<h1 style="font-size:1.25rem;color:#1e293b">Prevádzka neexistuje</h1>'
       . '<a href="/" style="color:#6366f1;font-size:.875rem">← Späť na úvod</a></div></body></html>';
    exit;
}

// Categories + items
$catSt = $db->prepare("SELECT * FROM categories WHERE venue_slug = ? ORDER BY sort_order, id");
$catSt->execute([$slug]);
$categories    = [];
$featuredItems = [];
foreach ($catSt->fetchAll() as $cat) {
    $iSt = $db->prepare("SELECT * FROM items WHERE category_id = ? ORDER BY sort_order, id");
    $iSt->execute([(int)$cat['id']]);
    $cat['items'] = $iSt->fetchAll();
    $categories[] = $cat;
    foreach ($cat['items'] as $it) { if ($it['is_featured']) $featuredItems[] = $it; }
}

// Settings
$ssSt = $db->prepare("SELECT * FROM venue_settings WHERE venue_slug = ?");
$ssSt->execute([$slug]);
$settings = $ssSt->fetch() ?: [
    'show_allergens'         => 1,
    'show_featured'          => 1,
    'default_category_color' => '#1E3A5F',
    'default_item_color'     => '#FFFFFF',
    'dark_mode_default'      => 0,
];

$accentArr  = resolveColor($venue['color']);
$accentHex  = $accentArr['hex'];
$accentText = menuTextColor($accentHex);

$accentIsLight  = ($accentText === '#1e293b');
$accentOverlay  = $accentIsLight ? 'rgba(0,0,0,.1)'  : 'rgba(255,255,255,.2)';
$accentOverlayH = $accentIsLight ? 'rgba(0,0,0,.16)' : 'rgba(255,255,255,.3)';

$darkDefault = (int)($settings['dark_mode_default'] ?? 0);
$defCatBg    = $settings['default_category_color'] ?? '#1E3A5F';
$defItemBg   = $settings['default_item_color']     ?? '#FFFFFF';

$quickActions = [];
if (!empty($venue['google_url']))    $quickActions[] = ['url'=>$venue['google_url'],    'emoji'=>'⭐','label'=>'Google'];
if (!empty($venue['instagram_url'])) $quickActions[] = ['url'=>$venue['instagram_url'], 'emoji'=>'📷','label'=>'Instagram'];

$AL = [
    1  => 'Obilniny obsahujúce lepok (pšenica, raž, jačmeň, ovos...)',
    2  => 'Kôrovce a výrobky z nich',
    3  => 'Vajcia a výrobky z nich',
    4  => 'Ryby a výrobky z nich',
    5  => 'Arašidy a výrobky z nich',
    6  => 'Sója a výrobky z nej',
    7  => 'Mlieko a výrobky z neho (vrátane laktózy)',
    8  => 'Orechy (mandle, vlašské orechy, kešu, pekanové, pistácie...)',
    9  => 'Zeler a výrobky z neho',
    10 => 'Horčica a výrobky z nej',
    11 => 'Sezamové semená a výrobky z nich',
    12 => 'Oxid siričitý a siričitany (> 10 mg/kg alebo 10 mg/l)',
    13 => 'Vlčí bôb a výrobky z neho',
    14 => 'Mäkkýše a výrobky z nich',
];
?>
<!DOCTYPE html>
<html lang="sk" class="">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0,viewport-fit=cover">
<meta name="theme-color" content="<?= e($accentHex) ?>">
<meta name="description" content="Jedálny lístok — <?= e($venue['name']) ?>">
<title><?= e($venue['name']) ?> — Menu</title>

<!-- Anti-flash: set dark class BEFORE any render -->
<script>
(function(){
  var s=localStorage.getItem('gl-dark'),d=<?= $darkDefault ?>;
  if(s!==null?s==='1':d===1)document.documentElement.classList.add('dark');
})();
</script>

<script src="https://cdn.tailwindcss.com"></script>
<script>tailwind.config={darkMode:'class'}</script>

<style>
*,*::before,*::after{box-sizing:border-box;-webkit-tap-highlight-color:transparent}
html{scroll-behavior:smooth}

/* Safe-area insets (iPhone notch) */
.pt-safe{padding-top:max(1.25rem,env(safe-area-inset-top))}
.pb-safe{padding-bottom:max(1.5rem,env(safe-area-inset-bottom))}

/* Hide scrollbar */
.no-scrollbar{-ms-overflow-style:none;scrollbar-width:none}
.no-scrollbar::-webkit-scrollbar{display:none}

/* Bottom sheet slide-up animation */
.sheet{
  transform:translateX(-50%) translateY(105%);
  transition:transform .38s cubic-bezier(.32,.72,0,1);
}
.sheet.open{transform:translateX(-50%) translateY(0)}

/* Sheet overlay fade */
.overlay{opacity:0;pointer-events:none;transition:opacity .3s ease}
.overlay.open{opacity:1;pointer-events:auto}

/* Sticky nav active pill */
.cat-pill{transition:background .2s,color .2s,box-shadow .2s}
.cat-pill.active{
  background:<?= e($accentHex) ?> !important;
  color:<?= e($accentText) ?> !important;
}

/* Line-clamp polyfill */
.clamp2{display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}

/* Desktop: show gray margin around the "phone" */
@media(min-width:30rem){
  body{background:#cbd5e1}
  html.dark body{background:#020617}
}
</style>
</head>
<body class="bg-white dark:bg-slate-950 min-h-screen">

<!-- ══ APP SHELL ═══════════════════════════════════════════════════ -->
<div id="app"
     class="w-full max-w-md mx-auto min-h-screen
            bg-white dark:bg-slate-900 shadow-2xl
            relative flex flex-col">

  <!-- ── HEADER ──────────────────────────────────────────────────── -->
  <header class="flex-shrink-0" style="background:<?= e($accentHex) ?>">
    <div class="pt-safe px-4 pb-5">

      <div class="flex items-start justify-between gap-3 mt-1">
        <div class="flex-1 min-w-0">
          <?php if (!empty($venue['logo'])): ?>
          <img src="<?= e($venue['logo']) ?>" alt="Logo"
               class="w-11 h-11 object-contain rounded-xl mb-3 shadow-md"
               style="outline:2px solid <?= $accentOverlayH ?>">
          <?php endif; ?>
          <h1 class="text-2xl font-extrabold leading-tight tracking-tight"
              style="color:<?= e($accentText) ?>"><?= e($venue['name']) ?></h1>
        </div>

        <!-- Sun / Moon toggle -->
        <button id="dark-btn" onclick="toggleDark()" aria-label="Prepnúť tmavý režim"
                class="w-9 h-9 rounded-full flex-shrink-0 flex items-center justify-center
                       transition active:scale-90"
                style="background:<?= $accentOverlay ?>;color:<?= e($accentText) ?>">
          <span id="dark-icon" class="w-4.5 h-4.5 block"></span>
        </button>
      </div>

      <!-- Quick actions: Google + Instagram only -->
      <?php if (!empty($quickActions)): ?>
      <div class="flex flex-wrap gap-2 mt-4">
        <?php foreach ($quickActions as $qa): ?>
        <a href="<?= e($qa['url']) ?>" target="_blank" rel="noopener noreferrer"
           class="flex items-center gap-1.5 px-3 py-1.5 rounded-full
                  text-xs font-semibold transition active:scale-95"
           style="background:<?= $accentOverlay ?>;color:<?= e($accentText) ?>">
          <span aria-hidden="true"><?= $qa['emoji'] ?></span>
          <span><?= e($qa['label']) ?></span>
        </a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </header>

  <!-- ── STICKY CATEGORY NAV ─────────────────────────────────────── -->
  <?php if (!empty($categories)): ?>
  <nav id="cat-nav"
       class="sticky top-0 z-40 flex-shrink-0
              bg-white/90 dark:bg-slate-900/90
              backdrop-blur-md
              border-b border-slate-100 dark:border-slate-800">
    <div class="flex gap-1 overflow-x-auto no-scrollbar px-3 py-2.5">
      <?php foreach ($categories as $cat): ?>
      <button onclick="scrollToSection(<?= (int)$cat['id'] ?>)"
              id="pill-<?= (int)$cat['id'] ?>"
              class="cat-pill flex-none px-3.5 py-1.5 rounded-full text-xs
                     font-semibold whitespace-nowrap
                     text-slate-500 dark:text-slate-400
                     hover:bg-slate-100 dark:hover:bg-slate-800">
        <?= e($cat['icon']) ?> <?= e($cat['name']) ?>
      </button>
      <?php endforeach; ?>
    </div>
  </nav>
  <?php endif; ?>

  <!-- ── FEATURED STRIP ──────────────────────────────────────────── -->
  <?php if (!empty($settings['show_featured']) && !empty($featuredItems)): ?>
  <section class="flex-shrink-0 px-4 pt-5 pb-3 border-b border-slate-100 dark:border-slate-800">
    <h2 class="text-[10px] font-black uppercase tracking-[.14em] mb-3
               text-slate-400 dark:text-slate-500">⭐ Odporúčame</h2>
    <div class="flex gap-3 overflow-x-auto no-scrollbar pb-1">
      <?php foreach ($featuredItems as $fi):
        $fbg = $fi['bg_color'] ?: $defItemBg;
        $ftc = menuTextColor($fbg);
        $fpr = number_format((float)$fi['price'], 2, ',', '');
      ?>
      <button onclick='openSheet(<?= json_encode($fi, JSON_HEX_APOS | JSON_UNESCAPED_UNICODE) ?>)'
              class="flex-none w-36 rounded-2xl p-3.5 text-left shadow-sm
                     active:scale-95 transition-transform"
              style="background:<?= e($fbg) ?>">
        <p class="font-bold text-sm leading-snug clamp2 mb-2.5"
           style="color:<?= e($ftc) ?>"><?= e($fi['name']) ?></p>
        <span class="inline-block text-xs font-extrabold px-2 py-1 rounded-lg"
              style="background:<?= e($accentHex) ?>;color:<?= e($accentText) ?>">
          <?= $fpr ?> €
        </span>
      </button>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <!-- ── MENU SECTIONS ───────────────────────────────────────────── -->
  <main class="flex-1 pb-safe">

    <?php if (empty($categories)): ?>
    <div class="flex flex-col items-center justify-center py-24 px-8 text-center">
      <p class="text-5xl mb-5">🍽️</p>
      <h2 class="text-lg font-bold text-slate-700 dark:text-slate-300">
        Jedálny lístok sa pripravuje
      </h2>
      <p class="text-sm text-slate-400 dark:text-slate-500 mt-2 leading-relaxed">
        Táto prevádzka zatiaľ nezverejnila jedálny lístok.
      </p>
    </div>

    <?php else: foreach ($categories as $catIdx => $cat):
      $catBg  = $cat['bg_color'] ?: $defCatBg;
      $catTc  = menuTextColor($catBg);
      $catMut = menuMutedColor($catBg);
    ?>
    <section id="cat-<?= (int)$cat['id'] ?>"
             class="<?= $catIdx > 0 ? 'mt-0.5' : '' ?>">

      <!-- Category header -->
      <div class="px-4 py-3.5" style="background:<?= e($catBg) ?>">
        <div class="flex items-center gap-2.5">
          <?php if (!empty($cat['icon'])): ?>
          <span class="text-xl leading-none" aria-hidden="true"><?= e($cat['icon']) ?></span>
          <?php endif; ?>
          <h2 class="font-extrabold text-base tracking-tight"
              style="color:<?= e($catTc) ?>"><?= e($cat['name']) ?></h2>
        </div>
      </div>

      <!-- Items -->
      <div class="px-3 pt-2 pb-2 space-y-2 bg-white dark:bg-slate-900">
        <?php if (empty($cat['items'])): ?>
        <p class="text-xs text-slate-400 dark:text-slate-600 text-center py-4">
          Žiadne jedlá v tejto kategórii.
        </p>
        <?php else: foreach ($cat['items'] as $item):
          $ibg   = $item['bg_color'] ?: $defItemBg;
          $itc   = menuTextColor($ibg);
          $imt   = menuMutedColor($ibg);
          $ipr   = number_format((float)$item['price'], 2, ',', '');
          $algN  = array_values(array_unique(array_filter(
              array_map('intval', array_filter(explode(',', (string)($item['allergens'] ?? '')), 'strlen')),
              fn($n) => $n >= 1 && $n <= 14
          )));
          $algDot = ($itc === '#ffffff') ? 'rgba(255,255,255,.22)' : 'rgba(0,0,0,.09)';
        ?>
        <button class="w-full text-left rounded-2xl overflow-hidden shadow-sm
                       active:scale-[.98] transition-transform"
                style="background:<?= e($ibg) ?>"
                onclick='openSheet(<?= json_encode($item, JSON_HEX_APOS | JSON_UNESCAPED_UNICODE) ?>)'>
          <div class="flex items-start gap-3 p-3.5">

            <!-- Text -->
            <div class="flex-1 min-w-0">
              <p class="font-semibold text-sm leading-snug" style="color:<?= e($itc) ?>">
                <?= e($item['name']) ?>
              </p>
              <?php if (!empty($item['weight'])): ?>
              <p class="text-[11px] font-medium mt-0.5 opacity-80" style="color:<?= e($imt) ?>">
                <?= e($item['weight']) ?>
              </p>
              <?php endif; ?>
              <?php if (!empty($item['description'])): ?>
              <p class="text-xs mt-1 leading-relaxed clamp2" style="color:<?= e($imt) ?>">
                <?= e($item['description']) ?>
              </p>
              <?php endif; ?>
              <?php if (!empty($settings['show_allergens']) && !empty($algN)): ?>
              <div class="flex flex-wrap gap-1 mt-2">
                <?php foreach ($algN as $aNum): ?>
                <span class="w-4 h-4 rounded-full text-[9px] font-bold
                             flex items-center justify-center leading-none"
                      style="background:<?= $algDot ?>;color:<?= e($imt) ?>">
                  <?= (int)$aNum ?>
                </span>
                <?php endforeach; ?>
              </div>
              <?php endif; ?>
            </div>

            <!-- Price -->
            <span class="flex-shrink-0 self-start mt-0.5 px-2.5 py-1 rounded-xl
                         text-xs font-extrabold leading-snug whitespace-nowrap"
                  style="background:<?= e($accentHex) ?>;color:<?= e($accentText) ?>">
              <?= $ipr ?> €
            </span>
          </div>
        </button>
        <?php endforeach; endif; ?>
      </div>
    </section>
    <?php endforeach; endif; ?>
  </main>

  <!-- ── OVERLAY ─────────────────────────────────────────────────── -->
  <div id="overlay" onclick="closeSheet()"
       class="overlay fixed inset-0 bg-black/60 dark:bg-black/75 z-40"></div>

  <!-- ── BOTTOM SHEET ────────────────────────────────────────────── -->
  <article id="sheet" role="dialog" aria-modal="true" aria-labelledby="sheet-name"
       class="sheet fixed bottom-0 left-1/2 w-full max-w-md z-50
              bg-white dark:bg-slate-900 rounded-t-3xl shadow-2xl
              max-h-[88dvh] flex flex-col overflow-hidden">

    <!-- Sheet top bar -->
    <div class="flex-shrink-0 px-5 pt-3 pb-4
                bg-white dark:bg-slate-900
                border-b border-slate-100 dark:border-slate-800">
      <div class="w-10 h-1 bg-slate-200 dark:bg-slate-700 rounded-full mx-auto mb-4"
           aria-hidden="true"></div>
      <div class="flex items-start justify-between gap-3">
        <div class="flex-1 min-w-0">
          <h2 id="sheet-name"
              class="text-xl font-extrabold text-slate-900 dark:text-slate-100 leading-tight"></h2>
          <p id="sheet-weight"
             class="text-sm text-slate-500 dark:text-slate-400 mt-0.5 hidden"></p>
        </div>
        <button onclick="closeSheet()" aria-label="Zavrieť"
                class="w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-800
                       text-slate-500 dark:text-slate-400
                       flex items-center justify-center flex-shrink-0
                       text-sm leading-none transition active:scale-90">
          ✕
        </button>
      </div>
      <p id="sheet-price" class="text-2xl font-extrabold mt-2"
         style="color:<?= e($accentHex) ?>"></p>
    </div>

    <!-- Sheet scrollable body -->
    <div id="sheet-body" class="flex-1 overflow-y-auto overscroll-contain px-5 py-5">
      <p id="sheet-desc"
         class="text-sm text-slate-600 dark:text-slate-400 leading-relaxed hidden"></p>

      <div id="sheet-allergens"
           class="hidden mt-5 pt-4 border-t border-slate-100 dark:border-slate-800">
        <h3 class="text-[10px] font-black uppercase tracking-[.12em] mb-3
                   text-slate-400 dark:text-slate-500">Alergény</h3>
        <ul id="sheet-allergen-list" class="space-y-2"></ul>
      </div>
    </div>

    <div class="flex-shrink-0 pb-safe bg-white dark:bg-slate-900"></div>
  </article>

</div><!-- /#app -->

<script>
const ACCENT      = <?= json_encode($accentHex) ?>;
const ACCENT_TEXT = <?= json_encode($accentText) ?>;

const AL = <?= json_encode($AL, JSON_UNESCAPED_UNICODE) ?>;

// ── SVG icons ─────────────────────────────────────────────────────
const SVG_SUN = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
  stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
  <circle cx="12" cy="12" r="4"/>
  <path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/>
</svg>`;

const SVG_MOON = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
  stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
  <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
</svg>`;

// ── Dark mode ─────────────────────────────────────────────────────
function toggleDark() {
  const isDark = document.documentElement.classList.toggle('dark');
  localStorage.setItem('gl-dark', isDark ? '1' : '0');
  refreshDarkIcon();
}
function refreshDarkIcon() {
  const el = document.getElementById('dark-icon');
  if (el) el.innerHTML = document.documentElement.classList.contains('dark') ? SVG_MOON : SVG_SUN;
}

// ── Bottom sheet ──────────────────────────────────────────────────
let _scrollY = 0;

function openSheet(item) {
  // Name
  document.getElementById('sheet-name').textContent = item.name;

  // Weight
  const wEl = document.getElementById('sheet-weight');
  item.weight ? (wEl.textContent = item.weight, wEl.classList.remove('hidden'))
              : wEl.classList.add('hidden');

  // Price
  document.getElementById('sheet-price').textContent =
    parseFloat(item.price || 0).toFixed(2).replace('.', ',') + ' €';

  // Description: prefer detailed
  const desc = (String(item.detail_description || '').trim()) ||
               (String(item.description || '').trim());
  const dEl = document.getElementById('sheet-desc');
  desc ? (dEl.textContent = desc, dEl.classList.remove('hidden'))
       : dEl.classList.add('hidden');

  // Allergens
  const nums = String(item.allergens || '').split(',')
    .map(n => parseInt(n.trim(), 10))
    .filter(n => n >= 1 && n <= 14);
  const algDiv  = document.getElementById('sheet-allergens');
  const algList = document.getElementById('sheet-allergen-list');
  if (nums.length) {
    algList.innerHTML = nums.map(n =>
      `<li class="flex items-start gap-2.5 text-sm text-slate-600 dark:text-slate-400">
        <span class="w-5 h-5 rounded-full bg-slate-100 dark:bg-slate-800
                     text-slate-600 dark:text-slate-300
                     text-[10px] font-bold flex items-center justify-center
                     flex-shrink-0 mt-0.5">${n}</span>
        <span>${AL[n] || ('Alergén ' + n)}</span>
      </li>`
    ).join('');
    algDiv.classList.remove('hidden');
  } else {
    algDiv.classList.add('hidden');
  }

  // Reset sheet scroll
  document.getElementById('sheet-body').scrollTop = 0;

  // Lock background scroll (iOS-safe)
  _scrollY = window.scrollY;
  document.body.style.cssText =
    `position:fixed;top:-${_scrollY}px;left:0;right:0;overflow:hidden;width:100%`;

  document.getElementById('sheet').classList.add('open');
  document.getElementById('overlay').classList.add('open');
}

function closeSheet() {
  document.getElementById('sheet').classList.remove('open');
  document.getElementById('overlay').classList.remove('open');
  document.body.style.cssText = '';
  window.scrollTo(0, _scrollY);
}

document.addEventListener('keydown', e => {
  if (e.key === 'Escape') closeSheet();
});

// ── Smooth scroll to section ──────────────────────────────────────
function scrollToSection(catId) {
  const target = document.getElementById('cat-' + catId);
  const nav    = document.getElementById('cat-nav');
  if (!target) return;
  const offset = nav ? nav.offsetHeight + 2 : 2;
  const top = target.getBoundingClientRect().top + window.scrollY - offset;
  window.scrollTo({ top, behavior: 'smooth' });
}

// ── Active category pill via IntersectionObserver ─────────────────
(function () {
  const sections = Array.from(document.querySelectorAll('section[id^="cat-"]'));
  if (!sections.length) return;

  let activeId = null;

  const obs = new IntersectionObserver(entries => {
    // Collect all currently intersecting sections
    entries.forEach(e => { e.target._intersecting = e.isIntersecting; });
    const vis = sections.filter(s => s._intersecting);
    if (!vis.length) return;

    const topSection = vis.reduce((a, b) =>
      a.getBoundingClientRect().top < b.getBoundingClientRect().top ? a : b
    );
    const id = topSection.id.replace('cat-', '');
    if (id === activeId) return;
    activeId = id;

    document.querySelectorAll('.cat-pill').forEach(p => {
      const match = p.id === 'pill-' + id;
      p.classList.toggle('active', match);
      if (match) p.scrollIntoView({ inline: 'nearest', block: 'nearest', behavior: 'smooth' });
    });
  }, { rootMargin: '-20% 0px -75% 0px', threshold: 0 });

  sections.forEach(s => obs.observe(s));
})();

// ── Init ──────────────────────────────────────────────────────────
refreshDarkIcon();
</script>
</body>
</html>
