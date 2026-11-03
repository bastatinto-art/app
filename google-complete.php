<?php
// New Google user chooses Student or Landlord. The identity comes from the verified token stored in the session, never from the form.
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/session_login.php';
require_once __DIR__ . '/includes/google_flow.php';
require_once __DIR__ . '/includes/auth_page.php';
$g = $_SESSION['google_pending'] ?? null;
if (!$g || $g['exp'] < time()) { unset($_SESSION['google_pending']); header('Location: login.html?err=google_failed'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $type = $_POST['account_type'] ?? '';
    if (!in_array($type, ['student', 'landlord'], true)) { flash_set('error', 'Choose Student or Landlord.'); header('Location: google-complete.php'); exit; }
    foreach (['students', 'landlords'] as $t) {                 // re-check: the email must still be free
        $c = $conn->prepare("SELECT 1 FROM $t WHERE email = ?"); $c->bind_param('s', $g['email']); $c->execute();
        if ($c->get_result()->num_rows) { unset($_SESSION['google_pending']); header('Location: login.html?err=google_conflict'); exit; }
    }
    try { $id = google_create_account($conn, $type, $g['sub'], $g['email'], $g['name'], $g['picture']); }
    catch (mysqli_sql_exception $e) { error_log('google-complete: ' . $e->getMessage()); unset($_SESSION['google_pending']); header('Location: login.html?err=google_failed'); exit; }
    $pic = $g['picture']; $name = $g['name']; unset($_SESSION['google_pending']);
    header('Location: ' . hr_login($type, $id, $name, $pic)); exit;
}
auth_page_start('Finish sign-up', 'Almost there!', 'Google verified ' . $g['email'] . '. Tell us how you will use HomeRent.');
?>
  <form method="post"><?= csrf_field() ?>
    <div class="role-pick">
      <label class="role-opt"><input type="radio" name="account_type" value="student" required><span><strong>Student</strong><small>Find and book rental spaces</small></span></label>
      <label class="role-opt"><input type="radio" name="account_type" value="landlord" required><span><strong>Landlord</strong><small>List and manage properties</small></span></label>
    </div>
    <p class="muted">Name: <?= e($g['name']) ?></p>
    <button class="btn btn-block" type="submit">Create my account</button>
  </form>
  <p class="center"><a href="login.html">Cancel</a></p>
<?php auth_page_end();
