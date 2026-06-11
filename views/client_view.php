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
    echo '<!DOCTYPE html><html lang="sk"><head><meta charset="UTF-8"><title>Nenájdené</title>'
       . '<link rel="stylesheet" href="' . asset('assets/css/style.css') . '"></head>'
       . '<body class="min-h-screen flex items-center justify-center bg-gray-50">'
       . '<div class="text-center"><p class="text-5xl mb-4">🔍</p>'
       . '<h1 class="text-xl font-bold text-gray-900">Prevádzka neexistuje</h1>'
       . '<a href="' . url() . '" class="text-indigo-600 text-sm mt-3 block hover:underline">← Späť na úvod</a>'
       . '</div></body></html>';
    exit;
}

// ── Plan expiry check ─────────────────────────────────────────
$ownerSt = $db->prepare("SELECT plan_ends_at FROM users WHERE id = ?");
$ownerSt->execute([(int)$venue['user_id']]);
$ownerRow = $ownerSt->fetch();
if ($ownerRow && $ownerRow['plan_ends_at'] !== null
    && strtotime((string)$ownerRow['plan_ends_at']) < time()) {
    // Lock down only if the menu content exceeds free plan limits (3 categories)
    $catCntSt = $db->prepare("SELECT COUNT(*) FROM categories WHERE venue_slug = ? AND is_visible = 1");
    $catCntSt->execute([$slug]);
    $visibleCatCount = (int)$catCntSt->fetchColumn();
    if ($visibleCatCount > 3) {
        http_response_code(503);
        echo '<!DOCTYPE html><html lang="sk"><head><meta charset="UTF-8">'
           . '<title>Menu nedostupné</title>'
           . '<link rel="stylesheet" href="' . asset('assets/css/style.css') . '"></head>'
           . '<body class="min-h-screen flex items-center justify-center bg-gray-50">'
           . '<div class="text-center max-w-sm px-6">'
           . '<p class="text-5xl mb-4">⏳</p>'
           . '<h1 class="text-xl font-bold text-gray-900 mb-2">Jedálny lístok dočasne nedostupný</h1>'
           . '<p class="text-sm text-gray-500">Toto menu je dočasne nedostupné. Kontaktujte prosím prevádzku.</p>'
           . '</div></body></html>';
        exit;
    }
}

// ── HTTP caching (ETag / 304) ─────────────────────────────────
$etag         = '"' . md5($venue['updated_at'] . $venue['slug']) . '"';
$lastModified = gmdate('D, d M Y H:i:s \G\M\T', (int)strtotime((string)$venue['updated_at']));

header('Cache-Control: public, max-age=0, must-revalidate');
header('ETag: ' . $etag);
header('Last-Modified: ' . $lastModified);

$ifNoneMatch   = trim($_SERVER['HTTP_IF_NONE_MATCH']     ?? '');
$ifModSince    = trim($_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? '');

if (($ifNoneMatch && $ifNoneMatch === $etag) ||
    (!$ifNoneMatch && $ifModSince && strtotime($ifModSince) >= strtotime((string)$venue['updated_at']))) {
    session_write_close();
    http_response_code(304);
    exit;
}

// Scan tracking — once per hour per venue per session
$scanKey = 'scan_ts_' . $slug;
if (time() - (int)($_SESSION[$scanKey] ?? 0) > 3600) {
    try {
        $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 512);
        $db->prepare("INSERT INTO scans (venue_slug, user_agent) VALUES (?, ?)")->execute([$slug, $ua]);
        $_SESSION[$scanKey] = time();
    } catch (Throwable $ignored) {}
}

// Categories + items
$catSt = $db->prepare("SELECT * FROM categories WHERE venue_slug = ? AND is_visible = 1 ORDER BY sort_order, id");
$catSt->execute([$slug]);
$categories    = [];
$featuredItems = [];
foreach ($catSt->fetchAll() as $cat) {
    $iSt = $db->prepare("SELECT * FROM items WHERE category_id = ? AND is_visible = 1 ORDER BY sort_order, id");
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
    'currency'               => 'EUR',
];
if (!isset($settings['currency'])) $settings['currency'] = 'EUR';

$venueCurrency = $settings['currency'] === 'CZK' ? 'CZK' : 'EUR';
$currencySym   = $venueCurrency === 'CZK' ? 'Kč' : '€';

function fmtPr(float|string $price, string $sym): string {
    return number_format((float)$price, 2, ',', '') . ' ' . $sym;
}

$accentArr  = resolveColor($venue['color']);
$accentHex  = $accentArr['hex'];
$accentText = menuTextColor($accentHex);

$defCatBg    = $settings['default_category_color'] ?? '#1E3A5F';
$defItemBg   = $settings['default_item_color']     ?? '#FFFFFF';

// First letter avatar fallback
$initial = mb_strtoupper(mb_substr($venue['name'], 0, 1));

