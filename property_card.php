<?php
// Base query for public listings: only approved, non-archived properties are ever shown to students.
const PROPERTY_SELECT = "SELECT p.id, p.name, p.property_type, p.monthly_price, p.address, p.rooms_available, p.availability,
    (SELECT file_path FROM property_images i WHERE i.property_id = p.id ORDER BY i.is_primary DESC, i.sort_order, i.id LIMIT 1) AS image
    FROM properties p";
const PUBLIC_WHERE = "p.approval_status = 'approved' AND p.is_archived = 0";

function fetch_favorite_ids(mysqli $conn, int $sid): array {
    $st = $conn->prepare("SELECT property_id FROM favorites WHERE student_id = ?");
    $st->bind_param('i', $sid); $st->execute();
    $ids = array_column($st->get_result()->fetch_all(MYSQLI_ASSOC), 'property_id');
    $st->close();
    return array_map('intval', $ids);
}

function render_property_card(array $p, bool $isFav, string $returnTo): void {
    require_once __DIR__ . '/ui.php'; ?>
  <article class="card prop">
    <div class="prop-media">
      <a class="prop-img" href="property-details.php?id=<?= (int)$p['id'] ?>">
        <?php if (!empty($p['image'])): ?>
          <img src="<?= e($p['image']) ?>" alt="<?= e($p['name']) ?>" loading="lazy">
        <?php else: ?>
          <span class="noimg">No photo yet</span>
        <?php endif; ?>
        <span class="badge badge-<?= e($p['availability']) ?>"><?= e(ucfirst($p['availability'])) ?></span>
      </a>
      <form class="fav-form" method="post" action="favorite-toggle.php">
        <?= csrf_field() ?>
        <input type="hidden" name="property_id" value="<?= (int)$p['id'] ?>">
        <input type="hidden" name="return" value="<?= e($returnTo) ?>">
        <button class="fav-btn" type="submit" data-nobusy aria-pressed="<?= $isFav ? 'true' : 'false' ?>"
                aria-label="<?= $isFav ? 'Remove from favorites' : 'Save to favorites' ?>" title="<?= $isFav ? 'Remove from favorites' : 'Save to favorites' ?>"><?= ui_icon('favorites') ?></button>
      </form>
    </div>
    <div class="prop-body">
      <h3><a href="property-details.php?id=<?= (int)$p['id'] ?>"><?= e($p['name']) ?></a></h3>
      <p class="price"><?= peso($p['monthly_price']) ?><small> / month</small></p>
      <p class="meta-line"><?= ui_icon('home') ?><?= e(type_label($p['property_type'])) ?></p>
      <p class="meta-line"><?= ui_icon('pin') ?><?= e($p['address']) ?></p>
      <p class="meta-line"><?= ui_icon('bed') ?><?= (int)$p['rooms_available'] ?> open</p>
      <div class="prop-actions">
        <a class="btn btn-small" href="property-details.php?id=<?= (int)$p['id'] ?>">View details <?= ui_icon('arrow') ?></a>
      </div>
    </div>
  </article>
<?php }
