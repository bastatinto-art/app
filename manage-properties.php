<?php
require_once __DIR__ . '/includes/auth.php';
require_role('landlord');
$lid = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $pid = (int)($_POST['property_id'] ?? 0);
    $act = $_POST['action'] ?? '';
    // Every update is restricted to this landlord's own property
    if ($act === 'availability' && in_array($_POST['availability'] ?? '', ['available', 'unavailable', 'rented'], true)) {
        $u = $conn->prepare("UPDATE properties SET availability = ? WHERE id = ? AND landlord_id = ?");
        $u->bind_param('sii', $_POST['availability'], $pid, $lid); $u->execute();
        flash_set($u->affected_rows ? 'success' : 'error', $u->affected_rows ? 'Availability updated.' : 'No change made.');
    } elseif ($act === 'archive' || $act === 'restore') {
        $val = $act === 'archive' ? 1 : 0;
        $u = $conn->prepare("UPDATE properties SET is_archived = ? WHERE id = ? AND landlord_id = ?");
        $u->bind_param('iii', $val, $pid, $lid); $u->execute();
        flash_set($u->affected_rows ? 'success' : 'error', $u->affected_rows ? ($val ? 'Listing archived. Students can no longer see it.' : 'Listing restored.') : 'No change made.');
    }
    header('Location: manage-properties.php' . (isset($_GET['archived']) ? '?archived=1' : '')); exit;
}

$showArchived = isset($_GET['archived']) ? 1 : 0;
$st = $conn->prepare("SELECT p.id, p.name, p.property_type, p.monthly_price, p.address, p.rooms_available, p.availability, p.approval_status, p.is_archived,
    (SELECT file_path FROM property_images i WHERE i.property_id = p.id ORDER BY i.is_primary DESC, i.sort_order, i.id LIMIT 1) AS image
    FROM properties p WHERE p.landlord_id = ? AND p.is_archived = ? ORDER BY p.created_at DESC");
$st->bind_param('ii', $lid, $showArchived); $st->execute();
$rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'My properties'; $active = 'properties';
require __DIR__ . '/includes/landlord_header.php';
?>
<div class="item-head"><h1><?= $showArchived ? 'Archived properties' : 'My properties' ?></h1>
  <a class="btn" href="add-property.php">Add a property</a></div>
<div class="filter-tabs">
  <a href="manage-properties.php" class="<?= !$showArchived ? 'active' : '' ?>">Active</a>
  <a href="manage-properties.php?archived=1" class="<?= $showArchived ? 'active' : '' ?>">Archived</a>
</div>
<?php if (!$rows): ?>
  <div class="empty"><p><?= $showArchived ? 'No archived properties.' : "You haven't added any properties yet." ?></p>
  <?php if (!$showArchived): ?><a class="btn" href="add-property.php">Add your first property</a><?php endif; ?></div>
<?php else: ?>
<div class="table-wrap"><table>
<thead><tr><th>Photo</th><th>Property</th><th>Price</th><th>Rooms</th><th>Approval</th><th>Availability</th><th>Actions</th></tr></thead><tbody>
<?php foreach ($rows as $r): ?>
<tr>
  <td><?php if ($r['image']): ?><img class="thumb-sm" src="<?= e($r['image']) ?>" alt=""><?php else: ?><span class="thumb-sm"></span><?php endif; ?></td>
  <td><strong><?= e($r['name']) ?></strong><br><span class="muted"><?= e(type_label($r['property_type'])) ?> &middot; <?= e($r['address']) ?></span></td>
  <td><?= peso($r['monthly_price']) ?></td>
  <td><?= (int)$r['rooms_available'] ?></td>
  <td><span class="badge badge-<?= e($r['approval_status']) ?>"><?= $r['approval_status'] === 'pending' ? 'Awaiting approval' : e(ucfirst($r['approval_status'])) ?></span></td>
  <td>
    <form method="post" class="row-actions">
      <?= csrf_field() ?><input type="hidden" name="action" value="availability"><input type="hidden" name="property_id" value="<?= (int)$r['id'] ?>">
      <select name="availability" aria-label="Availability">
        <?php foreach (['available', 'unavailable', 'rented'] as $a): ?><option value="<?= $a ?>" <?= $r['availability'] === $a ? 'selected' : '' ?>><?= ucfirst($a) ?></option><?php endforeach; ?>
      </select>
      <button class="btn btn-small btn-ghost" type="submit">Update</button>
    </form>
  </td>
  <td><div class="row-actions">
    <a class="btn btn-small" href="edit-property.php?id=<?= (int)$r['id'] ?>">Edit</a>
    <form method="post" <?= $r['is_archived'] ? '' : 'data-confirm="Archive this listing? Students will no longer see it."' ?>>
      <?= csrf_field() ?><input type="hidden" name="property_id" value="<?= (int)$r['id'] ?>">
      <input type="hidden" name="action" value="<?= $r['is_archived'] ? 'restore' : 'archive' ?>">
      <button class="btn btn-small btn-danger" type="submit"><?= $r['is_archived'] ? 'Restore' : 'Archive' ?></button>
    </form></div></td>
</tr>
<?php endforeach; ?></tbody></table></div>
<p class="muted">New and rejected listings are reviewed by an administrator before students can see them.</p>
<?php endif; ?>
<?php require __DIR__ . '/includes/landlord_footer.php'; ?>
