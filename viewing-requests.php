<?php
require_once __DIR__ . '/includes/auth.php';
require_role('student');
$sid = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $rid = (int)($_POST['request_id'] ?? 0);
    // Ownership enforced in the WHERE clause; only pending/approved can be cancelled
    $u = $conn->prepare("UPDATE viewing_requests SET status = 'cancelled' WHERE id = ? AND student_id = ? AND status IN ('pending','approved')");
    $u->bind_param('ii', $rid, $sid); $u->execute();
    flash_set($u->affected_rows ? 'success' : 'error', $u->affected_rows ? 'Viewing request cancelled.' : 'That request cannot be cancelled.');
    header('Location: viewing-requests.php'); exit;
}

$st = $conn->prepare("SELECT v.id, v.property_id, v.preferred_date, v.preferred_time, v.message, v.status, v.landlord_note, v.created_at,
                             p.name, p.address
                      FROM viewing_requests v JOIN properties p ON p.id = v.property_id
                      WHERE v.student_id = ? ORDER BY v.created_at DESC");
$st->bind_param('i', $sid); $st->execute();
$rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Viewing requests'; $active = 'requests';
require __DIR__ . '/includes/student_header.php';
?>
<h1>Your viewing requests</h1>
<?php if (!$rows): ?>
  <div class="empty"><p>No viewing requests yet.</p><p>Open a property and choose "Request a viewing".</p><a class="btn" href="student-properties.php">Find a place</a></div>
<?php else: ?>
  <div class="list">
  <?php foreach ($rows as $r): ?>
    <article class="card pad item">
      <div class="item-head">
        <h3><a href="property-details.php?id=<?= (int)$r['property_id'] ?>"><?= e($r['name']) ?></a></h3>
        <span class="status status-<?= e($r['status']) ?>"><?= e(ucfirst($r['status'])) ?></span>
      </div>
      <p class="meta"><?= e($r['address']) ?></p>
      <p><strong><?= e(date('D, M j, Y', strtotime($r['preferred_date']))) ?></strong> at <?= e(date('g:i A', strtotime($r['preferred_time']))) ?></p>
      <?php if ($r['message']): ?><p class="prewrap muted">Your note: <?= e($r['message']) ?></p><?php endif; ?>
      <?php if ($r['landlord_note']): ?><p class="reply"><strong>Landlord's note:</strong> <?= e($r['landlord_note']) ?></p><?php endif; ?>
      <?php if (in_array($r['status'], ['pending', 'approved'], true)): ?>
        <form method="post" data-confirm="Cancel this viewing request?">
          <?= csrf_field() ?><input type="hidden" name="request_id" value="<?= (int)$r['id'] ?>">
          <button class="btn btn-small btn-danger" type="submit">Cancel request</button>
        </form>
      <?php endif; ?>
    </article>
  <?php endforeach; ?>
  </div>
<?php endif; ?>
<?php require __DIR__ . '/includes/student_footer.php'; ?>
