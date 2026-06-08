<!DOCTYPE html>
<html lang="sk">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin — GastroLink QR</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">

<?php
$db = getDB();

// Stats
$totalUsers  = (int)$db->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalVenues = (int)$db->query("SELECT COUNT(*) FROM venues")->fetchColumn();

// All users with venue count
$users = $db->query("
    SELECT u.id, u.username, u.role, u.venue_limit, u.created_at,
           COUNT(v.slug) AS venue_count
    FROM users u
    LEFT JOIN venues v ON v.user_id = u.id
    GROUP BY u.id
    ORDER BY u.created_at DESC
")->fetchAll();

// All venues with owner
$venues = $db->query("
    SELECT v.slug, v.name, v.color, v.created_at, u.username AS owner
    FROM venues v
    JOIN users u ON u.id = v.user_id
    ORDER BY v.created_at DESC
")->fetchAll();

$flash = getFlash();
?>

<!-- Nav -->
<nav class="bg-white shadow-sm px-6 py-3 flex items-center justify-between">
  <span class="font-bold text-indigo-700 text-lg">GastroLink <span class="text-emerald-600">QR</span> <span class="text-xs bg-indigo-100 text-indigo-600 px-2 py-0.5 rounded-full ml-1">Admin</span></span>
  <div class="flex items-center gap-4 text-sm">
    <a href="<?= url('dashboard') ?>" class="text-indigo-600 hover:underline">Môj dashboard</a>
    <a href="<?= url('logout') ?>" class="text-red-500 hover:underline">Odhlásiť</a>
  </div>
</nav>

<!-- Toast container -->
<div id="toast-container" class="fixed top-5 right-5 z-50 flex flex-col gap-2 pointer-events-none"></div>

<div class="max-w-7xl mx-auto p-6 space-y-6">

  <!-- Flash -->
  <?php if ($flash): ?>
  <div class="px-4 py-3 rounded-lg text-sm <?= $flash['type']==='success'?'bg-emerald-100 text-emerald-800':'bg-red-100 text-red-800' ?>">
    <?= e($flash['msg']) ?>
  </div>
  <?php endif; ?>

  <!-- Stats -->
  <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
    <?php
    $stats = [
      ['Používatelia', $totalUsers, 'bg-indigo-50 text-indigo-700'],
      ['Prevádzky',    $totalVenues, 'bg-emerald-50 text-emerald-700'],
    ];
    foreach ($stats as [$label, $val, $cls]):
    ?>
    <div class="<?= $cls ?> rounded-2xl p-5 shadow-sm">
      <p class="text-sm font-medium opacity-70"><?= $label ?></p>
      <p class="text-3xl font-extrabold mt-1"><?= $val ?></p>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- ── Users table ── -->
  <div class="bg-white rounded-2xl shadow-sm overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
      <h2 class="font-bold text-gray-700">Používatelia (<?= count($users) ?>)</h2>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wide">
          <tr>
            <th class="px-6 py-3 text-left">E-mail</th>
            <th class="px-6 py-3 text-left">Rola</th>
            <th class="px-6 py-3 text-left">Prevádzky</th>
            <th class="px-6 py-3 text-left">Limit</th>
            <th class="px-6 py-3 text-left">Registrovaný</th>
            <th class="px-6 py-3 text-right">Akcie</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
        <?php foreach ($users as $u): ?>
          <?php $isSelf = ((int)$u['id'] === (int)$_SESSION['user_id']); ?>
          <tr class="hover:bg-gray-50 transition" id="user-row-<?= $u['id'] ?>">
            <td class="px-6 py-4 font-medium">
              <?= e($u['username']) ?>
              <?php if ($isSelf): ?><span class="ml-1 text-xs text-indigo-400">(vy)</span><?php endif; ?>
            </td>
            <td class="px-6 py-4">
              <span class="px-2 py-0.5 rounded-full text-xs font-semibold
                <?= $u['role']==='admin' ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-100 text-gray-600' ?>">
                <?= $u['role'] ?>
              </span>
            </td>
            <td class="px-6 py-4 text-gray-600"><?= $u['venue_count'] ?></td>
            <td class="px-6 py-4">
              <input type="number" min="0" max="9999"
                value="<?= (int)$u['venue_limit'] ?>"
                class="w-20 border border-gray-300 rounded px-2 py-1 text-sm focus:ring-2 focus:ring-indigo-300 outline-none"
                onchange="updateLimit(<?= $u['id'] ?>, this.value)">
            </td>
            <td class="px-6 py-4 text-gray-400 text-xs"><?= e(substr($u['created_at'],0,10)) ?></td>
            <td class="px-6 py-4 text-right">
              <div class="flex justify-end gap-2">
                <button onclick="resetPassword(<?= $u['id'] ?>,'<?= e($u['username']) ?>')"
                  title="Reset hesla"
                  class="px-3 py-1.5 text-xs bg-yellow-100 hover:bg-yellow-200 text-yellow-800 rounded-lg font-medium transition">
                  Heslo
                </button>
                <?php if (!$isSelf): ?>
                <button onclick="deleteUser(<?= $u['id'] ?>,'<?= e($u['username']) ?>')"
                  title="Zmazať účet"
                  class="px-3 py-1.5 text-xs bg-red-100 hover:bg-red-200 text-red-700 rounded-lg font-medium transition">
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

  <!-- ── Venues table ── -->
  <div class="bg-white rounded-2xl shadow-sm overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-100">
      <h2 class="font-bold text-gray-700">Všetky prevádzky (<?= count($venues) ?>)</h2>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wide">
          <tr>
            <th class="px-6 py-3 text-left">Slug</th>
            <th class="px-6 py-3 text-left">Názov</th>
            <th class="px-6 py-3 text-left">Vlastník</th>
            <th class="px-6 py-3 text-left">Vytvorená</th>
            <th class="px-6 py-3 text-right">Akcie</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
        <?php foreach ($venues as $v):
          $c = resolveColor($v['color']);
        ?>
          <tr class="hover:bg-gray-50 transition" id="venue-row-<?= e($v['slug']) ?>">
            <td class="px-6 py-4">
              <span class="flex items-center gap-2">
                <span class="w-3 h-3 rounded-full" style="background:<?= e($c['hex']) ?>"></span>
                <a href="<?= url('r/' . $v['slug']) ?>" target="_blank"
                  class="text-indigo-600 hover:underline font-mono text-xs">
                  /r/<?= e($v['slug']) ?>
                </a>
              </span>
            </td>
            <td class="px-6 py-4 font-medium"><?= e($v['name']) ?></td>
            <td class="px-6 py-4 text-gray-500"><?= e($v['owner']) ?></td>
            <td class="px-6 py-4 text-gray-400 text-xs"><?= e(substr($v['created_at'],0,10)) ?></td>
            <td class="px-6 py-4 text-right">
              <button onclick="adminDeleteVenue('<?= e($v['slug']) ?>','<?= e($v['name']) ?>')"
                class="px-3 py-1.5 text-xs bg-red-100 hover:bg-red-200 text-red-700 rounded-lg font-medium transition">
                Zmazať
              </button>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<!-- Password reset modal -->
<div id="pw-modal" class="hidden fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4">
  <div class="bg-white rounded-2xl shadow-xl p-6 w-full max-w-sm">
    <h3 class="font-bold text-lg mb-1">Reset hesla</h3>
    <p class="text-sm text-gray-500 mb-4">Účet: <span id="pw-username" class="font-medium text-gray-700"></span></p>
    <input id="pw-new" type="password" placeholder="Nové heslo (min. 8 znakov)" minlength="8"
      class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400 mb-4">
    <div class="flex gap-3">
      <button onclick="submitReset()"
        class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2 rounded-lg text-sm transition">
        Nastaviť heslo
      </button>
      <button onclick="closeModal()"
        class="px-4 bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold py-2 rounded-lg text-sm transition">
        Zrušiť
      </button>
    </div>
  </div>
</div>

<script>
const CSRF    = <?= json_encode(csrfToken()) ?>;
const API_URL = <?= json_encode(url('api/admin_actions.php')) ?>;

// ── API call helper ───────────────────────────────────────────
async function adminApi(payload) {
  const res = await fetch(API_URL, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ ...payload, csrf: CSRF })
  });
  return res.json();
}

