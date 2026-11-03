<?php
require_once __DIR__ . '/includes/auth.php';
$adminId = admin_require($conn);
$status = in_array($_GET['status'] ?? '', ['pending', 'approved', 'rejected', 'cancelled'], true) ? $_GET['status'] : '';
$sql = "SELECT v.id, v.preferred_date, v.preferred_time, v.status, v.created_at, p.name AS pname, s.fullname AS student, l.fullname AS landlord
        FROM viewing_requests v JOIN properties p ON p.id = v.property_id JOIN students s ON s.id = v.student_id LEFT JOIN landlords l ON l.id = p.landlord_id";
if ($status !== '') $sql .= " WHERE v.status = ?";
$sql .= " ORDER BY v.created_at DESC LIMIT 300";
$st = $conn->prepare($sql);
if ($status !== '') $st->bind_param('s', $status);
$st->execute(); $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Viewing requests'; $active = 'viewings';
require __DIR__ . '/includes/header.php';
?>
<h1>Viewing requests</h1>
<div class="filter-tabs">
  <a href="viewings.php" class="<?= $status === '' ? 'active' : '' ?>">All</a>
  <?php foreach (['pending', 'approved', 'rejected', 'cancelled'] as $s): ?><a href="viewings.php?status=<?= $s ?>" class="<?= $status === $s ? 'active' : '' ?>"><?= ucfirst($s) ?></a><?php endforeach; ?>
</div>
<?php if (!$rows): ?><div class="empty"><p>No viewing requests found.</p></div><?php else: ?>
<div class="table-wrap"><table><thead><tr><th>Student</th><th>Property</th><th>Landlord</th><th>Requested for</th><th>Status</th></tr></thead><tbody>
<?php foreach ($rows as $r): ?>
  <tr><td><?= e($r['student']) ?></td><td><?= e($r['pname']) ?></td><td><?= e($r['landlord'] ?? '-') ?></td>
  <td><?= e(date('M j, Y', strtotime($r['preferred_date']))) ?> <?= e(date('g:i A', strtotime($r['preferred_time']))) ?></td>
  <td><span class="status status-<?= e($r['status']) ?>"><?= e(ucfirst($r['status'])) ?></span></td></tr>
<?php endforeach; ?></tbody></table></div>
<p class="muted">Read-only. Landlords approve or reject requests for their own properties.</p>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
