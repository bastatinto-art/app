<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/property_card.php';
require_once __DIR__ . '/includes/messages_lib.php';
require_once __DIR__ . '/includes/contact.php';
require_role('student');
$sid = (int)$_SESSION['user_id'];
$pid = (int)($_GET['id'] ?? $_POST['property_id'] ?? 0);

// Visible listing only (approved, not archived)
$st = $conn->prepare("SELECT p.*, l.fullname AS landlord_name, l.phone AS landlord_phone, l.facebook_url AS landlord_facebook FROM properties p
                      LEFT JOIN landlords l ON l.id = p.landlord_id
                      WHERE p.id = ? AND " . PUBLIC_WHERE);
$st->bind_param('i', $pid); $st->execute();
$p = $st->get_result()->fetch_assoc(); $st->close();

if (!$p) {
    http_response_code(404);
    $pageTitle = 'Not found'; $active = 'search';
    require __DIR__ . '/includes/student_header.php';
    echo '<div class="empty"><p>This property is not available or no longer exists.</p><a class="btn" href="student-properties.php">Back to search</a></div>';
    require __DIR__ . '/includes/student_footer.php'; exit;
}

$self = 'property-details.php?id=' . $pid;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'open_chat') {   // "Send Message" button: open (or create) THIS student's conversation for THIS property
        $cid = get_or_create_conversation($conn, $sid, (int)$p['landlord_id'], $pid);
        header('Location: student-messages.php?c=' . $cid); exit;
    }

    if ($action === 'inquiry') {
        $msg = trim($_POST['message'] ?? '');
        if (mb_strlen($msg) < 10 || mb_strlen($msg) > 1000) {
            flash_set('error', 'Your message must be between 10 and 1000 characters.');
        } else {
            $cid = get_or_create_conversation($conn, $sid, (int)$p['landlord_id'], $pid);
            add_message($conn, $cid, 'student', $sid, $msg);
            flash_set('success', 'Message sent. Continue the conversation here.');
            header('Location: student-messages.php?c=' . $cid); exit;
        }
    } elseif ($action === 'viewing') {
        $date = $_POST['preferred_date'] ?? ''; $time = $_POST['preferred_time'] ?? '';
        $note = trim($_POST['message'] ?? '');
        $d = DateTime::createFromFormat('Y-m-d', $date);
        $t = DateTime::createFromFormat('H:i', $time);
        $today = new DateTime('today'); $limit = (new DateTime('today'))->modify('+60 days');
        if ($p['availability'] !== 'available') {
            flash_set('error', 'This property is not available for viewing right now.');
        } elseif (!$d || $d->format('Y-m-d') !== $date || !$t || $t->format('H:i') !== $time) {
            flash_set('error', 'Please choose a valid date and time.');
        } elseif ($d <= $today || $d > $limit) {
            flash_set('error', 'Choose a date from tomorrow up to 60 days ahead.');
        } elseif ($time < '08:00' || $time > '18:00') {
            flash_set('error', 'Viewing time must be between 8:00 AM and 6:00 PM.');
        } elseif (mb_strlen($note) > 500) {
            flash_set('error', 'Your note must be 500 characters or fewer.');
        } else {
            $c = $conn->prepare("SELECT 1 FROM viewing_requests WHERE student_id = ? AND property_id = ? AND status IN ('pending','approved')");
            $c->bind_param('ii', $sid, $pid); $c->execute();
            if ($c->get_result()->num_rows > 0) {
                flash_set('error', 'You already have an active viewing request for this property.');
            } else {
                $tt = $time . ':00';
                $i = $conn->prepare("INSERT INTO viewing_requests (property_id, student_id, preferred_date, preferred_time, message) VALUES (?, ?, ?, ?, ?)");
                $i->bind_param('iisss', $pid, $sid, $date, $tt, $note); $i->execute();
                flash_set('success', 'Viewing request sent. The landlord will respond soon.');
            }
        }
    }
    header('Location: ' . $self); exit;
}

$imgs = $conn->prepare("SELECT file_path FROM property_images WHERE property_id = ? ORDER BY is_primary DESC, sort_order, id");
$imgs->bind_param('i', $pid); $imgs->execute();
$images = array_column($imgs->get_result()->fetch_all(MYSQLI_ASSOC), 'file_path');

$am = $conn->prepare("SELECT a.name FROM property_amenities pa JOIN amenities a ON a.id = pa.amenity_id WHERE pa.property_id = ? ORDER BY a.name");
$am->bind_param('i', $pid); $am->execute();
$amenities = array_column($am->get_result()->fetch_all(MYSQLI_ASSOC), 'name');

$isFav = in_array($pid, fetch_favorite_ids($conn, $sid), true);
$back = safe_local_url($_SESSION['last_search'] ?? '', 'student-properties.php');
$minDate = (new DateTime('tomorrow'))->format('Y-m-d');
$maxDate = (new DateTime('today'))->modify('+60 days')->format('Y-m-d');

