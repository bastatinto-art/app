<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/property_card.php';
require_role('student');
$sid = (int)$_SESSION['user_id'];

$types = ['boarding_house', 'apartment', 'dormitory', 'bedspace'];
$q        = trim($_GET['q'] ?? '');
$location = trim($_GET['location'] ?? '');
$minRaw   = trim($_GET['min_price'] ?? '');
$maxRaw   = trim($_GET['max_price'] ?? '');
$type     = $_GET['type'] ?? '';
$avail    = $_GET['availability'] ?? 'available';
$selAm    = array_values(array_unique(array_map('intval', (array)($_GET['amenities'] ?? []))));
$errors   = [];

$min = $max = null;
if ($minRaw !== '') { if (!is_numeric($minRaw) || $minRaw < 0) $errors[] = 'Minimum price must be a positive number.'; else $min = (float)$minRaw; }
if ($maxRaw !== '') { if (!is_numeric($maxRaw) || $maxRaw < 0) $errors[] = 'Maximum price must be a positive number.'; else $max = (float)$maxRaw; }
if ($min !== null && $max !== null && $min > $max) $errors[] = 'Minimum price cannot be higher than the maximum price.';
if ($type !== '' && !in_array($type, $types, true)) { $type = ''; }
if (!in_array($avail, ['available', 'unavailable', 'rented', 'any'], true)) $avail = 'available';

$where = [PUBLIC_WHERE]; $params = []; $bind = '';
if (!$errors) {
    if ($q !== '')        { $where[] = "(p.name LIKE ? OR p.description LIKE ?)"; $like = "%$q%"; array_push($params, $like, $like); $bind .= 'ss'; }
    if ($location !== '') { $where[] = "p.address LIKE ?"; $params[] = "%$location%"; $bind .= 's'; }
    if ($min !== null)    { $where[] = "p.monthly_price >= ?"; $params[] = $min; $bind .= 'd'; }
    if ($max !== null)    { $where[] = "p.monthly_price <= ?"; $params[] = $max; $bind .= 'd'; }
    if ($type !== '')     { $where[] = "p.property_type = ?"; $params[] = $type; $bind .= 's'; }
    if ($avail !== 'any') { $where[] = "p.availability = ?"; $params[] = $avail; $bind .= 's'; }
    if ($selAm) {
        $in = implode(',', array_fill(0, count($selAm), '?'));
        $where[] = "p.id IN (SELECT property_id FROM property_amenities WHERE amenity_id IN ($in) GROUP BY property_id HAVING COUNT(DISTINCT amenity_id) = ?)";
        foreach ($selAm as $a) { $params[] = $a; $bind .= 'i'; }
        $params[] = count($selAm); $bind .= 'i';
    }
}

$results = [];
if (!$errors) {
    $st = $conn->prepare(PROPERTY_SELECT . " WHERE " . implode(' AND ', $where) . " ORDER BY p.created_at DESC LIMIT 60");
    if ($params) $st->bind_param($bind, ...$params);
    $st->execute();
    $results = $st->get_result()->fetch_all(MYSQLI_ASSOC);
    $st->close();
}
$amenityList = $conn->query("SELECT id, name FROM amenities ORDER BY name")->fetch_all(MYSQLI_ASSOC);
$favIds = fetch_favorite_ids($conn, $sid);

$self = 'student-properties.php' . ($_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : '');
$_SESSION['last_search'] = $self;
$returnTo = safe_local_url($self, 'student-properties.php');

$pageTitle = 'Find a place'; $active = 'search';
require __DIR__ . '/includes/student_header.php';
?>
<section class="page-hero">
  <h1><?= ui_icon('home') ?>Find a place</h1>
  <p>Discover the best rental spaces near your school in Barangay Mateuna.</p>
  <span class="tagline">Your next home is just a search away!</span>
</section>

<form class="card filters-card" method="get" action="student-properties.php">
  <div class="filters-head"><?= ui_icon('filter') ?>Search</div>
  <div class="filter-grid">
    <div class="field span2"><label for="q">Keyword</label>
      <div class="input-ico"><?= ui_icon('search') ?><input id="q" type="search" name="q" value="<?= e($q) ?>" placeholder="Name or description"></div></div>
    <div class="field span2"><label for="location">Location</label>
      <div class="input-ico"><?= ui_icon('pin') ?><input id="location" type="text" name="location" value="<?= e($location) ?>" placeholder="Street or purok"></div></div>
    <div class="field"><label for="min">Min price (₱/month)</label>
      <div class="input-ico"><?= ui_icon('cash') ?><input id="min" type="number" min="0" step="100" name="min_price" value="<?= e($minRaw) ?>" placeholder="e.g. 2000"></div></div>
    <div class="field"><label for="max">Max price (₱/month)</label>
      <div class="input-ico"><?= ui_icon('cash') ?><input id="max" type="number" min="0" step="100" name="max_price" value="<?= e($maxRaw) ?>" placeholder="e.g. 5000"></div></div>
    <div class="field"><label for="type">Property type</label>
      <div class="input-ico"><?= ui_icon('home') ?><select id="type" name="type"><option value="">All types</option>
        <?php foreach ($types as $t): ?><option value="<?= $t ?>" <?= $type === $t ? 'selected' : '' ?>><?= e(type_label($t)) ?></option><?php endforeach; ?>
      </select></div></div>
    <div class="field"><label for="av">Availability</label>
      <div class="input-ico"><?= ui_icon('requests') ?><select id="av" name="availability">
        <?php foreach (['available' => 'Available', 'unavailable' => 'Unavailable', 'rented' => 'Rented', 'any' => 'Any'] as $v => $l): ?>
          <option value="<?= $v ?>" <?= $avail === $v ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?>
      </select></div></div>
    <?php if ($amenityList): ?>
    <fieldset class="field wide amenity-set"><legend>Amenities (must have all selected)</legend>
      <?php foreach ($amenityList as $a): ?>
        <label class="chk-pill"><input type="checkbox" name="amenities[]" value="<?= (int)$a['id'] ?>" <?= in_array((int)$a['id'], $selAm, true) ? 'checked' : '' ?>> <?= e($a['name']) ?></label>
      <?php endforeach; ?>
    </fieldset>
    <?php endif; ?>
  </div>
  <div class="filter-actions">
    <button class="btn" type="submit" data-nobusy><?= ui_icon('filter') ?>Apply Filters</button>
    <a class="btn btn-ghost" href="student-properties.php"><?= ui_icon('refresh') ?>Clear Filters</a>
  </div>
</form>

<?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>

<?php if (!$errors): ?>
  <p class="result-count"><?= count($results) ?> result<?= count($results) === 1 ? '' : 's' ?></p>
  <?php if (!$results): ?>
    <div class="empty"><p>No properties match your filters.</p><p>Try a higher budget, fewer amenities, or clear the filters.</p>
      <a class="btn btn-ghost" href="student-properties.php">Clear Filters</a></div>
  <?php else: ?>
    <div class="grid cards-h">
      <?php foreach ($results as $p) render_property_card($p, in_array((int)$p['id'], $favIds, true), $returnTo); ?>
    </div>
  <?php endif; ?>
<?php endif; ?>
<?php require __DIR__ . '/includes/student_footer.php'; ?>
