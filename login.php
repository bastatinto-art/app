<?php
// Student / Landlord login. Administrators use /admin/ (completely separate).
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/session_login.php';
require_once __DIR__ . '/includes/otp.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: login.html'); exit; }

$email    = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
if ($email === '' || $password === '') { header('Location: login.html?err=empty'); exit; }

$sources = [['table' => 'students', 'role' => 'student'], ['table' => 'landlords', 'role' => 'landlord']];

foreach ($sources as $s) {
    $stmt = $conn->prepare("SELECT id, fullname, email, password, account_status, email_verified, avatar_url FROM {$s['table']} WHERE email = ? LIMIT 1");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $u = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($u && password_verify($password, $u['password'])) {
        if ($u['account_status'] !== 'active') { header('Location: login.html?err=inactive'); exit; }
        if ((int)$u['email_verified'] !== 1) {          // correct password but email not verified yet -> finish verification
            $_SESSION['pending_verify'] = ['type' => $s['role'], 'id' => (int)$u['id'], 'email' => $u['email'], 'name' => $u['fullname']];
            if (otp_wait_seconds($conn, 'register', $s['role'], (int)$u['id']) === 0 && !otp_has_active($conn, 'register', $s['role'], (int)$u['id'])) {
                otp_issue($conn, 'register', $s['role'], (int)$u['id'], $u['email'], $u['fullname']);
            }
            header('Location: verify-email.php'); exit;
        }
        if (password_needs_rehash($u['password'], PASSWORD_DEFAULT)) {
            $new = password_hash($password, PASSWORD_DEFAULT);
            $up = $conn->prepare("UPDATE {$s['table']} SET password = ? WHERE id = ?");
            $up->bind_param('si', $new, $u['id']); $up->execute(); $up->close();
        }
        header('Location: ' . hr_login($s['role'], (int)$u['id'], $u['fullname'], $u['avatar_url']));
        exit;
    }
}
header('Location: login.html?err=invalid');
exit;
