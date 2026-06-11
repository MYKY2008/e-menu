<?php
$title     = 'Admin — GastroLink QR';
$robots    = 'noindex, nofollow';
require __DIR__ . '/partials/header.php';
?>
<body class="bg-gray-50 dark:bg-slate-950 min-h-screen text-slate-900 dark:text-slate-100 transition-colors duration-200">

<?php
$db = getDB();

$totalUsers  = (int)$db->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalVenues = (int)$db->query("SELECT COUNT(*) FROM venues")->fetchColumn();

$users = $db->query("
    SELECT u.id, u.username, u.role, u.plan_name, u.max_venues, u.max_categories, u.max_items_per_cat,
           u.created_at, u.is_verified, COUNT(v.slug) AS venue_count
    FROM users u
    LEFT JOIN venues v ON v.user_id = u.id
    GROUP BY u.id
    ORDER BY u.created_at DESC
")->fetchAll();

$venues = $db->query("
    SELECT v.slug, v.name, v.color, v.created_at, u.username AS owner
    FROM venues v
    JOIN users u ON u.id = v.user_id
    ORDER BY v.created_at DESC
")->fetchAll();

$flash = getFlash();
?>

<!-- ── NAVBAR ──────────────────────────────────────────────────────── -->
<nav class="bg-white/80 dark:bg-slate-950/80 backdrop-blur-lg sticky top-0 z-30
            border-b border-gray-100 dark:border-slate-800 px-5 py-3
            flex items-center justify-between">

  <div class="flex items-center gap-3">
    <span class="font-extrabold text-sm tracking-tight">
      <span class="text-indigo-600">GastroLink</span><span class="text-emerald-500">QR</span>
    </span>
    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-widest
                 bg-indigo-100 dark:bg-indigo-900/50 text-indigo-600 dark:text-indigo-400">
      Admin
    </span>
  </div>

  <div class="flex items-center gap-3">
    <a href="<?= url('dashboard') ?>"
       class="text-xs font-semibold px-3 py-1.5 rounded-xl
              text-slate-600 dark:text-slate-400
              hover:bg-gray-100 dark:hover:bg-slate-800
              transition-all duration-200">
      Dashboard
    </a>
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

<!-- Toast -->
<div id="toast-container" class="fixed top-16 right-4 z-50 flex flex-col gap-2 pointer-events-none"></div>

<div class="max-w-6xl mx-auto px-4 py-6 space-y-5">

  <!-- Flash -->
  <?php if ($flash): ?>
  <div class="px-4 py-3 rounded-2xl text-sm font-medium
    <?= $flash['type']==='success'
        ? 'bg-emerald-50 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800'
        : 'bg-red-50 dark:bg-red-900/30 text-red-700 dark:text-red-400 border border-red-200 dark:border-red-800' ?>">
    <?= e($flash['msg']) ?>
  </div>
  <?php endif; ?>

  <!-- ── Stats cards ──────────────────────────────────────────────── -->
  <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
    <?php
    $stats = [
      ['👤', 'Používatelia', $totalUsers,  'bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300'],
      ['🏪', 'Prevádzky',    $totalVenues, 'bg-emerald-50 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-300'],
    ];
    foreach ($stats as [$icon, $label, $val, $cls]):
    ?>
    <div class="<?= $cls ?> rounded-[2rem] p-5 border border-gray-100 dark:border-slate-800 shadow-sm">
      <p class="text-xl mb-1"><?= $icon ?></p>
      <p class="text-xs font-semibold opacity-70 uppercase tracking-wide"><?= $label ?></p>
      <p class="text-3xl font-extrabold tracking-tight mt-0.5"><?= $val ?></p>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- ── Users table ──────────────────────────────────────────────── -->
  <div class="bg-white dark:bg-slate-900 rounded-[2rem] shadow-sm border border-gray-100 dark:border-slate-800 overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-100 dark:border-slate-800 flex items-center justify-between">
      <h2 class="font-bold text-slate-900 dark:text-white">
        Používatelia
        <span class="ml-2 px-2 py-0.5 rounded-full bg-gray-100 dark:bg-slate-800
                     text-slate-500 dark:text-slate-400 text-xs font-semibold">
          <?= count($users) ?>
        </span>
      </h2>
      <button onclick="openCreateUser()"
        class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold
               rounded-xl transition-all duration-200 active:scale-95 shadow-sm shadow-indigo-500/20">
        + Nový účet
      </button>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="bg-gray-50 dark:bg-slate-800 text-slate-500 dark:text-slate-400 text-xs uppercase tracking-wide">
          <tr>
            <th class="px-6 py-3 text-left">E-mail</th>
            <th class="px-6 py-3 text-left">Rola</th>
            <th class="px-6 py-3 text-left">Plán</th>
            <th class="px-6 py-3 text-left">Prev.</th>
            <th class="px-6 py-3 text-left">Max prev.</th>
            <th class="px-6 py-3 text-left">Max kat.</th>
            <th class="px-6 py-3 text-left">Max jed.</th>
            <th class="px-6 py-3 text-left">Registrovaný</th>
            <th class="px-6 py-3 text-right">Akcie</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-slate-800">
        <?php foreach ($users as $u): ?>
          <?php $isSelf = ((int)$u['id'] === (int)$_SESSION['user_id']); ?>
          <tr class="hover:bg-gray-50 dark:hover:bg-slate-800/50 transition-colors" id="user-row-<?= $u['id'] ?>">
            <td class="px-6 py-4 font-medium text-slate-900 dark:text-slate-100">
              <?= e($u['username']) ?>
              <?php if ($isSelf): ?>
              <span class="ml-1 text-[10px] text-indigo-400 dark:text-indigo-500">(vy)</span>
              <?php endif; ?>
              <?php if (!(int)($u['is_verified'] ?? 1)): ?>
              <span class="ml-1.5 px-1.5 py-0.5 rounded text-[9px] font-bold uppercase tracking-wide
                           bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-400">
                neoverený
              </span>
              <?php endif; ?>
            </td>
            <td class="px-6 py-4">
              <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold
                <?= $u['role']==='admin'
                    ? 'bg-indigo-100 dark:bg-indigo-900/50 text-indigo-700 dark:text-indigo-300'
                    : 'bg-gray-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400' ?>">
                <?= $u['role'] ?>
              </span>
            </td>
            <td class="px-6 py-4">
              <?php $uPlan = $u['plan_name'] ?? 'free'; ?>
              <select data-field="plan_name"
                      onchange="onPlanChange(<?= $u['id'] ?>, this)"
                      class="bg-gray-100 dark:bg-slate-800 border-none rounded-xl px-3 py-1.5 text-xs font-semibold
                             focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-all duration-200
                             <?= $uPlan === 'free' ? 'text-slate-600 dark:text-slate-400' : 'text-emerald-700 dark:text-emerald-400' ?>">
                <option value="free"   <?= $uPlan === 'free'   ? 'selected' : '' ?>>Free</option>
                <option value="pro"    <?= $uPlan === 'pro'    ? 'selected' : '' ?>>Pro</option>
                <option value="ultra"  <?= $uPlan === 'ultra'  ? 'selected' : '' ?>>Ultra</option>
                <option value="custom" <?= $uPlan === 'custom' ? 'selected' : '' ?>>Custom</option>
              </select>
            </td>
            <td class="px-6 py-4 text-slate-500 dark:text-slate-400"><?= $u['venue_count'] ?></td>
            <td class="px-6 py-4">
              <input type="number" min="0" max="9999" data-field="max_venues"
                value="<?= (int)($u['max_venues'] ?? 1) ?>"
                class="w-16 bg-gray-100 dark:bg-slate-800 border-none rounded-xl
                       px-2 py-1.5 text-xs text-slate-900 dark:text-slate-100
                       focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-all duration-200"
                onchange="updateUserPlan(<?= $u['id'] ?>)">
            </td>
            <td class="px-6 py-4">
              <input type="number" min="0" max="9999" data-field="max_categories"
                value="<?= (int)($u['max_categories'] ?? 3) ?>"
                class="w-16 bg-gray-100 dark:bg-slate-800 border-none rounded-xl
                       px-2 py-1.5 text-xs text-slate-900 dark:text-slate-100
                       focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-all duration-200"
                onchange="updateUserPlan(<?= $u['id'] ?>)">
            </td>
            <td class="px-6 py-4">
              <input type="number" min="0" max="9999" data-field="max_items_per_cat"
                value="<?= (int)($u['max_items_per_cat'] ?? 5) ?>"
                class="w-16 bg-gray-100 dark:bg-slate-800 border-none rounded-xl
                       px-2 py-1.5 text-xs text-slate-900 dark:text-slate-100
                       focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-all duration-200"
                onchange="updateUserPlan(<?= $u['id'] ?>)">
            </td>
            <td class="px-6 py-4 text-slate-400 dark:text-slate-500 text-xs">
              <?= e(substr($u['created_at'],0,10)) ?>
            </td>
            <td class="px-6 py-4 text-right">
              <div class="flex justify-end gap-2">
                <button onclick="resetPassword(<?= $u['id'] ?>,'<?= e($u['username']) ?>')"
                  class="px-3 py-1.5 text-xs rounded-xl font-semibold transition-all duration-200 active:scale-95
                         bg-amber-100 dark:bg-amber-900/30 hover:bg-amber-200 dark:hover:bg-amber-900/50
                         text-amber-800 dark:text-amber-300">
                  Heslo
                </button>
                <?php if (!$isSelf): ?>
                <button onclick="deleteUser(<?= $u['id'] ?>,'<?= e($u['username']) ?>')"
                  class="px-3 py-1.5 text-xs rounded-xl font-semibold transition-all duration-200 active:scale-95
                         bg-red-100 dark:bg-red-900/30 hover:bg-red-200 dark:hover:bg-red-900/50
                         text-red-700 dark:text-red-400">
                  Zmazať
                </button>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- ── Venues table ─────────────────────────────────────────────── -->
  <div class="bg-white dark:bg-slate-900 rounded-[2rem] shadow-sm border border-gray-100 dark:border-slate-800 overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-100 dark:border-slate-800">
      <h2 class="font-bold text-slate-900 dark:text-white">
        Všetky prevádzky
        <span class="ml-2 px-2 py-0.5 rounded-full bg-gray-100 dark:bg-slate-800
                     text-slate-500 dark:text-slate-400 text-xs font-semibold">
          <?= count($venues) ?>
        </span>
      </h2>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="bg-gray-50 dark:bg-slate-800 text-slate-500 dark:text-slate-400 text-xs uppercase tracking-wide">
          <tr>
            <th class="px-6 py-3 text-left">Slug / Link</th>
            <th class="px-6 py-3 text-left">Názov</th>
            <th class="px-6 py-3 text-left">Vlastník</th>
            <th class="px-6 py-3 text-left">Vytvorená</th>
            <th class="px-6 py-3 text-right">Akcie</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-slate-800">
        <?php foreach ($venues as $v):
          $c = resolveColor($v['color']);
        ?>
          <tr class="hover:bg-gray-50 dark:hover:bg-slate-800/50 transition-colors" id="venue-row-<?= e($v['slug']) ?>">
            <td class="px-6 py-4">
              <span class="flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full shrink-0" style="background:<?= e($c['hex']) ?>"></span>
                <a href="<?= url('r/' . $v['slug']) ?>" target="_blank"
                   class="text-indigo-600 dark:text-indigo-400 hover:underline font-mono text-xs">
                  /r/<?= e($v['slug']) ?>
                </a>
              </span>
            </td>
            <td class="px-6 py-4 font-medium text-slate-900 dark:text-slate-100"><?= e($v['name']) ?></td>
            <td class="px-6 py-4 text-slate-500 dark:text-slate-400"><?= e($v['owner']) ?></td>
            <td class="px-6 py-4 text-slate-400 dark:text-slate-500 text-xs">
              <?= e(substr($v['created_at'],0,10)) ?>
            </td>
            <td class="px-6 py-4 text-right">
              <button onclick="adminDeleteVenue('<?= e($v['slug']) ?>','<?= e($v['name']) ?>')"
                class="px-3 py-1.5 text-xs rounded-xl font-semibold transition-all duration-200 active:scale-95
                       bg-red-100 dark:bg-red-900/30 hover:bg-red-200 dark:hover:bg-red-900/50
                       text-red-700 dark:text-red-400">
                Zmazať
              </button>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <!-- ── Database backup ────────────────────────────────────────── -->
  <div class="bg-white dark:bg-slate-900 rounded-[2rem] shadow-sm
              border border-gray-100 dark:border-slate-800 p-6
              flex items-center justify-between gap-4">
    <div>
      <h2 class="font-bold text-slate-900 dark:text-white mb-1">Záloha databázy</h2>
      <p class="text-sm text-slate-500 dark:text-slate-400">
        Stiahnuť aktuálny stav databázy ako <code class="text-xs bg-gray-100 dark:bg-slate-800 px-1.5 py-0.5 rounded">.db</code> súbor.
      </p>
    </div>
    <a href="<?= url('api/backup.php') ?>?csrf=<?= csrfToken() ?>"
       class="flex-shrink-0 px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white
              font-semibold text-sm rounded-2xl transition-all duration-200 active:scale-95
              shadow-lg shadow-indigo-500/20">
      Stiahnuť zálohu
    </a>
  </div>

</div>

<!-- ── Create user modal ────────────────────────────────────────── -->
<div id="cu-modal" class="hidden fixed inset-0 bg-black/50 dark:bg-black/70 z-50 flex items-center justify-center p-4">
  <div class="bg-white dark:bg-slate-900 rounded-[2rem] shadow-2xl border border-gray-100 dark:border-slate-800 p-7 w-full max-w-sm">
    <h3 class="font-bold text-lg text-slate-900 dark:text-white mb-5">Vytvoriť účet</h3>
    <div class="space-y-3 mb-5">
      <input id="cu-email" type="email" placeholder="E-mail" autocomplete="off"
        class="w-full bg-gray-100 dark:bg-slate-800 border-none rounded-xl
               px-4 py-3 text-sm text-slate-900 dark:text-slate-100
               placeholder-slate-400 dark:placeholder-slate-500
               focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-all duration-200">
      <input id="cu-pass" type="password" placeholder="Heslo (min. 8 znakov)" minlength="8"
        class="w-full bg-gray-100 dark:bg-slate-800 border-none rounded-xl
               px-4 py-3 text-sm text-slate-900 dark:text-slate-100
               placeholder-slate-400 dark:placeholder-slate-500
               focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-all duration-200">
      <div class="flex gap-3">
        <select id="cu-role"
          class="flex-1 bg-gray-100 dark:bg-slate-800 border-none rounded-xl
                 px-4 py-3 text-sm text-slate-900 dark:text-slate-100
                 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-all duration-200">
          <option value="user">Používateľ</option>
          <option value="admin">Admin</option>
        </select>
        <input id="cu-limit" type="number" min="0" max="9999" value="1" placeholder="Limit"
          class="w-24 bg-gray-100 dark:bg-slate-800 border-none rounded-xl
                 px-4 py-3 text-sm text-slate-900 dark:text-slate-100
                 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-all duration-200">
      </div>
    </div>
    <div class="flex gap-3">
      <button onclick="submitCreateUser()"
        class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold
               py-3 rounded-2xl text-sm transition-all duration-200 active:scale-95">
        Vytvoriť
      </button>
      <button onclick="closeCreateUser()"
        class="px-5 bg-gray-100 dark:bg-slate-800 hover:bg-gray-200 dark:hover:bg-slate-700
               text-slate-700 dark:text-slate-300 font-semibold
               py-3 rounded-2xl text-sm transition-all duration-200">
        Zrušiť
      </button>
    </div>
  </div>
</div>

<!-- ── Password reset modal ─────────────────────────────────────── -->
<div id="pw-modal" class="hidden fixed inset-0 bg-black/50 dark:bg-black/70 z-50 flex items-center justify-center p-4">
  <div class="bg-white dark:bg-slate-900 rounded-[2rem] shadow-2xl border border-gray-100 dark:border-slate-800 p-7 w-full max-w-sm">
    <h3 class="font-bold text-lg text-slate-900 dark:text-white mb-1">Reset hesla</h3>
    <p class="text-sm text-slate-500 dark:text-slate-400 mb-5">
      Účet: <span id="pw-username" class="font-semibold text-slate-700 dark:text-slate-200"></span>
    </p>
    <input id="pw-new" type="password" placeholder="Nové heslo (min. 8 znakov)" minlength="8"
      class="w-full bg-gray-100 dark:bg-slate-800 border-none rounded-xl
             px-4 py-3 text-sm text-slate-900 dark:text-slate-100
             placeholder-slate-400 dark:placeholder-slate-500
             focus:outline-none focus:ring-2 focus:ring-indigo-500
             transition-all duration-200 mb-5">
    <div class="flex gap-3">
      <button onclick="submitReset()"
        class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold
               py-3 rounded-2xl text-sm transition-all duration-200 active:scale-95">
        Nastaviť heslo
      </button>
      <button onclick="closePwModal()"
        class="px-5 bg-gray-100 dark:bg-slate-800 hover:bg-gray-200 dark:hover:bg-slate-700
               text-slate-700 dark:text-slate-300 font-semibold
               py-3 rounded-2xl text-sm transition-all duration-200">
        Zrušiť
      </button>
    </div>
  </div>
</div>

<script>
const CSRF    = <?= json_encode(csrfToken()) ?>;
const API_URL = <?= json_encode(url('api/admin_actions.php')) ?>;

function fetchWithTimeout(url, options, ms = 10000) {
  const ctrl = new AbortController();
  const id   = setTimeout(() => ctrl.abort(), ms);
  return fetch(url, { ...options, signal: ctrl.signal }).finally(() => clearTimeout(id));
}

async function adminApi(payload) {
  const res = await fetchWithTimeout(API_URL, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ ...payload, csrf: CSRF })
  });
  return res.json();
}

