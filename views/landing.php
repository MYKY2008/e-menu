<!DOCTYPE html>
<html lang="sk">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>GastroLink QR — Digitálny rozcestník pre váš podnik</title>
<script src="https://cdn.tailwindcss.com"></script>
<style>
  .hero-gradient { background: linear-gradient(135deg, #1e3a5f 0%, #2d6a4f 100%); }
  .card { transition: transform .2s, box-shadow .2s; }
  .card:hover { transform: translateY(-4px); box-shadow: 0 20px 40px rgba(0,0,0,.12); }
</style>
</head>
<body class="bg-gray-50 text-gray-800">

<!-- Nav -->
<nav class="bg-white shadow-sm sticky top-0 z-50">
  <div class="max-w-6xl mx-auto px-4 py-3 flex items-center justify-between">
    <span class="font-bold text-xl text-indigo-700">GastroLink <span class="text-emerald-600">QR</span></span>
    <div class="flex gap-3">
      <a href="<?= url('login') ?>" class="px-4 py-2 rounded-lg border border-indigo-600 text-indigo-600 hover:bg-indigo-50 text-sm font-medium transition">Prihlásiť sa</a>
      <a href="<?= url('register') ?>" class="px-4 py-2 rounded-lg bg-indigo-600 text-white hover:bg-indigo-700 text-sm font-medium transition">Zadarmo začať</a>
    </div>
  </div>
</nav>

<!-- Hero -->
<section class="hero-gradient text-white py-24 px-4">
  <div class="max-w-4xl mx-auto text-center">
    <h1 class="text-4xl md:text-5xl font-extrabold mb-5 leading-tight">
      Jeden QR kód.<br>Všetko, čo hosť potrebuje.
    </h1>
    <p class="text-lg md:text-xl text-white/80 mb-8 max-w-2xl mx-auto">
      Vytvorte pekný digitálny rozcestník pre váš podnik za menej ako 2 minúty. Menu, Google recenzie, Instagram — na jednom mieste.
    </p>
    <a href="<?= url('register') ?>" class="inline-block px-8 py-4 bg-emerald-500 hover:bg-emerald-400 text-white font-bold rounded-xl text-lg shadow-lg transition">
      Vytvoriť zadarmo &rarr;
    </a>
    <p class="mt-4 text-white/60 text-sm">Bez kreditnej karty. Bez záväzkov.</p>
  </div>
</section>

<!-- How it works -->
<section class="py-20 px-4 bg-white">
  <div class="max-w-5xl mx-auto">
    <h2 class="text-3xl font-bold text-center mb-12">Ako to funguje?</h2>
    <div class="grid md:grid-cols-3 gap-8">
      <?php
      $steps = [
        ['1', 'Zaregistrujte sa', 'Stačí e-mail a heslo. Žiadne platobné údaje.', '📝'],
        ['2', 'Nastavte podnik', 'Pridajte meno, farby, logo a vaše odkazy jedným formulárom.', '⚙️'],
        ['3', 'Zdieľajte QR kód', 'Stiahnite QR kód a vytlačte ho na stôl, vitráž alebo servirovací listy.', '🖨️'],
      ];
      foreach ($steps as [$n, $title, $desc, $icon]):
      ?>
      <div class="card bg-gray-50 rounded-2xl p-8 text-center">
        <div class="text-4xl mb-4"><?= $icon ?></div>
        <div class="w-8 h-8 bg-indigo-600 text-white rounded-full flex items-center justify-center font-bold text-sm mx-auto mb-4"><?= $n ?></div>
        <h3 class="font-bold text-lg mb-2"><?= $title ?></h3>
        <p class="text-gray-500 text-sm"><?= $desc ?></p>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- Features -->
<section class="py-20 px-4 bg-gray-50">
  <div class="max-w-5xl mx-auto">
    <h2 class="text-3xl font-bold text-center mb-12">Čo dostanete?</h2>
    <div class="grid md:grid-cols-2 gap-6">
      <?php
      $features = [
        ['🔗', 'Vlastná URL adresa', 'Vaša stránka bude dostupná na peknej, zapamätateľnej adrese (napr. /r/nazov-podniku).'],
        ['🎨', 'Vlastné farby a logo', 'Prispôsobte vzhľad vašej prevádzky — vyberte farbu a nahrajte logo.'],
        ['📱', 'Mobilné QR kódy', 'Generujte a sťahujte QR kódy priamo z panela v PNG formáte.'],
        ['⭐', 'Google & Instagram', 'Hosť s jedným kliknutím prejde na Google recenzie alebo Instagram profil.'],
        ['🔒', 'Bezpečnosť', 'CSRF ochrana, bcrypt heslá, prepared statements — vaše dáta sú chránené.'],
        ['📊', 'Admin panel', 'Pre agentúry a správcov: spravujte viacero podnikov z jedného miesta.'],
      ];
      foreach ($features as [$icon, $title, $desc]):
      ?>
      <div class="card bg-white rounded-2xl p-6 flex gap-4 shadow-sm">
        <span class="text-3xl flex-shrink-0"><?= $icon ?></span>
        <div>
          <h3 class="font-bold mb-1"><?= $title ?></h3>
          <p class="text-gray-500 text-sm"><?= $desc ?></p>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- CTA -->
<section class="py-20 px-4 hero-gradient text-white text-center">
  <h2 class="text-3xl font-bold mb-4">Pripravení začať?</h2>
  <p class="text-white/80 mb-8">Prvá prevádzka je vždy zadarmo.</p>
  <a href="<?= url('register') ?>" class="inline-block px-8 py-4 bg-white text-indigo-700 font-bold rounded-xl text-lg shadow-lg hover:bg-indigo-50 transition">
    Vytvoriť účet zadarmo
  </a>
</section>

<!-- Footer -->
<footer class="bg-gray-900 text-gray-400 text-center py-6 text-sm">
  &copy; <?= date('Y') ?> GastroLink QR &mdash; Digitálny rozcestník pre gastronómiu
</footer>

</body>
</html>
