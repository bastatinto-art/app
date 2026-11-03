<?php
require_once __DIR__ . '/includes/auth.php';
$adminId = admin_require($conn);

$type = $_GET['type'] ?? $_POST['type'] ?? '';
if (!in_array($type, ['student', 'landlord'], true)) { header('Location: accounts.php'); exit; }
$table = $type === 'student' ? 'students' : 'landlords';       // fixed list, never user text
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$self = "account-view.php?type=$type&id=$id";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $act = $_POST['action'] ?? '';
    if ($act === 'status' && in_array($_POST['status'] ?? '', ['active', 'inactive', 'suspended'], true)) {
        $new = $_POST['status'];
        $u = $conn->prepare("UPDATE $table SET account_status = ? WHERE id = ?");
        $u->bind_param('si', $new, $id); $u->execute();
        admin_log($conn, $adminId, 'account_status', $type, $id, "set to $new");
        flash_set('success', 'Account is now ' . $new . '.');
    } elseif ($act === 'edit') {
        $name = trim($_POST['fullname'] ?? ''); $phone = trim($_POST['phone'] ?? '');
        if ($name === '' || mb_strlen($name) > 100) flash_set('error', 'Enter a name (up to 100 characters).');
        elseif ($phone !== '' && !preg_match('/^[0-9+\-\s()]{7,20}$/', $phone)) flash_set('error', 'Enter a valid phone number.');
        else {
            $phone = $phone === '' ? null : $phone;
            if ($type === 'student') {
                $school = mb_substr(trim($_POST['school'] ?? ''), 0, 120); $course = mb_substr(trim($_POST['course'] ?? ''), 0, 120); $year = mb_substr(trim($_POST['year_level'] ?? ''), 0, 30);
                $u = $conn->prepare("UPDATE students SET fullname=?, phone=?, school=?, course=?, year_level=? WHERE id=?");
                $u->bind_param('sssssi', $name, $phone, $school, $course, $year, $id);
            } else {
                $u = $conn->prepare("UPDATE landlords SET fullname=?, phone=? WHERE id=?");
                $u->bind_param('ssi', $name, $phone, $id);
            }
            $u->execute();
            admin_log($conn, $adminId, 'account_edit', $type, $id, 'profile details updated');
            flash_set('success', 'Account details updated.');
        }
    }
    header("Location: $self"); exit;
}

$cols = $type === 'student' ? 'id, fullname, email, phone, school, course, year_level, account_status, created_at'
                           : 'id, fullname, email, phone, facebook_url, account_status, created_at';
$st = $conn->prepare("SELECT $cols FROM $table WHERE id = ?");
$st->bind_param('i', $id); $st->execute();
$a = $st->get_result()->fetch_assoc();
if (!$a) { flash_set('error', 'Account not found.'); header('Location: accounts.php'); exit; }

$props = [];
if ($type === 'landlord') {
    $p = $conn->prepare("SELECT id, name, property_type, monthly_price, approval_status, availability, is_archived FROM properties WHERE landlord_id = ? ORDER BY created_at DESC");
    $p->bind_param('i', $id); $p->execute(); $props = $p->get_result()->fetch_all(MYSQLI_ASSOC);
}

$pageTitle = $a['fullname']; $active = 'accounts';
require __DIR__ . '/includes/header.php';
?>
<p><a href="accounts.php?type=<?= e($type) ?>">&larr; Back to accounts</a></p>
<h1><?= e($a['fullname']) ?> <span class="status status-<?= e($a['account_status']) ?>" style="font-size:13px"><?= e(ucfirst($a['account_status'])) ?></span></h1>
<div class="two-col">
  <div>
    <div class="card pad"><h2>Account details</h2>
      <dl class="detail-grid">
        <div><dt>Type</dt><dd><?= e(ucfirst($type)) ?></dd></div>
        <div><dt>Email</dt><dd><?= e($a['email']) ?></dd></div>
        <div><dt>Phone</dt><dd><?= e($a['phone'] ?: '-') ?></dd></div>
        <div><dt>Registered</dt><dd><?= e(date('M j, Y', strtotime($a['created_at']))) ?></dd></div>
        <?php if ($type === 'student'): ?>
          <div><dt>School</dt><dd><?= e($a['school'] ?: '-') ?></dd></div><div><dt>Course</dt><dd><?= e($a['course'] ?: '-') ?></dd></div>
          <div><dt>Year level</dt><dd><?= e($a['year_level'] ?: '-') ?></dd></div>
        <?php else: ?>
          <div><dt>Facebook</dt><dd><?= e($a['facebook_url'] ?: '-') ?></dd></div>
        <?php endif; ?>
      </dl>
      <p class="muted">Passwords are never shown. Email changes are not allowed here because email is the login.</p>
    </div>
    <form class="card pad" method="post"><h2>Edit details</h2>
      <?= csrf_field() ?><input type="hidden" name="action" value="edit"><input type="hidden" name="type" value="<?= e($type) ?>"><input type="hidden" name="id" value="<?= $id ?>">
      <div class="field"><label for="fn">Full name</label><input id="fn" name="fullname" value="<?= e($a['fullname']) ?>" maxlength="100" required></div>
      <div class="field"><label for="ph">Phone</label><input id="ph" name="phone" value="<?= e($a['phone']) ?>" maxlength="20"></div>
      <?php if ($type === 'student'): ?>
        <div class="field"><label for="sc">School</label><input id="sc" name="school" value="<?= e($a['school']) ?>" maxlength="120"></div>
        <div class="form-2"><div class="field"><label for="co">Course</label><input id="co" name="course" value="<?= e($a['course']) ?>" maxlength="120"></div>
        <div class="field"><label for="yl">Year level</label><input id="yl" name="year_level" value="<?= e($a['year_level']) ?>" maxlength="30"></div></div>
      <?php endif; ?>
      <button class="btn" type="submit">Save changes</button>
    </form>
  </div>
  <div>
    <div class="card pad"><h2>Account status</h2>
      <p class="muted">Inactive and suspended accounts cannot log in. Their data is kept.</p>
      <div class="row-actions" style="margin-top:10px">
        <?php foreach (['active' => 'Activate', 'inactive' => 'Deactivate', 'suspended' => 'Suspend'] as $v => $label): if ($a['account_status'] === $v) continue; ?>
          <form method="post" data-confirm="<?= e($label) ?> this account?">
            <?= csrf_field() ?><input type="hidden" name="action" value="status"><input type="hidden" name="status" value="<?= $v ?>">
            <input type="hidden" name="type" value="<?= e($type) ?>"><input type="hidden" name="id" value="<?= $id ?>">
            <button class="btn btn-small <?= $v === 'active' ? '' : 'btn-ghost' ?>" type="submit"><?= $label ?></button>
          </form>
        <?php endforeach; ?>
      </div>
    </div>
    <?php if ($type === 'landlord'): ?>
    <div class="card pad"><h2>Properties (<?= count($props) ?>)</h2>
      <?php if (!$props): ?><p class="muted">This landlord has no properties yet.</p><?php else: ?>
        <?php foreach ($props as $p): ?>
          <p style="margin:6px 0"><a href="properties.php?q=<?= urlencode($p['name']) ?>"><?= e($p['name']) ?></a>
            <span class="muted">&middot; <?= e(type_label($p['property_type'])) ?> &middot; <?= peso($p['monthly_price']) ?></span>
            <span class="badge badge-<?= e($p['approval_status']) ?>"><?= e(ucfirst($p['approval_status'])) ?></span><?= $p['is_archived'] ? ' <span class="muted">(archived)</span>' : '' ?></p>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
