<!DOCTYPE html>
<html lang="sk">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard — GastroLink QR</title>
<script src="https://cdn.tailwindcss.com"></script>
<script>tailwind.config={darkMode:'class'}</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<style>
*{-webkit-tap-highlight-color:transparent}
.no-scrollbar{-ms-overflow-style:none;scrollbar-width:none}
.no-scrollbar::-webkit-scrollbar{display:none}
</style>
</head>
<body class="bg-slate-100 min-h-screen">

<?php
$db     = getDB();
$userId = (int)$_SESSION['user_id'];
$role   = (string)($_SESSION['user_role'] ?? 'user');

$stVenues = $db->prepare("SELECT * FROM venues WHERE user_id = ? ORDER BY created_at DESC");
$stVenues->execute([$userId]);
$venues = $stVenues->fetchAll();

$stLimit = $db->prepare("SELECT venue_limit FROM users WHERE id = ?");
$stLimit->execute([$userId]);
$venueLimit = (int)($stLimit->fetchColumn() ?? 1);
$venueCount = count($venues);

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
    'dark_mode_default'      => 0,
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
<nav class="bg-white shadow-sm px-5 py-3 flex items-center justify-between sticky top-0 z-30 border-b border-slate-100">
  <a href="<?= url() ?>" class="font-extrabold text-indigo-700 text-sm">
    GastroLink <span class="text-emerald-600">QR</span>
  </a>
  <div class="flex items-center gap-4">
    <span class="text-slate-400 text-xs hidden sm:inline"><?= e($_SESSION['username']) ?></span>
    <?php if ($role === 'admin'): ?>
    <a href="<?= url('admin') ?>" class="text-indigo-600 hover:underline text-xs font-semibold">Admin</a>
    <?php endif; ?>
    <a href="<?= url('logout') ?>" class="text-red-500 hover:underline text-xs">Odhlásiť</a>
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

    <div class="bg-white rounded-2xl shadow-sm p-4 border border-slate-100">
      <div class="flex items-center justify-between mb-3">
        <span class="font-bold text-slate-700 text-xs uppercase tracking-widest">Prevádzky</span>
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
                    <?= $active ? 'bg-indigo-50 text-indigo-700 font-bold'
                                : 'hover:bg-slate-50 text-slate-600 font-medium' ?>">
            <span class="w-2.5 h-2.5 rounded-full flex-shrink-0"
                  style="background:<?= e($vc['hex']) ?>"></span>
            <span class="truncate"><?= e($v['name']) ?></span>
          </a>
        </li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>

      <?php if ($venueCount < $venueLimit || $role === 'admin'): ?>
      <button onclick="openNewVenue()"
              class="mt-3 w-full py-2 bg-emerald-600 hover:bg-emerald-700
                     text-white text-xs font-bold rounded-xl transition">
        + Nová prevádzka
      </button>
      <?php endif; ?>
    </div>

    <?php if ($selected): ?>
    <div class="bg-white rounded-2xl shadow-sm p-4 border border-slate-100">
      <h3 class="font-bold text-slate-700 text-xs uppercase tracking-widest mb-3">Rýchly odkaz</h3>
      <a href="<?= e(baseUrl() . '/r/' . $selected['slug']) ?>" target="_blank"
         class="block text-xs text-indigo-600 hover:underline break-all">
        /r/<?= e($selected['slug']) ?>
      </a>
    </div>
    <?php endif; ?>
  </aside>

  <!-- ══ CENTER: Tabs ════════════════════════════════════════════════ -->
  <section class="min-w-0 space-y-3">

    <!-- Tab bar -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 flex overflow-hidden">
      <button id="tab-btn-settings" onclick="switchTab('settings')"
              class="flex-1 py-3 text-sm font-bold border-b-2 border-indigo-600 text-indigo-600 transition">
        ⚙️ Nastavenia
      </button>
      <button id="tab-btn-menu" onclick="switchTab('menu')"
              class="flex-1 py-3 text-sm font-bold border-b-2 border-transparent text-slate-400 hover:text-slate-600 transition">
        🍽️ Jedálny lístok
      </button>
    </div>

    <!-- ── Tab: Settings ─────────────────────────────────────────── -->
    <div id="tab-settings" class="bg-white rounded-2xl shadow-sm p-6 border border-slate-100">
      <h2 id="settings-title" class="font-bold text-slate-800 text-sm mb-5">
        <?= $selected ? 'Upraviť prevádzku' : 'Nová prevádzka' ?>
      </h2>
      <div class="space-y-4">
        <input type="hidden" id="f-original-slug" value="<?= $selected ? e($selected['slug']) : '' ?>">

        <!-- Slug -->
        <div>
          <label class="text-xs font-semibold text-slate-600 mb-1.5 block">URL slug *</label>
          <div class="flex rounded-xl border border-slate-300 overflow-hidden
                      focus-within:ring-2 focus-within:ring-indigo-400 focus-within:border-indigo-400">
            <span class="bg-slate-50 text-slate-400 text-xs px-3 flex items-center border-r border-slate-200">
              /r/
            </span>
            <input id="f-slug" type="text" required maxlength="50"
                   pattern="[a-z0-9][a-z0-9_\-]{1,49}"
                   class="flex-1 px-3 py-2.5 text-sm outline-none bg-white"
                   value="<?= $selected ? e($selected['slug']) : '' ?>"
                   placeholder="nazov-podniku"
                   oninput="updatePreview()">
          </div>
        </div>

        <!-- Name -->
        <div>
          <label class="text-xs font-semibold text-slate-600 mb-1.5 block">Názov podniku *</label>
          <input id="f-name" type="text" required maxlength="200"
                 class="w-full border border-slate-300 rounded-xl px-3 py-2.5 text-sm
                        outline-none focus:ring-2 focus:ring-indigo-400 focus:border-indigo-400"
                 value="<?= $selected ? e($selected['name']) : '' ?>"
                 placeholder="Kaviareň U Márie"
                 oninput="updatePreview()">
        </div>

        <!-- URL fields (Google + Instagram ONLY) -->
        <?php foreach ([
            ['f-google', 'Google Recenzie (URL)', $selected['google_url']    ?? ''],
            ['f-insta',  'Instagram (URL)',        $selected['instagram_url'] ?? ''],
        ] as [$fid, $label, $val]): ?>
        <div>
          <label class="text-xs font-semibold text-slate-600 mb-1.5 block"><?= $label ?></label>
          <input id="<?= $fid ?>" type="url"
                 class="w-full border border-slate-300 rounded-xl px-3 py-2.5 text-sm
                        outline-none focus:ring-2 focus:ring-indigo-400 focus:border-indigo-400"
                 value="<?= e($val) ?>" placeholder="https://"
                 oninput="updatePreview()">
        </div>
        <?php endforeach; ?>

        <!-- Theme color (venue accent) -->
        <div>
          <label class="text-xs font-semibold text-slate-600 mb-2 block">Farba témy / hlavičky</label>
          <div class="flex flex-wrap gap-2 mb-3">
            <?php foreach ($pal as $key => $info): ?>
            <button type="button" onclick="setVenueColor('<?= e($key) ?>')"
                    title="<?= e($info['label']) ?>"
                    data-vc="<?= e($key) ?>"
                    class="vc-btn w-7 h-7 rounded-full border-2 transition ring-offset-1"
                    style="background:<?= e($info['swatch']) ?>;
                           border-color:<?= ($selected&&$selected['color']===$key)?'#6366f1':'transparent' ?>">
            </button>
            <?php endforeach; ?>
          </div>
          <div class="flex items-center gap-2">
            <input type="color" id="f-color-picker"
                   class="w-7 h-7 rounded-lg border border-slate-300 cursor-pointer"
                   value="<?= ($selected&&preg_match('/^#[0-9a-fA-F]{6}$/',$selected['color']))
                               ? e($selected['color']) : '#111827' ?>"
                   oninput="setVenueColor(this.value)">
            <span class="text-xs text-slate-400">Vlastná farba</span>
          </div>
          <input type="hidden" id="f-color" value="<?= $selected ? e($selected['color']) : 'black' ?>">
        </div>

        <!-- Logo -->
        <div>
          <label class="text-xs font-semibold text-slate-600 mb-1.5 block">Logo (voliteľné)</label>
          <div id="logo-preview-wrap"
               class="<?= ($selected&&$selected['logo']) ? '' : 'hidden' ?> mb-3">
            <img id="logo-preview"
                 src="<?= ($selected&&$selected['logo']) ? e($selected['logo']) : '' ?>"
                 alt="" class="h-14 w-14 object-contain rounded-xl border border-slate-200">
            <button type="button" onclick="clearLogo()"
                    class="mt-1 text-xs text-red-500 hover:underline block">
              Odstrániť logo
            </button>
          </div>
          <input type="file" id="f-logo-file" accept="image/*"
                 class="text-xs text-slate-500 w-full file:mr-3 file:py-1.5 file:px-3
                        file:rounded-lg file:border-0 file:text-xs file:font-semibold
                        file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
          <input type="hidden" id="f-logo"
                 value="<?= ($selected&&$selected['logo']) ? e($selected['logo']) : '' ?>">
          <p class="text-[11px] text-slate-400 mt-1.5">
            Max 512 KB · automaticky orezané na 256 × 256 px
          </p>
        </div>

        <!-- Actions -->
        <div class="flex gap-2 pt-1">
          <button type="button" onclick="saveVenue()"
                  class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white
                         font-bold py-2.5 rounded-xl text-sm transition">
            Uložiť nastavenia
          </button>
          <?php if ($selected): ?>
          <button type="button" onclick="deleteVenue('<?= e($selected['slug']) ?>')"
                  class="px-4 bg-red-50 hover:bg-red-100 text-red-600
                         font-bold py-2.5 rounded-xl text-sm transition">
            Zmazať
          </button>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- ── Tab: Menu ─────────────────────────────────────────────── -->
    <div id="tab-menu" class="space-y-3 hidden">
      <?php if (!$selected): ?>
      <div class="bg-white rounded-2xl shadow-sm p-12 text-center border border-slate-100">
        <p class="text-4xl mb-3">🍽️</p>
        <p class="text-sm text-slate-400">Najprv vytvorte prevádzku v záložke Nastavenia.</p>
      </div>
      <?php else: ?>

      <!-- Global menu settings -->
      <div class="bg-white rounded-2xl shadow-sm p-5 border border-slate-100">
        <h3 class="font-bold text-slate-700 text-sm mb-4">Globálne nastavenia menu</h3>

        <div class="grid grid-cols-3 gap-3 mb-5">
          <!-- Checkboxes -->
          <?php foreach ([
              ['m-show-featured',  'show_featured',  '⭐ Odporúčame'],
              ['m-show-allergens', 'show_allergens',  '🔢 Alergény'],
              ['m-dark-default',   'dark_mode_default','🌙 Temný štandardne'],
          ] as [$id, $key, $label]): ?>
          <label class="flex items-center gap-2 cursor-pointer select-none p-2.5 rounded-xl
                        border border-slate-100 hover:bg-slate-50 transition">
            <input type="checkbox" id="<?= $id ?>"
                   class="w-4 h-4 accent-indigo-600"
                   <?= !empty($menuSettings[$key]) ? 'checked' : '' ?>
                   onchange="menuData.settings['<?= $key ?>']=this.checked?1:0;updatePreview()">
            <span class="text-xs font-semibold text-slate-700"><?= $label ?></span>
          </label>
          <?php endforeach; ?>
        </div>

        <!-- Default category theme -->
        <div class="mb-4">
          <p class="text-xs font-semibold text-slate-600 mb-2">
            Predvolená farba kategórií
          </p>
          <div class="flex flex-wrap gap-2">
            <?php foreach ($GASTRO_THEMES as $gt): ?>
            <button type="button"
                    onclick="selectGlobalTheme('cat','<?= e($gt['bg']) ?>')"
                    data-gcat="<?= e($gt['bg']) ?>"
                    title="<?= e($gt['name']) ?>"
                    class="gcat-btn w-8 h-8 rounded-full border-2 transition ring-offset-1"
                    style="background:<?= e($gt['bg']) ?>;
                           border-color:<?= ($menuSettings['default_category_color']===$gt['bg'])
                                            ?'#6366f1':'#e2e8f0' ?>">
            </button>
            <?php endforeach; ?>
          </div>
          <input type="hidden" id="m-cat-color" value="<?= e($menuSettings['default_category_color']) ?>">
        </div>

        <!-- Default item theme -->
        <div class="mb-5">
          <p class="text-xs font-semibold text-slate-600 mb-2">
            Predvolená farba kariet jedál
          </p>
          <div class="flex flex-wrap gap-2">
            <?php foreach ($GASTRO_THEMES as $gt): ?>
            <button type="button"
                    onclick="selectGlobalTheme('item','<?= e($gt['bg']) ?>')"
                    data-gitem="<?= e($gt['bg']) ?>"
                    title="<?= e($gt['name']) ?>"
                    class="gitem-btn w-8 h-8 rounded-full border-2 transition ring-offset-1"
                    style="background:<?= e($gt['bg']) ?>;
                           border-color:<?= ($menuSettings['default_item_color']===$gt['bg'])
                                            ?'#6366f1':'#e2e8f0' ?>">
            </button>
            <?php endforeach; ?>
          </div>
          <input type="hidden" id="m-item-color" value="<?= e($menuSettings['default_item_color']) ?>">
        </div>

        <button onclick="saveMenuSettings()"
                class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700
                       text-white text-xs font-bold rounded-xl transition">
          Uložiť nastavenia menu
        </button>
      </div>

      <!-- Categories & items -->
      <div class="bg-white rounded-2xl shadow-sm p-5 border border-slate-100">
        <div class="flex items-center justify-between mb-4">
          <h3 class="font-bold text-slate-700 text-sm">Kategórie a jedlá</h3>
          <button onclick="openCatModal(null)"
                  class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700
                         text-white text-xs font-bold rounded-xl transition">
            + Kategória
          </button>
        </div>
        <div id="menu-tree">
          <p class="text-xs text-slate-400 text-center py-6">Načítavanie…</p>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </section>

  <!-- ══ RIGHT: Preview + QR ════════════════════════════════════════ -->
  <aside class="space-y-3">

    <!-- iPhone preview frame -->
    <div class="bg-white rounded-2xl shadow-sm p-4 border border-slate-100">
      <div class="flex items-center justify-between mb-3">
        <h2 class="font-bold text-slate-700 text-xs uppercase tracking-widest">Živý náhľad</h2>
        <button onclick="previewDark=!previewDark;updatePreview()"
                id="preview-dark-btn"
                class="text-xs px-2.5 py-1 rounded-lg border border-slate-200
                       text-slate-500 hover:bg-slate-50 transition font-medium">
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
    <div class="bg-white rounded-2xl shadow-sm p-4 border border-slate-100">
      <h2 class="font-bold text-slate-700 text-xs uppercase tracking-widest mb-3">QR kód</h2>
      <?php if ($selected): ?>
      <div id="qr-box" class="flex justify-center mb-3"></div>
      <button onclick="downloadQR()"
              class="w-full py-2 bg-slate-800 hover:bg-slate-700 text-white
                     text-xs font-bold rounded-xl transition">
        Stiahnuť PNG
      </button>
      <p class="text-[10px] text-slate-400 text-center mt-2 break-all">/r/<?= e($selected['slug']) ?></p>
      <?php else: ?>
      <p class="text-xs text-slate-400 text-center py-4">Najprv uložte prevádzku.</p>
      <?php endif; ?>
    </div>
  </aside>

