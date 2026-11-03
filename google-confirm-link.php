<?php
// An account with this email already exists. Link Google only after the HomeRent password proves ownership.
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/session_login.php';
require_once __DIR__ . '/includes/auth_page.php';
$p = $_SESSION['google_link_pending'] ?? null;
if (!$p || $p['exp'] < time()) { unset($_SESSION['google_link_pending']); header('Location: login.html?err=google_failed'); exit; }
$table = hr_table($p['type']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $q = $conn->prepare("SELECT fullname, password, account_status, avatar_url, password_set FROM $table WHERE id = ?");
    $q->bind_param('i', $p['id']); $q->execute(); $a = $q->get_result()->fetch_assoc(); $q->close();
    if (!$a || $a['account_status'] !== 'active') { unset($_SESSION['google_link_pending']); header('Location: login.html?err=inactive'); exit; }
    if ((int)$a['password_set'] === 1 && password_verify($_POST['password'] ?? '', $a['password'])) {
        try {
            $i = $conn->prepare("INSERT INTO oauth_links (provider, provider_user_id, account_type, account_id, email) VALUES ('google', ?, ?, ?, ?)");
            $i->bind_param('ssis', $p['sub'], $p['type'], $p['id'], $p['email']); $i->execute();
        } catch (mysqli_sql_exception $e) { unset($_SESSION['google_link_pending']); header('Location: login.html?err=google_conflict'); exit; }
        unset($_SESSION['google_link_pending']);
        header('Location: ' . hr_login($p['type'], $p['id'], $a['fullname'], $a['avatar_url'] ?: $p['picture'])); exit;
    }
    $_SESSION['google_link_pending']['fails']++;
    if ($_SESSION['google_link_pending']['fails'] >= 5) { unset($_SESSION['google_link_pending']); header('Location: login.html?err=invalid'); exit; }
    flash_set('error', 'That password is not correct.');
    header('Location: google-confirm-link.php'); exit;
}
auth_page_start('Link Google', 'Link your Google account', 'HomeRent already has an account for ' . $p['email'] . '. Enter its password once to connect Google.');
?>
  <form method="post"><?= csrf_field() ?>
    <div class="field"><label for="pw">HomeRent password</label><input id="pw" type="password" name="password" autocomplete="current-password" required autofocus></div>
    <button class="btn btn-block" type="submit">Link and log in</button>
  </form>
  <p class="center"><a href="forgot-password.php">Forgot your password?</a> &middot; <a href="login.html">Cancel</a></p>
<?php auth_page_end();
