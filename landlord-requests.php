<?php
require_once __DIR__ . '/includes/auth.php';
require_role('landlord');
$lid = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $rid = (int)($_POST['request_id'] ?? 0);
    $act = $_POST['action'] ?? '';
    $note = trim($_POST['note'] ?? '');
    $back = 'landlord-requests.php' . (isset($_GET['status']) ? '?status=' . urlencode($_GET['status']) : '');

    if (!in_array($act, ['approve', 'reject'], true)) { flash_set('error', 'Unknown action.'); header("Location: $back"); exit; }
    if (mb_strlen($note) > 500) { flash_set('error', 'Your note must be 500 characters or fewer.'); header("Location: $back"); exit; }

    // Load only a pending request on one of THIS landlord's properties
    $st = $conn->prepare("SELECT v.id, v.property_id, v.preferred_date, v.preferred_time
        FROM viewing_requests v JOIN properties p ON p.id = v.property_id
        WHERE v.id = ? AND p.landlord_id = ? AND v.status = 'pending'");
    $st->bind_param('ii', $rid, $lid); $st->execute();
    $r = $st->get_result()->fetch_assoc(); $st->close();

    if (!$r) {
        flash_set('error', 'That request was not found or has already been answered.');
    } elseif ($act === 'approve' && $r['preferred_date'] < date('Y-m-d')) {
        flash_set('error', 'This viewing date has already passed. Reject it or ask the student to request again.');
    } else {
        $conflict = false;
        if ($act === 'approve') {
            $c = $conn->prepare("SELECT 1 FROM viewing_requests WHERE property_id = ? AND preferred_date = ? AND preferred_time = ? AND status = 'approved' AND id <> ?");
            $c->bind_param('issi', $r['property_id'], $r['preferred_date'], $r['preferred_time'], $rid); $c->execute();
            $conflict = $c->get_result()->num_rows > 0; $c->close();
        }
        if ($conflict) {
            flash_set('error', 'Another viewing is already approved for this property at that date and time.');
        } else {
            $new = $act === 'approve' ? 'approved' : 'rejected';
            $u = $conn->prepare("UPDATE viewing_requests v JOIN properties p ON p.id = v.property_id
                SET v.status = ?, v.landlord_note = ? WHERE v.id = ? AND p.landlord_id = ? AND v.status = 'pending'");
            $u->bind_param('ssii', $new, $note, $rid, $lid); $u->execute();
            flash_set('success', 'Request ' . $new . '. The student can see the update.');
        }
    }
    header("Location: $back"); exit;
}

$filters = ['all', 'pending', 'approved', 'rejected', 'cancelled'];
$status = in_array($_GET['status'] ?? 'all', $filters, true) ? ($_GET['status'] ?? 'all') : 'all';
$sql = "SELECT v.id, v.preferred_date, v.preferred_time, v.message, v.status, v.landlord_note, v.created_at,
               p.name AS pname, s.fullname, s.school, s.course, s.year_level, s.email, s.phone
        FROM viewing_requests v JOIN properties p ON p.id = v.property_id JOIN students s ON s.id = v.student_id
        WHERE p.landlord_id = ?" . ($status !== 'all' ? " AND v.status = ?" : "") .
       " ORDER BY (v.status = 'pending') DESC, v.preferred_date ASC, v.preferred_time ASC";
$st = $conn->prepare($sql);
if ($status !== 'all') $st->bind_param('is', $lid, $status); else $st->bind_param('i', $lid);
$st->execute();
$rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Viewing requests'; $active = 'requests';
require __DIR__ . '/includes/landlord_header.php';
?>
<h1>Viewing requests</h1>
<div class="filter-tabs">
  <?php foreach ($filters as $f): ?><a href="landlord-requests.php?status=<?= $f ?>" class="<?= $status === $f ? 'active' : '' ?>"><?= ucfirst($f) ?></a><?php endforeach; ?>
</div>
<?php if (!$rows): ?>
  <div class="empty"><p>No <?= $status === 'all' ? '' : e($status) . ' ' ?>viewing requests.</p></div>
<?php else: ?>
<div class="list">
<?php foreach ($rows as $r): ?>
  <article class="card pad item">
    <div class="item-head"><h3><?= e($r['fullname']) ?> <span class="muted">wants to view</span> <?= e($r['pname']) ?></h3>
      <span class="status status-<?= e($r['status']) ?>"><?= e(ucfirst($r['status'])) ?></span></div>
    <p><strong><?= e(date('D, M j, Y', strtotime($r['preferred_date']))) ?></strong> at <?= e(date('g:i A', strtotime($r['preferred_time']))) ?></p>
    <p class="muted"><?= e(implode(' · ', array_filter([$r['school'], $r['course'], $r['year_level']]))) ?: 'No school details provided' ?></p>
    <?php if ($r['status'] === 'approved'): ?><p class="muted">Contact: <?= e($r['email']) ?><?= $r['phone'] ? ' · ' . e($r['phone']) : '' ?></p><?php endif; ?>
    <?php if ($r['message']): ?><p class="prewrap">Student's note: <?= e($r['message']) ?></p><?php endif; ?>
    <?php if ($r['landlord_note']): ?><p class="reply"><strong>Your note:</strong> <?= e($r['landlord_note']) ?></p><?php endif; ?>
    <?php if ($r['status'] === 'pending'): ?>
      <form method="post" class="inline-form">
        <?= csrf_field() ?><input type="hidden" name="request_id" value="<?= (int)$r['id'] ?>">
        <label for="n<?= (int)$r['id'] ?>" class="muted">Note to student (optional)</label>
        <textarea id="n<?= (int)$r['id'] ?>" name="note" rows="2" maxlength="500"></textarea>
        <div class="row-actions">
          <button class="btn btn-small" type="submit" name="action" value="approve">Approve</button>
          <button class="btn btn-small btn-danger" type="submit" name="action" value="reject" data-nobusy>Reject</button>
        </div>
      </form>
    <?php endif; ?>
  </article>
<?php endforeach; ?>
</div>
<?php endif; ?>
<?php require __DIR__ . '/includes/landlord_footer.php'; ?>