</div><!-- /.grid -->

<!-- ══ MODAL: Category ════════════════════════════════════════════════ -->
<div id="modal-cat"
     class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
  <div class="bg-white rounded-2xl shadow-2xl p-6 w-full max-w-sm max-h-[92vh] overflow-y-auto">
    <h3 class="font-bold text-lg mb-5" id="modal-cat-title">Kategória</h3>

    <!-- Emoji picker -->
    <div class="mb-5">
      <label class="text-xs font-semibold text-slate-600 mb-2 block">Ikona kategórie</label>
      <div class="grid grid-cols-6 gap-1.5 p-3 bg-slate-50 rounded-xl">
        <?php foreach ($GASTRO_EMOJIS as $em): ?>
        <button type="button" onclick="selectEmoji('<?= e($em) ?>')"
                data-emoji="<?= e($em) ?>"
                class="emoji-btn w-9 h-9 rounded-xl text-xl flex items-center justify-center
                       hover:bg-white border-2 border-transparent transition
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
      <label class="text-xs font-semibold text-slate-600 mb-1.5 block">Názov kategórie *</label>
      <input id="mc-name" type="text" placeholder="napr. Polievky"
             class="w-full border border-slate-300 rounded-xl px-3 py-2.5 text-sm
                    outline-none focus:ring-2 focus:ring-indigo-400">
    </div>

    <!-- Gastro theme palette -->
    <div class="mb-5">
      <label class="text-xs font-semibold text-slate-600 mb-2 block">
        Farba pozadia <span class="text-slate-400 font-normal">(alebo sa použije predvolená)</span>
      </label>
      <div class="flex flex-wrap gap-2">
        <!-- "None / use default" -->
        <button type="button" onclick="selectCatTheme('')" data-ccat=""
                class="cat-theme-btn w-9 h-9 rounded-full border-2 border-slate-300
                       bg-white flex items-center justify-center text-slate-400
                       ring-offset-1 transition hover:border-slate-400">
          <svg class="w-4 h-4" viewBox="0 0 16 16" fill="none">
            <line x1="3" y1="13" x2="13" y2="3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
          </svg>
        </button>
        <?php foreach ($GASTRO_THEMES as $gt): ?>
        <button type="button" onclick="selectCatTheme('<?= e($gt['bg']) ?>')"
                data-ccat="<?= e($gt['bg']) ?>"
                title="<?= e($gt['name']) ?>"
                class="cat-theme-btn w-9 h-9 rounded-full border-2 border-slate-200
                       ring-offset-1 transition hover:scale-110"
                style="background:<?= e($gt['bg']) ?>">
        </button>
        <?php endforeach; ?>
      </div>
      <input type="hidden" id="mc-color" value="">
    </div>

    <input type="hidden" id="mc-id" value="">
    <div class="flex gap-2">
      <button onclick="saveCat()"
              class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white
                     font-bold py-2.5 rounded-xl text-sm transition">
        Uložiť
      </button>
      <button onclick="closeModal('modal-cat')"
              class="px-4 bg-slate-100 hover:bg-slate-200 text-slate-700
                     font-bold py-2.5 rounded-xl text-sm transition">
        Zrušiť
      </button>
    </div>
  </div>
