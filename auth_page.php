<?php
// Shared layout for the pages shown BEFORE login (verify email, forgot/reset password, Google steps).
require_once __DIR__ . '/ui.php';
function auth_page_start(string $title, string $heading, string $sub = ''): void {
    ?><!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($title) ?> - HomeRent</title>
<link rel="stylesheet" href="assets/css/student.css"><link rel="stylesheet" href="assets/css/motion.css"><link rel="stylesheet" href="assets/css/auth-pages.css">
</head><body class="auth-page"><div class="auth-deco" aria-hidden="true"><span class="cloud c1"></span><span class="cloud c2"></span><span class="float-house"><?= ui_icon('home') ?></span></div>
<main class="auth-wrap"><div class="card auth-card enter">
  <div class="auth-brand"><?= ui_logo() ?><span>Home<b>Rent</b></span></div>
  <h1><?= e($heading) ?></h1><?php if ($sub): ?><p class="muted"><?= e($sub) ?></p><?php endif; ?>
  <?php foreach (flash_pull() as [$type, $msg]): ?><div class="alert alert-<?= e($type) ?>" role="status"><?= e($msg) ?></div><?php endforeach; ?>
<?php }
function auth_page_end(): void { ?></div></main>
<script>document.querySelectorAll('form').forEach(function(f){f.addEventListener('submit',function(){var b=f.querySelector('button[type=submit]:not([data-nobusy])');if(b)setTimeout(function(){b.disabled=true;},0);});});</script>
</body></html><?php }
