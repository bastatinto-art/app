<?php
require_once __DIR__ . '/includes/auth.php';
$adminId = admin_require($conn);

$type = in_array($_GET['type'] ?? 'all', ['all', 'student', 'landlord'], true) ? ($_GET['type'] ?? 'all') : 'all';
$status = in_array($_GET['status'] ?? '', ['active', 'inactive', 'suspended'], true) ? $_GET['status'] : '';
$q = trim($_GET['q'] ?? '');

$sql = "SELECT * FROM (
          SELECT 'student' AS type, id, fullname, email, account_status, created_at FROM students
          UNION ALL
          SELECT 'landlord' AS type, id, fullname, email, account_status, created_at FROM landlords
        ) a WHERE 1=1";
$params = []; $bind = '';
if ($type !== 'all') { $sql .= " AND type = ?"; $params[] = $type; $bind .= 's'; }
if ($status !== '')  { $sql .= " AND account_status = ?"; $params[] = $status; $bind .= 's'; }
if ($q !== '')       { $like = '%' . addcslashes($q, '%_\\') . '%'; $sql .= " AND (fullname LIKE ? OR email LIKE ?)"; array_push($params, $like, $like); $bind .= 'ss'; }
$sql .= " ORDER BY created_at DESC, id DESC LIMIT 300";
$st = $conn->prepare($sql);
if ($params) $st->bind_param($bind, ...$params);
$st->execute(); $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Accounts'; $active = 'accounts';
require __DIR__ . '/includes/header.php';
?>
<h1>Account management</h1>
<div class="filter-tabs">
  <?php foreach (['all' => 'All accounts', 'student' => 'Students', 'landlord' => 'Landlords'] as $k => $l): ?>
    <a href="accounts.php?type=<?= $k ?>" class="<?= $type === $k ? 'active' : '' ?>"><?= $l ?></a>
  <?php endforeach; ?>
</div>
<form class="toolbar" method="get" action="accounts.php">
  <input type="hidden" name="type" value="<?= e($type) ?>">
  <div class="field grow"><label for="q">Search name or email</label><input id="q" type="search" name="q" value="<?= e($q) ?>" maxlength="100"></div>
  <div class="field"><label for="st">Status</label><select id="st" name="status"><option value="">Any status</option>
    <?php foreach (['active', 'inactive', 'suspended'] as $s): ?><option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option><?php endforeach; ?></select></div>
  <button class="btn" type="submit">Filter</button>
  <a class="btn btn-ghost" href="accounts.php">Clear</a>
</form>
<?php if (!$rows): ?><div class="empty"><p>No accounts match your search.</p></div><?php else: ?>
<div class="table-wrap"><table>
<thead><tr><th>Name</th><th>Email</th><th>Type</th><th>Status</th><th>Registered</th><th></th></tr></thead><tbody>
<?php foreach ($rows as $r): ?>
  <tr><td><strong><?= e($r['fullname']) ?></strong></td><td><?= e($r['email']) ?></td><td><?= e(ucfirst($r['type'])) ?></td>
  <td><span class="status status-<?= e($r['account_status']) ?>"><?= e(ucfirst($r['account_status'])) ?></span></td>
  <td><?= e(date('M j, Y', strtotime($r['created_at']))) ?></td>
  <td><a class="btn btn-small" href="account-view.php?type=<?= e($r['type']) ?>&amp;id=<?= (int)$r['id'] ?>">View</a></td></tr>
<?php endforeach; ?></tbody></table></div>
<p class="muted">Showing up to 300 accounts, newest first. Accounts created before the admin portal show the date it was installed.</p>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