</div>

<!-- ══ MODAL: Item ════════════════════════════════════════════════════ -->
<div id="modal-item"
     class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
  <div class="bg-white rounded-2xl shadow-2xl p-6 w-full max-w-lg max-h-[92vh] overflow-y-auto">
    <h3 class="font-bold text-lg mb-5" id="modal-item-title">Jedlo</h3>
    <div class="space-y-4">

      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="text-xs font-semibold text-slate-600 mb-1.5 block">Názov *</label>
          <input id="mi-name" type="text" placeholder="Rajčinová polievka"
                 class="w-full border border-slate-300 rounded-xl px-3 py-2.5 text-sm
                        outline-none focus:ring-2 focus:ring-indigo-400">
        </div>
        <div>
          <label class="text-xs font-semibold text-slate-600 mb-1.5 block">Cena (€) *</label>
          <input id="mi-price" type="number" step="0.01" min="0" placeholder="4.50"
                 class="w-full border border-slate-300 rounded-xl px-3 py-2.5 text-sm
                        outline-none focus:ring-2 focus:ring-indigo-400">
        </div>
      </div>

      <div>
        <label class="text-xs font-semibold text-slate-600 mb-1.5 block">Gramáž / objem</label>
        <input id="mi-weight" type="text" placeholder="300 ml, 150 g..."
               class="w-full border border-slate-300 rounded-xl px-3 py-2.5 text-sm
                      outline-none focus:ring-2 focus:ring-indigo-400">
      </div>

      <div>
        <label class="text-xs font-semibold text-slate-600 mb-1.5 block">
          Krátky popis <span class="text-slate-400 font-normal">(zobrazuje sa v zozname)</span>
        </label>
        <input id="mi-desc" type="text"
               placeholder="Krémová polievka z čerstvých rajčín"
               class="w-full border border-slate-300 rounded-xl px-3 py-2.5 text-sm
                      outline-none focus:ring-2 focus:ring-indigo-400">
      </div>

      <div>
        <label class="text-xs font-semibold text-slate-600 mb-1.5 block">
          Detailný popis <span class="text-slate-400 font-normal">(v detail okne)</span>
        </label>
        <textarea id="mi-detail" rows="3"
                  placeholder="Podrobný opis jedla pre zákazníka…"
                  class="w-full border border-slate-300 rounded-xl px-3 py-2.5 text-sm
                         outline-none focus:ring-2 focus:ring-indigo-400 resize-none"></textarea>
      </div>

      <label class="flex items-center gap-2.5 cursor-pointer">
        <input type="checkbox" id="mi-featured" class="w-4 h-4 accent-amber-500">
        <span class="text-xs font-semibold text-slate-700">⭐ Zobraziť v sekcii "Odporúčame"</span>
      </label>

      <!-- Allergens -->
      <div>
        <label class="text-xs font-semibold text-slate-600 mb-2 block">Alergény (EU 1–14)</label>
        <div class="grid grid-cols-2 gap-1.5">
          <?php foreach ($EU_ALLERGENS as $num => $label): ?>
          <label class="flex items-center gap-1.5 cursor-pointer
                        px-2 py-1.5 rounded-lg hover:bg-slate-50 transition">
            <input type="checkbox" class="mi-allergen w-3.5 h-3.5 accent-indigo-600"
                   value="<?= $num ?>">
            <span class="text-xs text-slate-600"><?= $num ?> – <?= e($label) ?></span>
          </label>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Item gastro theme -->
      <div>
        <label class="text-xs font-semibold text-slate-600 mb-2 block">Farba karty jedla</label>
        <div class="flex flex-wrap gap-2">
          <button type="button" onclick="selectItemTheme('')" data-iitem=""
                  class="item-theme-btn w-9 h-9 rounded-full border-2 border-slate-300
                         bg-white flex items-center justify-center text-slate-400
                         ring-offset-1 transition hover:border-slate-400">
            <svg class="w-4 h-4" viewBox="0 0 16 16" fill="none">
              <line x1="3" y1="13" x2="13" y2="3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
            </svg>
          </button>
          <?php foreach ($GASTRO_THEMES as $gt): ?>
          <button type="button" onclick="selectItemTheme('<?= e($gt['bg']) ?>')"
                  data-iitem="<?= e($gt['bg']) ?>"
                  title="<?= e($gt['name']) ?>"
                  class="item-theme-btn w-9 h-9 rounded-full border-2 border-slate-200
                         ring-offset-1 transition hover:scale-110"
                  style="background:<?= e($gt['bg']) ?>">
          </button>
          <?php endforeach; ?>
        </div>
        <input type="hidden" id="mi-color" value="">
      </div>
    </div>

    <input type="hidden" id="mi-id" value="">
    <input type="hidden" id="mi-cat-id" value="">
    <div class="flex gap-2 mt-5">
      <button onclick="saveItemData()"
              class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white
                     font-bold py-2.5 rounded-xl text-sm transition">
        Uložiť jedlo
      </button>
      <button onclick="closeModal('modal-item')"
              class="px-4 bg-slate-100 hover:bg-slate-200 text-slate-700
                     font-bold py-2.5 rounded-xl text-sm transition">
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
const GASTRO  = <?= json_encode($GASTRO_THEMES) ?>;   // [{bg, name}, ...]

