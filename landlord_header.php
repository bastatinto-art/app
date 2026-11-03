<?php
require_once __DIR__ . '/messages_lib.php';
require_once __DIR__ . '/ui.php';
$pageTitle = $pageTitle ?? 'HomeRent';
$active    = $active ?? '';
$extraHead = $extraHead ?? '';
$navUnread = msg_unread_total($conn, 'landlord', (int)$_SESSION['user_id']);
$nav = [
    'dashboard'  => ['landlord-dashboard.php', 'Dashboard'],
    'properties' => ['manage-properties.php', 'My properties'],
    'requests'   => ['landlord-requests.php', 'Viewing requests'],
    'messages'   => ['landlord-messages.php', 'Messages'],
    'profile'    => ['landlord-profile.php', 'Profile'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle) ?> - HomeRent Landlord</title>
<link rel="stylesheet" href="assets/css/student.css">
<link rel="stylesheet" href="assets/css/motion.css">
<link rel="stylesheet" href="assets/css/landlord.css">
<?= $extraHead ?>
</head>
<body>
<header class="topbar">
  <a class="brand" href="landlord-dashboard.php"><?= ui_logo() ?>Home<span>Rent</span></a>
  <button class="nav-toggle" type="button" aria-label="Menu" aria-expanded="false">Menu</button>
  <nav class="nav" id="nav">
    <?php foreach ($nav as $key => [$href, $label]): ?>
      <a href="<?= $href ?>" class="<?= $key === $active ? 'active' : '' ?>"><?= ui_icon($key) ?><span><?= e($label) ?></span><?php if ($key === 'messages'): ?><span class="nav-badge" id="navMsgBadge" <?= $navUnread ? '' : 'hidden' ?>><?= $navUnread > 99 ? '99+' : $navUnread ?></span><?php endif; ?></a>
    <?php endforeach; ?>
    <?= ui_user_menu('landlord', 'landlord-profile.php') ?>
  </nav>
</header>
<main class="container">
<?php foreach (flash_pull() as [$type, $msg]): ?>
  <div class="alert alert-<?= e($type) ?>" role="status"><?= e($msg) ?></div>
<?php endforeach; ?>
