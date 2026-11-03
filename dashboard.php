<?php
require_once __DIR__ . '/includes/auth.php';
$adminId = admin_require($conn);

function total(mysqli $conn, string $sql): int { return (int)$conn->query($sql)->fetch_row()[0]; }
$ns = total($conn, "SELECT COUNT(*) FROM students"); $nl = total($conn, "SELECT COUNT(*) FROM landlords");
$stats = [
    ['Total Students',       $ns,        'users'],
    ['Total Landlords',      $nl,        'users'],
    ['Total Accounts',       $ns + $nl,  'profile'],
    ['Total Properties',     total($conn, "SELECT COUNT(*) FROM properties"), 'properties'],
    ['Pending Properties',   total($conn, "SELECT COUNT(*) FROM properties WHERE approval_status='pending'"), 'requests'],
    ['Approved Properties',  total($conn, "SELECT COUNT(*) FROM properties WHERE approval_status='approved'"), 'check'],
    ['Rejected Properties',  total($conn, "SELECT COUNT(*) FROM properties WHERE approval_status='rejected'"), 'shield'],
    ['Total Viewing Requests', total($conn, "SELECT COUNT(*) FROM viewing_requests"), 'eye'],
];
$recent = $conn->query("SELECT l.action, l.target_type, l.target_id, l.details, l.created_at, a.fullname
    FROM admin_activity_log l LEFT JOIN admins a ON a.id = l.admin_id ORDER BY l.id DESC LIMIT 8")->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Dashboard'; $active = 'dashboard';
require __DIR__ . '/includes/header.php';
?>
<h1>Admin dashboard</h1>
<section class="stats">
  <?php foreach ($stats as [$label, $n, $icon]): ?>
    <div class="stat has-ico"><span class="stat-ico"><?= ui_icon($icon) ?></span><span class="stat-text"><strong><?= $n ?></strong><span><?= e($label) ?></span></span></div>
  <?php endforeach; ?>
</section>
<?php if ($stats[4][1] > 0): ?>
  <div class="alert alert-success"><?= $stats[4][1] ?> propert<?= $stats[4][1] === 1 ? 'y is' : 'ies are' ?> waiting for approval. <a href="property-approvals.php">Review now</a></div>
<?php endif; ?>
<h2 class="section-title"><?= ui_icon('list') ?>Recent activities</h2>
<?php if (!$recent): ?><div class="empty"><p>No administrative activity recorded yet.</p></div><?php else: ?>
<div class="table-wrap"><table><thead><tr><th>When</th><th>Admin</th><th>Action</th><th>Details</th></tr></thead><tbody>
<?php foreach ($recent as $r): ?>
  <tr><td><?= e(date('M j, g:i A', strtotime($r['created_at']))) ?></td><td><?= e($r['fullname'] ?? 'Unknown') ?></td>
  <td><?= e(str_replace('_', ' ', $r['action'])) ?></td><td><?= e(trim(($r['target_type'] ? $r['target_type'] . ' #' . $r['target_id'] . ' ' : '') . ($r['details'] ?? ''))) ?></td></tr>
<?php endforeach; ?></tbody></table></div>
<p><a href="activity-logs.php">View full activity log</a></p>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
