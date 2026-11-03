<?php
require_once __DIR__ . '/includes/auth.php';
require_role('landlord');
$lid = (int)$_SESSION['user_id'];

function one(mysqli $conn, string $sql, int $lid): int {
    $st = $conn->prepare($sql); $st->bind_param('i', $lid); $st->execute();
    $n = (int)$st->get_result()->fetch_row()[0]; $st->close(); return $n;
}
$P = "FROM properties WHERE landlord_id = ? AND is_archived = 0";
$total      = one($conn, "SELECT COUNT(*) $P", $lid);
$active     = one($conn, "SELECT COUNT(*) $P AND approval_status='approved' AND availability='available'", $lid);
$awaiting   = one($conn, "SELECT COUNT(*) $P AND approval_status='pending'", $lid);
$rejected   = one($conn, "SELECT COUNT(*) $P AND approval_status='rejected'", $lid);
$unavail    = one($conn, "SELECT COUNT(*) $P AND availability='unavailable'", $lid);
$rented     = one($conn, "SELECT COUNT(*) $P AND availability='rented'", $lid);
$V = "FROM viewing_requests v JOIN properties p ON p.id = v.property_id WHERE p.landlord_id = ?";
$vPend = one($conn, "SELECT COUNT(*) $V AND v.status='pending'", $lid);
$vApp  = one($conn, "SELECT COUNT(*) $V AND v.status='approved'", $lid);
$vRej  = one($conn, "SELECT COUNT(*) $V AND v.status='rejected'", $lid);
require_once __DIR__ . '/includes/messages_lib.php';
$iOpen = msg_unread_total($conn, 'landlord', $lid);

$st = $conn->prepare("SELECT v.id, v.preferred_date, v.preferred_time, v.status, p.name AS pname, s.fullname
    FROM viewing_requests v JOIN properties p ON p.id = v.property_id JOIN students s ON s.id = v.student_id
    WHERE p.landlord_id = ? ORDER BY v.created_at DESC LIMIT 5");
$st->bind_param('i', $lid); $st->execute();
$recentReq = $st->get_result()->fetch_all(MYSQLI_ASSOC);

$st = $conn->prepare("SELECT c.id, p.name AS pname, s.fullname, c.last_message_at,
    (SELECT m.body FROM messages m WHERE m.conversation_id = c.id ORDER BY m.id DESC LIMIT 1) AS last_body,
    (SELECT COUNT(*) FROM messages m WHERE m.conversation_id = c.id AND m.sender_role = 'student' AND m.is_read = 0) AS unread
    FROM conversations c JOIN properties p ON p.id = c.property_id JOIN students s ON s.id = c.student_id
    WHERE c.landlord_id = ? ORDER BY c.last_message_at DESC LIMIT 5");
$st->bind_param('i', $lid); $st->execute();
$recentInq = $st->get_result()->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Dashboard'; $active = 'dashboard';
require __DIR__ . '/includes/landlord_header.php';
?>
<section class="hero">
  <h1>Welcome, <?= e(explode(' ', trim($_SESSION['name']))[0]) ?></h1>
  <p>Manage your listings and respond to students.</p>
  <p style="margin-top:14px"><a class="btn btn-ghost" href="add-property.php">Add a property</a></p>
</section>

<section class="stats">
  <a class="stat" href="manage-properties.php"><strong><?= $total ?></strong><span>Properties</span></a>
  <a class="stat" href="manage-properties.php"><strong><?= $active ?></strong><span>Live listings</span></a>
  <a class="stat" href="manage-properties.php"><strong><?= $awaiting ?></strong><span>Awaiting approval</span></a>
  <a class="stat" href="landlord-messages.php"><strong><?= $iOpen ?></strong><span>Unread messages</span></a>
</section>
<section class="stats">
  <a class="stat" href="landlord-requests.php?status=pending"><strong><?= $vPend ?></strong><span>Pending viewings</span></a>
  <a class="stat" href="landlord-requests.php?status=approved"><strong><?= $vApp ?></strong><span>Approved viewings</span></a>
  <a class="stat" href="landlord-requests.php?status=rejected"><strong><?= $vRej ?></strong><span>Rejected viewings</span></a>
  <a class="stat" href="manage-properties.php"><strong><?= $rejected ?></strong><span>Listings rejected</span></a>
</section>
<p class="muted">Availability: <?= $active ?> available &middot; <?= $unavail ?> unavailable &middot; <?= $rented ?> rented</p>

<h2 class="section-title">Recent viewing requests</h2>
<?php if (!$recentReq): ?><div class="empty"><p>No viewing requests yet.</p></div><?php else: ?>
<div class="table-wrap"><table><thead><tr><th>Student</th><th>Property</th><th>Date</th><th>Status</th></tr></thead><tbody>
<?php foreach ($recentReq as $r): ?>
  <tr><td><?= e($r['fullname']) ?></td><td><?= e($r['pname']) ?></td>
  <td><?= e(date('M j, Y', strtotime($r['preferred_date']))) ?> <?= e(date('g:i A', strtotime($r['preferred_time']))) ?></td>
  <td><span class="status status-<?= e($r['status']) ?>"><?= e(ucfirst($r['status'])) ?></span></td></tr>
<?php endforeach; ?></tbody></table></div>
<p><a href="landlord-requests.php">See all requests</a></p>
<?php endif; ?>

<h2 class="section-title">Recent messages</h2>
<?php if (!$recentInq): ?><div class="empty"><p>No messages yet.</p></div><?php else: ?>
<div class="table-wrap"><table><thead><tr><th>Student</th><th>Property</th><th>Latest message</th><th></th></tr></thead><tbody>
<?php foreach ($recentInq as $r): ?>
  <tr><td><?= e($r['fullname']) ?></td><td><?= e($r['pname']) ?></td>
  <td><?= e(mb_strimwidth((string)$r['last_body'], 0, 70, '...')) ?></td>
  <td><a href="landlord-messages.php?c=<?= (int)$r['id'] ?>"><?= $r['unread'] ? '<span class="nav-badge">' . (int)$r['unread'] . ' new</span>' : 'Open' ?></a></td></tr>
<?php endforeach; ?></tbody></table></div>
<p><a href="landlord-messages.php">Open messages</a></p>
<?php endif; ?>
<?php require __DIR__ . '/includes/landlord_footer.php'; ?>