// Open Graph metadata
$ogTitle    = 'Jedálny lístok — ' . $venue['name'];
$ogDesc     = 'Pozrite si ponuku podniku ' . $venue['name'] . ' online.';
$ogUrl      = rtrim(baseUrl(), '/') . '/r/' . $venue['slug'];
$ogImageRaw = !empty($venue['cover_image']) ? $venue['cover_image']
            : (!empty($venue['logo'])        ? $venue['logo'] : null);
$ogImage    = ($ogImageRaw && !str_starts_with($ogImageRaw, 'data:'))
              ? rtrim(baseUrl(), '/') . '/' . ltrim($ogImageRaw, '/') : '';

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
<meta name="description" content="<?= e($ogDesc) ?>">
<title><?= e($venue['name']) ?> — Menu</title>
<!-- Open Graph / Social sharing -->
<meta property="og:type"         content="website">
<meta property="og:title"        content="<?= e($ogTitle) ?>">
<meta property="og:description"  content="<?= e($ogDesc) ?>">
<meta property="og:url"          content="<?= e($ogUrl) ?>">
<?php if ($ogImage): ?><meta property="og:image" content="<?= e($ogImage) ?>"><?php endif; ?>
<meta name="twitter:card"        content="summary_large_image">
<meta name="twitter:title"       content="<?= e($ogTitle) ?>">
<meta name="twitter:description" content="<?= e($ogDesc) ?>">
<?php if ($ogImage): ?><meta name="twitter:image" content="<?= e($ogImage) ?>"><?php endif; ?>

<!-- Anti-flash: set dark class before any render -->
<script>
(function(){
  var s=localStorage.getItem('gl-dark');
  if(s!==null?s==='1':window.matchMedia('(prefers-color-scheme: dark)').matches)
    document.documentElement.classList.add('dark');
})();
</script>

<!-- Inter font -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= asset('assets/css/style.css') ?>">
<style>
/* Dynamic accent color for active nav pill (PHP-injected) */
.cat-pill{transition:all .2s}
.cat-pill.active{background:<?= e($accentHex) ?> !important;color:<?= e($accentText) ?> !important}
</style>
</head>
<body class="bg-gray-50 dark:bg-slate-950 min-h-screen client-bg">

