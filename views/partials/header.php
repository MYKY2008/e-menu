<!DOCTYPE html>
<html lang="sk" class="">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<link rel="icon" href="/favicon.ico">
<?php if (!empty($robots)): ?><meta name="robots" content="<?= e($robots) ?>">
<?php endif; ?>
<?php if (!empty($themeColor)): ?><meta name="theme-color" content="<?= e($themeColor) ?>">
<?php endif; ?>
<?php
$_pt = $title ?? 'GastroLink QR';
if (!str_contains($_pt, 'GastroLink QR')) $_pt .= ' | GastroLink QR';
?>
<title><?= e($_pt) ?></title>
<!-- Anti-flash dark mode -->
<script>(function(){if(localStorage.getItem('gl-dark')==='1')document.documentElement.classList.add('dark')})();</script>
<!-- Inter font -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= asset('assets/css/style.css') ?>">
<style>
*{-webkit-tap-highlight-color:transparent}
body{overscroll-behavior-y:none}
::-webkit-scrollbar{width:5px;height:5px}
::-webkit-scrollbar-track{background:transparent}
::-webkit-scrollbar-thumb{background:#cbd5e1;border-radius:9999px}
::-webkit-scrollbar-thumb:hover{background:#94a3b8}
html.dark ::-webkit-scrollbar-thumb{background:#334155}
html.dark ::-webkit-scrollbar-thumb:hover{background:#475569}
*{scrollbar-width:thin;scrollbar-color:#cbd5e1 transparent}
html.dark *{scrollbar-color:#334155 transparent}
.no-scrollbar{-ms-overflow-style:none;scrollbar-width:none}
.no-scrollbar::-webkit-scrollbar{display:none}
</style>
<?php if (!empty($extraHead)) echo $extraHead; ?>
</head>
