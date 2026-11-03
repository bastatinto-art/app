<?php
// Shared by categories.php and logo-management.php. Set $mode = 'logo' | 'categories' first.
require_once __DIR__ . '/auth.php';
$adminId = admin_require($conn);
$isLogo = $mode === 'logo';
$slots = $isLogo
  ? ['logo_path' => ['label' => 'HomeRent logo', 'max' => 2 * 1048576, 'minW' => 32, 'minH' => 32, 'hint' => 'PNG with a transparent background works best. JPG, PNG or WebP, max 2 MB.']]
  : ['cat_img_apartment' => ['label' => 'Apartment'], 'cat_img_bedspace' => ['label' => 'Bed Spacer'], 'cat_img_dormitory' => ['label' => 'Dormitory'],
     'cat_img_boarding_house' => ['label' => 'Boarding House'], 'cat_img_all' => ['label' => 'All Categories (optional card)']];
if (!$isLogo) foreach ($slots as $k => &$s) $s += ['max' => 4 * 1048576, 'minW' => 400, 'minH' => 200, 'hint' => 'Landscape photo, at least 400 x 200 px. JPG, PNG or WebP, max 4 MB.'];
unset($s);
$self = $isLogo ? 'logo-management.php' : 'categories.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $key = $_POST['slot'] ?? '';
    if (!isset($slots[$key])) flash_set('error', 'Unknown image slot.');
    else {
        $s = $slots[$key];
        $err = save_site_image($conn, $key, $_FILES['image'] ?? [], $s['max'], $s['minW'], $s['minH'], $adminId);
        if ($err) flash_set('error', $s['label'] . ': ' . $err);
        else { admin_log($conn, $adminId, $isLogo ? 'logo_updated' : 'category_image_updated', 'setting', null, $key); flash_set('success', $s['label'] . ' saved. It is live on the website now.'); }
    }
    header("Location: $self"); exit;
}
$pageTitle = $isLogo ? 'Logo Management' : 'Category Images'; $active = $isLogo ? 'logo' : 'categories';
require __DIR__ . '/header.php';
?>
<h1><?= $isLogo ? 'Logo Management' : 'Category Image Management' ?></h1>
<p class="muted" style="margin-bottom:14px"><?= $isLogo
  ? 'The logo replaces the house icon beside "HomeRent" in the Student, Landlord and Admin navigation bars. Without an upload, the default house icon is shown.'
  : 'Upload one photo per category. The Student Dashboard "Browse by Category" cards show these photos as soon as you save.' ?></p>
<div class="upload-grid">
<?php foreach ($slots as $k => $s): $cur = site_image_url($conn, $k); ?>
  <div class="card upload-card">
    <h3><?= e($s['label']) ?></h3>
    <div class="upload-preview <?= $isLogo ? 'logo' : '' ?>" id="prev-<?= $k ?>"><?php if ($cur): ?><img src="../<?= e($cur) ?>" alt="Current image"><?php else: ?><?= $isLogo ? 'Default house icon in use' : 'No photo uploaded yet' ?><?php endif; ?></div>
    <form method="post" enctype="multipart/form-data">
      <?= csrf_field() ?><input type="hidden" name="slot" value="<?= e($k) ?>">
      <label class="preview-label" for="f-<?= e($k) ?>">Choose a file (preview shows above before saving)</label>
      <input id="f-<?= e($k) ?>" type="file" name="image" accept="image/jpeg,image/png,image/webp" data-preview="prev-<?= e($k) ?>" required>
      <span class="upload-hint"><?= e($s['hint']) ?></span>
      <button class="btn btn-small" type="submit"><?= $cur ? 'Replace' : 'Upload' ?></button>
    </form>
  </div>
<?php endforeach; ?>
</div>
<script>
  document.querySelectorAll('input[type=file][data-preview]').forEach(function (inp) {
    inp.addEventListener('change', function () {
      var box = document.getElementById(inp.dataset.preview), f = inp.files[0];
      if (!f) return;
      if (!/^image\/(jpeg|png|webp)$/.test(f.type)) { box.textContent = 'Only JPG, PNG or WebP images are allowed.'; inp.value = ''; return; }
      var img = document.createElement('img'); img.alt = 'Preview'; img.src = URL.createObjectURL(f);
      box.textContent = ''; box.appendChild(img);
    });
  });
</script>
<?php require __DIR__ . '/footer.php'; ?>
