<?php
// TEMPORARY diagnostic page. Delete it when login works.
require_once __DIR__ . '/includes/auth.php';
header('Content-Type: text/plain; charset=utf-8');
echo "Session ID: " . session_id() . "\n";
echo "Session save path: " . session_save_path() . "\n";
echo "Logged in user_id: " . ($_SESSION['user_id'] ?? '(none)') . "\n";
echo "Role: " . ($_SESSION['role'] ?? '(none)') . "\n";
echo "Name: " . ($_SESSION['name'] ?? '(none)') . "\n";
$r = $conn->query("SHOW COLUMNS FROM students LIKE 'account_status'");
echo "students.account_status column: " . ($r->num_rows ? 'exists' : 'MISSING - run the SQL script') . "\n";
$r = $conn->query("SELECT COUNT(*) FROM students");
echo "Students in database: " . $r->fetch_row()[0] . "\n";