let menuData = {
  categories: <?= json_encode($menuCategories, JSON_UNESCAPED_UNICODE) ?>,
  settings:   <?= json_encode($menuSettings,   JSON_UNESCAPED_UNICODE) ?>,
};
let currentVenueColor = <?= json_encode($selected ? $selected['color'] : 'black') ?>;
let previewDark       = <?= json_encode((int)($menuSettings['dark_mode_default'] ?? 0) === 1) ?>;
let activeTab         = 'settings';

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
    b.style.borderColor = b.dataset.vc === c ? '#6366f1' : 'transparent';
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
  document.querySelectorAll('.vc-btn').forEach(b => b.style.borderColor = 'transparent');
  setVenueColor(this.value);
});

// ── Logo upload ───────────────────────────────────────────────────
document.getElementById('f-logo-file')?.addEventListener('change', function () {
  const file = this.files[0]; if (!file) return;
  const fr = new FileReader();
  fr.onload = e => {
    const img = new Image();
    img.onload = () => {
      const cv = document.createElement('canvas');
      cv.width = cv.height = 256;
      const ctx = cv.getContext('2d');
      const sz = Math.min(img.width, img.height);
      ctx.drawImage(img, (img.width-sz)/2, (img.height-sz)/2, sz, sz, 0, 0, 256, 256);
      const url = cv.toDataURL('image/jpeg', 0.85);
      if (url.length > 700000) { toast('Logo je príliš veľké!', 'error'); return; }
      document.getElementById('f-logo').value = url;
      document.getElementById('logo-preview').src = url;
      document.getElementById('logo-preview-wrap').classList.remove('hidden');
      updatePreview();
    };
    img.src = e.target.result;
  };
  fr.readAsDataURL(file);
});

