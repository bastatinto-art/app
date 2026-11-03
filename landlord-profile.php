<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/contact.php';
require_role('landlord');
$lid = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    if (($_POST['action'] ?? '') === 'profile') {
        $fullname = trim($_POST['fullname'] ?? ''); $phone = trim($_POST['phone'] ?? '');
        $fb = clean_facebook_url($_POST['facebook_url'] ?? '');
        if ($fullname === '' || mb_strlen($fullname) > 100) flash_set('error', 'Enter your full name (up to 100 characters).');
        elseif ($phone !== '' && !preg_match('/^[0-9+\-\s()]{7,20}$/', $phone)) flash_set('error', 'Enter a valid phone number.');
        elseif (!$fb['ok']) flash_set('error', $fb['error']);
        else {
            // Saved once on the landlord's account; every property page reads it from here.
            $phoneVal = $phone === '' ? null : $phone;
            $u = $conn->prepare("UPDATE landlords SET fullname = ?, phone = ?, facebook_url = ? WHERE id = ?");
            $u->bind_param('sssi', $fullname, $phoneVal, $fb['value'], $lid); $u->execute();
            $_SESSION['name'] = $fullname;
            flash_set('success', 'Profile updated. Your contact details now show on all your properties.');
        }
    }
    header('Location: landlord-profile.php'); exit;
}

$st = $conn->prepare("SELECT fullname, email, phone, facebook_url FROM landlords WHERE id = ?");
$st->bind_param('i', $lid); $st->execute();
$l = $st->get_result()->fetch_assoc();

$gl = $conn->prepare("SELECT email FROM oauth_links WHERE provider = 'google' AND account_type = 'landlord' AND account_id = ?");
$gl->bind_param('i', $lid); $gl->execute(); $glr = $gl->get_result()->fetch_row(); $gl->close();
$googleLinked = (bool)$glr; $googleEmail = $glr[0] ?? '';
$ps = $conn->prepare("SELECT password_set, email FROM landlords WHERE id = ?"); $ps->bind_param('i', $lid); $ps->execute(); $psr = $ps->get_result()->fetch_row(); $ps->close();
$passwordSet = (int)($psr[0] ?? 1) === 1; $acctEmail = $psr[1] ?? '';
$pageTitle = 'Profile'; $active = 'profile';
require __DIR__ . '/includes/landlord_header.php';
?>
<h1>Your profile</h1>
<div class="two-col">
  <form class="card pad" method="post">
    <h2>Contact details</h2>
    <?= csrf_field() ?><input type="hidden" name="action" value="profile">
    <div class="field"><label for="fn">Full name</label><input id="fn" name="fullname" value="<?= e($l['fullname']) ?>" maxlength="100" required></div>
    <div class="field"><label for="em">Email (used to log in)</label><input id="em" value="<?= e($l['email']) ?>" disabled></div>
    <div class="field"><label for="ph">Phone number (optional)</label><input id="ph" type="tel" name="phone" value="<?= e($l['phone']) ?>" maxlength="20" placeholder="09XX XXX XXXX"></div>
    <div class="field"><label for="fb">Facebook profile or page link (optional)</label><input id="fb" type="url" name="facebook_url" value="<?= e($l['facebook_url']) ?>" maxlength="255" placeholder="https://facebook.com/yourpage"></div>
    <p class="muted">Saved once here. Students see these details on every property you own. Leave a field empty to hide it.</p>
    <button class="btn" type="submit">Save changes</button>
  </form>
  <div>
    <div class="card pad"><h2>Security</h2>
      <p><a class="btn btn-small" href="change-password.php">Change password</a></p>
      <p class="muted">We email a one-time code before the password changes.</p>
      <h2 style="margin-top:18px">Google account</h2>
      <?php if ($googleLinked): ?>
        <p>Connected as <strong><?= e($googleEmail) ?></strong></p>
        <form method="post" action="google-unlink.php" data-confirm="Disconnect your Google account?"><?= csrf_field() ?><button class="btn btn-small btn-ghost" type="submit">Disconnect Google</button></form>
        <?php if (!$passwordSet): ?><p class="muted">You sign in with Google, so your Google password is managed by Google. To recover it, use <a href="https://accounts.google.com/signin/recovery" target="_blank" rel="noopener noreferrer">Google Account Recovery</a>. You can also create a HomeRent password with <a href="forgot-password.php">Forgot password</a> (needed before you can disconnect Google).</p><?php endif; ?>
      <?php else: ?>
        <p class="muted">Connect the Google account that uses <?= e($acctEmail) ?> to log in with one click.</p>
        <a class="google-btn" href="google-start.php?intent=link"><svg viewBox="0 0 48 48" width="20" height="20" aria-hidden="true"><path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/><path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/><path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/><path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/></svg><span>Connect Google account</span></a>
      <?php endif; ?>
    </div>
    <div class="card pad"><h2>Quick links</h2>
      <p><a href="manage-properties.php">My properties</a></p><p><a href="landlord-requests.php">Viewing requests</a></p><p><a href="landlord-messages.php">Messages</a></p>
      <p><a class="btn btn-ghost" href="logout.php">Log out</a></p></div>
  </div>
</div>
<?php require __DIR__ . '/includes/landlord_footer.php'; ?>
