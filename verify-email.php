<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/otp.php';
require_once __DIR__ . '/includes/auth_page.php';
$p = $_SESSION['pending_verify'] ?? null;
if (!$p) { header('Location: login.html'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $table = $p['type'] === 'landlord' ? 'landlords' : 'students';
    if (($_POST['action'] ?? '') === 'resend') {
        $r = otp_issue($conn, 'register', $p['type'], $p['id'], $p['email'], $p['name']);
        if ($r['ok']) flash_set('success', 'A new code was sent. The previous code no longer works.');
        elseif ($r['reason'] === 'cooldown') flash_set('error', 'Please wait ' . $r['wait'] . ' seconds before requesting another code.');
        elseif ($r['reason'] === 'limit') flash_set('error', 'Too many codes requested. Try again in an hour.');
        else flash_set('error', 'We could not send the email. Please check the email settings.');
    } else {
        $res = otp_verify($conn, 'register', $p['type'], $p['id'], $_POST['code'] ?? '');
        if ($res === 'ok') {
            $now = date('Y-m-d H:i:s');
            $u = $conn->prepare("UPDATE $table SET email_verified = 1, email_verified_at = ? WHERE id = ?");
            $u->bind_param('si', $now, $p['id']); $u->execute();
            unset($_SESSION['pending_verify']);
            header('Location: login.html?msg=verified'); exit;
        }
        flash_set('error', otp_message($res));
    }
    header('Location: verify-email.php'); exit;
}
$wait = otp_wait_seconds($conn, 'register', $p['type'], $p['id']);
auth_page_start('Verify email', 'Verify your email', 'Enter the 6-digit code we sent to ' . mask_email($p['email']) . '. It expires in 5 minutes.');
?>
  <form method="post" autocomplete="off"><?= csrf_field() ?>
    <div class="field"><label for="code">Verification code</label>
      <input id="code" class="otp-input" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required autofocus></div>
    <button class="btn btn-block" type="submit">Verify email</button>
  </form>
  <form method="post" style="margin-top:12px"><?= csrf_field() ?><input type="hidden" name="action" value="resend">
    <button class="btn btn-ghost btn-block" type="submit" data-nobusy>Resend code</button>
    <p class="muted center" style="margin-top:6px"><?= $wait > 0 ? 'You can request a new code in ' . $wait . ' seconds.' : 'Didn\'t get it? Check your spam folder.' ?></p></form>
  <p class="center"><a href="login.html">Back to login</a></p>
<?php auth_page_end();
