<?php
// Shown ONCE right after a successful login. Refreshing or opening it later just goes to the dashboard.
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/ui.php';
$role = $_SESSION['role'] ?? '';
if (empty($_SESSION['user_id']) || !in_array($role, ['student', 'landlord'], true)) { header('Location: login.html?err=login_required'); exit; }
$dash = $role === 'landlord' ? 'landlord-dashboard.php' : 'student-dashboard.php';
if (empty($_SESSION['show_welcome'])) { header('Location: ' . $dash); exit; }
unset($_SESSION['show_welcome']);

$first = explode(' ', trim($_SESSION['name']))[0] ?: 'there';
$copy = $role === 'landlord'
    ? ['Welcome Back!', 'Manage your rental properties with ease.']
    : ['Welcome Home!', "Let's find a comfortable place near your school."];
?><!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Welcome - HomeRent</title>
<link rel="stylesheet" href="assets/css/student.css"><link rel="stylesheet" href="assets/css/motion.css"><link rel="stylesheet" href="assets/css/auth-pages.css">
<noscript><meta http-equiv="refresh" content="4;url=<?= e($dash) ?>"></noscript>
</head><body class="welcome-body">
<div class="auth-deco" aria-hidden="true"><span class="cloud c1"></span><span class="cloud c2"></span><span class="cloud c3"></span>
  <span class="spark s1"></span><span class="spark s2"></span><span class="spark s3"></span></div>
<main class="welcome-stage" role="status" aria-live="polite">
  <div class="welcome-house" aria-hidden="true">
    <svg viewBox="0 0 120 110" width="150" height="138" fill="none" stroke="#fff" stroke-width="5" stroke-linecap="round" stroke-linejoin="round">
      <path d="M10 55 60 12l50 43"/><path d="M22 48v52h76V48"/><path class="door" d="M48 100V70h24v30" stroke="#7cc0ff"/><circle class="win" cx="86" cy="64" r="6" stroke="#7cc0ff"/>
    </svg>
  </div>
  <h1><?= e($copy[0]) ?></h1>
  <p class="hello">Hello, <?= e($first) ?>!</p>
  <p class="line"><?= e($copy[1]) ?></p>
  <div class="welcome-bar" aria-hidden="true"><span></span></div>
  <a class="btn btn-ghost" id="skip" href="<?= e($dash) ?>">Skip</a>
</main>
<script>setTimeout(function () { location.replace(<?= json_encode($dash) ?>); }, 3000);</script>
</body></html>