// ── Update limit ──────────────────────────────────────────────
async function updateLimit(userId, limit) {
  try {
    const data = await adminApi({ action: 'update_limit', user_id: userId, venue_limit: parseInt(limit) });
    if (data.ok) toast('Limit aktualizovaný.', 'success');
    else toast(data.error || 'Chyba.', 'error');
  } catch (e) {
    toast('Sieťová chyba.', 'error');
  }
}

// ── Delete user ───────────────────────────────────────────────
async function deleteUser(userId, username) {
  if (!confirm(`Naozaj zmazať účet "${username}" a všetky jeho prevádzky?`)) return;
  try {
    const data = await adminApi({ action: 'delete_user', user_id: userId });
    if (data.ok) {
      toast('Účet zmazaný.', 'success');
      document.getElementById('user-row-' + userId)?.remove();
    } else {
      toast(data.error || 'Chyba.', 'error');
    }
  } catch (e) {
    toast('Sieťová chyba.', 'error');
  }
}

// ── Delete venue (admin) ──────────────────────────────────────
async function adminDeleteVenue(slug, name) {
  if (!confirm(`Naozaj zmazať prevádzku "${name}"?`)) return;
  try {
    const data = await adminApi({ action: 'delete_venue', slug });
    if (data.ok) {
      toast('Prevádzka zmazaná.', 'success');
      document.getElementById('venue-row-' + slug)?.remove();
    } else {
      toast(data.error || 'Chyba.', 'error');
    }
  } catch (e) {
    toast('Sieťová chyba.', 'error');
  }
}

// ── Password reset modal ──────────────────────────────────────
let _pwUserId = null;
function resetPassword(userId, username) {
  _pwUserId = userId;
  document.getElementById('pw-username').textContent = username;
  document.getElementById('pw-new').value = '';
  document.getElementById('pw-modal').classList.remove('hidden');
}
function closeModal() {
  document.getElementById('pw-modal').classList.add('hidden');
  _pwUserId = null;
}
async function submitReset() {
  const pass = document.getElementById('pw-new').value;
  if (pass.length < 8) { toast('Heslo musí mať aspoň 8 znakov.', 'error'); return; }
  try {
    const data = await adminApi({ action: 'reset_password', user_id: _pwUserId, password: pass });
    if (data.ok) { toast('Heslo zmenené.', 'success'); closeModal(); }
    else toast(data.error || 'Chyba.', 'error');
  } catch (e) {
    toast('Sieťová chyba.', 'error');
  }
}

// Close modal on backdrop click
document.getElementById('pw-modal').addEventListener('click', function(e) {
  if (e.target === this) closeModal();
});

// ── Toast ─────────────────────────────────────────────────────
function toast(msg, type = 'info') {
  const el = document.createElement('div');
  const bg  = type === 'success' ? 'bg-emerald-600' : type === 'error' ? 'bg-red-600' : 'bg-gray-700';
  el.className = `pointer-events-auto px-5 py-3 rounded-xl text-white text-sm font-medium shadow-lg ${bg}`;
  el.textContent = msg;
  document.getElementById('toast-container').appendChild(el);
  setTimeout(() => el.remove(), 3500);
}
</script>
</body>
</html>