const PLAN_DEFAULTS = {
  free:   { max_venues: 1, max_categories: 3,  max_items_per_cat: 5  },
  pro:    { max_venues: 1, max_categories: 10, max_items_per_cat: 25 },
  ultra:  { max_venues: 1, max_categories: 20, max_items_per_cat: 50 },
  custom: { max_venues: 1, max_categories: 10, max_items_per_cat: 25 },
};

function onPlanChange(userId, select) {
  const row      = document.getElementById('user-row-' + userId);
  const planName = select.value;
  const def      = PLAN_DEFAULTS[planName] || PLAN_DEFAULTS.free;
  row.querySelector('[data-field="max_venues"]').value        = def.max_venues;
  row.querySelector('[data-field="max_categories"]').value   = def.max_categories;
  row.querySelector('[data-field="max_items_per_cat"]').value = def.max_items_per_cat;
  updateUserPlan(userId);
}

async function updateUserPlan(userId) {
  const row = document.getElementById('user-row-' + userId);
  const planName   = row.querySelector('[data-field="plan_name"]').value;
  const maxVenues  = parseInt(row.querySelector('[data-field="max_venues"]').value)        || 1;
  const maxCats    = parseInt(row.querySelector('[data-field="max_categories"]').value)    || 3;
  const maxItems   = parseInt(row.querySelector('[data-field="max_items_per_cat"]').value) || 5;
  try {
    const data = await adminApi({
      action: 'update_plan', user_id: userId,
      plan_name: planName, max_venues: maxVenues, max_categories: maxCats, max_items_per_cat: maxItems
    });
    if (data.ok) toast('Plán aktualizovaný.', 'success');
    else toast(data.error || 'Chyba.', 'error');
  } catch { toast('Sieťová chyba.', 'error'); }
}

