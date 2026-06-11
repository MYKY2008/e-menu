<?php
$title     = 'Dashboard — GastroLink QR';
$robots    = 'noindex, nofollow';
$extraHead = '<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>';
require __DIR__ . '/partials/header.php';
?>
<body class="bg-gray-50 dark:bg-slate-950 min-h-screen transition-colors duration-200">

<?php
$db     = getDB();
$userId = (int)$_SESSION['user_id'];
$role   = (string)($_SESSION['user_role'] ?? 'user');

// Apply deferred plan transition if billing period expired
if ($role !== 'admin') {
    applyPlanTransitionIfNeeded($db, $userId);
}

$stVenues = $db->prepare("SELECT * FROM venues WHERE user_id = ? ORDER BY created_at DESC");
$stVenues->execute([$userId]);
$venues = $stVenues->fetchAll();

$stLimit = $db->prepare("SELECT max_venues, plan_name, max_categories, max_items_per_cat, plan_ends_at, next_plan_name FROM users WHERE id = ?");
$stLimit->execute([$userId]);
$stLimitRow  = $stLimit->fetch() ?: [];
$venueLimit  = $role === 'admin' ? 9999 : (int)($stLimitRow['max_venues']        ?? 1);
$userPlan    = $role === 'admin' ? 'admin' : (string)($stLimitRow['plan_name']   ?? 'free');
$maxCats     = $role === 'admin' ? 9999 : (int)($stLimitRow['max_categories']    ?? 3);
$maxItemsCat = $role === 'admin' ? 9999 : (int)($stLimitRow['max_items_per_cat'] ?? 5);
$planEndsAt  = $stLimitRow['plan_ends_at'] ?? null;
$planExpired = $role !== 'admin' && $planEndsAt !== null && strtotime((string)$planEndsAt) < time();
$venueCount  = count($venues);
$planLabel   = match($userPlan) {
    'pro'    => 'Pro',
    'ultra'  => 'Ultra',
    'custom' => 'Custom',
    'admin'  => 'Admin',
    default  => 'Free',
};

$selected = null;
if (!empty($venues)) {
    $selSlug = $_GET['edit'] ?? $venues[0]['slug'] ?? null;
    foreach ($venues as $v) { if ($v['slug'] === $selSlug) { $selected = $v; break; } }
    if (!$selected) $selected = $venues[0];
}

$pal   = getPalette();
$flash = getFlash();

// Menu data
$menuCategories = [];
$menuSettings   = [
    'show_allergens'         => 1,
    'show_featured'          => 1,
    'default_category_color' => '#1E3A5F',
    'default_item_color'     => '#FFFFFF',
];
if ($selected) {
    $catSt = $db->prepare("SELECT * FROM categories WHERE venue_slug = ? ORDER BY sort_order, id");
    $catSt->execute([$selected['slug']]);
    foreach ($catSt->fetchAll() as $cat) {
        $iSt = $db->prepare("SELECT * FROM items WHERE category_id = ? ORDER BY sort_order, id");
        $iSt->execute([(int)$cat['id']]);
        $cat['items'] = $iSt->fetchAll();
        $menuCategories[] = $cat;
    }
    $ssSt = $db->prepare("SELECT * FROM venue_settings WHERE venue_slug = ?");
    $ssSt->execute([$selected['slug']]);
    $row = $ssSt->fetch();
    if ($row) $menuSettings = array_merge($menuSettings, $row);
}

// Lockdown: plan expired OR limits exceeded after downgrade
$maxItemsInAnyCat = 0;
foreach ($menuCategories as $_c) { $maxItemsInAnyCat = max($maxItemsInAnyCat, count($_c['items'])); }
$limitOverrun = $role !== 'admin' && (count($menuCategories) > $maxCats || $maxItemsInAnyCat > $maxItemsCat);
$lockdown     = $planExpired || $limitOverrun;

