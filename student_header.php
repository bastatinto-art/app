<?php
// Usage: $pageTitle = '...'; $active = 'dashboard'; [$extraHead = '<link ...>']; require 'includes/student_header.php';
require_once __DIR__ . '/messages_lib.php';
require_once __DIR__ . '/ui.php';
$pageTitle = $pageTitle ?? 'HomeRent';
$active    = $active ?? '';
$extraHead = $extraHead ?? '';
$navUnread = msg_unread_total($conn, 'student', (int)$_SESSION['user_id']);
$nav = [
    'dashboard' => ['student-dashboard.php', 'Dashboard'],
    'search'    => ['student-properties.php', 'Find a place'],
    'map'       => ['student-map.php', 'Map'],
    'favorites' => ['favorites.php', 'Favorites'],
    'requests'  => ['viewing-requests.php', 'Viewings'],
    'messages'  => ['student-messages.php', 'Messages'],
    'profile'   => ['student-profile.php', 'Profile'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle) ?> - HomeRent</title>
<link rel="stylesheet" href="assets/css/student.css">
<link rel="stylesheet" href="assets/css/motion.css">
<?= $extraHead ?>
</head>
<body>
<header class="topbar">
  <a class="brand" href="student-dashboard.php"><?= ui_logo() ?>Home<span>Rent</span></a>
  <button class="nav-toggle" type="button" aria-label="Menu" aria-expanded="false">Menu</button>
  <nav class="nav" id="nav">
    <?php foreach ($nav as $key => [$href, $label]): ?>
      <a href="<?= $href ?>" class="<?= $key === $active ? 'active' : '' ?>"><?= ui_icon($key) ?><span><?= e($label) ?></span><?php if ($key === 'messages'): ?><span class="nav-badge" id="navMsgBadge" <?= $navUnread ? '' : 'hidden' ?>><?= $navUnread > 99 ? '99+' : $navUnread ?></span><?php endif; ?></a>
    <?php endforeach; ?>
    <?= ui_user_menu('student', 'student-profile.php') ?>
  </nav>
</header>
<main class="container">
<?php foreach (flash_pull() as [$type, $msg]): ?>
  <div class="alert alert-<?= e($type) ?>" role="status"><?= e($msg) ?></div>
<?php endforeach; ?>