function clearLogo() {
  document.getElementById('f-logo').value = '';
  document.getElementById('f-logo-file').value = '';
  document.getElementById('logo-preview').src = '';
  document.getElementById('logo-preview-wrap').classList.add('hidden');
  updatePreview();
}

// ── Tab switching ─────────────────────────────────────────────────
function switchTab(tab) {
  activeTab = tab;
  ['settings', 'menu'].forEach(t => {
    document.getElementById('tab-' + t)?.classList.toggle('hidden', t !== tab);
    const btn = document.getElementById('tab-btn-' + t);
    if (!btn) return;
    btn.classList.toggle('border-indigo-600', t === tab);
    btn.classList.toggle('text-indigo-600',   t === tab);
    btn.classList.toggle('border-transparent', t !== tab);
    btn.classList.toggle('text-slate-400',    t !== tab);
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
      b.style.borderColor = b.dataset.gcat === color ? '#6366f1' : '#e2e8f0');
  } else {
    document.getElementById('m-item-color').value = color;
    menuData.settings.default_item_color = color;
    document.querySelectorAll('.gitem-btn').forEach(b =>
      b.style.borderColor = b.dataset.gitem === color ? '#6366f1' : '#e2e8f0');
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
}

function selectItemTheme(color) {
  document.getElementById('mi-color').value = color;
  document.querySelectorAll('.item-theme-btn').forEach(b => {
    const match = b.dataset.iitem === color;
    b.classList.toggle('ring-2',          match);
    b.classList.toggle('ring-indigo-500', match);
    b.classList.toggle('ring-offset-1',   match);
  });
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

['modal-cat', 'modal-item'].forEach(id => {
  document.getElementById(id)?.addEventListener('click', e => {
    if (e.target === document.getElementById(id)) closeModal(id);
  });
});

// ── Category modal ────────────────────────────────────────────────
function openCatModal(catId) {
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
    dark_mode_default:     document.getElementById('m-dark-default').checked   ? 1 : 0,
    default_category_color: document.getElementById('m-cat-color').value,
    default_item_color:    document.getElementById('m-item-color').value,
  });
  if (data.ok) {
    previewDark = !!menuData.settings.dark_mode_default;
    updatePreview();
    toast('Nastavenia uložené.', 'success');
  } else {
    toast(data.error || 'Chyba.', 'error');
  }
}

