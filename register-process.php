<?php
// Registration: creates an UNVERIFIED account, emails a code, and sends the user to verify-email.php.
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/otp.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: login.html?panel=signup'); exit; }

$fullname    = trim($_POST['fullname'] ?? '');
$email       = trim($_POST['email'] ?? '');
$password    = $_POST['password'] ?? '';
$accountType = $_POST['account_type'] ?? '';
$back = fn(string $m) => header('Location: login.html?panel=signup&rmsg=' . $m);

if ($fullname === '' || mb_strlen($fullname) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150
    || strlen($password) < 8 || !in_array($accountType, ['student', 'landlord'], true)) { $back('invalid'); exit; }

$table = $accountType === 'student' ? 'students' : 'landlords';
$other = $accountType === 'student' ? 'landlords' : 'students';
$id = 0;

// Email must not be used by a verified account (either table)
$c = $conn->prepare("SELECT 1 FROM $other WHERE email = ? LIMIT 1"); $c->bind_param('s', $email); $c->execute();
if ($c->get_result()->num_rows > 0) { $back('email_exists'); exit; } $c->close();
$c = $conn->prepare("SELECT id, email_verified FROM $table WHERE email = ? LIMIT 1"); $c->bind_param('s', $email); $c->execute();
$existing = $c->get_result()->fetch_assoc(); $c->close();

$hash = password_hash($password, PASSWORD_DEFAULT);
try {
    if ($existing && (int)$existing['email_verified'] === 1) { $back('email_exists'); exit; }
    if ($existing) {                    // earlier sign-up that was never verified: let the new submission replace it (still needs the emailed code)
        $id = (int)$existing['id'];
        $u = $conn->prepare("UPDATE $table SET fullname = ?, password = ?, password_set = 1 WHERE id = ? AND email_verified = 0");
        $u->bind_param('ssi', $fullname, $hash, $id); $u->execute();
    } else {
        $i = $conn->prepare("INSERT INTO $table (fullname, email, password, email_verified) VALUES (?, ?, ?, 0)");
        $i->bind_param('sss', $fullname, $email, $hash); $i->execute();
        $id = (int)$conn->insert_id;
    }
} catch (mysqli_sql_exception $e) { error_log('register: ' . $e->getMessage()); $back($e->getCode() === 1062 ? 'email_exists' : 'error'); exit; }

$_SESSION['pending_verify'] = ['type' => $accountType, 'id' => $id, 'email' => $email, 'name' => $fullname];
$r = otp_issue($conn, 'register', $accountType, $id, $email, $fullname);
if (!$r['ok'] && $r['reason'] === 'mail') flash_set('error', 'We created your account but could not send the email. Use "Resend code" below, or check the email settings.');
header('Location: verify-email.php');
exit;
