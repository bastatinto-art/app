<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/session_login.php';
$role = $_SESSION['role'] ?? '';
if (!in_array($role, ['student', 'landlord'], true)) { header('Location: login.html?err=login_required'); exit; }
require_role($role);
$profile = $role === 'landlord' ? 'landlord-profile.php' : 'student-profile.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: ' . $profile); exit; }
csrf_verify();
$uid = (int)$_SESSION['user_id']; $table = hr_table($role);
$q = $conn->prepare("SELECT password_set FROM $table WHERE id = ?"); $q->bind_param('i', $uid); $q->execute();
$hasPw = (int)($q->get_result()->fetch_row()[0] ?? 0) === 1; $q->close();
if (!$hasPw) { flash_set('error', 'Create a HomeRent password first (use "Forgot password"), so you still have a way to log in.'); header('Location: ' . $profile); exit; }
$d = $conn->prepare("DELETE FROM oauth_links WHERE provider = 'google' AND account_type = ? AND account_id = ?");
$d->bind_param('si', $role, $uid); $d->execute();
flash_set('success', 'Google account disconnected.');
header('Location: ' . $profile);
