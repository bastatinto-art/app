<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/otp.php';
require_once __DIR__ . '/includes/auth_page.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $email = trim($_POST['email'] ?? '');
    $ctx = ['type' => null, 'id' => 0, 'email' => $email, 'at' => time()];
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        foreach (['student' => 'students', 'landlord' => 'landlords'] as $type => $table) {
            $st = $conn->prepare("SELECT id, fullname, email, password_set FROM $table WHERE email = ? AND email_verified = 1 AND account_status = 'active' LIMIT 1");
            $st->bind_param('s', $email); $st->execute();
            $a = $st->get_result()->fetch_assoc(); $st->close();
            if ($a) {
                $ctx = ['type' => $type, 'id' => (int)$a['id'], 'email' => $a['email'], 'at' => time()];
                otp_issue($conn, 'reset', $type, (int)$a['id'], $a['email'], $a['fullname'], (int)$a['password_set'] === 0);   // result is ignored on purpose (no hints to the visitor)
                break;
            }
        }
    }
    unset($_SESSION['reset_ok']);
    $_SESSION['reset_ctx'] = $ctx;            // same screen whether or not the email exists
    header('Location: reset-password.php'); exit;
}
auth_page_start('Forgot password', 'Forgot your password?', 'Enter your account email and we\'ll send a 6-digit code.');
?>
  <form method="post"><?= csrf_field() ?>
    <div class="field"><label for="email">Email address</label><div class="input-ico"><?= ui_icon('messages') ?><input id="email" type="email" name="email" required autofocus maxlength="150"></div></div>
    <button class="btn btn-block" type="submit">Send code</button>
  </form>
  <p class="muted" style="margin-top:12px;font-size:12px">Do you sign in with Google? Google manages that password, so HomeRent cannot reset it. Use <a href="https://accounts.google.com/signin/recovery" target="_blank" rel="noopener noreferrer">Google Account Recovery</a> instead.</p>
  <p class="center"><a href="login.html">Back to login</a></p>
<?php auth_page_end();
