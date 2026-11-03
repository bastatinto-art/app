<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/otp.php';
require_once __DIR__ . '/includes/auth_page.php';
$ctx = $_SESSION['reset_ctx'] ?? null;
if (!$ctx || time() - ($ctx['at'] ?? 0) > 1800) { unset($_SESSION['reset_ctx'], $_SESSION['reset_ok']); header('Location: forgot-password.php'); exit; }
$tables = ['student' => 'students', 'landlord' => 'landlords'];
$ok = $_SESSION['reset_ok'] ?? null;
$stage = ($ok && $ok['until'] > time() && $ok['id'] === $ctx['id'] && $ctx['id'] > 0) ? 2 : 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $act = $_POST['action'] ?? '';
    if ($act === 'resend') {
        if ($ctx['id'] > 0) {
            $st = $conn->prepare("SELECT fullname, password_set FROM {$tables[$ctx['type']]} WHERE id = ?"); $st->bind_param('i', $ctx['id']); $st->execute();
            $rw = $st->get_result()->fetch_row() ?: ['', 1]; $st->close();
            otp_issue($conn, 'reset', $ctx['type'], $ctx['id'], $ctx['email'], $rw[0], (int)$rw[1] === 0);
        }
        flash_set('success', 'If that email is registered, a new code was sent. You can request one per minute.');
    } elseif ($act === 'code' && $stage === 1) {
        $res = $ctx['id'] > 0 ? otp_verify($conn, 'reset', $ctx['type'], $ctx['id'], $_POST['code'] ?? '') : 'none';
        if ($res === 'ok') {
            session_regenerate_id(true);
            $_SESSION['reset_ok'] = ['id' => $ctx['id'], 'type' => $ctx['type'], 'until' => time() + 600];
            flash_set('success', 'Code verified. Choose a new password.');
        } else flash_set('error', 'That code is invalid or has expired. Request a new one if needed.');   // same message for every failure
    } elseif ($act === 'password' && $stage === 2) {
        $new = $_POST['new_password'] ?? '';
        if (strlen($new) < 8) flash_set('error', 'New password must be at least 8 characters.');
        elseif ($new !== ($_POST['confirm_password'] ?? '')) flash_set('error', 'Passwords do not match.');
        else {
            $table = $tables[$ok['type']]; $hash = password_hash($new, PASSWORD_DEFAULT); $now = date('Y-m-d H:i:s');
            $u = $conn->prepare("UPDATE $table SET password = ?, password_set = 1, password_changed_at = ? WHERE id = ?");
            $u->bind_param('ssi', $hash, $now, $ok['id']); $u->execute();
            $g = $conn->prepare("SELECT fullname, email FROM $table WHERE id = ?"); $g->bind_param('i', $ok['id']); $g->execute();
            $a = $g->get_result()->fetch_assoc();
            unset($_SESSION['reset_ctx'], $_SESSION['reset_ok']);
            if ($a) { [$s, $h, $t] = mail_password_changed($a['fullname']); send_mail($a['email'], $a['fullname'], $s, $h, $t); }
            header('Location: login.html?msg=reset_done'); exit;
        }
    }
    header('Location: reset-password.php'); exit;
}
auth_page_start('Reset password', $stage === 1 ? 'Enter your code' : 'Create a new password',
    $stage === 1 ? 'If that email is registered, we sent a 6-digit code (valid 5 minutes).' : 'Pick a strong password you do not use anywhere else.');
?>
<?php if ($stage === 1): ?>
  <form method="post" autocomplete="off"><?= csrf_field() ?><input type="hidden" name="action" value="code">
    <div class="field"><label for="code">Verification code</label><input id="code" class="otp-input" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required autofocus></div>
    <button class="btn btn-block" type="submit">Verify code</button>
  </form>
  <form method="post" style="margin-top:12px"><?= csrf_field() ?><input type="hidden" name="action" value="resend"><button class="btn btn-ghost btn-block" type="submit" data-nobusy>Resend code</button></form>
  <p class="muted" style="margin-top:12px;font-size:12px">Do you sign in with Google? Google manages that password, so HomeRent cannot reset it. Use <a href="https://accounts.google.com/signin/recovery" target="_blank" rel="noopener noreferrer">Google Account Recovery</a> instead.</p>
<?php else: ?>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="password">
    <div class="field"><label for="np">New password (8+ characters)</label><input id="np" type="password" name="new_password" minlength="8" autocomplete="new-password" required autofocus></div>
    <div class="field"><label for="cf">Confirm new password</label><input id="cf" type="password" name="confirm_password" minlength="8" autocomplete="new-password" required></div>
    <button class="btn btn-block" type="submit">Save new password</button>
  </form>
<?php endif; ?>
  <p class="center"><a href="login.html">Back to login</a></p>
<?php auth_page_end();
