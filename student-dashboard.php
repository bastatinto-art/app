<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/property_card.php';
require_role('student');
$sid = (int)$_SESSION['user_id'];

function count_q(mysqli $conn, string $sql, int $sid): int {
    $st = $conn->prepare($sql); $st->bind_param('i', $sid); $st->execute();
    $n = (int)$st->get_result()->fetch_row()[0]; $st->close(); return $n;
}
$nFav  = count_q($conn, "SELECT COUNT(*) FROM favorites WHERE student_id = ?", $sid);
$nPend = count_q($conn, "SELECT COUNT(*) FROM viewing_requests WHERE student_id = ? AND status = 'pending'", $sid);
$nApp  = count_q($conn, "SELECT COUNT(*) FROM viewing_requests WHERE student_id = ? AND status = 'approved'", $sid);
$nInq  = count_q($conn, "SELECT COUNT(*) FROM conversations WHERE student_id = ?", $sid);

// Category = the existing properties.property_type column (apartment, bedspace, dormitory, boarding_house)
$categories = [
    'apartment'      => ['Apartment',      '<path d="M4 21V5a1 1 0 0 1 1-1h8a1 1 0 0 1 1 1v16"/><path d="M14 9h5a1 1 0 0 1 1 1v11"/><path d="M8 8h2M8 12h2M8 16h2M17 13h1M17 17h1"/><path d="M3 21h18"/>'],
    'bedspace'       => ['Bed Spacer',     '<path d="M3 18V7"/><path d="M3 14h18v4"/><path d="M21 14v-2a3 3 0 0 0-3-3h-7v5"/><circle cx="7" cy="11" r="1.6"/>'],
    'dormitory'      => ['Dormitory',      '<path d="M3 21h18"/><path d="M5 21V8l7-4 7 4v13"/><path d="M9 21v-5h6v5"/><path d="M9 11h1.5M13.5 11H15"/>'],
    'boarding_house' => ['Boarding House', '<path d="M3 11l9-7 9 7"/><path d="M5 10v11h14V10"/><path d="M10 21v-6h4v6"/>'],
];
$category = $_GET['category'] ?? '';
if (!isset($categories[$category])) $category = '';

$catCounts = array_fill_keys(array_keys($categories), 0);
$cres = $conn->query("SELECT p.property_type, COUNT(*) AS n FROM properties p WHERE " . PUBLIC_WHERE . " AND p.availability = 'available' GROUP BY p.property_type");
foreach ($cres->fetch_all(MYSQLI_ASSOC) as $cr) if (isset($catCounts[$cr['property_type']])) $catCounts[$cr['property_type']] = (int)$cr['n'];
$totalAvail = array_sum($catCounts);

$sql = PROPERTY_SELECT . " WHERE " . PUBLIC_WHERE . " AND p.availability = 'available'";
if ($category !== '') $sql .= " AND p.property_type = ?";
$sql .= " ORDER BY p.created_at DESC LIMIT 6";
$rs = $conn->prepare($sql);
if ($category !== '') $rs->bind_param('s', $category);
$rs->execute();
$recent = $rs->get_result()->fetch_all(MYSQLI_ASSOC);
$returnTo = 'student-dashboard.php' . ($category !== '' ? '?category=' . $category : '');
$favIds = fetch_favorite_ids($conn, $sid);

$pageTitle = 'Dashboard'; $active = 'dashboard';
$extraHead = '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css"><link rel="stylesheet" href="assets/css/map.css">';
require __DIR__ . '/includes/student_header.php';
require_once __DIR__ . '/includes/site_settings.php';
// Photos uploaded by the admin (Admin > Category Images). No photo uploaded = a plain "not set yet" panel.
$photoAttr = function (string $k) use ($conn): string {
    $u = site_image_url($conn, 'cat_img_' . $k);
    return $u ? 'class="cat-photo" style="background-image:url(\'' . e($u) . '\')" role="img" aria-label="Category photo"' : 'class="cat-photo no-photo"';
};
?>
<section class="hero">
  <h1>Welcome, <?= e(explode(' ', trim($_SESSION['name']))[0]) ?>! &#128075;</h1>
  <p>Find a rental space near school in Barangay Mateuna.</p>
  <form class="quick-search" action="student-properties.php" method="get">
    <?php if ($category !== ''): ?><input type="hidden" name="type" value="<?= e($category) ?>"><?php endif; ?>
    <input type="search" name="q" placeholder="Search by name or keyword..." aria-label="Search properties">
    <button class="btn" type="submit"><?= ui_icon('search') ?>Search</button>
  </form>
  <span class="tagline">Your next home is just a click away!</span>