$hasCoords = is_numeric($p['latitude'] ?? null) && is_numeric($p['longitude'] ?? null)
    && abs((float)$p['latitude']) <= 90 && abs((float)$p['longitude']) <= 180;
$typeColors = ['boarding_house' => '#0b3a8c', 'apartment' => '#2563eb', 'dormitory' => '#3b82f6', 'bedspace' => '#7cb8ff'];
$phone = trim((string)($p['landlord_phone'] ?? ''));
$facebook = trim((string)($p['landlord_facebook'] ?? ''));
$pageTitle = $p['name']; $active = 'search';
$extraHead = $hasCoords ? '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css"><link rel="stylesheet" href="assets/css/map.css">' : ''; 
require __DIR__ . '/includes/student_header.php';
?>
<p><a href="<?= e($back) ?>">&larr; Back to search results</a></p>
<div class="detail">
  <section>
    <?php if ($images): ?>
      <div class="gallery">
        <img id="mainImg" src="<?= e($images[0]) ?>" alt="<?= e($p['name']) ?>">
        <?php if (count($images) > 1): ?>
        <div class="thumbs">
          <?php foreach ($images as $k => $src): ?>
            <button type="button" class="thumb" data-src="<?= e($src) ?>" data-nobusy aria-label="Photo <?= $k + 1 ?>"><img src="<?= e($src) ?>" alt=""></button>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    <?php else: ?><div class="gallery noimg">No photos uploaded yet</div><?php endif; ?>

    <h1><?= e($p['name']) ?></h1>
    <p><span class="cat-badge">Category: <?= e(type_label($p['property_type'])) ?></span></p>
    <p class="price big"><?= peso($p['monthly_price']) ?><small> / month</small></p>
    <p class="meta"><?= e(type_label($p['property_type'])) ?> &middot; <?= e($p['address']) ?></p>
    <p><span class="badge badge-<?= e($p['availability']) ?> inline"><?= e(ucfirst($p['availability'])) ?></span>
       <?= (int)$p['rooms_available'] ?> room/bedspace<?= (int)$p['rooms_available'] === 1 ? '' : 's' ?> open
       &middot; Listed by <?= e($p['landlord_name'] ?? 'Landlord') ?></p>

    <section class="loc-section" id="location">
      <h2>Property Location</h2>
      <p class="meta"><?= e($p['address']) ?></p>
      <?php if ($hasCoords): ?>
        <div id="propMap" class="prop-map" data-lat="<?= e((float)$p['latitude']) ?>" data-lng="<?= e((float)$p['longitude']) ?>"
             data-name="<?= e($p['name']) ?>" data-address="<?= e($p['address']) ?>" data-color="<?= e($typeColors[$p['property_type']] ?? '#2563eb') ?>"></div>
        <p class="muted"><a href="https://www.openstreetmap.org/?mlat=<?= e((float)$p['latitude']) ?>&amp;mlon=<?= e((float)$p['longitude']) ?>#map=18/<?= e((float)$p['latitude']) ?>/<?= e((float)$p['longitude']) ?>" target="_blank" rel="noopener noreferrer">Open in OpenStreetMap</a></p>
      <?php else: ?>
        <div class="loc-unavailable">Location map is currently unavailable because this property has not been assigned a map location.</div>
      <?php endif; ?>
    </section>

    <?php if ($p['description']): ?><h2>About this place</h2><p class="prewrap"><?= e($p['description']) ?></p><?php endif; ?>
    <?php if ($amenities): ?><h2>Amenities</h2><ul class="tags"><?php foreach ($amenities as $a): ?><li><?= e($a) ?></li><?php endforeach; ?></ul><?php endif; ?>
    <?php if ($p['house_rules']): ?><h2>House rules</h2><p class="prewrap"><?= e($p['house_rules']) ?></p><?php endif; ?>
    <?php if ($p['requirements']): ?><h2>Rental requirements</h2><p class="prewrap"><?= e($p['requirements']) ?></p><?php endif; ?>
    <p class="muted">Last updated <?= e(date('M j, Y', strtotime($p['updated_at']))) ?></p>
  </section>

  <aside>
    <div class="card pad">
      <form method="post" action="favorite-toggle.php">
        <?= csrf_field() ?>
        <input type="hidden" name="property_id" value="<?= $pid ?>">
        <input type="hidden" name="return" value="<?= e($self) ?>">
        <button class="btn btn-block <?= $isFav ? 'btn-ghost' : '' ?>" type="submit" data-nobusy><?= $isFav ? 'Remove from favorites' : 'Add to favorites' ?></button>
      </form>
    </div>

    <div class="card pad contact-card">
      <h2>Contact Landlord</h2>
      <p class="contact-name"><?= e($p['landlord_name'] ?? 'Landlord') ?></p>
      <?php if ($phone !== ''): ?><p class="contact-line"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 1.9.7 2.8a2 2 0 0 1-.5 2.1L8 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.8.7a2 2 0 0 1 1.7 2z"/></svg><?= e($phone) ?></p><?php endif; ?>
      <?php if ($facebook !== ''): ?><p class="contact-line"><svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M13.5 22v-8h2.7l.5-3.2h-3.2V8.7c0-.9.4-1.7 1.8-1.7h1.5V4.2c-.3 0-1.2-.2-2.2-.2-2.3 0-3.9 1.4-3.9 4v2.4H8v3.2h2.7v8z"/></svg><?= e($facebook) ?></p><?php endif; ?>
      <?php if ($phone === '' && $facebook === ''): ?><p class="muted">This landlord has not added a phone number or Facebook link. You can still send a message.</p><?php endif; ?>
      <div class="contact-actions">
        <?php if ($phone !== ''): ?>
          <a class="btn btn-call btn-ico" href="<?= e(tel_href($phone)) ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 1.9.7 2.8a2 2 0 0 1-.5 2.1L8 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.8.7a2 2 0 0 1 1.7 2z"/></svg>Call Landlord</a>
        <?php endif; ?>
        <?php if ($facebook !== ''): ?>
          <a class="btn btn-fb btn-ico" href="<?= e($facebook) ?>" target="_blank" rel="noopener noreferrer"><svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M13.5 22v-8h2.7l.5-3.2h-3.2V8.7c0-.9.4-1.7 1.8-1.7h1.5V4.2c-.3 0-1.2-.2-2.2-.2-2.3 0-3.9 1.4-3.9 4v2.4H8v3.2h2.7v8z"/></svg>Facebook</a>
        <?php endif; ?>
        <form method="post" action="<?= e($self) ?>">
          <?= csrf_field() ?><input type="hidden" name="action" value="open_chat"><input type="hidden" name="property_id" value="<?= $pid ?>">
          <button class="btn btn-ico" type="submit" data-nobusy><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>Send Message</button>
        </form>
      </div>
    </div>

    <div class="card pad">
      <h2>Request a viewing</h2>
      <?php if ($p['availability'] !== 'available'): ?>
        <p class="muted">Viewings are closed while this property is <?= e($p['availability']) ?>.</p>
      <?php else: ?>
      <form method="post" action="<?= e($self) ?>">
        <?= csrf_field() ?><input type="hidden" name="action" value="viewing"><input type="hidden" name="property_id" value="<?= $pid ?>">
        <div class="field"><label for="d">Date</label><input id="d" type="date" name="preferred_date" min="<?= $minDate ?>" max="<?= $maxDate ?>" required></div>
        <div class="field"><label for="t">Time (8 AM - 6 PM)</label><input id="t" type="time" name="preferred_time" min="08:00" max="18:00" required></div>
        <div class="field"><label for="n">Note (optional)</label><textarea id="n" name="message" rows="2" maxlength="500"></textarea></div>
        <button class="btn btn-block" type="submit">Send request</button>
      </form>
      <?php endif; ?>
    </div>

    <div class="card pad">
      <h2>Message the landlord</h2>
      <p class="muted">Contact details stay private. Your message opens a conversation you can continue in Messages.</p>
      <form method="post" action="<?= e($self) ?>">
        <?= csrf_field() ?><input type="hidden" name="action" value="inquiry"><input type="hidden" name="property_id" value="<?= $pid ?>">
        <div class="field"><label for="m">Your message</label><textarea id="m" name="message" rows="4" minlength="10" maxlength="1000" required></textarea></div>
        <button class="btn btn-block" type="submit">Send a message</button>
      </form>
    </div>
  </aside>
