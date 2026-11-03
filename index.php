<?php
// http://localhost/<project>/admin/  ->  login page, or the dashboard if already logged in.
require_once __DIR__ . '/includes/auth.php';
header('Location: ' . (admin_logged_in($conn) ? 'dashboard.php' : 'login.php'));
exit;