</section>

<section class="stats">
  <a class="stat has-ico" href="favorites.php"><span class="stat-ico"><?= ui_icon('home') ?></span><span class="stat-text"><strong><?= $nFav ?></strong><span>Saved properties</span></span><span class="stat-chev"><?= ui_icon('chev') ?></span></a>
  <a class="stat has-ico" href="viewing-requests.php"><span class="stat-ico"><?= ui_icon('eye') ?></span><span class="stat-text"><strong><?= $nPend ?></strong><span>Pending viewings</span></span><span class="stat-chev"><?= ui_icon('chev') ?></span></a>
  <a class="stat has-ico" href="viewing-requests.php"><span class="stat-ico"><?= ui_icon('requests') ?></span><span class="stat-text"><strong><?= $nApp ?></strong><span>Approved viewings</span></span><span class="stat-chev"><?= ui_icon('chev') ?></span></a>
  <a class="stat has-ico" href="student-messages.php"><span class="stat-ico"><?= ui_icon('chat') ?></span><span class="stat-text"><strong><?= $nInq ?></strong><span>Conversations</span></span><span class="stat-chev"><?= ui_icon('chev') ?></span></a>
</section>

<section class="browse-cat" id="browse">
  <h2 class="section-title"><?= ui_icon('dashboard') ?>Browse by Category</h2>
  <div class="cat-grid">
    <a class="cat-card cat-all <?= $category === '' ? 'active' : '' ?>" href="student-dashboard.php#recent" <?= $category === '' ? 'aria-current="true"' : '' ?>>
      <div <?= $photoAttr('all') ?>><span class="cat-ico"><?= ui_icon('home') ?></span></div>
      <div class="cat-text"><strong>All Categories</strong><small><?= $totalAvail ?> available</small><?= ui_icon('chev') ?></div>
    </a>
    <?php foreach ($categories as $key => [$label, $iconPath]): ?>
      <a class="cat-card cat-<?= $key ?> <?= $category === $key ? 'active' : '' ?>" href="student-dashboard.php?category=<?= $key ?>#recent" <?= $category === $key ? 'aria-current="true"' : '' ?>>
        <div <?= $photoAttr($key) ?>><span class="cat-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?= $iconPath ?></svg></span></div>
        <div class="cat-text"><strong><?= e($label) ?></strong><small><?= $catCounts[$key] ?> available</small><?= ui_icon('chev') ?></div>
      </a>
    <?php endforeach; ?>
  </div>
</section>

<section class="card map-card" style="margin-top:22px">
  <div class="item-head"><h2 class="section-title" style="margin:0"><?= ui_icon('pin') ?>Rentals around Tayabas City</h2><a class="btn btn-small" href="student-map.php"><?= ui_icon('map') ?>Open full map</a></div>
  <p class="map-status" id="dashMapStatus" aria-live="polite"></p>
  <div id="dashMap" class="map-box"></div>
</section>

<h2 class="section-title" id="recent"><?= $category === '' ? 'Recently added' : e($categories[$category][0]) . ' listings' ?></h2>
<?php if (!$recent): ?>
  <div class="empty">
    <?php if ($category === ''): ?><p>No available listings yet.</p><p>Landlords' approved listings will appear here.</p>
    <?php else: ?><p>No available <?= e(strtolower($categories[$category][0])) ?> listings right now.</p><a class="btn btn-ghost" href="student-dashboard.php#recent">Show all categories</a><?php endif; ?>
  </div>
<?php else: ?>
  <div class="grid">
    <?php foreach ($recent as $p) render_property_card($p, in_array((int)$p['id'], $favIds, true), $returnTo); ?>
  </div>
  <p class="center"><a class="btn btn-ghost" href="student-properties.php<?= $category !== '' ? '?type=' . e($category) : '' ?>"><?= $category === '' ? 'Browse all properties' : 'Search all ' . e(strtolower($categories[$category][0])) . ' listings' ?></a></p>
<?php endif; ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
<script src="assets/js/hr-map.js"></script>
<script>HRMap.studentMap({ el: 'dashMap', statusEl: 'dashMapStatus', extraParams: <?= json_encode($category !== '' ? 'type[]=' . $category : '') ?> });</script>
<?php require __DIR__ . '/includes/student_footer.php'; ?>