<!-- ══ APP SHELL ══════════════════════════════════════════════════ -->
<div id="app"
     class="w-full max-w-md mx-auto min-h-screen
            bg-gray-50 dark:bg-slate-950 shadow-2xl relative flex flex-col">

  <!-- ── HEADER ────────────────────────────────────────────────────── -->
  <?php $hasCover = !empty($venue['cover_image']); ?>
  <header class="flex-shrink-0">

    <?php if ($hasCover): ?>
    <!-- ── COVER PHOTO variant: fotka ako pozadie celého headera ── -->
    <div class="relative overflow-hidden border-b border-gray-100 dark:border-slate-800"
         style="min-height:220px">

      <!-- Cover photo — full background -->
      <img src="<?= e(imgUrl($venue['cover_image'])) ?>" alt=""
           class="absolute inset-0 w-full h-full object-cover">

      <!-- Gradient overlay for readability -->
      <div class="absolute inset-0 bg-gradient-to-b from-black/30 via-black/40 to-black/65 pointer-events-none"></div>

      <!-- Dark mode toggle top-right -->
      <button onclick="toggleDark()" aria-label="Prepnúť tmavý/svetlý režim"
              class="absolute top-3 right-3 z-20
                     w-8 h-8 rounded-full bg-black/30 backdrop-blur-sm
                     border border-white/20 shadow
                     flex items-center justify-center text-white
                     transition-all duration-200 active:scale-90">
        <span id="dark-icon" class="w-3.5 h-3.5 block"></span>
      </button>

      <!-- Content over the image -->
      <div class="relative z-10 pt-safe px-6 pb-6 flex flex-col items-center text-center">
        <div class="mt-4 mb-4">
          <?php if (!empty($venue['logo'])): ?>
          <div class="w-20 h-20 rounded-full overflow-hidden
                      ring-4 ring-white/80 shadow-xl border-2 border-white/60">
            <img src="<?= e(imgUrl($venue['logo'])) ?>" alt="Logo" class="w-full h-full object-cover">
          </div>
          <?php else: ?>
          <div class="w-20 h-20 rounded-full shadow-xl
                      flex items-center justify-center text-3xl font-extrabold
                      ring-4 ring-white/40 border-2 border-white/40"
               style="background:<?= e($accentHex) ?>;color:<?= e($accentText) ?>">
            <?= e($initial) ?>
          </div>
          <?php endif; ?>
        </div>

        <h1 class="text-2xl font-bold text-white leading-tight drop-shadow">
          <?= e($venue['name']) ?>
        </h1>

        <?php if (!empty($quickActions)): ?>
        <div class="flex flex-wrap justify-center gap-2 mt-3">
          <?php foreach ($quickActions as $qa): ?>
          <a href="<?= e($qa['url']) ?>" target="_blank" rel="noopener noreferrer"
             class="flex items-center gap-1.5 px-4 py-1.5 rounded-full
                    bg-white/20 backdrop-blur-sm text-white border border-white/30
                    text-xs font-semibold hover:bg-white/30
                    transition-all duration-200 active:scale-95">
            <span aria-hidden="true"><?= $qa['emoji'] ?></span>
            <span><?= e($qa['label']) ?></span>
          </a>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <?php else: ?>
    <!-- ── NO COVER: avatar-style header ───────────────────────── -->
    <div class="relative bg-white dark:bg-slate-900 border-b border-gray-100 dark:border-slate-800">

      <!-- Dark mode toggle top-right -->
      <button onclick="toggleDark()" aria-label="Prepnúť tmavý/svetlý režim"
              class="absolute top-3 right-3 z-10
                     w-8 h-8 rounded-full bg-gray-100 dark:bg-slate-800
                     border border-gray-200 dark:border-slate-600 shadow-sm
                     flex items-center justify-center
                     text-gray-500 dark:text-slate-400
                     transition-all duration-200 active:scale-90">
        <span id="dark-icon" class="w-3.5 h-3.5 block"></span>
      </button>

      <div class="pt-safe px-6 pb-6 flex flex-col items-center text-center">
        <div class="mt-3 mb-4">
          <?php if (!empty($venue['logo'])): ?>
          <div class="w-20 h-20 rounded-full overflow-hidden
                      ring-4 ring-white dark:ring-slate-900
                      shadow-lg border-2 border-gray-100 dark:border-slate-700">
            <img src="<?= e(imgUrl($venue['logo'])) ?>" alt="Logo" class="w-full h-full object-cover">
          </div>
          <?php else: ?>
          <div class="w-20 h-20 rounded-full
                      ring-4 ring-white dark:ring-slate-900
                      shadow-lg flex items-center justify-center text-3xl font-extrabold"
               style="background:<?= e($accentHex) ?>;color:<?= e($accentText) ?>">
            <?= e($initial) ?>
          </div>
          <?php endif; ?>
        </div>

        <h1 class="text-2xl font-bold text-gray-900 dark:text-white leading-tight">
          <?= e($venue['name']) ?>
        </h1>

        <?php if (!empty($quickActions)): ?>
        <div class="flex flex-wrap justify-center gap-2 mt-3">
          <?php foreach ($quickActions as $qa): ?>
          <a href="<?= e($qa['url']) ?>" target="_blank" rel="noopener noreferrer"
             class="flex items-center gap-1.5 px-4 py-1.5 rounded-full
                    bg-gray-100 dark:bg-slate-800 text-gray-700 dark:text-gray-300
                    text-xs font-semibold hover:bg-gray-200 dark:hover:bg-slate-700
                    transition-all duration-200 active:scale-95">
            <span aria-hidden="true"><?= $qa['emoji'] ?></span>
            <span><?= e($qa['label']) ?></span>
          </a>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

  </header>

  <!-- ── STICKY CATEGORY NAV (domovský stav) ───────────────────────── -->
  <?php if (!empty($categories)): ?>
  <nav id="cat-nav"
       class="sticky top-0 z-40 flex-shrink-0
              bg-white/90 dark:bg-slate-900/90 backdrop-blur-md
              border-b border-gray-100 dark:border-slate-800">
    <div class="flex items-center">
      <div class="flex-1 flex gap-1.5 overflow-x-auto no-scrollbar px-4 py-3">
        <?php foreach ($categories as $cat): ?>
        <button onclick="showCategory(<?= (int)$cat['id'] ?>, '<?= e(addslashes($cat['name'])) ?>')"
                id="pill-<?= (int)$cat['id'] ?>"
                class="cat-pill flex-none px-3.5 py-1.5 rounded-full
                       text-xs font-semibold whitespace-nowrap
                       text-gray-500 dark:text-gray-400
                       hover:bg-gray-100 dark:hover:bg-slate-800
                       transition-all duration-200">
          <?= e($cat['icon']) ?> <?= e($cat['name']) ?>
        </button>
        <?php endforeach; ?>
      </div>
      <button onclick="openSearch()" aria-label="Hľadať v menu"
              class="flex-shrink-0 w-9 h-9 mr-3 rounded-full
                     bg-gray-100 dark:bg-slate-800
                     flex items-center justify-center
                     text-gray-500 dark:text-slate-400
                     hover:bg-gray-200 dark:hover:bg-slate-700
                     transition-all duration-200">
        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"
             stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
        </svg>
      </button>
    </div>
  </nav>
  <?php endif; ?>

  <!-- ── BACK BAR (kategória stav) ──────────────────────────────────── -->
  <div id="back-bar"
       style="display:none"
       class="sticky top-0 z-40 flex-shrink-0
              bg-white/90 dark:bg-slate-900/90 backdrop-blur-md
              border-b border-gray-100 dark:border-slate-800 px-4 py-3
              flex items-center gap-3">
    <button onclick="showHome()"
            class="flex items-center gap-1.5 text-sm font-bold
                   text-indigo-600 dark:text-indigo-400
                   active:scale-95 transition-all duration-200 flex-shrink-0">
      <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"
           stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
        <path d="M19 12H5M12 19l-7-7 7-7"/>
      </svg>
      Späť
    </button>
    <span class="w-px h-4 bg-gray-200 dark:bg-slate-700 flex-shrink-0"></span>
    <span id="back-cat-name"
          class="text-sm font-bold text-gray-900 dark:text-white truncate"></span>
  </div>

  <!-- ── SEARCH PANEL ─────────────────────────────────────────────── -->
  <div id="search-panel" class="hidden flex-shrink-0
         bg-white dark:bg-slate-900 border-b border-gray-100 dark:border-slate-800 px-4 py-3">
    <div class="relative flex items-center">
      <svg class="absolute left-3 w-4 h-4 text-gray-400 dark:text-slate-500 pointer-events-none"
           viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
           stroke-linecap="round" stroke-linejoin="round">
        <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
      </svg>
      <input id="search-input" type="search" placeholder="Hľadať jedlo…"
             oninput="doSearch()"
             class="flex-1 pl-9 pr-10 py-2.5
                    bg-gray-100 dark:bg-slate-800 border-none rounded-2xl
                    text-sm text-slate-900 dark:text-slate-100
                    placeholder-gray-400 dark:placeholder-slate-500
                    focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-all duration-200">
      <button onclick="closeSearch()" aria-label="Zavrieť hľadanie"
              class="absolute right-3 w-5 h-5 rounded-full
                     bg-gray-300 dark:bg-slate-600 text-gray-600 dark:text-slate-300
                     flex items-center justify-center text-[10px] font-bold
                     hover:bg-gray-400 dark:hover:bg-slate-500 transition">
        ✕
      </button>
    </div>
  </div>

  <!-- ── SEARCH RESULTS ────────────────────────────────────────────── -->
  <div id="search-results" class="hidden flex-1 overflow-y-auto pb-safe px-4 pt-4 space-y-5"></div>

  <!-- ══ HOME VIEW (Odporúčame) ════════════════════════════════════════ -->
  <div id="home-view" class="flex-1 flex flex-col">

    <?php if (!empty($settings['show_featured']) && !empty($featuredItems)): ?>
    <!-- Featured horizontal cards -->
    <section class="bg-white dark:bg-slate-900 px-4 pt-5 pb-4
                    border-b border-gray-100 dark:border-slate-800">
      <h2 class="text-[10px] font-black uppercase tracking-[.15em] mb-3
                 text-gray-400 dark:text-slate-500">⭐ Odporúčame</h2>
      <div class="flex gap-3 overflow-x-auto no-scrollbar pb-1">
        <?php foreach ($featuredItems as $fi):
          $fbg = $fi['bg_color'] ?: $defItemBg;
          $fbgIsWhite = in_array($fbg, ['#FFFFFF','#FFF8E7','#F2ECD9'], true);
          $ftc = menuTextColor($fbg);
          $fpr = fmtPr((float)$fi['price'], $currencySym);
        ?>
        <button data-item="<?= e(json_encode($fi, JSON_UNESCAPED_UNICODE)) ?>"
                onclick="openSheet(JSON.parse(this.dataset.item))"
                class="flex-none w-36 rounded-[2rem] p-4 text-left shadow-sm
                       border border-gray-100 dark:border-slate-700
                       hover:shadow-md active:scale-95 transition-all duration-200
                       <?= $fbgIsWhite ? 'bg-white dark:bg-slate-800' : '' ?>"
                <?= !$fbgIsWhite ? "style=\"background:{$fbg}\"" : '' ?>>
          <p class="font-semibold text-sm leading-snug clamp2 mb-3
                    <?= $fbgIsWhite ? 'text-gray-800 dark:text-gray-100' : '' ?>"
             <?= !$fbgIsWhite ? "style=\"color:{$ftc}\"" : '' ?>>
            <?= e($fi['name']) ?>
          </p>
          <span class="inline-block text-xs font-bold px-2.5 py-1 rounded-full
                       bg-gray-100 dark:bg-slate-700 text-gray-900 dark:text-gray-100">
            <?= $fpr ?>
          </span>
        </button>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>

    <!-- Category list -->
    <?php if (!empty($categories)): ?>
    <div class="flex-1 overflow-y-auto">
      <p class="text-[10px] font-black uppercase tracking-[.15em] px-4 pt-5 pb-3
                text-gray-400 dark:text-slate-500">Kategórie</p>
      <div class="px-4 space-y-2 pb-6">
        <?php foreach ($categories as $cat):
          $catBg = $cat['bg_color'] ?: $defCatBg;
          $catTc = menuTextColor($catBg);
          $catMt = menuMutedColor($catBg);
          $itemCount = count($cat['items']);
        ?>
        <button onclick="showCategory(<?= (int)$cat['id'] ?>, '<?= e(addslashes($cat['name'])) ?>')"
                class="w-full text-left rounded-[2rem] px-5 py-4
                       shadow-sm hover:shadow-md active:scale-[.98]
                       transition-all duration-200 flex items-center justify-between gap-3"
                style="background:<?= e($catBg) ?>">
          <div class="flex items-center gap-3 min-w-0">
            <?php if (!empty($cat['icon'])): ?>
            <span class="text-xl leading-none flex-shrink-0"><?= e($cat['icon']) ?></span>
            <?php endif; ?>
            <span class="font-bold text-sm truncate"
                  style="color:<?= e($catTc) ?>"><?= e($cat['name']) ?></span>
          </div>
          <div class="flex items-center gap-2 flex-shrink-0">
            <span class="text-xs font-semibold"
                  style="color:<?= e($catMt) ?>"><?= $itemCount ?> <?= $itemCount === 1 ? 'jedlo' : ($itemCount < 5 ? 'jedlá' : 'jedál') ?></span>
            <svg class="w-4 h-4 opacity-60" viewBox="0 0 24 24" fill="none"
                 stroke="<?= e($catTc) ?>" stroke-width="2.5"
                 stroke-linecap="round" stroke-linejoin="round">
              <path d="M9 18l6-6-6-6"/>
            </svg>
          </div>
        </button>
        <?php endforeach; ?>
      </div>
    </div>
    <?php else: ?>
    <div class="flex-1 flex flex-col items-center justify-center py-24 px-8 text-center">
      <p class="text-5xl mb-5">🍽️</p>
      <h2 class="text-lg font-bold text-gray-700 dark:text-slate-300">
        Jedálny lístok sa pripravuje
      </h2>
      <p class="text-sm text-gray-400 dark:text-slate-500 mt-2 leading-relaxed">
        Táto prevádzka zatiaľ nezverejnila jedálny lístok.
      </p>
    </div>
    <?php endif; ?>

  </div><!-- /#home-view -->

  <!-- ══ CATEGORY VIEW (pre-rendered, všetky sekcie, hidden) ═══════════ -->
  <div id="cat-views" class="hidden flex-1 pb-safe overflow-y-auto">
    <?php foreach ($categories as $cat):
      $catBg = $cat['bg_color'] ?: $defCatBg;
      $catTc = menuTextColor($catBg);
    ?>
    <section class="cat-section hidden" data-cat-id="<?= (int)$cat['id'] ?>">

      <!-- Item cards -->
      <div class="px-4 pt-4 pb-6 space-y-2.5">
        <?php if (empty($cat['items'])): ?>
        <p class="text-xs text-gray-400 dark:text-slate-600 text-center py-4">
          Žiadne jedlá v tejto kategórii.
        </p>
        <?php else: foreach ($cat['items'] as $item):
          $ibg  = $item['bg_color'] ?: $defItemBg;
          $isWhiteCard = in_array($ibg, ['#FFFFFF', '#FFF8E7', '#F2ECD9'], true);
          $itc  = $isWhiteCard ? '#1f2937' : menuTextColor($ibg);
          $imt  = $isWhiteCard ? '#6b7280' : menuMutedColor($ibg);
          $ipr  = fmtPr((float)$item['price'], $currencySym);
          $algN = array_values(array_unique(array_filter(
              array_map('intval', array_filter(explode(',', (string)($item['allergens'] ?? '')), 'strlen')),
              fn($n) => $n >= 1 && $n <= 14
          )));
        ?>
        <button class="w-full text-left rounded-[2rem] p-4 shadow-sm
                       hover:shadow-md active:scale-[.98] transition-all duration-200
                       <?= $isWhiteCard
                           ? 'bg-white dark:bg-slate-800 border border-gray-100 dark:border-slate-800'
                           : '' ?>"
                <?= !$isWhiteCard ? "style=\"background:{$ibg}\"" : '' ?>
                data-item="<?= e(json_encode($item, JSON_UNESCAPED_UNICODE)) ?>"
                onclick="openSheet(JSON.parse(this.dataset.item))">
          <div class="flex items-start gap-3">
            <div class="flex-1 min-w-0">
              <p class="font-semibold text-sm leading-snug
                        <?= $isWhiteCard ? 'text-gray-800 dark:text-gray-100' : '' ?>"
                 <?= !$isWhiteCard ? "style=\"color:{$itc}\"" : '' ?>>
                <?= e($item['name']) ?>
              </p>
              <?php if (!empty($item['weight'])): ?>
              <p class="text-xs font-medium mt-0.5
                        <?= $isWhiteCard ? 'text-gray-400 dark:text-slate-500' : '' ?>"
                 <?= !$isWhiteCard ? "style=\"color:{$imt};opacity:.85\"" : '' ?>>
                <?= e($item['weight']) ?>
              </p>
              <?php endif; ?>
              <?php if (!empty($item['description'])): ?>
              <p class="text-sm mt-1 leading-relaxed clamp2
                        <?= $isWhiteCard ? 'text-gray-500 dark:text-slate-400' : '' ?>"
                 <?= !$isWhiteCard ? "style=\"color:{$imt}\"" : '' ?>>
                <?= e($item['description']) ?>
              </p>
              <?php endif; ?>
              <?php if (!empty($settings['show_allergens']) && !empty($algN)): ?>
              <div class="flex flex-wrap gap-1 mt-2">
                <?php foreach ($algN as $aNum):
                  $dotBg = $isWhiteCard ? 'rgba(0,0,0,.06)' : ($itc === '#ffffff' ? 'rgba(255,255,255,.22)' : 'rgba(0,0,0,.09)');
                  $dotTc = $isWhiteCard ? '#9ca3af' : $imt;
                ?>
                <span onclick="showAllergenInfo(<?= (int)$aNum ?>,event)"
                      role="button" tabindex="0"
                      class="w-4 h-4 rounded-full text-[9px] font-bold cursor-pointer
                             inline-flex items-center justify-center leading-none
                             hover:ring-2 hover:ring-offset-1 hover:ring-indigo-400 transition-all"
                      style="background:<?= $dotBg ?>;color:<?= $dotTc ?>">
                  <?= (int)$aNum ?>
                </span>
                <?php endforeach; ?>
              </div>
              <?php endif; ?>
            </div>
            <div class="flex-shrink-0 flex flex-col items-end gap-1.5 self-start mt-0.5">
              <?php if (!empty($item['image'])): ?>
              <div class="w-14 h-14 rounded-xl overflow-hidden border border-gray-100 dark:border-slate-600">
                <img src="<?= e(imgUrl($item['image'])) ?>" alt="" class="w-full h-full object-cover">
              </div>
              <?php endif; ?>
              <span class="px-3 py-1 rounded-full text-sm font-bold leading-snug whitespace-nowrap
                           <?= $isWhiteCard
                               ? 'bg-gray-100 dark:bg-slate-700 text-gray-900 dark:text-gray-100'
                               : '' ?>"
                    <?= !$isWhiteCard ? "style=\"background:{$accentHex};color:{$accentText}\"" : '' ?>>
                <?= $ipr ?>
              </span>
            </div>
          </div>
        </button>
        <?php endforeach; endif; ?>
      </div>
      <div class="h-3"></div>
    </section>
    <?php endforeach; ?>
  </div><!-- /#cat-views -->

  <!-- ── OVERLAY ─────────────────────────────────────────────────── -->
  <div id="overlay" onclick="closeSheet()"
       class="overlay fixed inset-0 bg-black/50 dark:bg-black/70 z-40"></div>

  <!-- ── BOTTOM SHEET ────────────────────────────────────────────── -->
  <article id="sheet" role="dialog" aria-modal="true" aria-labelledby="sheet-name"
       class="sheet fixed bottom-0 left-1/2 w-full max-w-md z-50
              bg-white dark:bg-slate-900 rounded-t-3xl shadow-2xl
              max-h-[88dvh] flex flex-col overflow-hidden">
    <div class="flex-shrink-0 px-5 pt-3 pb-4
                bg-white dark:bg-slate-900
                border-b border-gray-100 dark:border-slate-800">
      <div class="w-10 h-1 bg-gray-200 dark:bg-slate-700 rounded-full mx-auto mb-4"></div>
      <div class="flex items-start justify-between gap-3">
        <div class="flex-1 min-w-0">
          <h2 id="sheet-name"
              class="text-xl font-bold text-gray-900 dark:text-slate-100 leading-tight"></h2>
          <p id="sheet-weight"
             class="text-sm text-gray-400 dark:text-slate-500 mt-0.5 hidden"></p>
        </div>
        <button onclick="closeSheet()" aria-label="Zavrieť"
                class="w-8 h-8 rounded-full bg-gray-100 dark:bg-slate-800
                       text-gray-500 dark:text-slate-400
                       flex items-center justify-center flex-shrink-0
                       text-sm transition-all duration-200 active:scale-90">✕</button>
      </div>
      <p id="sheet-price"
         class="text-2xl font-extrabold mt-2 text-gray-900 dark:text-white"></p>
    </div>
    <div id="sheet-body" class="flex-1 overflow-y-auto overscroll-contain px-5 py-5">
      <div id="sheet-img-wrap" class="hidden -mx-5 -mt-5 mb-5">
        <img id="sheet-img" src="" alt="" class="w-full object-cover" style="max-height:200px">
      </div>
      <p id="sheet-short-desc"
         class="text-sm font-medium text-gray-700 dark:text-slate-300 leading-relaxed mb-2 hidden"></p>
      <p id="sheet-desc"
         class="text-sm italic text-gray-400 dark:text-slate-500 leading-relaxed hidden"></p>
      <div id="sheet-allergens"
           class="hidden mt-5 pt-4 border-t border-gray-100 dark:border-slate-800">
        <h3 class="text-[10px] font-black uppercase tracking-[.12em] mb-3
                   text-gray-400 dark:text-slate-500">Alergény</h3>
        <ul id="sheet-allergen-list" class="space-y-2"></ul>
      </div>
    </div>
    <div class="flex-shrink-0 pb-safe bg-white dark:bg-slate-900"></div>
  </article>

  <!-- ── ALLERGEN POPOVER ──────────────────────────────────────────── -->
  <div id="al-pop" onclick="closeAllergenPop()"
       class="hidden fixed inset-0 z-[60] flex items-center justify-center p-5 bg-black/40">
    <div class="bg-white dark:bg-slate-900 rounded-[2rem] shadow-2xl p-6 max-w-xs w-full
                border border-gray-100 dark:border-slate-800" onclick="event.stopPropagation()">
      <div class="flex items-center gap-3 mb-3">
        <span class="w-8 h-8 rounded-full bg-indigo-100 dark:bg-indigo-900/50
                     text-indigo-600 dark:text-indigo-400 text-sm font-bold
                     flex items-center justify-center flex-shrink-0" id="al-pop-num"></span>
        <h3 class="font-bold text-slate-900 dark:text-white text-sm">Alergén</h3>
      </div>
      <p class="text-sm text-slate-600 dark:text-slate-400 leading-relaxed" id="al-pop-text"></p>
      <button onclick="closeAllergenPop()"
              class="mt-5 w-full py-2.5 rounded-2xl bg-gray-100 dark:bg-slate-800
                     text-slate-700 dark:text-slate-300 text-sm font-semibold
                     hover:bg-gray-200 dark:hover:bg-slate-700 transition">
        Zatvoriť
      </button>
    </div>
  </div>

