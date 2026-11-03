<?php
require_once __DIR__ . '/includes/auth.php';
$adminId = admin_require($conn);
$rows = $conn->query("SELECT l.action, l.target_type, l.target_id, l.details, l.ip, l.created_at, a.fullname
    FROM admin_activity_log l LEFT JOIN admins a ON a.id = l.admin_id ORDER BY l.id DESC LIMIT 300")->fetch_all(MYSQLI_ASSOC);
$pageTitle = 'Activity logs'; $active = 'logs';
require __DIR__ . '/includes/header.php';
?>
<h1>Activity logs</h1>
<?php if (!$rows): ?><div class="empty"><p>No activity recorded yet.</p></div><?php else: ?>
<div class="table-wrap"><table><thead><tr><th>When</th><th>Admin</th><th>Action</th><th>Target</th><th>Details</th><th>IP</th></tr></thead><tbody>
<?php foreach ($rows as $r): ?>
  <tr><td><?= e(date('M j, Y g:i A', strtotime($r['created_at']))) ?></td><td><?= e($r['fullname'] ?? 'Unknown') ?></td>
  <td><?= e(str_replace('_', ' ', $r['action'])) ?></td><td><?= e($r['target_type'] ? $r['target_type'] . ' #' . $r['target_id'] : '-') ?></td>
  <td><?= e($r['details'] ?? '') ?></td><td><?= e($r['ip']) ?></td></tr>
<?php endforeach; ?></tbody></table></div>
<p class="muted">Showing the latest 300 actions.</p>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
