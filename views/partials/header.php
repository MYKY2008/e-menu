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
<?php if (!empty($extraHead)) echo $extraHead; ?>
</head>
