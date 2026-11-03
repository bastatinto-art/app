<?php
require_once __DIR__ . '/includes/auth.php';
$adminId = admin_require($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $pid = (int)($_POST['property_id'] ?? 0); $act = $_POST['action'] ?? '';
    $map = ['approve' => ["approval_status = 'approved'", 'property_approved'], 'reject' => ["approval_status = 'rejected'", 'property_rejected'],
            'pending' => ["approval_status = 'pending'", 'property_set_pending'], 'archive' => ['is_archived = 1', 'property_archived'], 'restore' => ['is_archived = 0', 'property_restored']];
    if (isset($map[$act])) {
        $u = $conn->prepare("UPDATE properties SET {$map[$act][0]} WHERE id = ?");   // fixed SQL from the map above
        $u->bind_param('i', $pid); $u->execute();
        if ($u->affected_rows) { admin_log($conn, $adminId, $map[$act][1], 'property', $pid); flash_set('success', 'Property updated. Students see the change immediately.'); }
        else flash_set('error', 'No change was made.');
    }
    header('Location: ' . (preg_match('/^[a-z]+\.php(\?[A-Za-z0-9_=&%.+-]*)?$/', $_POST['return'] ?? '') ? $_POST['return'] : 'properties.php')); exit;
}

$status = in_array($_GET['status'] ?? '', ['pending', 'approved', 'rejected'], true) ? $_GET['status'] : '';
$q = trim($_GET['q'] ?? ''); $arch = isset($_GET['archived']) ? 1 : 0;
$sql = "SELECT p.id, p.name, p.property_type, p.monthly_price, p.address, p.availability, p.approval_status, p.is_archived, p.created_at, l.fullname AS landlord, l.id AS lid
        FROM properties p LEFT JOIN landlords l ON l.id = p.landlord_id WHERE p.is_archived = ?";
$params = [$arch]; $bind = 'i';
if ($status !== '') { $sql .= " AND p.approval_status = ?"; $params[] = $status; $bind .= 's'; }
if ($q !== '') { $like = '%' . addcslashes($q, '%_\\') . '%'; $sql .= " AND (p.name LIKE ? OR p.address LIKE ? OR l.fullname LIKE ?)"; array_push($params, $like, $like, $like); $bind .= 'sss'; }
$sql .= " ORDER BY (p.approval_status = 'pending') DESC, p.created_at DESC LIMIT 300";
$st = $conn->prepare($sql); $st->bind_param($bind, ...$params); $st->execute();
$rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
$ret = 'properties.php' . ($_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : '');

$pageTitle = 'Properties'; $active = $status === 'pending' ? 'approvals' : 'properties';
require __DIR__ . '/includes/header.php';
?>
<h1><?= $status === 'pending' ? 'Property approvals' : 'Property management' ?></h1>
<div class="filter-tabs">
  <a href="properties.php" class="<?= $status === '' && !$arch ? 'active' : '' ?>">All</a>
  <a href="properties.php?status=pending" class="<?= $status === 'pending' ? 'active' : '' ?>">Pending</a>
  <a href="properties.php?status=approved" class="<?= $status === 'approved' ? 'active' : '' ?>">Approved</a>
  <a href="properties.php?status=rejected" class="<?= $status === 'rejected' ? 'active' : '' ?>">Rejected</a>
  <a href="properties.php?archived=1" class="<?= $arch ? 'active' : '' ?>">Archived</a>
</div>
<form class="toolbar" method="get" action="properties.php">
  <?php if ($status): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?><?php if ($arch): ?><input type="hidden" name="archived" value="1"><?php endif; ?>
  <div class="field grow"><label for="q">Search property, address or landlord</label><input id="q" type="search" name="q" value="<?= e($q) ?>" maxlength="100"></div>
  <button class="btn" type="submit">Search</button>
</form>
<?php if (!$rows): ?><div class="empty"><p>No properties found.</p></div><?php else: ?>
<div class="table-wrap"><table>
<thead><tr><th>Property</th><th>Landlord</th><th>Price</th><th>Approval</th><th>Availability</th><th>Actions</th></tr></thead><tbody>
<?php foreach ($rows as $r): ?>
<tr>
  <td><strong><?= e($r['name']) ?></strong><br><span class="muted"><?= e(type_label($r['property_type'])) ?> &middot; <?= e($r['address']) ?></span></td>
  <td><?php if ($r['lid']): ?><a href="account-view.php?type=landlord&amp;id=<?= (int)$r['lid'] ?>"><?= e($r['landlord']) ?></a><?php else: ?>-<?php endif; ?></td>
  <td><?= peso($r['monthly_price']) ?></td>
  <td><span class="badge badge-<?= e($r['approval_status']) ?>"><?= e(ucfirst($r['approval_status'])) ?></span></td>
  <td><?= e(ucfirst($r['availability'])) ?></td>
  <td><div class="row-actions">
    <?php
    $buttons = $r['is_archived'] ? [['restore', 'Restore', 'btn-ghost', 'Restore this listing?']]
      : array_values(array_filter([
          $r['approval_status'] !== 'approved' ? ['approve', 'Approve', '', 'Approve this listing? Students will be able to see it.'] : null,
          $r['approval_status'] !== 'rejected' ? ['reject', 'Reject', 'btn-danger', 'Reject this listing? Students will no longer see it.'] : null,
          ['archive', 'Archive', 'btn-ghost', 'Archive (hide) this listing?'],
        ]));
    foreach ($buttons as [$act, $label, $cls, $confirm]): ?>
      <form method="post" data-confirm="<?= e($confirm) ?>"><?= csrf_field() ?>
        <input type="hidden" name="action" value="<?= $act ?>"><input type="hidden" name="property_id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="return" value="<?= e($ret) ?>">
        <button class="btn btn-small <?= $cls ?>" type="submit"><?= $label ?></button></form>
    <?php endforeach; ?>
  </div></td>
</tr>
<?php endforeach; ?></tbody></table></div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