async function deleteUser(userId, username) {
  if (!confirm(`Naozaj zmazať účet "${username}" a všetky jeho prevádzky?`)) return;
  try {
    const data = await adminApi({ action: 'delete_user', user_id: userId });
    if (data.ok) {
      toast('Účet zmazaný.', 'success');
      document.getElementById('user-row-' + userId)?.remove();
    } else toast(data.error || 'Chyba.', 'error');
  } catch { toast('Sieťová chyba.', 'error'); }
}

async function adminDeleteVenue(slug, name) {
  if (!confirm(`Naozaj zmazať prevádzku "${name}"?`)) return;
  try {
    const data = await adminApi({ action: 'delete_venue', slug });
    if (data.ok) {
      toast('Prevádzka zmazaná.', 'success');
      document.getElementById('venue-row-' + slug)?.remove();
    } else toast(data.error || 'Chyba.', 'error');
  } catch { toast('Sieťová chyba.', 'error'); }
}

let _pwUserId = null;
function resetPassword(userId, username) {
  _pwUserId = userId;
  document.getElementById('pw-username').textContent = username;
  document.getElementById('pw-new').value = '';
  document.getElementById('pw-modal').classList.remove('hidden');
}
function closePwModal() {
  document.getElementById('pw-modal').classList.add('hidden');
  _pwUserId = null;
}
async function submitReset() {
  const pass = document.getElementById('pw-new').value;
  if (pass.length < 8) { toast('Heslo musí mať aspoň 8 znakov.', 'error'); return; }
  try {
    const data = await adminApi({ action: 'reset_password', user_id: _pwUserId, password: pass });
    if (data.ok) { toast('Heslo zmenené.', 'success'); closePwModal(); }
    else toast(data.error || 'Chyba.', 'error');
  } catch { toast('Sieťová chyba.', 'error'); }
}


