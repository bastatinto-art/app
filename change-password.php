<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/password_flow.php';
$role = $_SESSION['role'] ?? '';
if (!in_array($role, ['student', 'landlord'], true)) { header('Location: login.html?err=login_required'); exit; }
require_role($role);
$id = (int)$_SESSION['user_id'];
$table = hr_table_name($role);
function hr_table_name(string $r): string { return $r === 'landlord' ? 'landlords' : 'students'; }

$st = $conn->prepare("SELECT fullname, email, password_set FROM $table WHERE id = ?");
$st->bind_param('i', $id); $st->execute(); $acct = $st->get_result()->fetch_assoc(); $st->close();
$profile = $role === 'landlord' ? 'landlord-profile.php' : 'student-profile.php';

$stage = 1;
if ((int)$acct['password_set'] === 1) {
    $stage = pw_flow($conn, [
        'key' => 'cp_' . $role, 'type' => $role, 'id' => $id, 'email' => $acct['email'], 'name' => $acct['fullname'], 'minLen' => 8,
        'self' => 'change-password.php', 'doneUrl' => $profile,
        'hashSql' => "SELECT password FROM $table WHERE id = ?",
        'updateSql' => "UPDATE $table SET password = ?, password_set = 1, password_changed_at = ? WHERE id = ?",
        'after' => function () { $_SESSION['auth_at'] = time(); session_regenerate_id(true); },
    ]);
}
$pageTitle = 'Change password'; $active = 'profile';
require __DIR__ . '/includes/' . $role . '_header.php';
?>
<h1>Change password</h1>
<div class="card pad" style="max-width:520px">
<?php if ((int)$acct['password_set'] !== 1): ?>
  <p>Your account was created with Google, so it has no HomeRent password yet.</p>
  <p class="muted">Your password is managed by Google. To recover it, use <a href="https://accounts.google.com/signin/recovery" target="_blank" rel="noopener noreferrer">Google Account Recovery</a>. If you also want a HomeRent password, use <a href="forgot-password.php">Forgot password</a>: we will email a code to <?= e(mask_email($acct['email'])) ?>.</p>
<?php elseif ($stage === 1): ?>
  <p class="muted">Step 1 of 3 &middot; Confirm it's you. We'll then email a code to <?= e(mask_email($acct['email'])) ?>.</p>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="current">
    <div class="field"><label for="cp">Current password</label><input id="cp" type="password" name="current_password" autocomplete="current-password" required></div>
    <button class="btn" type="submit">Send me a code</button> <a class="btn btn-ghost" href="<?= e($profile) ?>">Cancel</a>
  </form>
<?php elseif ($stage === 2): ?>
  <p class="muted">Step 2 of 3 &middot; Enter the 6-digit code sent to <?= e(mask_email($acct['email'])) ?> (valid 5 minutes).</p>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="otp">
    <div class="field"><label for="code">Verification code</label><input id="code" class="otp-input" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required autofocus></div>
    <button class="btn" type="submit">Verify code</button>
  </form>
  <form method="post" style="margin-top:10px"><?= csrf_field() ?><input type="hidden" name="action" value="resend"><button class="btn btn-ghost btn-small" type="submit" data-nobusy>Resend code</button>
    <span class="muted">(once per minute)</span></form>
  <form method="post" style="margin-top:6px"><?= csrf_field() ?><input type="hidden" name="action" value="cancel"><button class="btn btn-ghost btn-small" type="submit">Cancel</button></form>
<?php else: ?>
  <p class="muted">Step 3 of 3 &middot; Choose a new password.</p>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="new">
    <div class="field"><label for="np">New password (8+ characters)</label><input id="np" type="password" name="new_password" minlength="8" autocomplete="new-password" required></div>
    <div class="field"><label for="cf">Confirm new password</label><input id="cf" type="password" name="confirm_password" minlength="8" autocomplete="new-password" required></div>
    <button class="btn" type="submit">Save new password</button>
  </form>
<?php endif; ?>
</div>
<?php require __DIR__ . '/includes/' . $role . '_footer.php'; ?>
