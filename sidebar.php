<?php
$links = [
    'dashboard'  => ['dashboard.php',          'Dashboard',           'dashboard'],
    'accounts'   => ['accounts.php',           'Accounts',            'users'],
    'properties' => ['properties.php',         'Properties',          'properties'],
    'approvals'  => ['property-approvals.php', 'Property Approvals',  'check'],
    'viewings'   => ['viewings.php',           'Viewing Requests',    'requests'],
    'categories' => ['categories.php',         'Category Images',     'image'],
    'logo'       => ['logo-management.php',    'Logo Management',     'home'],
    'logs'       => ['activity-logs.php',      'Activity Logs',       'list'],
    'profile'    => ['profile.php',            'My Profile',          'profile'],
];
?>
<aside class="admin-side" id="adminSide">
  <nav aria-label="Admin navigation">
    <?php foreach ($links as $key => [$href, $label, $icon]): ?>
      <a href="<?= $href ?>" class="<?= $key === $active ? 'active' : '' ?>"><?= ui_icon($icon) ?><span><?= e($label) ?></span></a>
    <?php endforeach; ?>
    <a href="logout.php" class="side-logout"><?= ui_icon('shield') ?><span>Log out</span></a>
  </nav>
</aside>