function openCreateUser() {
  document.getElementById('cu-email').value  = '';
  document.getElementById('cu-pass').value   = '';
  document.getElementById('cu-role').value   = 'user';
  document.getElementById('cu-limit').value  = '1';
  document.getElementById('cu-modal').classList.remove('hidden');
  setTimeout(() => document.getElementById('cu-email').focus(), 50);
}
function closeCreateUser() {
  document.getElementById('cu-modal').classList.add('hidden');
}
async function submitCreateUser() {
  const email = document.getElementById('cu-email').value.trim();
  const pass  = document.getElementById('cu-pass').value;
  const role  = document.getElementById('cu-role').value;
  const limit = parseInt(document.getElementById('cu-limit').value) || 1;
  if (!email) { toast('Zadajte e-mail.', 'error'); return; }
  if (pass.length < 8) { toast('Heslo musí mať aspoň 8 znakov.', 'error'); return; }
  try {
    const data = await adminApi({ action: 'create_user', username: email, password: pass, role, venue_limit: limit });
    if (data.ok) {
      toast('Účet bol vytvorený.', 'success');
      closeCreateUser();
      const tbody = document.querySelector('#user-row-' + data.id)?.closest('tbody')
                 || document.querySelector('tbody');
      if (tbody) {
        const tr = document.createElement('tr');
        tr.id = 'user-row-' + data.id;
        tr.className = 'hover:bg-gray-50 dark:hover:bg-slate-800/50 transition-colors';
        const inp = 'class="w-16 bg-gray-100 dark:bg-slate-800 border-none rounded-xl px-2 py-1.5 text-xs text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-all duration-200"';
        tr.innerHTML = `
          <td class="px-6 py-4 font-medium text-slate-900 dark:text-slate-100">${escHtml(email)}</td>
          <td class="px-6 py-4"><span class="px-2.5 py-0.5 rounded-full text-xs font-semibold ${role==='admin'?'bg-indigo-100 dark:bg-indigo-900/50 text-indigo-700 dark:text-indigo-300':'bg-gray-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400'}">${escHtml(role)}</span></td>
          <td class="px-6 py-4">
            <select data-field="plan_name" onchange="onPlanChange(${data.id},this)"
              class="bg-gray-100 dark:bg-slate-800 border-none rounded-xl px-3 py-1.5 text-xs font-semibold text-slate-600 dark:text-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-all duration-200">
              <option value="free" selected>Free</option>
              <option value="pro">Pro</option><option value="ultra">Ultra</option><option value="custom">Custom</option>
            </select>
          </td>
          <td class="px-6 py-4 text-slate-500 dark:text-slate-400">0</td>
          <td class="px-6 py-4"><input type="number" min="0" max="9999" data-field="max_venues" value="${limit}" ${inp} onchange="updateUserPlan(${data.id})"></td>
          <td class="px-6 py-4"><input type="number" min="0" max="9999" data-field="max_categories" value="3" ${inp} onchange="updateUserPlan(${data.id})"></td>
          <td class="px-6 py-4"><input type="number" min="0" max="9999" data-field="max_items_per_cat" value="5" ${inp} onchange="updateUserPlan(${data.id})"></td>
          <td class="px-6 py-4 text-slate-400 dark:text-slate-500 text-xs">${new Date().toISOString().slice(0,10)}</td>
          <td class="px-6 py-4 text-right"><div class="flex justify-end gap-2">
            <button onclick="resetPassword(${data.id},'${escHtml(email).replace(/'/g,"\\\'")}')"
              class="px-3 py-1.5 text-xs rounded-xl font-semibold transition-all duration-200 active:scale-95 bg-amber-100 dark:bg-amber-900/30 hover:bg-amber-200 dark:hover:bg-amber-900/50 text-amber-800 dark:text-amber-300">Heslo</button>
            <button onclick="deleteUser(${data.id},'${escHtml(email).replace(/'/g,"\\\'")}')"
              class="px-3 py-1.5 text-xs rounded-xl font-semibold transition-all duration-200 active:scale-95 bg-red-100 dark:bg-red-900/30 hover:bg-red-200 dark:hover:bg-red-900/50 text-red-700 dark:text-red-400">Zmazať</button>
          </div></td>`;
        tbody.prepend(tr);
      }
    } else toast(data.error || 'Chyba.', 'error');
  } catch { toast('Sieťová chyba.', 'error'); }
}