</div>
<?php if ($hasCoords): ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
<script>
(function () {
  var box = document.getElementById('propMap');
  if (!box) return;
  if (typeof L === 'undefined') { box.textContent = 'The map could not be loaded. Check your internet connection.'; return; }
  var lat = parseFloat(box.dataset.lat), lng = parseFloat(box.dataset.lng);
  var map = L.map(box).setView([lat, lng], 17);
  L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19, attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
  }).addTo(map);
  var icon = L.divIcon({ className: 'hr-pin', html: '<span style="background:' + box.dataset.color + '"></span>', iconSize: [26, 26], iconAnchor: [13, 26], popupAnchor: [0, -24] });
  var pop = document.createElement('div');
  var t = document.createElement('strong'); t.textContent = box.dataset.name;
  var a = document.createElement('div'); a.textContent = box.dataset.address;
  pop.appendChild(t); pop.appendChild(a);
  L.marker([lat, lng], { icon: icon, title: box.dataset.name }).addTo(map).bindPopup(pop).openPopup();
  setTimeout(function () { map.invalidateSize(); map.setView([lat, lng], 17); }, 250);
  window.addEventListener('resize', function () { map.invalidateSize(); });
})();
</script>
<?php endif; ?>
<script>
  document.querySelectorAll('.thumb').forEach(function (b) {
    b.addEventListener('click', function () { document.getElementById('mainImg').src = b.dataset.src; });
  });
</script>
<?php require __DIR__ . '/includes/student_footer.php'; ?>
