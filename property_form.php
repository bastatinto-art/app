<?php
// Shared add/edit logic. Set $editId (0 = add) before including.
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/geo.php';
require_role('landlord');
$lid = (int)$_SESSION['user_id'];

const MAX_PHOTOS = 6;
const MAX_PHOTO_BYTES = 3 * 1024 * 1024;
$typeKeys = ['boarding_house', 'apartment', 'dormitory', 'bedspace'];
$availKeys = ['available', 'unavailable', 'rented'];
$mimeExt = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
$uploadDir = __DIR__ . '/../uploads/properties/';

$p = ['name' => '', 'description' => '', 'property_type' => '', 'monthly_price' => '', 'address' => '',
      'rooms_available' => 1, 'house_rules' => '', 'requirements' => '', 'availability' => 'available', 'approval_status' => 'pending', 'latitude' => '', 'longitude' => ''];
$images = []; $selAm = [];

if ($editId) {
    $st = $conn->prepare("SELECT * FROM properties WHERE id = ? AND landlord_id = ?");   // ownership check
    $st->bind_param('ii', $editId, $lid); $st->execute();
    $row = $st->get_result()->fetch_assoc(); $st->close();
    if (!$row) { flash_set('error', 'Property not found.'); header('Location: manage-properties.php'); exit; }
    $p = $row;
    $im = $conn->prepare("SELECT id, file_path, is_primary FROM property_images WHERE property_id = ? ORDER BY is_primary DESC, sort_order, id");
    $im->bind_param('i', $editId); $im->execute(); $images = $im->get_result()->fetch_all(MYSQLI_ASSOC); $im->close();
    $am = $conn->prepare("SELECT amenity_id FROM property_amenities WHERE property_id = ?");
    $am->bind_param('i', $editId); $am->execute();
    $selAm = array_map('intval', array_column($am->get_result()->fetch_all(MYSQLI_ASSOC), 'amenity_id')); $am->close();
}
$amenityList = $conn->query("SELECT id, name FROM amenities ORDER BY name")->fetch_all(MYSQLI_ASSOC);
$validAmIds = array_map('intval', array_column($amenityList, 'id'));
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $p['name'] = trim($_POST['name'] ?? '');
    $p['description'] = trim($_POST['description'] ?? '');
    $p['property_type'] = $_POST['property_type'] ?? '';
    $p['monthly_price'] = trim($_POST['monthly_price'] ?? '');
    $p['address'] = trim($_POST['address'] ?? '');
    $p['rooms_available'] = trim($_POST['rooms_available'] ?? '');
    $p['house_rules'] = trim($_POST['house_rules'] ?? '');
    $p['requirements'] = trim($_POST['requirements'] ?? '');
    $p['availability'] = $_POST['availability'] ?? '';
    $p['latitude'] = trim($_POST['latitude'] ?? '');
    $p['longitude'] = trim($_POST['longitude'] ?? '');
    $selAm = array_values(array_intersect(array_map('intval', (array)($_POST['amenities'] ?? [])), $validAmIds));

    if ($p['name'] === '' || mb_strlen($p['name']) > 150) $errors[] = 'Enter a property name (up to 150 characters).';
    if (!in_array($p['property_type'], $typeKeys, true)) $errors[] = 'Choose a property category.';
    if (!is_numeric($p['monthly_price']) || $p['monthly_price'] <= 0 || $p['monthly_price'] > 1000000) $errors[] = 'Enter a monthly price greater than 0.';
    if ($p['address'] === '' || mb_strlen($p['address']) > 255) $errors[] = 'Enter the address in Barangay Mateuna (up to 255 characters).';
    if (!ctype_digit((string)$p['rooms_available']) || (int)$p['rooms_available'] > 500) $errors[] = 'Rooms or bedspaces must be a whole number from 0 to 500.';
    if (!in_array($p['availability'], $availKeys, true)) $errors[] = 'Choose an availability.';
    if (!is_numeric($p['latitude']) || !is_numeric($p['longitude'])) $errors[] = 'Pin the property location on the map.';
    elseif (!point_in_tayabas((float)$p['latitude'], (float)$p['longitude'])) $errors[] = 'The pinned location must be inside Tayabas City.';
    if (mb_strlen($p['description']) > 3000 || mb_strlen($p['house_rules']) > 2000 || mb_strlen($p['requirements']) > 2000) $errors[] = 'One of the text fields is too long.';

    // Photos: figure out which existing ones stay, then validate new uploads
    $deleteIds = array_map('intval', (array)($_POST['delete_images'] ?? []));
    $existingIds = array_map('intval', array_column($images, 'id'));
    $deleteIds = array_values(array_intersect($deleteIds, $existingIds));
    $keep = count($existingIds) - count($deleteIds);
    $newFiles = [];
    if (!empty($_FILES['photos']['name'][0] ?? '') || count($_FILES['photos']['name'] ?? []) > 1) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        foreach ($_FILES['photos']['name'] as $i => $orig) {
            $err = $_FILES['photos']['error'][$i];
            if ($err === UPLOAD_ERR_NO_FILE) continue;
            if ($err !== UPLOAD_ERR_OK) { $errors[] = "Photo \"$orig\" failed to upload."; continue; }
            $tmp = $_FILES['photos']['tmp_name'][$i];
            if ($_FILES['photos']['size'][$i] > MAX_PHOTO_BYTES) { $errors[] = "Photo \"$orig\" is larger than 3 MB."; continue; }
            $mime = $finfo->file($tmp);
            if (!isset($mimeExt[$mime]) || !@getimagesize($tmp)) { $errors[] = "Photo \"$orig\" must be a JPG, PNG or WebP image."; continue; }
            $newFiles[] = ['tmp' => $tmp, 'ext' => $mimeExt[$mime]];
        }
    }
    if ($keep + count($newFiles) > MAX_PHOTOS) $errors[] = 'A property can have at most ' . MAX_PHOTOS . ' photos.';

    if (!$errors) {
        $moved = []; $toUnlink = [];
        try {
            $conn->begin_transaction();
            $price = (float)$p['monthly_price']; $rooms = (int)$p['rooms_available'];
            $lat = round((float)$p['latitude'], 7); $lng = round((float)$p['longitude'], 7);
            if ($editId) {
                $u = $conn->prepare("UPDATE properties SET name=?, description=?, property_type=?, monthly_price=?, address=?, rooms_available=?, house_rules=?, requirements=?, availability=?, latitude=?, longitude=? WHERE id=? AND landlord_id=?");
                $u->bind_param('sssdsisssddii', $p['name'], $p['description'], $p['property_type'], $price, $p['address'], $rooms, $p['house_rules'], $p['requirements'], $p['availability'], $lat, $lng, $editId, $lid);
                $u->execute();
                $r = $conn->prepare("UPDATE properties SET approval_status='pending' WHERE id=? AND landlord_id=? AND approval_status='rejected'");  // resubmit after rejection
                $r->bind_param('ii', $editId, $lid); $r->execute();
                $pid = $editId;
            } else {
                $u = $conn->prepare("INSERT INTO properties (landlord_id, name, description, property_type, monthly_price, address, rooms_available, house_rules, requirements, availability, latitude, longitude) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
                $u->bind_param('isssdsisssdd', $lid, $p['name'], $p['description'], $p['property_type'], $price, $p['address'], $rooms, $p['house_rules'], $p['requirements'], $p['availability'], $lat, $lng);
                $u->execute();
                $pid = $conn->insert_id;
            }
            // Amenities: replace the set
            $d = $conn->prepare("DELETE FROM property_amenities WHERE property_id = ?"); $d->bind_param('i', $pid); $d->execute();
            if ($selAm) {
                $a = $conn->prepare("INSERT INTO property_amenities (property_id, amenity_id) VALUES (?, ?)");
                foreach ($selAm as $aid) { $a->bind_param('ii', $pid, $aid); $a->execute(); }
            }
            // Delete chosen photos (rows first, files after commit)
            foreach ($images as $im) {
                if (in_array((int)$im['id'], $deleteIds, true)) {
                    $x = $conn->prepare("DELETE FROM property_images WHERE id = ? AND property_id = ?");
                    $x->bind_param('ii', $im['id'], $pid); $x->execute();
                    $toUnlink[] = $im['file_path'];
                }
            }
            // Save new photos
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $ins = $conn->prepare("INSERT INTO property_images (property_id, file_path, is_primary, sort_order) VALUES (?, ?, 0, ?)");
            $order = 100;
            foreach ($newFiles as $f) {
                $fname = bin2hex(random_bytes(12)) . '.' . $f['ext'];
                if (!move_uploaded_file($f['tmp'], $uploadDir . $fname)) throw new RuntimeException('Could not save a photo.');
                $moved[] = $uploadDir . $fname;
                $path = 'uploads/properties/' . $fname;
                $ins->bind_param('isi', $pid, $path, $order); $ins->execute(); $order++;
            }
            // Primary photo: chosen existing one, otherwise the first remaining
            $q = $conn->prepare("SELECT id FROM property_images WHERE property_id = ? ORDER BY sort_order, id");
            $q->bind_param('i', $pid); $q->execute();
            $ids = array_map('intval', array_column($q->get_result()->fetch_all(MYSQLI_ASSOC), 'id'));
            if ($ids) {
                $chosen = (int)($_POST['primary_image'] ?? 0);
                if (!in_array($chosen, $ids, true)) $chosen = $ids[0];
                $z = $conn->prepare("UPDATE property_images SET is_primary = (id = ?) WHERE property_id = ?");
                $z->bind_param('ii', $chosen, $pid); $z->execute();
            }
            $conn->commit();
            foreach ($toUnlink as $rel) {
                if (preg_match('#^uploads/properties/[a-f0-9]+\.(jpg|png|webp)$#', $rel)) @unlink(__DIR__ . '/../' . $rel);
            }
            flash_set('success', $editId ? 'Property updated.' : 'Property added. It will be visible to students after an administrator approves it.');
            header('Location: manage-properties.php'); exit;
        } catch (Throwable $ex) {
            $conn->rollback();
            foreach ($moved as $f) @unlink($f);
            error_log('Property save failed: ' . $ex->getMessage());
            $errors[] = 'Something went wrong while saving. Please try again.';
        }
    }
}

