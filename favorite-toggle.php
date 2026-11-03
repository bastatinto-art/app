<?php
require_once __DIR__ . '/includes/auth.php';
require_role('student');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: favorites.php'); exit; }
csrf_verify();

$sid = (int)$_SESSION['user_id'];
$pid = (int)($_POST['property_id'] ?? 0);
$ret = safe_local_url($_POST['return'] ?? '', 'favorites.php');

$st = $conn->prepare("SELECT 1 FROM favorites WHERE student_id = ? AND property_id = ?");
$st->bind_param('ii', $sid, $pid); $st->execute();
$exists = $st->get_result()->num_rows > 0; $st->close();

if ($exists) {
    $d = $conn->prepare("DELETE FROM favorites WHERE student_id = ? AND property_id = ?");
    $d->bind_param('ii', $sid, $pid); $d->execute();
    flash_set('success', 'Removed from your favorites.');
} else {
    // Only listings students can actually see may be saved
    $c = $conn->prepare("SELECT 1 FROM properties p WHERE p.id = ? AND " . "p.approval_status = 'approved' AND p.is_archived = 0");
    $c->bind_param('i', $pid); $c->execute();
    if ($c->get_result()->num_rows === 0) {
        flash_set('error', 'That property is no longer available.');
    } else {
        $i = $conn->prepare("INSERT IGNORE INTO favorites (student_id, property_id) VALUES (?, ?)");
        $i->bind_param('ii', $sid, $pid); $i->execute();
        flash_set('success', 'Saved to your favorites.');
    }
}
header('Location: ' . $ret);
exit;