// ── Menu API ──────────────────────────────────────────────────────
async function menuApi(payload) {
  try {
    const res = await fetch(APP_URL + '/api/manage_menu.php', {
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
  updatePreview();
}

// ── Venue AJAX ────────────────────────────────────────────────────
async function saveVenue() {
  const payload = {
    csrf:          CSRF,
    action:        'save',
    original_slug: document.getElementById('f-original-slug').value,
    slug:          document.getElementById('f-slug').value.trim(),
    name:          document.getElementById('f-name').value.trim(),
    google_url:    document.getElementById('f-google').value.trim(),
    instagram_url: document.getElementById('f-insta').value.trim(),
    color:         document.getElementById('f-color').value,
    logo:          document.getElementById('f-logo').value,
  };
  try {
    const res  = await fetch(APP_URL + '/api/save_venue.php', {
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
    }
  } catch (e) {
    toast('Sieťová chyba: ' + e.message, 'error');
  }
}

async function deleteVenue(slug) {
  if (!confirm(`Naozaj zmazať prevádzku "${slug}" aj so všetkými jedlami?`)) return;
  try {
    const res  = await fetch(APP_URL + '/api/save_venue.php', {
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
  ['f-original-slug','f-slug','f-name','f-google','f-insta','f-logo'].forEach(id => {
    const el = document.getElementById(id); if (el) el.value = '';
  });
  const logoFile = document.getElementById('f-logo-file');
  if (logoFile) logoFile.value = '';
  document.getElementById('logo-preview-wrap')?.classList.add('hidden');
  document.getElementById('settings-title').textContent = 'Nová prevádzka';
  setVenueColor('black');
  switchTab('settings');
}

// ── Menu tree renderer ────────────────────────────────────────────
function renderMenuTree() {
  const el = document.getElementById('menu-tree');
  if (!el) return;
  const cats = menuData.categories;
  if (!cats.length) {
    el.innerHTML = '<p class="text-xs text-slate-400 text-center py-8">Žiadne kategórie. Pridajte prvú.</p>';
    return;
  }
  el.innerHTML = cats.map(cat => {
    const catBg = cat.bg_color || menuData.settings.default_category_color || '#1E3A5F';
    const catTc = yiq(catBg);
    const items = cat.items.map(item => `
      <div class="flex items-center gap-2 py-2 border-b border-slate-50 last:border-0">
        <span class="flex-1 text-xs text-slate-700 truncate font-medium">${esc(item.name)}</span>
        <span class="text-xs font-bold text-slate-400">${fmtPrice(item.price)}</span>
        ${item.is_featured ? '<span title="Odporúčame" class="text-xs">⭐</span>' : ''}
        <button onclick="openItemModal(${item.id},${cat.id})"
                class="text-slate-400 hover:text-indigo-600 text-xs px-1 transition"
                title="Upraviť">✏️</button>
        <button onclick="deleteItemData(${item.id},'${esc(item.name)}')"
                class="text-slate-400 hover:text-red-500 text-xs px-1 transition"
                title="Zmazať">🗑️</button>
      </div>`).join('');
    return `
      <div class="rounded-2xl border border-slate-100 overflow-hidden mb-3">
        <div class="flex items-center gap-2 px-3 py-3" style="background:${catBg}">
          <span class="text-base leading-none" style="color:${catTc}">${esc(cat.icon)||'📁'}</span>
          <span class="font-bold text-sm flex-1 truncate" style="color:${catTc}">${esc(cat.name)}</span>
          <span class="text-xs opacity-60" style="color:${catTc}">${cat.items.length} jedál</span>
          <button onclick="openCatModal(${cat.id})"
                  class="p-1.5 rounded-lg transition opacity-80 hover:opacity-100 text-xs"
                  style="background:rgba(${catTc==='#ffffff'?'255,255,255':'0,0,0'},.15);color:${catTc}"
                  title="Upraviť kategóriu">✏️</button>
          <button onclick="deleteCatData(${cat.id},'${esc(cat.name)}')"
                  class="p-1.5 rounded-lg transition opacity-80 hover:opacity-100 text-xs"
                  style="background:rgba(${catTc==='#ffffff'?'255,255,255':'0,0,0'},.15);color:${catTc}"
                  title="Zmazať kategóriu">🗑️</button>
        </div>
        <div class="px-3 pt-1 pb-2">
          ${items}
          <button onclick="openItemModal(null,${cat.id})"
                  class="mt-2 w-full py-2 border border-dashed border-slate-200 rounded-xl
                         text-xs text-slate-400 hover:text-indigo-600 hover:border-indigo-300 transition">
            + Pridať jedlo
          </button>
        </div>
      </div>`;
  }).join('');
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
  const cats   = menuData.categories;
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
    const fi = cats.flatMap(c => c.items.filter(i => i.is_featured));
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
    const catBg = cat.bg_color || s.default_category_color || '#1E3A5F';
    const catTc = yiq(catBg);
    html += `<div>
      <div style="background:${catBg};padding:6px 8px;display:flex;align-items:center;gap:4px">
        ${cat.icon ? `<span style="font-size:10px">${esc(cat.icon)}</span>` : ''}
        <span style="font-weight:800;font-size:8.5px;color:${catTc}">${esc(cat.name)}</span>
      </div>
      <div style="background:${sectionBg};padding:4px 5px 3px;display:flex;flex-direction:column;gap:3px">
        ${cat.items.length ? cat.items.map(item => {
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
</body>
</html>