// Scan stats per venue
$scanStats = [];
if (!empty($venues)) {
    $slugs   = array_column($venues, 'slug');
    $inPh    = implode(',', array_fill(0, count($slugs), '?'));
    $stAll   = $db->prepare("SELECT venue_slug, COUNT(*) as c FROM scans WHERE venue_slug IN ($inPh) GROUP BY venue_slug");
    $stAll->execute($slugs);
    foreach ($stAll->fetchAll() as $r) $scanStats[$r['venue_slug']]['total'] = (int)$r['c'];
    $st30    = $db->prepare("SELECT venue_slug, COUNT(*) as c FROM scans WHERE venue_slug IN ($inPh)
        AND created_at >= strftime('%Y-%m-%dT%H:%M:%SZ','now','-30 days') GROUP BY venue_slug");
    $st30->execute($slugs);
    foreach ($st30->fetchAll() as $r) $scanStats[$r['venue_slug']]['month'] = (int)$r['c'];
}

$GASTRO_THEMES = getGastroThemes();

$GASTRO_EMOJIS = [
    '🍲','🍔','🍕','🥩','🥗','🍰',
    '☕','🍺','🍷','🍣','🍦','🥐',
    '🍜','🥪','🍛','🫕','🥘','🍳',
    '🍟','🧆','🍱','🥟','🍧','🥂',
];

$EU_ALLERGENS = [
    1=>'Obilniny s lepkom', 2=>'Kôrovce', 3=>'Vajcia', 4=>'Ryby', 5=>'Arašidy',
    6=>'Sója', 7=>'Mlieko', 8=>'Orechy', 9=>'Zeler', 10=>'Horčica',
    11=>'Sezam', 12=>'Siričitany', 13=>'Vlčí bôb', 14=>'Mäkkýše',
];
?>

<!-- ── NAV ────────────────────────────────────────────────────────── -->
<nav class="bg-white/80 dark:bg-slate-950/80 backdrop-blur-lg shadow-sm px-5 py-3 flex items-center justify-between sticky top-0 z-30 border-b border-gray-100 dark:border-slate-800">
  <a href="<?= url() ?>" class="font-extrabold text-sm tracking-tight">
    <span class="text-indigo-600">GastroLink</span><span class="text-emerald-500">QR</span>
  </a>
  <div class="flex items-center gap-3">
    <?php if ($role === 'admin'): ?>
    <a href="<?= url('admin') ?>"
       class="text-xs font-semibold px-3 py-1.5 rounded-xl
              bg-indigo-50 dark:bg-indigo-900/40 text-indigo-600 dark:text-indigo-400
              hover:bg-indigo-100 dark:hover:bg-indigo-900/60 transition-all duration-200">
      Admin
    </a>
    <?php endif; ?>
    <!-- Dark mode toggle -->
    <button id="dark-toggle" onclick="toggleDark()" aria-label="Prepnúť tmavý režim"
            class="w-8 h-8 rounded-xl bg-gray-100 dark:bg-slate-800
                   flex items-center justify-center
                   text-slate-500 dark:text-slate-400
                   hover:bg-gray-200 dark:hover:bg-slate-700
                   transition-all duration-200">
      <span id="dark-icon" class="w-3.5 h-3.5 block pointer-events-none"></span>
    </button>
    <a href="<?= url('profile') ?>" id="profile-toggle" aria-label="Profil"
       class="w-8 h-8 rounded-xl bg-gray-100 dark:bg-slate-800
              flex items-center justify-center
              text-slate-500 dark:text-slate-400
              hover:bg-gray-200 dark:hover:bg-slate-700
              transition-all duration-200">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <circle cx="12" cy="8" r="4"/><path stroke-linecap="round" stroke-linejoin="round" d="M4 20c0-4 3.582-7 8-7s8 3 8 7"/>
      </svg>
    </a>
  </div>
</nav>

<!-- Toast container -->
<div id="toast-wrap" class="fixed top-14 right-4 z-50 flex flex-col gap-2 pointer-events-none"></div>

<!-- ── MAIN GRID ───────────────────────────────────────────────────── -->
<div class="max-w-7xl mx-auto px-4 py-4 grid grid-cols-1 lg:grid-cols-[220px_1fr_230px] gap-4">

  <!-- ══ LEFT: Venue list ════════════════════════════════════════════ -->
  <aside class="space-y-3">
    <?php if ($flash): ?>
    <div class="px-3 py-2.5 rounded-xl text-xs font-medium
                <?= $flash['type']==='success' ? 'bg-emerald-50 text-emerald-800 border border-emerald-200'
                                                : 'bg-red-50 text-red-800 border border-red-200' ?>">
      <?= e($flash['msg']) ?>
    </div>
    <?php endif; ?>

    <div class="bg-white dark:bg-slate-900 rounded-[2rem] shadow-sm p-4 border border-gray-100 dark:border-slate-800">
      <div class="flex items-center justify-between mb-3">
        <div class="flex items-center gap-2">
          <span class="font-bold text-slate-700 dark:text-slate-300 text-xs uppercase tracking-widest">Prevádzky</span>
          <?php if ($role !== 'admin'): ?>
          <span class="px-2 py-0.5 rounded-full text-[10px] font-bold
                       <?= $userPlan === 'free' ? 'bg-gray-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400' : 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400' ?>">
            <?= e($planLabel) ?>
          </span>
          <?php endif; ?>
        </div>
        <span class="text-xs text-slate-400">
          <?= $venueCount ?>/<?= ($role==='admin'||$venueLimit===9999)?'∞':$venueLimit ?>
        </span>
      </div>

      <?php if (empty($venues)): ?>
      <p class="text-xs text-slate-400 text-center py-4">Žiadna prevádzka.</p>
      <?php else: ?>
      <ul class="space-y-0.5">
        <?php foreach ($venues as $v):
          $active = ($selected && $selected['slug']===$v['slug']);
          $vc = resolveColor($v['color']);
        ?>
        <li>
          <a href="?edit=<?= e($v['slug']) ?>"
             class="flex items-center gap-2 px-2.5 py-2 rounded-xl text-xs transition
                    <?= $active ? 'bg-indigo-50 dark:bg-indigo-900/40 text-indigo-700 dark:text-indigo-300 font-bold'
                                : 'hover:bg-gray-100 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-400 font-medium' ?>">
            <span class="w-2.5 h-2.5 rounded-full flex-shrink-0"
                  style="background:<?= e($vc['hex']) ?>"></span>
            <span class="flex-1 truncate"><?= e($v['name']) ?></span>
            <?php $sc = $scanStats[$v['slug']] ?? []; if (!empty($sc['month'])): ?>
            <span class="text-[10px] font-semibold text-slate-400 dark:text-slate-500 flex-shrink-0">
              👁 <?= $sc['month'] ?>
            </span>
            <?php endif; ?>
          </a>
        </li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>

      <?php if ($venueCount < $venueLimit || $role === 'admin'): ?>
      <button onclick="openNewVenue()"
              class="mt-3 w-full py-2 bg-emerald-600 hover:bg-emerald-700
                     text-white text-xs font-bold rounded-2xl transition">
        + Nová prevádzka
      </button>
      <?php endif; ?>
    </div>

  </aside>

  <!-- ══ CENTER: Tabs ════════════════════════════════════════════════ -->
  <section class="min-w-0 space-y-3">

    <!-- Tab bar (segment control) -->
    <div class="bg-gray-100 dark:bg-slate-800 rounded-[2rem] p-1.5 grid grid-cols-3 gap-1 border border-gray-200 dark:border-slate-700">
      <button id="tab-btn-settings" onclick="switchTab('settings')"
              class="py-2.5 text-sm font-bold rounded-3xl transition-all duration-200
                     bg-white dark:bg-slate-900 text-slate-900 dark:text-white shadow-sm border border-gray-100 dark:border-slate-700">
        ⚙️ Nastavenia
      </button>
      <button id="tab-btn-menu" onclick="switchTab('menu')"
              class="py-2.5 text-sm font-bold rounded-3xl transition-all duration-200
                     text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200">
        🍽️ Jedálny lístok
      </button>
      <button id="tab-btn-analytics" onclick="switchTab('analytics')"
              class="py-2.5 text-sm font-bold rounded-3xl transition-all duration-200
                     text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200">
        📊 Analytika
      </button>
    </div>

    <!-- ── Tab: Settings ─────────────────────────────────────────── -->
    <div id="tab-settings" class="space-y-4">

      <!-- Card: Základné info -->
      <div class="bg-white dark:bg-slate-900 rounded-[2rem] shadow-sm p-5 border border-gray-100 dark:border-slate-800">
        <p id="settings-title" class="text-[10px] font-black uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-5">
          <?= $selected ? 'Upraviť prevádzku' : 'Nová prevádzka' ?>
        </p>
        <input type="hidden" id="f-original-slug" value="<?= $selected ? e($selected['slug']) : '' ?>">
        <div class="space-y-4">

          <!-- Slug -->
          <div>
            <label class="text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1.5 block">URL slug *</label>
            <div class="flex rounded-xl bg-gray-100 dark:bg-slate-800 overflow-hidden
                        focus-within:ring-2 focus-within:ring-indigo-500 transition-all duration-200">
              <span class="text-slate-500 dark:text-slate-400 text-xs px-3 flex items-center border-r border-gray-200 dark:border-slate-700">
                /r/
              </span>
              <input id="f-slug" type="text" required maxlength="50"
                     pattern="[a-z0-9][a-z0-9_\-]{1,49}"
                     class="flex-1 px-3 py-2.5 text-sm outline-none bg-transparent text-slate-900 dark:text-slate-100"
                     value="<?= $selected ? e($selected['slug']) : '' ?>"
                     placeholder="nazov-podniku"
                     oninput="updatePreview()">
            </div>
          </div>

          <!-- Name -->
          <div>
            <label class="text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1.5 block">Názov podniku *</label>
            <input id="f-name" type="text" required maxlength="200"
                   class="w-full bg-gray-100 dark:bg-slate-800 border-none rounded-xl px-4 py-2.5 text-sm
                          text-slate-900 dark:text-slate-100 outline-none focus:ring-2 focus:ring-indigo-500 transition-all duration-200"
                   value="<?= $selected ? e($selected['name']) : '' ?>"
                   placeholder="Kaviareň U Márie"
                   oninput="updatePreview()">
          </div>

          <!-- URL fields (Google + Instagram) -->
          <?php foreach ([
              ['f-google', 'Google Recenzie (URL)', $selected['google_url']    ?? ''],
              ['f-insta',  'Instagram (URL)',        $selected['instagram_url'] ?? ''],
          ] as [$fid, $label, $val]): ?>
          <div>
            <label class="text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1.5 block"><?= $label ?></label>
            <input id="<?= $fid ?>" type="url"
                   class="w-full bg-gray-100 dark:bg-slate-800 border-none rounded-xl px-4 py-2.5 text-sm
                          text-slate-900 dark:text-slate-100 outline-none focus:ring-2 focus:ring-indigo-500 transition-all duration-200"
                   value="<?= e($val) ?>" placeholder="https://"
                   oninput="updatePreview()">
          </div>
          <?php endforeach; ?>

        </div>
      </div>

      <!-- Card: Farba témy -->
      <?php $currentVenueHex = $selected ? resolveColor($selected['color'])['hex'] : ''; ?>
      <div class="bg-white dark:bg-slate-900 rounded-[2rem] shadow-sm p-5 border border-gray-100 dark:border-slate-800">
        <p class="text-[10px] font-black uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-4">Farba témy / hlavičky</p>
        <div class="flex flex-wrap gap-2">
          <?php foreach ($GASTRO_THEMES as $gt): ?>
          <button type="button" onclick="setVenueColor('<?= e($gt['bg']) ?>')"
                  title="<?= e($gt['name']) ?>"
                  data-vc="<?= e($gt['bg']) ?>"
                  class="vc-btn w-8 h-8 rounded-full border-2 border-slate-200 dark:border-slate-700 transition ring-offset-1"
                  style="background:<?= e($gt['bg']) ?>;<?= ($currentVenueHex===$gt['bg']) ? 'border-color:#6366f1' : '' ?>">
          </button>
          <?php endforeach; ?>
          <!-- Custom color picker -->
          <label class="relative w-8 h-8 rounded-full border-2 border-dashed border-slate-300 dark:border-slate-600
                        hover:border-indigo-400 dark:hover:border-indigo-500 transition cursor-pointer
                        flex items-center justify-center" title="Vlastná farba">
            <span class="text-sm pointer-events-none">🎨</span>
            <input type="color" id="f-color-picker" class="sr-only"
                   value="<?= $selected ? e(resolveColor($selected['color'])['hex']) : '#111827' ?>"
                   oninput="setVenueColor(this.value)">
          </label>
        </div>
        <input type="hidden" id="f-color" value="<?= $selected ? e($selected['color']) : '#111827' ?>">
      </div>

      <!-- Card: Logo & Cover -->
      <div class="bg-white dark:bg-slate-900 rounded-[2rem] shadow-sm p-5 border border-gray-100 dark:border-slate-800">
        <p class="text-[10px] font-black uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-5">Logo &amp; Cover fotka</p>
        <div class="space-y-5">

          <!-- Logo -->
          <div>
            <label class="text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1.5 block">Logo (voliteľné)</label>
            <div id="logo-preview-wrap"
                 class="<?= ($selected&&$selected['logo']) ? '' : 'hidden' ?> mb-3">
              <img id="logo-preview"
                   src="<?= ($selected&&$selected['logo']) ? e(imgUrl($selected['logo'])) : '' ?>"
                   alt="" class="h-14 w-14 object-contain rounded-xl border border-slate-200 dark:border-slate-700">
              <button type="button" onclick="clearLogo()"
                      class="mt-1 text-xs text-red-500 dark:text-red-400 hover:underline block">
                Odstrániť logo
              </button>
            </div>
            <input type="file" id="f-logo-file" accept="image/*"
                   class="text-xs text-slate-500 dark:text-slate-400 w-full
                          file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0
                          file:text-xs file:font-semibold
                          file:bg-indigo-50 dark:file:bg-indigo-900/30
                          file:text-indigo-700 dark:file:text-indigo-400
                          hover:file:bg-indigo-100 dark:hover:file:bg-indigo-900/50">
            <input type="hidden" id="f-logo"
                   value="<?= ($selected&&$selected['logo']) ? e(imgUrl($selected['logo'])) : '' ?>">
            <p class="text-[11px] text-slate-400 dark:text-slate-500 mt-1.5">
              Max 512 KB · automaticky orezané na 256 × 256 px
            </p>
          </div>

          <!-- Cover image -->
          <div>
            <label class="text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1.5 block">
              Cover fotka <span class="text-slate-400 font-normal">(voliteľné · namiesto farebnej hlavičky)</span>
            </label>
            <div id="cover-preview-wrap"
                 class="<?= ($selected && !empty($selected['cover_image'])) ? '' : 'hidden' ?> mb-3">
              <img id="cover-preview"
                   src="<?= ($selected && !empty($selected['cover_image'])) ? e(imgUrl($selected['cover_image'])) : '' ?>"
                   alt="" class="w-full h-28 object-cover rounded-2xl border border-gray-200 dark:border-slate-700">
              <button type="button" onclick="clearCover()"
                      class="mt-1 text-xs text-red-500 dark:text-red-400 hover:underline block">
                Odstrániť cover fotku
              </button>
            </div>
            <input type="file" id="f-cover-file" accept="image/*"
                   class="text-xs text-slate-500 dark:text-slate-400 w-full
                          file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0
                          file:text-xs file:font-semibold
                          file:bg-indigo-50 dark:file:bg-indigo-900/30
                          file:text-indigo-700 dark:file:text-indigo-400
                          hover:file:bg-indigo-100 dark:hover:file:bg-indigo-900/50">
            <input type="hidden" id="f-cover"
                   value="<?= ($selected && !empty($selected['cover_image'])) ? e(imgUrl($selected['cover_image'])) : '' ?>">
            <p class="text-[11px] text-slate-400 dark:text-slate-500 mt-1.5">
              Max 1 MB · automaticky orezané na 1200 × 400 px (3:1)
            </p>
          </div>

        </div>
      </div>

      <!-- Actions -->
      <div class="flex gap-2">
        <button id="btn-save-venue" type="button" onclick="saveVenue()"
                class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white
                       font-bold py-2.5 rounded-2xl text-sm transition">
          Uložiť nastavenia
        </button>
        <?php if ($selected): ?>
        <button type="button" onclick="deleteVenue('<?= e($selected['slug']) ?>')"
                class="px-4 bg-red-50 dark:bg-red-900/30 hover:bg-red-100 dark:hover:bg-red-900/50
                       text-red-600 dark:text-red-400
                       font-bold py-2.5 rounded-2xl text-sm transition">
          Zmazať
        </button>
        <?php endif; ?>
      </div>

    </div>

    <!-- ── Tab: Menu ─────────────────────────────────────────────── -->
    <div id="tab-menu" class="space-y-4 hidden">
      <?php if (!$selected): ?>
      <div class="bg-white dark:bg-slate-900 rounded-[2rem] shadow-sm p-12 text-center border border-gray-100 dark:border-slate-800">
        <p class="text-4xl mb-3">🍽️</p>
        <p class="text-sm text-slate-400">Najprv vytvorte prevádzku v záložke Nastavenia.</p>
      </div>
      <?php else: ?>

      <!-- Global menu settings -->
      <div class="bg-white dark:bg-slate-900 rounded-[2rem] shadow-sm p-5 border border-gray-100 dark:border-slate-800">
        <p class="text-[10px] font-black uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-5">Globálne nastavenia menu</p>

        <div class="grid grid-cols-2 gap-3 mb-5">
          <!-- Toggle: Odporúčame -->
          <label class="flex items-center justify-between cursor-pointer select-none p-3 rounded-xl
                        border border-gray-100 dark:border-slate-700
                        hover:bg-gray-50 dark:hover:bg-slate-800 transition">
            <span class="flex items-center gap-2">
              <span>⭐</span>
              <span class="text-xs font-semibold text-slate-700 dark:text-slate-300">Odporúčame</span>
            </span>
            <div class="relative flex-shrink-0">
              <input type="checkbox" id="m-show-featured" class="sr-only peer"
                     <?= !empty($menuSettings['show_featured']) ? 'checked' : '' ?>
                     onchange="menuData.settings['show_featured']=this.checked?1:0;updatePreview()">
              <div class="w-10 h-5 bg-gray-200 dark:bg-slate-600 rounded-full transition-colors duration-200
                          peer-checked:bg-indigo-600
                          after:content-[''] after:absolute after:top-0.5 after:left-0.5
                          after:bg-white after:rounded-full after:h-4 after:w-4
                          after:transition-all after:duration-200
                          peer-checked:after:translate-x-5"></div>
            </div>
          </label>
          <!-- Toggle: Alergény -->
          <label class="flex items-center justify-between cursor-pointer select-none p-3 rounded-xl
                        border border-gray-100 dark:border-slate-700
                        hover:bg-gray-50 dark:hover:bg-slate-800 transition">
            <span class="flex items-center gap-2">
              <span>🔢</span>
              <span class="text-xs font-semibold text-slate-700 dark:text-slate-300">Alergény</span>
            </span>
            <div class="relative flex-shrink-0">
              <input type="checkbox" id="m-show-allergens" class="sr-only peer"
                     <?= !empty($menuSettings['show_allergens']) ? 'checked' : '' ?>
                     onchange="menuData.settings['show_allergens']=this.checked?1:0;updatePreview()">
              <div class="w-10 h-5 bg-gray-200 dark:bg-slate-600 rounded-full transition-colors duration-200
                          peer-checked:bg-indigo-600
                          after:content-[''] after:absolute after:top-0.5 after:left-0.5
                          after:bg-white after:rounded-full after:h-4 after:w-4
                          after:transition-all after:duration-200
                          peer-checked:after:translate-x-5"></div>
            </div>
          </label>
        </div>

        <!-- Default category theme -->
        <div class="mb-6">
          <p class="text-[10px] font-black uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-3">
            Predvolená farba kategórií
          </p>
          <div class="flex flex-wrap gap-2">
            <?php foreach ($GASTRO_THEMES as $gt): ?>
            <button type="button"
                    onclick="selectGlobalTheme('cat','<?= e($gt['bg']) ?>')"
                    data-gcat="<?= e($gt['bg']) ?>"
                    title="<?= e($gt['name']) ?>"
                    class="gcat-btn w-8 h-8 rounded-full border-2 border-slate-200 dark:border-slate-700 transition ring-offset-1"
                    style="background:<?= e($gt['bg']) ?>;<?= ($menuSettings['default_category_color']===$gt['bg']) ? 'border-color:#6366f1' : '' ?>">
            </button>
            <?php endforeach; ?>
            <label class="relative w-8 h-8 rounded-full border-2 border-dashed border-slate-300 dark:border-slate-600
                          hover:border-indigo-400 dark:hover:border-indigo-500 transition cursor-pointer
                          flex items-center justify-center" title="Vlastná farba">
              <span class="text-sm pointer-events-none">🎨</span>
              <input type="color" id="m-cat-custom-color" class="sr-only"
                     value="<?= e($menuSettings['default_category_color']) ?>"
                     oninput="selectGlobalTheme('cat', this.value)">
            </label>
          </div>
          <input type="hidden" id="m-cat-color" value="<?= e($menuSettings['default_category_color']) ?>">
        </div>

        <!-- Default item theme -->
        <div class="mb-5">
          <p class="text-[10px] font-black uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-3">
            Predvolená farba kariet jedál
          </p>
          <div class="flex flex-wrap gap-2">
            <?php foreach ($GASTRO_THEMES as $gt): ?>
            <button type="button"
                    onclick="selectGlobalTheme('item','<?= e($gt['bg']) ?>')"
                    data-gitem="<?= e($gt['bg']) ?>"
                    title="<?= e($gt['name']) ?>"
                    class="gitem-btn w-8 h-8 rounded-full border-2 border-slate-200 dark:border-slate-700 transition ring-offset-1"
                    style="background:<?= e($gt['bg']) ?>;<?= ($menuSettings['default_item_color']===$gt['bg']) ? 'border-color:#6366f1' : '' ?>">
            </button>
            <?php endforeach; ?>
            <label class="relative w-8 h-8 rounded-full border-2 border-dashed border-slate-300 dark:border-slate-600
                          hover:border-indigo-400 dark:hover:border-indigo-500 transition cursor-pointer
                          flex items-center justify-center" title="Vlastná farba">
              <span class="text-sm pointer-events-none">🎨</span>
              <input type="color" id="m-item-custom-color" class="sr-only"
                     value="<?= e($menuSettings['default_item_color']) ?>"
                     oninput="selectGlobalTheme('item', this.value)">
            </label>
          </div>
          <input type="hidden" id="m-item-color" value="<?= e($menuSettings['default_item_color']) ?>">
        </div>

        <button onclick="saveMenuSettings()"
                class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700
                       text-white text-xs font-bold rounded-2xl transition">
          Uložiť nastavenia menu
        </button>
      </div>

      <?php if ($lockdown): ?>
      <!-- Lockdown: expired or limit overrun -->
      <div class="bg-amber-50 dark:bg-amber-900/20 rounded-[2rem] p-8 border border-amber-200 dark:border-amber-800/40 text-center">
        <p class="text-4xl mb-4">🔒</p>
        <h3 class="text-base font-bold text-amber-900 dark:text-amber-200 mb-2">Ochranný režim</h3>
        <p class="text-sm text-amber-700 dark:text-amber-300 mb-6 leading-relaxed max-w-xs mx-auto">
          Prístup k správe menu je obmedzený. Vaše menu prekračuje limity vášho aktuálneho plánu alebo platba expirovala.
        </p>
        <div class="flex flex-col sm:flex-row gap-3 justify-center">
          <a href="<?= url('plans') ?>"
             class="inline-flex items-center justify-center gap-2 px-6 py-2.5
                    bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold
                    rounded-2xl transition-all active:scale-95">
            Upgrade
          </a>
          <?php if ($selected): ?>
          <button onclick="resetMenu('<?= e($selected['slug']) ?>')"
             class="inline-flex items-center justify-center gap-2 px-6 py-2.5
                    bg-amber-100 hover:bg-amber-200 dark:bg-amber-800/30 dark:hover:bg-amber-800/50
                    text-amber-800 dark:text-amber-200 text-sm font-bold
                    rounded-2xl transition-all active:scale-95">
            Zresetovať menu podľa limitov
          </button>
          <?php endif; ?>
        </div>
      </div>
      <?php else: ?>
      <!-- Categories & items -->
      <div class="bg-white dark:bg-slate-900 rounded-[2rem] shadow-sm p-5 border border-gray-100 dark:border-slate-800">
        <div class="flex items-center justify-between mb-4">
          <p class="text-[10px] font-black uppercase tracking-wider text-slate-400 dark:text-slate-500">Kategórie a jedlá</p>
          <?php $catLimitReached = $userPlan !== 'admin' && count($menuCategories) >= $maxCats; ?>
          <button id="btn-add-cat" onclick="openCatModal(null)"
                  <?= $catLimitReached ? "title=\"Dosiahnutý limit {$maxCats} kategórií\"" : '' ?>
                  class="px-4 py-2 rounded-2xl transition text-xs font-bold
                         <?= $catLimitReached
                             ? 'bg-gray-100 dark:bg-slate-800 text-slate-400 dark:text-slate-500 opacity-60'
                             : 'bg-emerald-600 hover:bg-emerald-700 text-white' ?>">
            <?= $catLimitReached ? '🔒 Limit' : '+ Kategória' ?>
          </button>
        </div>
        <!-- Live search -->
        <div class="mb-3 relative">
          <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 pointer-events-none"
               viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
               stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
          </svg>
          <input id="menu-search" type="search"
                 placeholder="Hľadať jedlo alebo kategóriu…"
                 oninput="menuSearchDebounced()"
                 class="w-full pl-9 pr-4 py-2.5 bg-gray-100 dark:bg-slate-800 border-none rounded-xl
                        text-sm text-slate-900 dark:text-slate-100
                        placeholder-slate-400 dark:placeholder-slate-500
                        focus:outline-none focus:ring-2 focus:ring-indigo-500
                        transition-all duration-200">
        </div>

        <div id="menu-tree">
          <p class="text-xs text-slate-400 text-center py-6">Načítavanie…</p>
        </div>
      </div>
      <?php endif; ?>
      <?php endif; ?>
    </div>

    <!-- ── Tab: Analytics ──────────────────────────────────────────── -->
    <div id="tab-analytics" class="space-y-4 hidden">
      <?php if (!$selected): ?>
      <div class="bg-white dark:bg-slate-900 rounded-[2rem] shadow-sm p-12 text-center border border-gray-100 dark:border-slate-800">
        <p class="text-4xl mb-3">📊</p>
        <p class="text-sm text-slate-400">Najprv vytvorte prevádzku v záložke Nastavenia.</p>
      </div>
      <?php else: ?>
      <?php
      $ss    = $scanStats[$selected['slug']] ?? [];
      $total = (int)($ss['total'] ?? 0);
      $month = (int)($ss['month'] ?? 0);
      ?>
      <div class="grid grid-cols-2 gap-4">
        <div class="bg-white dark:bg-slate-900 rounded-[2rem] shadow-sm p-6 border border-gray-100 dark:border-slate-800 flex flex-col items-center justify-center text-center">
          <div class="w-12 h-12 rounded-2xl bg-indigo-50 dark:bg-indigo-900/30 flex items-center justify-center mb-4">
            <svg class="w-6 h-6 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
              <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
            </svg>
          </div>
          <p class="text-5xl font-extrabold text-slate-900 dark:text-white mb-2"><?= $month ?></p>
          <p class="text-sm font-semibold text-slate-500 dark:text-slate-400">Zobrazenia</p>
          <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">tento mesiac</p>
        </div>
        <div class="bg-white dark:bg-slate-900 rounded-[2rem] shadow-sm p-6 border border-gray-100 dark:border-slate-800 flex flex-col items-center justify-center text-center">
          <div class="w-12 h-12 rounded-2xl bg-emerald-50 dark:bg-emerald-900/30 flex items-center justify-center mb-4">
            <svg class="w-6 h-6 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
            </svg>
          </div>
          <p class="text-5xl font-extrabold text-slate-900 dark:text-white mb-2"><?= $total ?></p>
          <p class="text-sm font-semibold text-slate-500 dark:text-slate-400">Celkovo</p>
          <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">od začiatku</p>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </section>

  <!-- ══ RIGHT: Preview + QR ════════════════════════════════════════ -->
  <aside class="space-y-3">

    <!-- iPhone preview frame -->
    <div class="bg-white dark:bg-slate-900 rounded-[2rem] shadow-sm p-4 border border-gray-100 dark:border-slate-800">
      <div class="flex items-center justify-between mb-3">
        <h2 class="font-bold text-slate-700 dark:text-slate-300 text-xs uppercase tracking-widest">Živý náhľad</h2>
        <button onclick="previewDark=!previewDark;updatePreview()"
                id="preview-dark-btn"
                class="text-xs px-2.5 py-1 rounded-xl border border-gray-200 dark:border-slate-700
                       bg-white dark:bg-slate-800 text-slate-500 dark:text-slate-400
                       hover:bg-gray-50 dark:hover:bg-slate-700 transition font-medium">
          🌙 Dark
        </button>
      </div>
      <!-- Phone frame -->
      <div class="flex justify-center">
        <div class="relative rounded-[2.25rem] border-[4px] border-slate-800 shadow-2xl overflow-hidden"
             style="width:176px;height:360px;background:#fff">
          <!-- Notch -->
          <div class="absolute top-0 left-1/2 -translate-x-1/2 w-16 h-4 bg-slate-800 rounded-b-xl z-10"></div>
          <div id="preview-frame"
               class="absolute inset-0 overflow-y-auto no-scrollbar"
               style="font-size:9.5px;line-height:1.4"></div>
        </div>
      </div>
    </div>

    <!-- QR code -->
    <div class="bg-white dark:bg-slate-900 rounded-[2rem] shadow-sm p-4 border border-gray-100 dark:border-slate-800">
      <h2 class="font-bold text-slate-700 dark:text-slate-300 text-xs uppercase tracking-widest mb-3">QR kód</h2>
      <?php if ($selected): ?>
      <div id="qr-box" class="flex justify-center mb-3"></div>
      <button onclick="downloadQR()"
              class="w-full py-2 bg-slate-800 hover:bg-slate-700 text-white
                     text-xs font-bold rounded-2xl transition">
        Stiahnuť PNG
      </button>
      <a href="<?= e(baseUrl() . '/r/' . $selected['slug']) ?>" target="_blank"
         class="block text-xs font-mono text-indigo-600 hover:underline text-center mt-2 break-all">
        /r/<?= e($selected['slug']) ?>
      </a>
      <?php else: ?>
      <p class="text-xs text-slate-400 text-center py-4">Najprv uložte prevádzku.</p>
      <?php endif; ?>
    </div>
  </aside>

</div><!-- /.grid -->

<!-- ══ MODAL: Category ════════════════════════════════════════════════ -->
<div id="modal-cat"
     class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
  <div class="bg-white dark:bg-slate-900 rounded-[2rem] shadow-2xl p-6 w-full max-w-sm max-h-[92vh] overflow-y-auto border border-gray-100 dark:border-slate-800">
    <h3 class="font-bold text-lg mb-5 text-slate-900 dark:text-white" id="modal-cat-title">Kategória</h3>

    <!-- Emoji picker -->
    <div class="mb-5">
      <label class="text-xs font-semibold text-slate-600 dark:text-slate-400 mb-2 block">Ikona kategórie</label>
      <div class="grid grid-cols-6 gap-1.5 p-3 bg-slate-50 dark:bg-slate-800 rounded-xl">
        <?php foreach ($GASTRO_EMOJIS as $em): ?>
        <button type="button" onclick="selectEmoji('<?= e($em) ?>')"
                data-emoji="<?= e($em) ?>"
                class="emoji-btn w-9 h-9 rounded-xl text-xl flex items-center justify-center
                       hover:bg-white dark:hover:bg-slate-700 border-2 border-transparent transition
                       hover:shadow-sm hover:scale-110">
          <?= $em ?>
        </button>
        <?php endforeach; ?>
      </div>
      <input type="hidden" id="mc-icon" value="">
      <p id="mc-icon-label" class="text-xs text-slate-400 mt-2 text-center">
        Kliknite na emoji pre výber
      </p>
    </div>

    <!-- Name -->
    <div class="mb-5">
      <label class="text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1.5 block">Názov kategórie *</label>
      <input id="mc-name" type="text" placeholder="napr. Polievky"
             class="w-full bg-gray-100 dark:bg-slate-800 border-none rounded-xl px-4 py-3 text-sm
                    text-slate-900 dark:text-slate-100 outline-none focus:ring-2 focus:ring-indigo-500 transition-all duration-200">
    </div>

    <!-- Gastro theme palette -->
    <div class="mb-5">
      <label class="text-xs font-semibold text-slate-600 dark:text-slate-400 mb-2 block">
        Farba pozadia <span class="text-slate-400 font-normal">(alebo sa použije predvolená)</span>
      </label>
      <div class="flex flex-wrap gap-2">
        <!-- "None / use default" -->
        <button type="button" onclick="selectCatTheme('')" data-ccat=""
                class="cat-theme-btn w-9 h-9 rounded-full border-2 border-slate-300 dark:border-slate-600
                       bg-white dark:bg-slate-700 flex items-center justify-center text-slate-400 dark:text-slate-500
                       ring-offset-1 transition hover:border-slate-400">
          <svg class="w-4 h-4" viewBox="0 0 16 16" fill="none">
            <line x1="3" y1="13" x2="13" y2="3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
          </svg>
        </button>
        <?php foreach ($GASTRO_THEMES as $gt): ?>
        <button type="button" onclick="selectCatTheme('<?= e($gt['bg']) ?>')"
                data-ccat="<?= e($gt['bg']) ?>"
                title="<?= e($gt['name']) ?>"
                class="cat-theme-btn w-9 h-9 rounded-full border-2 border-slate-200 dark:border-slate-700
                       ring-offset-1 transition hover:scale-110"
                style="background:<?= e($gt['bg']) ?>">
        </button>
        <?php endforeach; ?>
        <label class="relative w-9 h-9 rounded-full border-2 border-dashed border-slate-300 dark:border-slate-600
                      hover:border-indigo-400 dark:hover:border-indigo-500 transition cursor-pointer
                      flex items-center justify-center" title="Vlastná farba">
          <span class="text-sm pointer-events-none">🎨</span>
          <input type="color" id="mc-custom-color" class="sr-only"
                 value="#ffffff"
                 oninput="selectCatTheme(this.value)">
        </label>
      </div>
      <input type="hidden" id="mc-color" value="">
    </div>

    <input type="hidden" id="mc-id" value="">
    <div class="flex gap-2">
      <button onclick="saveCat()"
              class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white
                     font-bold py-2.5 rounded-2xl text-sm transition">
        Uložiť
      </button>
      <button onclick="closeModal('modal-cat')"
              class="px-4 bg-gray-100 dark:bg-slate-800 hover:bg-gray-200 dark:hover:bg-slate-700
                     text-slate-700 dark:text-slate-300
                     font-bold py-2.5 rounded-2xl text-sm transition">
        Zrušiť
      </button>
    </div>
  </div>
</div>

<!-- ══ MODAL: Item ════════════════════════════════════════════════════ -->
<div id="modal-item"
     class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
  <div class="bg-white dark:bg-slate-900 rounded-[2rem] shadow-2xl p-6 w-full max-w-lg max-h-[92vh] overflow-y-auto border border-gray-100 dark:border-slate-800">
    <h3 class="font-bold text-lg mb-5 text-slate-900 dark:text-white" id="modal-item-title">Jedlo</h3>
    <div class="space-y-4">

      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1.5 block">Názov *</label>
          <input id="mi-name" type="text" placeholder="Rajčinová polievka"
                 class="w-full bg-gray-100 dark:bg-slate-800 border-none rounded-xl px-4 py-3 text-sm
                        text-slate-900 dark:text-slate-100 outline-none focus:ring-2 focus:ring-indigo-500 transition-all duration-200">
        </div>
        <div>
          <label class="text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1.5 block">Cena (€) *</label>
          <input id="mi-price" type="number" step="0.01" min="0" placeholder="4.50"
                 class="w-full bg-gray-100 dark:bg-slate-800 border-none rounded-xl px-4 py-3 text-sm
                        text-slate-900 dark:text-slate-100 outline-none focus:ring-2 focus:ring-indigo-500 transition-all duration-200">
        </div>
      </div>

      <div>
        <label class="text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1.5 block">Gramáž / objem</label>
        <input id="mi-weight" type="text" placeholder="300 ml, 150 g..."
               class="w-full bg-gray-100 dark:bg-slate-800 border-none rounded-xl px-4 py-3 text-sm
                      text-slate-900 dark:text-slate-100 outline-none focus:ring-2 focus:ring-indigo-500 transition-all duration-200">
      </div>

      <div>
        <label class="text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1.5 block">
          Krátky popis <span class="text-slate-400 font-normal">(zobrazuje sa v zozname)</span>
        </label>
        <input id="mi-desc" type="text"
               placeholder="Krémová polievka z čerstvých rajčín"
               class="w-full bg-gray-100 dark:bg-slate-800 border-none rounded-xl px-4 py-3 text-sm
                      text-slate-900 dark:text-slate-100 outline-none focus:ring-2 focus:ring-indigo-500 transition-all duration-200">
      </div>

      <div>
        <label class="text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1.5 block">
          Detailný popis <span class="text-slate-400 font-normal">(v detail okne)</span>
        </label>
        <textarea id="mi-detail" rows="3"
                  placeholder="Podrobný opis jedla pre zákazníka…"
                  class="w-full bg-gray-100 dark:bg-slate-800 border-none rounded-xl px-4 py-3 text-sm
                         text-slate-900 dark:text-slate-100 outline-none focus:ring-2 focus:ring-indigo-500 transition-all duration-200 resize-none"></textarea>
      </div>

      <label class="flex items-center gap-2.5 cursor-pointer">
        <input type="checkbox" id="mi-featured" class="w-4 h-4 accent-amber-500">
        <span class="text-xs font-semibold text-slate-700 dark:text-slate-300">⭐ Zobraziť v sekcii "Odporúčame"</span>
      </label>

      <!-- Allergens -->
      <div>
        <label class="text-xs font-semibold text-slate-600 dark:text-slate-400 mb-2 block">Alergény (EU 1–14)</label>
        <div class="grid grid-cols-2 gap-1.5">
          <?php foreach ($EU_ALLERGENS as $num => $label): ?>
          <label class="flex items-center gap-1.5 cursor-pointer
                        px-2 py-1.5 rounded-lg hover:bg-gray-50 dark:hover:bg-slate-800 transition">
            <input type="checkbox" class="mi-allergen w-3.5 h-3.5 accent-indigo-600"
                   value="<?= $num ?>">
            <span class="text-xs text-slate-600 dark:text-slate-400"><?= $num ?> – <?= e($label) ?></span>
          </label>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Item gastro theme -->
      <div>
        <label class="text-xs font-semibold text-slate-600 dark:text-slate-400 mb-2 block">Farba karty jedla</label>
        <div class="flex flex-wrap gap-2">
          <button type="button" onclick="selectItemTheme('')" data-iitem=""
                  class="item-theme-btn w-9 h-9 rounded-full border-2 border-slate-300 dark:border-slate-600
                         bg-white dark:bg-slate-700 flex items-center justify-center text-slate-400 dark:text-slate-500
                         ring-offset-1 transition hover:border-slate-400">
            <svg class="w-4 h-4" viewBox="0 0 16 16" fill="none">
              <line x1="3" y1="13" x2="13" y2="3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
            </svg>
          </button>
          <?php foreach ($GASTRO_THEMES as $gt): ?>
          <button type="button" onclick="selectItemTheme('<?= e($gt['bg']) ?>')"
                  data-iitem="<?= e($gt['bg']) ?>"
                  title="<?= e($gt['name']) ?>"
                  class="item-theme-btn w-9 h-9 rounded-full border-2 border-slate-200 dark:border-slate-700
                         ring-offset-1 transition hover:scale-110"
                  style="background:<?= e($gt['bg']) ?>">
          </button>
          <?php endforeach; ?>
          <label class="relative w-9 h-9 rounded-full border-2 border-dashed border-slate-300 dark:border-slate-600
                        hover:border-indigo-400 dark:hover:border-indigo-500 transition cursor-pointer
                        flex items-center justify-center" title="Vlastná farba">
            <span class="text-sm pointer-events-none">🎨</span>
            <input type="color" id="mi-custom-color" class="sr-only"
                   value="#ffffff"
                   oninput="selectItemTheme(this.value)">
          </label>
        </div>
        <input type="hidden" id="mi-color" value="">
      </div>

      <!-- Item image -->
      <div>
        <label class="text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1.5 block">
          Fotka jedla <span class="text-slate-400 font-normal">(voliteľné)</span>
        </label>
        <div id="mi-img-preview-wrap" class="hidden mb-2">
          <img id="mi-img-preview" src="" alt=""
               class="w-full h-36 object-cover rounded-2xl border border-gray-200 dark:border-slate-700">
          <button type="button" onclick="clearItemImage()"
                  class="text-xs text-red-500 hover:underline mt-1.5 block">
            Odstrániť fotku
          </button>
        </div>
        <label class="cursor-pointer inline-flex items-center gap-2 px-4 py-2
                      bg-gray-100 dark:bg-slate-800 hover:bg-gray-200 dark:hover:bg-slate-700
                      text-slate-700 dark:text-slate-300 text-xs font-semibold
                      rounded-xl transition-all duration-200">
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"
               stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/>
            <path d="m21 15-5-5L5 21"/>
          </svg>
          Nahrať fotku
          <input type="file" id="mi-img-file" accept="image/*" class="sr-only">
        </label>
        <input type="hidden" id="mi-image" value="">
      </div>
    </div>

    <input type="hidden" id="mi-id" value="">
    <input type="hidden" id="mi-cat-id" value="">
    <div class="flex gap-2 mt-5">
      <button onclick="saveItemData()"
              class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white
                     font-bold py-2.5 rounded-2xl text-sm transition">
        Uložiť jedlo
      </button>
      <button onclick="closeModal('modal-item')"
              class="px-4 bg-gray-100 dark:bg-slate-800 hover:bg-gray-200 dark:hover:bg-slate-700
                     text-slate-700 dark:text-slate-300
                     font-bold py-2.5 rounded-2xl text-sm transition">
        Zrušiť
      </button>
    </div>
  </div>
</div>

<!-- ══ MODAL: Confirm Slug Change ════════════════════════════════════ -->
<div id="modal-confirm-slug"
     class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
  <div class="bg-white dark:bg-slate-900 rounded-[2rem] shadow-2xl p-6 w-full max-w-sm border border-gray-100 dark:border-slate-800">
    <h3 class="font-bold text-lg mb-3 text-slate-900 dark:text-white">⚠️ Zmena URL adresy</h3>
    <p class="text-sm text-slate-600 dark:text-slate-400 mb-5">Pozor: Zmena adresy znefunkční všetky vytlačené QR kódy. Budete musieť vytlačiť nové.</p>
    <div class="flex gap-2">
      <button id="modal-confirm-slug-ok"
              class="flex-1 bg-red-600 hover:bg-red-700 text-white
                     font-bold py-2.5 rounded-2xl text-sm transition">
        Potvrdiť zmenu
      </button>
      <button id="modal-confirm-slug-cancel"
              class="px-4 bg-gray-100 dark:bg-slate-800 hover:bg-gray-200 dark:hover:bg-slate-700
                     text-slate-700 dark:text-slate-300
                     font-bold py-2.5 rounded-2xl text-sm transition">
        Zrušiť
      </button>
    </div>
  </div>
</div>

<script>
// ── Constants & state ─────────────────────────────────────────────
const CSRF    = <?= json_encode(csrfToken()) ?>;
const APP_URL = <?= json_encode(rtrim(url(), '/')) ?>;
const BASE_URL= <?= json_encode(rtrim(baseUrl(), '/')) ?>;
const CUR_SLUG= <?= json_encode($selected ? $selected['slug'] : '') ?>;
const PAL     = <?= json_encode(getPalette()) ?>;
const GASTRO    = <?= json_encode($GASTRO_THEMES) ?>;   // [{bg, name}, ...]
const USER_PLAN     = <?= json_encode($userPlan) ?>;
const MAX_CATS      = <?= json_encode($maxCats) ?>;
const MAX_ITEMS_CAT = <?= json_encode($maxItemsCat) ?>;

let menuData = {
  categories: <?= json_encode($menuCategories, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>,
  settings:   <?= json_encode($menuSettings,   JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>,
};
let currentVenueColor = <?= json_encode($selected ? $selected['color'] : 'black') ?>;
let previewDark       = document.documentElement.classList.contains('dark');
let activeTab         = 'settings';

// ── Network helpers ───────────────────────────────────────────────
function fetchWithTimeout(url, options, ms = 10000) {
  const ctrl = new AbortController();
  const id   = setTimeout(() => ctrl.abort(), ms);
  return fetch(url, { ...options, signal: ctrl.signal }).finally(() => clearTimeout(id));
}

// ── YIQ contrast (threshold 140) ─────────────────────────────────
function yiq(hex) {
  if (!hex || !/^#[0-9a-fA-F]{6}$/.test(hex)) return '#1e293b';
  const r=parseInt(hex.slice(1,3),16), g=parseInt(hex.slice(3,5),16), b=parseInt(hex.slice(5,7),16);
  return (r*299+g*587+b*114)/1000 >= 140 ? '#1e293b' : '#ffffff';
}
function yiqMuted(hex) {
  if (!hex || !/^#[0-9a-fA-F]{6}$/.test(hex)) return '#64748b';
  const r=parseInt(hex.slice(1,3),16), g=parseInt(hex.slice(3,5),16), b=parseInt(hex.slice(5,7),16);
  return (r*299+g*587+b*114)/1000 >= 140 ? '#64748b' : '#cbd5e1';
}

function resolveVenueColor() {
  const c = currentVenueColor;
  if (PAL[c]) return PAL[c].hex;
  if (/^#[0-9a-fA-F]{6}$/.test(c)) return c;
  return '#111827';
}

function esc(s) {
  return String(s || '').replace(/[&<>"']/g,
    m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
}
function fmtPrice(p) {
  return parseFloat(p || 0).toFixed(2).replace('.', ',') + ' €';
}

// ── Venue color selector ──────────────────────────────────────────
function setVenueColor(c) {
  currentVenueColor = c;
  document.getElementById('f-color').value = c;
  document.querySelectorAll('.vc-btn').forEach(b => {
    b.style.borderColor = b.dataset.vc === c ? '#6366f1' : '';
  });
  if (/^#[0-9a-fA-F]{6}$/.test(c)) {
    document.getElementById('f-color-picker').value = c;
  }
  updatePreview();
}

document.querySelectorAll('.vc-btn').forEach(b =>
  b.addEventListener('click', () => setVenueColor(b.dataset.vc))
);
document.getElementById('f-color-picker')?.addEventListener('input', function () {
  document.querySelectorAll('.vc-btn').forEach(b => b.style.borderColor = '');
  setVenueColor(this.value);
});

// ── Logo upload ───────────────────────────────────────────────────
document.getElementById('f-logo-file')?.addEventListener('change', function () {
  const file = this.files[0]; if (!file) return;
  if (file.size > 1_000_000) toast('Obrázok je príliš veľký, automaticky ho optimalizujeme pre rýchle načítanie…', 'info');
  const img = new Image();
  const objUrl = URL.createObjectURL(file);
  img.onload = () => {
    const MAX = 512;
    let w = img.width, h = img.height;
    if (w > MAX || h > MAX) {
      if (w >= h) { h = Math.round(h * MAX / w); w = MAX; }
      else        { w = Math.round(w * MAX / h); h = MAX; }
    }
    const cv = document.createElement('canvas');
    cv.width = w; cv.height = h;
    cv.getContext('2d').drawImage(img, 0, 0, w, h);
    const url = cv.toDataURL('image/jpeg', 0.8);
    URL.revokeObjectURL(objUrl);
    if (url.length > 700000) { toast('Logo je príliš veľké!', 'error'); return; }
    document.getElementById('f-logo').value = url;
    document.getElementById('logo-preview').src = url;
    document.getElementById('logo-preview-wrap').classList.remove('hidden');
    updatePreview();
  };
  img.src = objUrl;
});

function clearLogo() {
  document.getElementById('f-logo').value = '';
  document.getElementById('f-logo-file').value = '';
  document.getElementById('logo-preview').src = '';
  document.getElementById('logo-preview-wrap').classList.add('hidden');
  updatePreview();
}

// ── Cover image upload ────────────────────────────────────────────
document.getElementById('f-cover-file')?.addEventListener('change', function () {
  const file = this.files[0]; if (!file) return;
  if (file.size > 2_000_000) toast('Obrázok je príliš veľký, automaticky ho optimalizujeme pre rýchle načítanie…', 'info');
  const img = new Image();
  const objUrl = URL.createObjectURL(file);
  img.onload = () => {
    const MAX_W = 1200;
    let w = img.width, h = img.height;
    if (w > MAX_W) { h = Math.round(h * MAX_W / w); w = MAX_W; }
    const cv = document.createElement('canvas');
    cv.width = w; cv.height = h;
    cv.getContext('2d').drawImage(img, 0, 0, w, h);
    const url = cv.toDataURL('image/jpeg', 0.8);
    URL.revokeObjectURL(objUrl);
    if (url.length > 1_500_000) { toast('Cover fotka je príliš veľká (max 1 MB).', 'error'); return; }
    document.getElementById('f-cover').value = url;
    document.getElementById('cover-preview').src = url;
    document.getElementById('cover-preview-wrap').classList.remove('hidden');
    updatePreview();
  };
  img.src = objUrl;
});

function clearCover() {
  document.getElementById('f-cover').value = '';
  document.getElementById('f-cover-file').value = '';
  document.getElementById('cover-preview').src = '';
  document.getElementById('cover-preview-wrap').classList.add('hidden');
  updatePreview();
}

// ── Item image upload ─────────────────────────────────────────────
document.getElementById('mi-img-file')?.addEventListener('change', function () {
  const file = this.files[0]; if (!file) return;
  if (file.size > 1_000_000) toast('Obrázok je príliš veľký, automaticky ho optimalizujeme…', 'info');
  const img = new Image();
  const objUrl = URL.createObjectURL(file);
  img.onload = () => {
    const MAX_W = 600;
    let w = img.width, h = img.height;
    if (w > MAX_W) { h = Math.round(h * MAX_W / w); w = MAX_W; }
    const cv = document.createElement('canvas');
    cv.width = w; cv.height = h;
    cv.getContext('2d').drawImage(img, 0, 0, w, h);
    const url = cv.toDataURL('image/jpeg', 0.8);
    URL.revokeObjectURL(objUrl);
    document.getElementById('mi-image').value = url;
    document.getElementById('mi-img-preview').src = url;
    document.getElementById('mi-img-preview-wrap').classList.remove('hidden');
  };
  img.src = objUrl;
});

function clearItemImage() {
  document.getElementById('mi-image').value = '';
  const f = document.getElementById('mi-img-file');
  if (f) f.value = '';
  document.getElementById('mi-img-preview').src = '';
  document.getElementById('mi-img-preview-wrap').classList.add('hidden');
}

// ── Tab switching ─────────────────────────────────────────────────
function switchTab(tab) {
  activeTab = tab;
  const ACTIVE   = 'py-2.5 text-sm font-bold rounded-3xl transition-all duration-200 bg-white dark:bg-slate-900 text-slate-900 dark:text-white shadow-sm border border-gray-100 dark:border-slate-700';
  const INACTIVE = 'py-2.5 text-sm font-bold rounded-3xl transition-all duration-200 text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200';
  ['settings', 'menu', 'analytics'].forEach(t => {
    document.getElementById('tab-' + t)?.classList.toggle('hidden', t !== tab);
    const btn = document.getElementById('tab-btn-' + t);
    if (btn) btn.className = t === tab ? ACTIVE : INACTIVE;
  });
  if (tab === 'menu') renderMenuTree();
  updatePreview();
}

// ── Gastro theme pickers ──────────────────────────────────────────
function selectGlobalTheme(type, color) {
  if (type === 'cat') {
    document.getElementById('m-cat-color').value = color;
    menuData.settings.default_category_color = color;
    document.querySelectorAll('.gcat-btn').forEach(b =>
      b.style.borderColor = b.dataset.gcat === color ? '#6366f1' : '');
    const cp = document.getElementById('m-cat-custom-color');
    if (cp && /^#[0-9a-fA-F]{6}$/.test(color)) cp.value = color;
  } else {
    document.getElementById('m-item-color').value = color;
    menuData.settings.default_item_color = color;
    document.querySelectorAll('.gitem-btn').forEach(b =>
      b.style.borderColor = b.dataset.gitem === color ? '#6366f1' : '');
    const cp = document.getElementById('m-item-custom-color');
    if (cp && /^#[0-9a-fA-F]{6}$/.test(color)) cp.value = color;
  }
  updatePreview();
}

function selectCatTheme(color) {
  document.getElementById('mc-color').value = color;
  document.querySelectorAll('.cat-theme-btn').forEach(b => {
    const match = b.dataset.ccat === color;
    b.classList.toggle('ring-2',          match);
    b.classList.toggle('ring-indigo-500', match);
    b.classList.toggle('ring-offset-1',   match);
  });
  const cp = document.getElementById('mc-custom-color');
  if (cp && /^#[0-9a-fA-F]{6}$/.test(color)) cp.value = color;
}

function selectItemTheme(color) {
  document.getElementById('mi-color').value = color;
  document.querySelectorAll('.item-theme-btn').forEach(b => {
    const match = b.dataset.iitem === color;
    b.classList.toggle('ring-2',          match);
    b.classList.toggle('ring-indigo-500', match);
    b.classList.toggle('ring-offset-1',   match);
  });
  const cp = document.getElementById('mi-custom-color');
  if (cp && /^#[0-9a-fA-F]{6}$/.test(color)) cp.value = color;
}

// ── Emoji picker ──────────────────────────────────────────────────
function selectEmoji(emoji) {
  document.getElementById('mc-icon').value = emoji;
  document.getElementById('mc-icon-label').textContent = 'Vybrané: ' + emoji;
  document.querySelectorAll('.emoji-btn').forEach(b => {
    const sel = b.dataset.emoji === emoji;
    b.classList.toggle('bg-white',         sel);
    b.classList.toggle('border-indigo-400',sel);
    b.classList.toggle('shadow-md',        sel);
    b.classList.toggle('scale-110',        sel);
    b.classList.toggle('border-transparent', !sel);
  });
}

// ── Modal utils ───────────────────────────────────────────────────
function openModal(id)  { document.getElementById(id)?.classList.remove('hidden'); }
function closeModal(id) { document.getElementById(id)?.classList.add('hidden'); }

const MODAL_IDS = ['modal-cat', 'modal-item', 'modal-confirm-slug'];
MODAL_IDS.forEach(id => {
  const el = document.getElementById(id);
  if (!el) return;
  let _d = false;
  el.addEventListener('mousedown', e => { _d = e.target === el; });
  el.addEventListener('mouseup',   e => { if (_d && e.target === el) closeModal(id); });
});
document.addEventListener('keydown', e => {
  if (e.key !== 'Escape') return;
  MODAL_IDS.forEach(id => { const el = document.getElementById(id); if (el && !el.classList.contains('hidden')) closeModal(id); });
});

// ── Category modal ────────────────────────────────────────────────
function openCatModal(catId) {
  if (catId === null && USER_PLAN !== 'admin' && menuData.categories.length >= MAX_CATS) {
    toast(`Dosiahli ste limit ${MAX_CATS} kategórií pre váš plán.`, 'error');
    return;
  }
  document.getElementById('mc-id').value = catId ?? '';
  document.getElementById('modal-cat-title').textContent =
    catId ? 'Upraviť kategóriu' : 'Nová kategória';

  // Reset
  document.getElementById('mc-name').value = '';
  document.getElementById('mc-icon').value = '';
  document.getElementById('mc-icon-label').textContent = 'Kliknite na emoji pre výber';
  document.querySelectorAll('.emoji-btn').forEach(b => {
    b.classList.remove('bg-white','border-indigo-400','shadow-md','scale-110');
    b.classList.add('border-transparent');
  });
  selectCatTheme('');

  if (catId) {
    const cat = menuData.categories.find(c => c.id == catId);
    if (!cat) return;
    document.getElementById('mc-name').value = cat.name;
    if (cat.icon) selectEmoji(cat.icon);
    selectCatTheme(cat.bg_color || '');
  }
  openModal('modal-cat');
}

async function saveCat() {
  const id    = document.getElementById('mc-id').value;
  const name  = document.getElementById('mc-name').value.trim();
  const icon  = document.getElementById('mc-icon').value;
  const color = document.getElementById('mc-color').value || null;

  if (!name) { toast('Zadajte názov kategórie.', 'error'); return; }

  const payload = { csrf: CSRF, name, icon, bg_color: color };
  if (id) { payload.action = 'edit_category'; payload.id = parseInt(id); }
  else    { payload.action = 'add_category';  payload.venue_slug = CUR_SLUG; }

  const data = await menuApi(payload);
  if (data.ok) {
    applyMenuData(data);
    closeModal('modal-cat');
    toast(id ? 'Kategória aktualizovaná.' : 'Kategória pridaná.', 'success');
  } else {
    toast(data.error || 'Chyba.', 'error');
  }
}

async function deleteCatData(id, name) {
  if (!confirm(`Zmazať kategóriu "${name}" vrátane všetkých jedál?`)) return;
  const data = await menuApi({ csrf: CSRF, action: 'delete_category', id });
  if (data.ok) { applyMenuData(data); toast('Kategória zmazaná.', 'success'); }
  else toast(data.error || 'Chyba.', 'error');
}

// ── Item modal ────────────────────────────────────────────────────
function openItemModal(itemId, catId) {
  if (itemId === null && USER_PLAN !== 'admin') {
    const _cat = menuData.categories.find(c => c.id === catId);
    if (_cat && _cat.items.length >= MAX_ITEMS_CAT) {
      toast(`Dosiahli ste limit ${MAX_ITEMS_CAT} jedál na kategóriu pre váš plán.`, 'error');
      return;
    }
  }
  document.getElementById('mi-id').value     = itemId ?? '';
  document.getElementById('mi-cat-id').value = catId  ?? '';
  document.getElementById('modal-item-title').textContent =
    itemId ? 'Upraviť jedlo' : 'Nové jedlo';

  // Reset
  ['mi-name','mi-price','mi-weight','mi-desc','mi-detail'].forEach(id =>
    { const el=document.getElementById(id); if(el) el.value=''; });
  document.getElementById('mi-featured').checked = false;
  document.querySelectorAll('.mi-allergen').forEach(cb => cb.checked = false);
  selectItemTheme('');
  clearItemImage();

  if (itemId) {
    let item = null;
    for (const cat of menuData.categories) {
      item = cat.items.find(i => i.id == itemId);
      if (item) break;
    }
    if (!item) return;
    document.getElementById('mi-name').value   = item.name;
    document.getElementById('mi-price').value  = item.price;
    document.getElementById('mi-weight').value = item.weight || '';
    document.getElementById('mi-desc').value   = item.description || '';
    document.getElementById('mi-detail').value = item.detail_description || '';
    document.getElementById('mi-featured').checked = !!item.is_featured;
    if (item.allergens) {
      item.allergens.toString().split(',').forEach(n => {
        const cb = document.querySelector(`.mi-allergen[value="${n.trim()}"]`);
        if (cb) cb.checked = true;
      });
    }
    selectItemTheme(item.bg_color || '');
    if (item.image) {
      document.getElementById('mi-image').value = item.image;
      document.getElementById('mi-img-preview').src = BASE_URL + '/' + item.image;
      document.getElementById('mi-img-preview-wrap').classList.remove('hidden');
    }
  }
  openModal('modal-item');
}

async function saveItemData() {
  const id    = document.getElementById('mi-id').value;
  const catId = document.getElementById('mi-cat-id').value;
  const name  = document.getElementById('mi-name').value.trim();
  const priceStr = document.getElementById('mi-price').value;
  const price = parseFloat(priceStr);

  if (!name)               { toast('Zadajte názov jedla.', 'error'); return; }
  if (isNaN(price)||price<0) { toast('Zadajte platnú cenu.', 'error'); return; }

  const payload = {
    csrf: CSRF, action: 'save_item',
    name, price,
    weight:              document.getElementById('mi-weight').value.trim(),
    description:         document.getElementById('mi-desc').value.trim(),
    detail_description:  document.getElementById('mi-detail').value.trim(),
    is_featured:         document.getElementById('mi-featured').checked ? 1 : 0,
    allergens: [...document.querySelectorAll('.mi-allergen:checked')].map(c=>c.value).join(','),
    bg_color:  document.getElementById('mi-color').value || null,
    image:     document.getElementById('mi-image').value || null,
  };
  if (id) payload.id = parseInt(id);
  else    payload.category_id = parseInt(catId);

  const data = await menuApi(payload);
  if (data.ok) {
    applyMenuData(data);
    closeModal('modal-item');
    toast(id ? 'Jedlo aktualizované.' : 'Jedlo pridané.', 'success');
  } else {
    toast(data.error || 'Chyba.', 'error');
  }
}

async function deleteItemData(id, name) {
  if (!confirm(`Zmazať jedlo "${name}"?`)) return;
  const data = await menuApi({ csrf: CSRF, action: 'delete_item', id });
  if (data.ok) { applyMenuData(data); toast('Jedlo zmazané.', 'success'); }
  else toast(data.error || 'Chyba.', 'error');
}

// ── Menu settings save ────────────────────────────────────────────
async function saveMenuSettings() {
  const data = await menuApi({
    csrf:                  CSRF,
    action:                'update_settings',
    venue_slug:            CUR_SLUG,
    show_allergens:        document.getElementById('m-show-allergens').checked ? 1 : 0,
    show_featured:         document.getElementById('m-show-featured').checked  ? 1 : 0,
    default_category_color: document.getElementById('m-cat-color').value,
    default_item_color:    document.getElementById('m-item-color').value,
  });
  if (data.ok) {
    updatePreview();
    toast('Nastavenia uložené.', 'success');
  } else {
    toast(data.error || 'Chyba.', 'error');
  }
}

// ── Menu API ──────────────────────────────────────────────────────
async function menuApi(payload) {
  try {
    const res = await fetchWithTimeout(APP_URL + '/api/manage_menu.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    });
    return await res.json();
  } catch (e) {
    return { ok: false, error: 'Sieťová chyba: ' + e.message };
  }
}

function applyMenuData(data) {
  if (Array.isArray(data.categories)) menuData.categories = data.categories;
  if (data.settings)                  menuData.settings   = data.settings;
  renderMenuTree();
  updateCatLimitBtn();
  updatePreview();
}

function updateCatLimitBtn() {
  const btn = document.getElementById('btn-add-cat');
  if (!btn || USER_PLAN === 'admin') return;
  const reached   = menuData.categories.length >= MAX_CATS;
  btn.title       = reached ? `Dosiahnutý limit ${MAX_CATS} kategórií` : '';
  btn.textContent = reached ? '🔒 Limit' : '+ Kategória';
  btn.className   = reached
    ? 'px-4 py-2 rounded-2xl transition text-xs font-bold bg-gray-100 dark:bg-slate-800 text-slate-400 dark:text-slate-500 opacity-60'
    : 'px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-2xl transition';
}

// ── Venue AJAX ────────────────────────────────────────────────────
function confirmSlugModal() {
  return new Promise(resolve => {
    openModal('modal-confirm-slug');
    const ok  = document.getElementById('modal-confirm-slug-ok');
    const can = document.getElementById('modal-confirm-slug-cancel');
    const done = (val) => {
      closeModal('modal-confirm-slug');
      ok.onclick = null;
      can.onclick = null;
      resolve(val);
    };
    ok.onclick  = () => done(true);
    can.onclick = () => done(false);
  });
}

async function saveVenue() {
  const originalSlug = document.getElementById('f-original-slug').value;
  const newSlug      = document.getElementById('f-slug').value.trim();
  if (originalSlug && originalSlug !== newSlug) {
    if (!await confirmSlugModal()) return;
  }
  const btn = document.getElementById('btn-save-venue');
  if (btn) { btn.textContent = 'Ukladám...'; btn.classList.add('opacity-70', 'pointer-events-none'); }
  const payload = {
    csrf:          CSRF,
    action:        'save',
    original_slug: originalSlug,
    slug:          newSlug,
    name:          document.getElementById('f-name').value.trim(),
    google_url:    document.getElementById('f-google').value.trim(),
    instagram_url: document.getElementById('f-insta').value.trim(),
    color:         document.getElementById('f-color').value,
    logo:          document.getElementById('f-logo').value,
    cover_image:   document.getElementById('f-cover').value,
  };
  try {
    const res  = await fetchWithTimeout(APP_URL + '/api/save_venue.php', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    });
    const data = await res.json();
    if (data.ok) {
      toast('Prevádzka uložená!', 'success');
      setTimeout(() => {
        location.href = APP_URL + '/dashboard?edit=' + encodeURIComponent(data.slug);
      }, 700);
    } else {
      toast(data.error || 'Chyba.', 'error');
      if (btn) { btn.textContent = 'Uložiť nastavenia'; btn.classList.remove('opacity-70', 'pointer-events-none'); }
    }
  } catch (e) {
    toast('Sieťová chyba: ' + e.message, 'error');
    if (btn) { btn.textContent = 'Uložiť nastavenia'; btn.classList.remove('opacity-70', 'pointer-events-none'); }
  }
}

function postDownload(url, params) {
  const form = document.createElement('form');
  form.method = 'POST';
  form.action = url;
  for (const [k, v] of Object.entries(params)) {
    const inp = document.createElement('input');
    inp.type = 'hidden'; inp.name = k; inp.value = v;
    form.appendChild(inp);
  }
  document.body.appendChild(form);
  form.submit();
  document.body.removeChild(form);
}

async function resetMenu(slug) {
  if (!confirm('Zresetovanie zmaže všetky kategórie a jedlá tejto prevádzky. Záloha bude stiahnutá automaticky. Pokračovať?')) return;
  // Trigger CSV export first via POST form
  postDownload(APP_URL + '/api/export_full.php', { slug, csrf: CSRF });
  await new Promise(r => setTimeout(r, 800));
  // Call reset_menu
  try {
    const res  = await fetchWithTimeout(APP_URL + '/api/user_actions.php', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ csrf: CSRF, action: 'reset_menu', slug }),
    });
    const data = await res.json();
    if (data.ok) {
      toast('Menu bolo zresetované.', 'success');
      setTimeout(() => { location.reload(); }, 900);
    } else {
      toast(data.error || 'Chyba.', 'error');
    }
  } catch { toast('Sieťová chyba.', 'error'); }
}

async function deleteVenue(slug) {
  if (!confirm(`Naozaj zmazať prevádzku "${slug}" aj so všetkými jedlami?`)) return;
  try {
    const res  = await fetchWithTimeout(APP_URL + '/api/save_venue.php', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ csrf: CSRF, action: 'delete', slug }),
    });
    const data = await res.json();
    if (data.ok) {
      toast('Prevádzka zmazaná.', 'success');
      setTimeout(() => { location.href = APP_URL + '/dashboard'; }, 700);
    } else {
      toast(data.error || 'Chyba.', 'error');
    }
  } catch (e) {
    toast('Sieťová chyba.', 'error');
  }
}

function openNewVenue() {
  ['f-original-slug','f-slug','f-name','f-google','f-insta','f-logo','f-cover'].forEach(id => {
    const el = document.getElementById(id); if (el) el.value = '';
  });
  const logoFile = document.getElementById('f-logo-file');
  if (logoFile) logoFile.value = '';
  const coverFile = document.getElementById('f-cover-file');
  if (coverFile) coverFile.value = '';
  document.getElementById('logo-preview-wrap')?.classList.add('hidden');
  document.getElementById('cover-preview-wrap')?.classList.add('hidden');
  document.getElementById('settings-title').textContent = 'Nová prevádzka';
  setVenueColor('#111827');
  switchTab('settings');
}

// ── Drag handle SVG (2×3 dots) ────────────────────────────────────
const DRAG_SVG = `<svg class="w-3 h-4 pointer-events-none" viewBox="0 0 8 14" fill="currentColor" aria-hidden="true">
  <circle cx="2" cy="2" r="1.3"/><circle cx="6" cy="2" r="1.3"/>
  <circle cx="2" cy="7" r="1.3"/><circle cx="6" cy="7" r="1.3"/>
  <circle cx="2" cy="12" r="1.3"/><circle cx="6" cy="12" r="1.3"/>
</svg>`;
const EYE_SVG     = `<svg class="w-3 h-3 pointer-events-none" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>`;
const EYE_OFF_SVG = `<svg class="w-3 h-3 pointer-events-none" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/></svg>`;

// ── Menu tree renderer ────────────────────────────────────────────
function renderMenuTree() {
  const el = document.getElementById('menu-tree');
  if (!el) return;
  const cats = menuData.categories;
  if (!cats.length) {
    el.innerHTML = '<p class="text-xs text-slate-400 text-center py-8">Žiadne kategórie. Pridajte prvú.</p>';
    return;
  }

  // Preserve accordion open states across re-renders
  const openStates = {};
  let firstRender = true;
  document.querySelectorAll('#sortable-cats > [data-cat-id]').forEach(catEl => {
    firstRender = false;
    const body = catEl.querySelector('[id^="cat-body-"]');
    if (body) openStates[catEl.dataset.catId] = body.dataset.open === 'true';
  });

  el.innerHTML = '<div id="sortable-cats">' + cats.map((cat, idx) => {
    const catBg = cat.bg_color || menuData.settings.default_category_color || '#1E3A5F';
    const catTc = yiq(catBg);
    // First render: only first open. Re-renders: preserve state (new cats default open).
    const isOpen           = openStates[cat.id] !== undefined ? openStates[cat.id] : (firstRender ? idx === 0 : true);
    const cvRot            = isOpen ? ' rotate-180' : '';
    const itemLimitReached = USER_PLAN !== 'admin' && cat.items.length >= MAX_ITEMS_CAT;
    const CHEVRON = `<svg class="cat-chevron w-3 h-3 flex-shrink-0 transition-transform duration-200${cvRot}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>`;

    const items = cat.items.map(item => `
      <div data-item-id="${item.id}"
           data-search="${esc(item.name + ' ' + (item.description || ''))}"
           class="cat-item-row flex items-center gap-2 py-3 px-2 rounded-xl border-b border-gray-100 dark:border-slate-700/60 last:border-0 hover:bg-gray-50 dark:hover:bg-slate-800/60 transition-colors${item.is_visible === 0 ? ' opacity-50' : ''}">
        <span class="drag-item-handle cursor-grab active:cursor-grabbing text-slate-300 dark:text-slate-600 hover:text-slate-400 flex-shrink-0 select-none">${DRAG_SVG}</span>
        <span class="flex-1 text-xs text-slate-700 dark:text-slate-300 truncate font-medium">${esc(item.name)}</span>
        <span class="text-xs font-bold text-slate-400 dark:text-slate-500">${fmtPrice(item.price)}</span>
        ${item.is_featured ? '<span title="Odporúčame" class="text-xs">⭐</span>' : ''}
        <button onclick="toggleVisibility('item',${item.id})"
                class="text-slate-400 dark:text-slate-500 hover:text-amber-500 dark:hover:text-amber-400 px-1 transition"
                title="${item.is_visible === 0 ? 'Zobraziť' : 'Skryť'}">${item.is_visible === 0 ? EYE_OFF_SVG : EYE_SVG}</button>
        <button onclick="openItemModal(${item.id},${cat.id})"
                class="text-slate-400 dark:text-slate-500 hover:text-indigo-600 dark:hover:text-indigo-400 text-xs px-1 transition"
                title="Upraviť">✏️</button>
        <button onclick="deleteItemData(${item.id},'${esc(item.name)}')"
                class="text-slate-400 dark:text-slate-500 hover:text-red-500 dark:hover:text-red-400 text-xs px-1 transition"
                title="Zmazať">🗑️</button>
      </div>`).join('');

    return `
      <div data-cat-id="${cat.id}" data-cat-name="${esc(cat.name)}"
           class="rounded-[2rem] border border-gray-100 dark:border-slate-700 overflow-hidden mb-3${cat.is_visible === 0 ? ' opacity-50' : ''}">
        <div class="flex items-center gap-2 px-5 py-4" style="background:${catBg}">
          <span class="drag-cat-handle cursor-grab active:cursor-grabbing opacity-50 hover:opacity-90 flex-shrink-0 select-none" style="color:${catTc}">${DRAG_SVG}</span>
          <button type="button" onclick="toggleCat(${cat.id})"
                  class="flex items-center gap-2 flex-1 min-w-0 text-left focus:outline-none"
                  style="color:${catTc}">
            <span class="text-base leading-none">${esc(cat.icon)||'📁'}</span>
            <span class="font-bold text-sm flex-1 truncate">${esc(cat.name)}</span>
            <span class="text-xs opacity-60">${cat.items.length} jedál</span>
            <span class="opacity-70">${CHEVRON}</span>
          </button>
          <button onclick="toggleVisibility('category',${cat.id})"
                  class="p-1.5 rounded-lg transition opacity-80 hover:opacity-100"
                  style="background:rgba(${catTc==='#ffffff'?'255,255,255':'0,0,0'},.15);color:${catTc}"
                  title="${cat.is_visible === 0 ? 'Zobraziť' : 'Skryť'}">${cat.is_visible === 0 ? EYE_OFF_SVG : EYE_SVG}</button>
          <button onclick="openCatModal(${cat.id})"
                  class="p-1.5 rounded-lg transition opacity-80 hover:opacity-100 text-xs"
                  style="background:rgba(${catTc==='#ffffff'?'255,255,255':'0,0,0'},.15);color:${catTc}"
                  title="Upraviť kategóriu">✏️</button>
          <button onclick="deleteCatData(${cat.id},'${esc(cat.name)}')"
                  class="p-1.5 rounded-lg transition opacity-80 hover:opacity-100 text-xs"
                  style="background:rgba(${catTc==='#ffffff'?'255,255,255':'0,0,0'},.15);color:${catTc}"
                  title="Zmazať kategóriu">🗑️</button>
        </div>
        <div id="cat-body-${cat.id}" data-open="${isOpen}" class="${isOpen ? '' : 'hidden'}">
          <div class="sortable-items px-2 pt-2 pb-1 bg-white dark:bg-slate-900" data-cat-id="${cat.id}">
            ${items}
          </div>
          <div class="px-4 pb-3 bg-white dark:bg-slate-900">
            <button onclick="openItemModal(null,${cat.id})"
                    title="${itemLimitReached ? 'Dosiahnutý limit 5 jedál na kategóriu (Free plán)' : ''}"
                    class="w-full py-2.5 border border-dashed rounded-2xl text-xs transition
                           ${itemLimitReached
                             ? 'border-gray-200 dark:border-slate-700 text-slate-300 dark:text-slate-600 opacity-60 cursor-default'
                             : 'border-gray-200 dark:border-slate-700 text-slate-400 dark:text-slate-500 hover:text-indigo-600 dark:hover:text-indigo-400 hover:border-indigo-300 dark:hover:border-indigo-700'}">
              ${itemLimitReached ? '🔒 Limit jedál (Free)' : '+ Pridať jedlo'}
            </button>
          </div>
        </div>
      </div>`;
  }).join('') + '</div>';
  initSortable();
}

// ── Drag & Drop (SortableJS) ──────────────────────────────────────
function initSortable() {
  if (typeof Sortable === 'undefined') return;
  const catContainer = document.getElementById('sortable-cats');
  if (!catContainer) return;

  new Sortable(catContainer, {
    handle: '.drag-cat-handle',
    animation: 150,
    ghostClass: 'opacity-40',
    onEnd() {
      const newIds = [...catContainer.querySelectorAll(':scope > [data-cat-id]')]
        .map(el => parseInt(el.dataset.catId));
      const catMap = Object.fromEntries(menuData.categories.map(c => [c.id, c]));
      menuData.categories = newIds.map(id => catMap[id]).filter(Boolean);
      const slug = menuData.categories[0]?.venue_slug
                || <?= json_encode($selected ? $selected['slug'] : '') ?>;
      reorderApi('categories', newIds, slug);
    },
  });

  document.querySelectorAll('.sortable-items').forEach(container => {
    new Sortable(container, {
      handle: '.drag-item-handle',
      animation: 150,
      ghostClass: 'opacity-40',
      group: 'shared-items',
      onEnd(evt) {
        const fromCatId = parseInt(evt.from.dataset.catId);
        const toCatId   = parseInt(evt.to.dataset.catId);
        const movedId   = parseInt(evt.item.dataset.itemId);
        const newToIds  = [...evt.to.querySelectorAll(':scope > [data-item-id]')]
          .map(el => parseInt(el.dataset.itemId));

        const snapshot = JSON.parse(JSON.stringify(menuData.categories));
        const fromCat  = menuData.categories.find(c => c.id === fromCatId);
        const toCat    = menuData.categories.find(c => c.id === toCatId);

        if (fromCatId !== toCatId && fromCat && toCat) {
          const movedItem = fromCat.items.find(i => i.id === movedId);
          if (movedItem) {
            fromCat.items = fromCat.items.filter(i => i.id !== movedId);
            const toMap = Object.fromEntries(toCat.items.map(i => [i.id, i]));
            toCat.items = newToIds.map(id =>
              id === movedId ? { ...movedItem, category_id: toCatId } : toMap[id]
            ).filter(Boolean);
          }
          reorderApi('items', newToIds, null, toCatId, snapshot);
        } else if (toCat) {
          const itemMap = Object.fromEntries(toCat.items.map(i => [i.id, i]));
          toCat.items = newToIds.map(id => itemMap[id]).filter(Boolean);
          reorderApi('items', newToIds, null, null, snapshot);
        }
      },
    });
  });
}

async function reorderApi(type, ids, venueSlug, targetCatId = null, snapshot = null) {
  const payload = { csrf: CSRF, action: 'reorder', type, ids };
  if (venueSlug) payload.venue_slug = venueSlug;
  if (targetCatId) payload.target_category_id = targetCatId;
  const data = await menuApi(payload);
  if (!data.ok) {
    toast(data.error || 'Chyba pri ukladaní poradia.', 'error');
    if (snapshot) { menuData.categories = snapshot; renderMenuTree(); }
  }
}

async function toggleVisibility(type, id) {
  const data = await menuApi({ csrf: CSRF, action: 'toggle_visibility', type, id });
  if (data.ok) {
    if (type === 'category') {
      const cat = menuData.categories.find(c => c.id === id);
      if (cat) cat.is_visible = cat.is_visible === 0 ? 1 : 0;
    } else {
      for (const cat of menuData.categories) {
        const item = cat.items.find(i => i.id === id);
        if (item) { item.is_visible = item.is_visible === 0 ? 1 : 0; break; }
      }
    }
    renderMenuTree();
    updatePreview();
  } else {
    toast(data.error || 'Chyba.', 'error');
  }
}

// ── Accordion toggle ──────────────────────────────────────────────
function toggleCat(catId) {
  const body = document.getElementById('cat-body-' + catId);
  if (!body) return;
  const nowOpen = body.dataset.open !== 'true';
  body.dataset.open = nowOpen;
  body.classList.toggle('hidden', !nowOpen);
  const chevron = body.previousElementSibling?.querySelector('.cat-chevron');
  if (chevron) chevron.classList.toggle('rotate-180', nowOpen);
}

// ── Live search ───────────────────────────────────────────────────
let _menuSearchTimer = null;
function menuSearchDebounced() {
  clearTimeout(_menuSearchTimer);
  _menuSearchTimer = setTimeout(menuSearch, 180);
}

function menuSearch() {
  const q = (document.getElementById('menu-search')?.value || '').trim().toLowerCase();
  document.querySelectorAll('#sortable-cats > [data-cat-id]').forEach(catEl => {
    const catName = (catEl.dataset.catName || '').toLowerCase();
    const catNameMatches = !q || catName.includes(q);
    const itemRows = catEl.querySelectorAll('.cat-item-row');
    let anyItemVisible = false;

    itemRows.forEach(row => {
      const haystack = (row.dataset.search || '').toLowerCase();
      const show = !q || catNameMatches || haystack.includes(q);
      row.classList.toggle('hidden', !show);
      if (show) anyItemVisible = true;
    });

    const catVisible = catNameMatches || anyItemVisible;
    catEl.classList.toggle('hidden', !catVisible);

    // Auto-expand categories that have matching results
    if (q && catVisible) {
      const body = document.getElementById('cat-body-' + catEl.dataset.catId);
      if (body) {
        body.dataset.open = 'true';
        body.classList.remove('hidden');
        const chevron = body.previousElementSibling?.querySelector('.cat-chevron');
        if (chevron) chevron.classList.add('rotate-180');
      }
    }
  });
}

// ── Live preview renderer ─────────────────────────────────────────
function updatePreview() {
  const frame = document.getElementById('preview-frame');
  if (!frame) return;

  const vHex   = resolveVenueColor();
  const vText  = yiq(vHex);
  const vIsLight = vText === '#1e293b';
  const vOvl   = vIsLight ? 'rgba(0,0,0,.1)' : 'rgba(255,255,255,.2)';

  const vName  = (document.getElementById('f-name')?.value.trim()) || 'Váš podnik';
  const vLogo  = document.getElementById('f-logo')?.value || '';
  const cats   = menuData.categories.filter(c => c.is_visible !== 0);
  const s      = menuData.settings;

  // Dark mode colors
  const pageBg   = previewDark ? '#0f172a' : '#ffffff';
  const navBg    = previewDark ? 'rgba(15,23,42,.95)' : 'rgba(255,255,255,.95)';
  const pageText = previewDark ? '#f1f5f9' : '#1e293b';
  const pageSub  = previewDark ? '#94a3b8' : '#64748b';
  const pageBdr  = previewDark ? '#1e293b' : '#f1f5f9';
  const sectionBg = previewDark ? '#0f172a' : '#ffffff';

  // Header
  let html = `
    <div style="background:${vHex};padding:18px 10px 12px">
      ${vLogo ? `<img src="${vLogo}" style="width:32px;height:32px;object-fit:contain;border-radius:8px;margin-bottom:8px">` : ''}
      <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:4px">
        <div style="font-weight:800;font-size:11px;color:${vText};line-height:1.3;flex:1">${esc(vName)}</div>
        <div style="width:18px;height:18px;border-radius:50%;background:${vOvl};display:flex;align-items:center;justify-content:center;font-size:8px;color:${vText};flex-shrink:0">
          ${previewDark ? '☾' : '☀'}
        </div>
      </div>
    </div>`;

  // Category nav (if categories exist)
  if (cats.length) {
    html += `<div style="background:${navBg};border-bottom:1px solid ${pageBdr};
                          overflow-x:auto;white-space:nowrap;padding:5px 6px">
      ${cats.map(c =>
        `<span style="display:inline-block;padding:3px 8px;border-radius:99px;
                      font-size:7.5px;font-weight:700;color:${pageSub};margin-right:2px">
          ${esc(c.icon||'')} ${esc(c.name)}
        </span>`
      ).join('')}
    </div>`;
  }

  // Featured strip
  if (s.show_featured) {
    const fi = cats.flatMap(c => c.items.filter(i => i.is_featured && i.is_visible !== 0));
    if (fi.length) {
      html += `<div style="background:${sectionBg};padding:8px 6px 4px;
                            border-bottom:1px solid ${pageBdr}">
        <div style="font-size:6px;font-weight:800;color:${pageSub};
                    text-transform:uppercase;letter-spacing:.1em;margin-bottom:5px">⭐ Odporúčame</div>
        <div style="display:flex;gap:5px;overflow-x:auto">
          ${fi.slice(0,3).map(i => {
            const ibg = i.bg_color || s.default_item_color || '#FFFFFF';
            const itc = yiq(ibg);
            return `<div style="flex:0 0 62px;background:${ibg};border-radius:8px;padding:6px 7px">
              <div style="font-weight:700;font-size:7px;color:${itc};line-height:1.3">${esc(i.name)}</div>
              <div style="font-size:7px;font-weight:800;color:${vHex};margin-top:3px">${fmtPrice(i.price)}</div>
            </div>`;
          }).join('')}
        </div>
      </div>`;
    }
  }

  // Category sections
  cats.forEach(cat => {
    const catBg    = cat.bg_color || s.default_category_color || '#1E3A5F';
    const catTc    = yiq(catBg);
    const visItems = cat.items.filter(i => i.is_visible !== 0);
    if (!visItems.length) return;
    html += `<div>
      <div style="background:${catBg};padding:6px 8px;display:flex;align-items:center;gap:4px">
        ${cat.icon ? `<span style="font-size:10px">${esc(cat.icon)}</span>` : ''}
        <span style="font-weight:800;font-size:8.5px;color:${catTc}">${esc(cat.name)}</span>
      </div>
      <div style="background:${sectionBg};padding:4px 5px 3px;display:flex;flex-direction:column;gap:3px">
        ${visItems.length ? visItems.map(item => {
          const ibg = item.bg_color || s.default_item_color || '#FFFFFF';
          const itc = yiq(ibg);
          const imt = yiqMuted(ibg);
          return `<div style="background:${ibg};border-radius:8px;padding:5px 7px;
                              display:flex;align-items:flex-start;justify-content:space-between;gap:4px">
            <div style="flex:1;min-width:0">
              <div style="font-weight:700;font-size:7.5px;color:${itc};line-height:1.3">${esc(item.name)}</div>
              ${item.description ? `<div style="font-size:6.5px;color:${imt};margin-top:1px;overflow:hidden;white-space:nowrap;text-overflow:ellipsis">${esc(item.description)}</div>` : ''}
            </div>
            <span style="font-size:7px;font-weight:800;padding:2px 5px;border-radius:5px;
                         background:${vHex};color:${vText};flex-shrink:0;white-space:nowrap">
              ${fmtPrice(item.price)}
            </span>
          </div>`;
        }).join('')
        : `<p style="font-size:7px;color:${pageSub};padding:5px;text-align:center">Žiadne jedlá</p>`
        }
      </div>
    </div>`;
  });

  if (!cats.length) {
    html += `<div style="text-align:center;padding:30px 10px;color:${pageSub}">
      <div style="font-size:20px;margin-bottom:6px">🍽️</div>
      <div style="font-size:7.5px">Pridajte kategórie a jedlá</div>
    </div>`;
  }

  frame.style.background = pageBg;
  frame.style.color = pageText;
  frame.innerHTML = html;
}

// ── QR code ───────────────────────────────────────────────────────
<?php if ($selected): ?>
(function () {
  try {
    new QRCode(document.getElementById('qr-box'), {
      text: BASE_URL + '/r/' + <?= json_encode($selected['slug']) ?>,
      width: 156, height: 156,
      correctLevel: QRCode.CorrectLevel.H,
    });
  } catch (e) {}
})();
<?php endif; ?>

function downloadQR() {
  const box = document.getElementById('qr-box');
  if (!box) return;
  const canvas = box.querySelector('canvas');
  const img    = box.querySelector('img');
  const src    = canvas?.toDataURL() || img?.src || '';
  if (!src) return;
  const a = document.createElement('a');
  a.download = (document.getElementById('f-slug')?.value || 'venue') + '-qr.png';
  a.href = src; a.click();
}

// ── Toast ─────────────────────────────────────────────────────────
function toast(msg, type = 'info') {
  const el = document.createElement('div');
  el.className = `pointer-events-auto px-4 py-2.5 rounded-2xl text-white text-sm
    font-semibold shadow-xl transition-all
    ${type==='success' ? 'bg-emerald-600' : type==='error' ? 'bg-red-600' : 'bg-slate-800'}`;
  el.textContent = msg;
  document.getElementById('toast-wrap').appendChild(el);
  setTimeout(() => { el.style.opacity = '0'; setTimeout(() => el.remove(), 300); }, 3200);
}

// ── Init ──────────────────────────────────────────────────────────
updatePreview();
if (menuData.categories.length) renderMenuTree();

</script>
<?php require __DIR__ . '/partials/footer.php'; ?>
