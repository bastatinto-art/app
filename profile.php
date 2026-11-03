<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/../includes/password_flow.php';
$adminId = admin_require($conn);

$st = $conn->prepare("SELECT fullname, username, email, created_at FROM admins WHERE id = ?");
$st->bind_param('i', $adminId); $st->execute(); $a = $st->get_result()->fetch_assoc(); $st->close();

// Admin password change = current password + emailed one-time code + new password (12+ characters)
$stage = pw_flow($conn, [
    'key' => 'cp_admin', 'type' => 'admin', 'id' => $adminId, 'email' => $a['email'], 'name' => $a['fullname'], 'minLen' => 12,
    'self' => 'profile.php',
    'hashSql' => 'SELECT password FROM admins WHERE id = ?',
    'updateSql' => 'UPDATE admins SET password = ?, password_changed_at = ? WHERE id = ?',
    'after' => function () use ($conn, $adminId) { $_SESSION['admin_auth_at'] = time(); session_regenerate_id(true); admin_log($conn, $adminId, 'password_changed'); },
]);

$pageTitle = 'My profile'; $active = 'profile';
require __DIR__ . '/includes/header.php';
?>
<h1>My profile</h1>
<div class="two-col">
  <div class="card pad"><h2>Account</h2>
    <dl class="detail-grid"><div><dt>Name</dt><dd><?= e($a['fullname']) ?></dd></div><div><dt>Username</dt><dd><?= e($a['username'] ?: '-') ?></dd></div>
    <div><dt>Email</dt><dd><?= e($a['email']) ?></dd></div><div><dt>Created</dt><dd><?= e(date('M j, Y', strtotime($a['created_at']))) ?></dd></div></dl>
  </div>
  <div class="card pad"><h2>Change password</h2>
  <?php if ($stage === 1): ?>
    <p class="muted">Step 1 of 3 &middot; Confirm your current password. We'll then email a code to <?= e(mask_email($a['email'])) ?>.</p>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="current">
      <div class="field"><label for="cp">Current password</label><input id="cp" type="password" name="current_password" autocomplete="current-password" required></div>
      <button class="btn" type="submit">Send me a code</button></form>
  <?php elseif ($stage === 2): ?>
    <p class="muted">Step 2 of 3 &middot; Enter the 6-digit code (valid 5 minutes).</p>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="otp">
      <div class="field"><label for="code">Verification code</label><input id="code" class="otp-input" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required autofocus></div>
      <button class="btn" type="submit">Verify code</button></form>
    <form method="post" style="margin-top:10px"><?= csrf_field() ?><input type="hidden" name="action" value="resend"><button class="btn btn-ghost btn-small" type="submit">Resend code</button></form>
    <form method="post" style="margin-top:6px"><?= csrf_field() ?><input type="hidden" name="action" value="cancel"><button class="btn btn-ghost btn-small" type="submit">Cancel</button></form>
  <?php else: ?>
    <p class="muted">Step 3 of 3 &middot; Choose a new password (12+ characters).</p>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="new">
      <div class="field"><label for="np">New password</label><input id="np" type="password" name="new_password" minlength="12" autocomplete="new-password" required></div>
      <div class="field"><label for="cf">Confirm new password</label><input id="cf" type="password" name="confirm_password" minlength="12" autocomplete="new-password" required></div>
      <button class="btn" type="submit">Save new password</button></form>
  <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
