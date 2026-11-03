<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/property_card.php';
require_role('student');
$sid = (int)$_SESSION['user_id'];

$st = $conn->prepare(PROPERTY_SELECT . " JOIN favorites f ON f.property_id = p.id
                      WHERE f.student_id = ? AND " . PUBLIC_WHERE . " ORDER BY f.created_at DESC");
$st->bind_param('i', $sid); $st->execute();
$rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Favorites'; $active = 'favorites';
require __DIR__ . '/includes/student_header.php';
?>
<h1>Your favorites</h1>
<?php if (!$rows): ?>
  <div class="empty"><p>You haven't saved any properties yet.</p><a class="btn" href="student-properties.php">Find a place</a></div>
<?php else: ?>
  <div class="grid"><?php foreach ($rows as $p) render_property_card($p, true, 'favorites.php'); ?></div>
<?php endif; ?>
<?php require __DIR__ . '/includes/student_footer.php'; ?>