</div><!-- /#app -->

<script>
const AL          = <?= json_encode($AL,          JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const BASE_URL    = <?= json_encode(rtrim(baseUrl(), '/')) ?>;
const MENU_CATS   = <?= json_encode($categories, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const VENUE_CUR   = <?= json_encode($currencySym) ?>;

function fmtPrice(p) {
  return parseFloat(p || 0).toFixed(2).replace('.', ',') + ' ' + VENUE_CUR;
}

// ── SVG icons ─────────────────────────────────────────────────────
const SVG_SUN = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/></svg>`;
const SVG_MOON = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>`;

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

// ── Category navigation ────────────────────────────────────────────
function showCategory(catId, catName) {
  // Swap nav ↔ back bar
  document.getElementById('cat-nav')?.classList.add('hidden');
  const bar = document.getElementById('back-bar');
  bar.style.display = 'flex';
  document.getElementById('back-cat-name').textContent = catName;

  // Highlight active pill
  document.querySelectorAll('.cat-pill').forEach(p => {
    p.classList.toggle('active', p.id === 'pill-' + catId);
  });

  // Hide home, show cat-views
  document.getElementById('home-view').classList.add('hidden');
  const cv = document.getElementById('cat-views');
  cv.classList.remove('hidden');

  // Show only the target section, animate it
  document.querySelectorAll('.cat-section').forEach(s => {
    const match = parseInt(s.dataset.catId) === catId;
    if (match) {
      s.classList.remove('hidden');
      // re-trigger animation
      s.classList.remove('view-animate');
      void s.offsetWidth; // reflow
      s.classList.add('view-animate');
    } else {
      s.classList.add('hidden');
    }
  });

  // Scroll content to top
  cv.scrollTop = 0;
  window.scrollTo(0, 0);
}

function showHome() {
  // Swap back bar ↔ nav
  document.getElementById('back-bar').style.display = 'none';
  document.getElementById('cat-nav')?.classList.remove('hidden');

  // Clear active pill
  document.querySelectorAll('.cat-pill').forEach(p => p.classList.remove('active'));

  // Hide cat-views, show home (with animation)
  document.getElementById('cat-views').classList.add('hidden');
  document.querySelectorAll('.cat-section').forEach(s => {
    s.classList.add('hidden');
    s.classList.remove('view-animate');
  });

  const hv = document.getElementById('home-view');
  hv.classList.remove('hidden');
  hv.classList.remove('view-animate');
  void hv.offsetWidth;
  hv.classList.add('view-animate');

  window.scrollTo(0, 0);
}

// ── Bottom sheet ──────────────────────────────────────────────────
let _scrollY = 0;

function openSheet(item) {
  document.getElementById('sheet-name').textContent = item.name;
  const wEl = document.getElementById('sheet-weight');
  item.weight ? (wEl.textContent = item.weight, wEl.classList.remove('hidden')) : wEl.classList.add('hidden');
  document.getElementById('sheet-price').textContent = fmtPrice(item.price);
  const shortDesc = String(item.description        || '').trim();
  const longDesc  = String(item.detail_description || '').trim();
  const sdEl = document.getElementById('sheet-short-desc');
  const dEl  = document.getElementById('sheet-desc');
  shortDesc ? (sdEl.textContent = shortDesc, sdEl.classList.remove('hidden')) : sdEl.classList.add('hidden');
  longDesc  ? (dEl.textContent  = longDesc,  dEl.classList.remove('hidden'))  : dEl.classList.add('hidden');
  const imgWrap = document.getElementById('sheet-img-wrap');
  const imgEl   = document.getElementById('sheet-img');
  if (item.image && imgWrap && imgEl) {
    imgEl.src = BASE_URL + '/' + item.image;
    imgWrap.classList.remove('hidden');
  } else if (imgWrap) {
    imgWrap.classList.add('hidden');
  }
  const nums = String(item.allergens || '').split(',')
    .map(n => parseInt(n.trim(), 10)).filter(n => n >= 1 && n <= 14);
  const algDiv  = document.getElementById('sheet-allergens');
  const algList = document.getElementById('sheet-allergen-list');
  if (nums.length) {
    algList.innerHTML = nums.map(n =>
      `<li class="flex items-start gap-2.5 text-sm text-gray-600 dark:text-slate-400">
        <button onclick="showAllergenInfo(${n},event)"
                class="w-5 h-5 rounded-full bg-gray-100 dark:bg-slate-800
                       text-gray-500 dark:text-slate-400 text-[10px] font-bold
                       flex items-center justify-center flex-shrink-0 mt-0.5
                       hover:bg-indigo-100 dark:hover:bg-indigo-900/30 transition">${n}</button>
        <span>${AL[n] || ('Alergén ' + n)}</span>
      </li>`
    ).join('');
    algDiv.classList.remove('hidden');
  } else {
    algDiv.classList.add('hidden');
  }
  document.getElementById('sheet-body').scrollTop = 0;
  _scrollY = window.scrollY;
  document.body.style.cssText = `position:fixed;top:-${_scrollY}px;left:0;right:0;overflow:hidden;width:100%`;
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
  if (e.key === 'Escape') { closeSheet(); closeAllergenPop(); closeSearch(); }
});

// ── Allergen popover ──────────────────────────────────────────────
function showAllergenInfo(num, event) {
  event.stopPropagation();
  document.getElementById('al-pop-num').textContent = num;
  document.getElementById('al-pop-text').textContent = AL[num] || ('Alergén ' + num);
  document.getElementById('al-pop').classList.remove('hidden');
}
function closeAllergenPop() {
  document.getElementById('al-pop').classList.add('hidden');
}

// ── Live search ───────────────────────────────────────────────────
function escHtml(s) {
  return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}

let _srCache = [];

function openSearch() {
  document.getElementById('search-panel').classList.remove('hidden');
  document.getElementById('search-input').focus();
  document.getElementById('home-view').classList.add('hidden');
  document.getElementById('cat-views').classList.add('hidden');
  document.getElementById('back-bar').style.display = 'none';
  document.getElementById('cat-nav')?.classList.remove('hidden');
}

function closeSearch() {
  document.getElementById('search-panel').classList.add('hidden');
  document.getElementById('search-results').classList.add('hidden');
  document.getElementById('search-input').value = '';
  _srCache = [];
  showHome();
}

function doSearch() {
  const q = (document.getElementById('search-input')?.value || '').trim().toLowerCase();
  const resultsEl = document.getElementById('search-results');
  _srCache = [];

  if (!q) {
    resultsEl.classList.add('hidden');
    document.getElementById('home-view').classList.remove('hidden');
    return;
  }

  document.getElementById('home-view').classList.add('hidden');
  document.getElementById('cat-views').classList.add('hidden');

  let html = '';
  let total = 0;

  for (const cat of (MENU_CATS || [])) {
    const catMatch = cat.name.toLowerCase().includes(q);
    const matches = (cat.items || []).filter(item =>
      catMatch ||
      item.name.toLowerCase().includes(q) ||
      (item.description || '').toLowerCase().includes(q)
    );
    if (!matches.length) continue;
    total += matches.length;
    html += `<div>
      <p class="text-[10px] font-black uppercase tracking-[.15em] mb-2 text-gray-400 dark:text-slate-500">${escHtml(cat.name)}</p>
      <div class="space-y-2">`;
    for (const item of matches) {
      const idx = _srCache.length;
      _srCache.push(item);
      html += `<button onclick="_openSrItem(${idx})"
               class="w-full text-left bg-white dark:bg-slate-800 rounded-[2rem] p-4
                      shadow-sm border border-gray-100 dark:border-slate-700
                      hover:shadow-md active:scale-[.98] transition-all duration-200">
        <div class="flex items-center justify-between gap-3">
          <div class="flex-1 min-w-0">
            <p class="font-semibold text-sm text-gray-800 dark:text-gray-100 truncate">${escHtml(item.name)}</p>
            ${item.description ? `<p class="text-xs text-gray-500 dark:text-slate-400 mt-0.5 truncate">${escHtml(item.description)}</p>` : ''}
          </div>
          <span class="flex-shrink-0 px-3 py-1 rounded-full bg-gray-100 dark:bg-slate-700
                       text-sm font-bold text-gray-900 dark:text-gray-100 whitespace-nowrap">${fmtPrice(item.price)}</span>
        </div>
      </button>`;
    }
    html += `</div></div>`;
  }

  if (!total) {
    html = `<div class="text-center py-16">
      <p class="text-4xl mb-3">🔍</p>
      <p class="text-sm font-semibold text-gray-700 dark:text-slate-300 mb-1">Nič nenájdené</p>
      <p class="text-xs text-gray-400 dark:text-slate-500">Skúste iný výraz</p>
    </div>`;
  }

  resultsEl.innerHTML = html;
  resultsEl.classList.remove('hidden');
}

function _openSrItem(idx) { openSheet(_srCache[idx]); }

// ── Init ──────────────────────────────────────────────────────────
refreshDarkIcon();
</script>
</body>
</html>