function escHtml(s) {
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ── Modal utils & backdrop close ──────────────────────────────────
function openModal(id)  { document.getElementById(id)?.classList.remove('hidden'); }
function closeModal(id) { document.getElementById(id)?.classList.add('hidden'); }

[['pw-modal', () => closePwModal()], ['cu-modal', () => closeCreateUser()]].forEach(([id, fn]) => {
  const el = document.getElementById(id);
  if (!el) return;
  let _d = false;
  el.addEventListener('mousedown', e => { _d = e.target === el; });
  el.addEventListener('mouseup',   e => { if (_d && e.target === el) fn(); });
});
document.addEventListener('keydown', e => {
  if (e.key !== 'Escape') return;
  ['pw-modal', 'cu-modal'].forEach(id => {
    const el = document.getElementById(id);
    if (el && !el.classList.contains('hidden')) {
      if (id === 'pw-modal') closePwModal();
      else closeCreateUser();
    }
  });
});

function toast(msg, type = 'info') {
  const el = document.createElement('div');
  const bg = type === 'success' ? 'bg-emerald-600' : type === 'error' ? 'bg-red-600' : 'bg-slate-800';
  el.className = `pointer-events-auto px-5 py-3 rounded-2xl text-white text-sm font-semibold shadow-xl ${bg}`;
  el.textContent = msg;
  document.getElementById('toast-container').appendChild(el);
  setTimeout(() => { el.style.opacity = '0'; setTimeout(() => el.remove(), 300); }, 3200);
}

</script>
<?php require __DIR__ . '/partials/footer.php'; ?>
