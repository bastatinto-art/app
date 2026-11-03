<?php
// Usage: $adminId = admin_require($conn); $pageTitle=''; $active=''; require 'includes/header.php';
$GLOBALS['ASSET_BASE'] = '../';
$pageTitle = $pageTitle ?? 'Admin';
$active    = $active ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">
<title><?= e($pageTitle) ?> - HomeRent Admin</title>
<link rel="stylesheet" href="../assets/css/student.css">
<link rel="stylesheet" href="../assets/css/admin.css">
<link rel="stylesheet" href="../assets/css/motion.css">
</head>
<body>
<header class="topbar">
  <a class="brand" href="dashboard.php"><?= ui_logo() ?>Home<span>Rent</span> <small class="admin-tag">Admin</small></a>
  <button class="nav-toggle side-toggle" type="button" aria-label="Menu" aria-expanded="false">Menu</button>
  <div class="admin-topright"><?= ui_user_menu('admin', 'profile.php') ?></div>
</header>
<div class="admin-shell">
<?php require __DIR__ . '/sidebar.php'; ?>
<main class="admin-main">
<?php foreach (flash_pull() as [$type, $msg]): ?>
  <div class="alert alert-<?= e($type) ?>" role="status"><?= e($msg) ?></div>
<?php endforeach; ?>