$pageTitle = $editId ? 'Edit property' : 'Add property'; $active = 'properties';
$extraHead = '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css"><link rel="stylesheet" href="assets/css/map.css">';
require __DIR__ . '/landlord_header.php';
$self = $editId ? 'edit-property.php?id=' . $editId : 'add-property.php';
?>
<p><a href="manage-properties.php">&larr; Back to my properties</a></p>
<h1><?= $editId ? 'Edit property' : 'Add a property' ?></h1>
<?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>
<?php if ($editId && $p['approval_status'] === 'rejected'): ?><div class="alert alert-error">This listing was rejected. Saving your changes will send it for review again.</div><?php endif; ?>

<form class="card pad" method="post" action="<?= e($self) ?>" enctype="multipart/form-data">
  <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$editId ?>">
  <div class="form-grid">
    <div class="field full"><label for="name">Property name</label><input id="name" name="name" value="<?= e($p['name']) ?>" maxlength="150" required></div>
    <div class="field"><label for="type">Property category</label>
      <select id="type" name="property_type" required>
        <option value="">Select a category</option>
        <?php foreach ($typeKeys as $t): ?><option value="<?= $t ?>" <?= $p['property_type'] === $t ? 'selected' : '' ?>><?= e(type_label($t)) ?></option><?php endforeach; ?>
      </select></div>
    <div class="field"><label for="price">Monthly rent (₱)</label><input id="price" type="number" name="monthly_price" min="1" max="1000000" step="1" value="<?= e($p['monthly_price']) ?>" required></div>
    <div class="field full"><label for="addr">Address (Barangay Mateuna)</label><input id="addr" name="address" value="<?= e($p['address']) ?>" maxlength="255" required></div>
    <div class="field full"><label>Pin the exact location (Tayabas City only)</label>
      <p class="pin-help">Click the map to place the pin, then drag it to fine-tune. The pin must be inside the blue outline.</p>
      <div id="pinMap" class="map-box"></div>
      <input type="hidden" id="latitude" name="latitude" value="<?= e($p['latitude']) ?>">
      <input type="hidden" id="longitude" name="longitude" value="<?= e($p['longitude']) ?>">
      <div id="pinReadout" class="pin-readout" role="status"></div>
    </div>
    <div class="field"><label for="rooms">Rooms / bedspaces open</label><input id="rooms" type="number" name="rooms_available" min="0" max="500" value="<?= e($p['rooms_available']) ?>" required></div>
    <div class="field"><label for="av">Availability</label>
      <select id="av" name="availability">
        <?php foreach ($availKeys as $a): ?><option value="<?= $a ?>" <?= $p['availability'] === $a ? 'selected' : '' ?>><?= ucfirst($a) ?></option><?php endforeach; ?>
      </select></div>
    <div class="field full"><label for="desc">Description</label><textarea id="desc" name="description" rows="4" maxlength="3000"><?= e($p['description']) ?></textarea></div>
    <fieldset class="field full amenity-set"><legend>Amenities</legend>
      <?php foreach ($amenityList as $a): ?>
        <label class="chk"><input type="checkbox" name="amenities[]" value="<?= (int)$a['id'] ?>" <?= in_array((int)$a['id'], $selAm, true) ? 'checked' : '' ?>> <?= e($a['name']) ?></label>
      <?php endforeach; ?>
    </fieldset>
    <div class="field full"><label for="rules">House rules</label><textarea id="rules" name="house_rules" rows="3" maxlength="2000"><?= e($p['house_rules']) ?></textarea></div>
    <div class="field full"><label for="req">Rental requirements</label><textarea id="req" name="requirements" rows="3" maxlength="2000"><?= e($p['requirements']) ?></textarea></div>

    <div class="field full"><label>Photos (JPG, PNG or WebP, up to 3 MB each, <?= MAX_PHOTOS ?> total)</label>
      <?php if ($images): ?>
        <div class="img-manage">
          <?php foreach ($images as $im): ?>
            <figure><img src="<?= e($im['file_path']) ?>" alt="">
              <label><input type="radio" name="primary_image" value="<?= (int)$im['id'] ?>" <?= $im['is_primary'] ? 'checked' : '' ?>> Main photo</label>
              <label><input type="checkbox" name="delete_images[]" value="<?= (int)$im['id'] ?>"> Remove</label></figure>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple>
    </div>
  </div>
  <button class="btn" type="submit"><?= $editId ? 'Save changes' : 'Add property' ?></button>
</form>
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
<script src="assets/js/hr-map.js"></script>
<script>HRMap.picker({ el: 'pinMap', lat: 'latitude', lng: 'longitude', readout: 'pinReadout' });</script>
<?php require __DIR__ . '/landlord_footer.php'; ?>
