<?php
// JSON of properties for the student map. ONLY approved + available + not archived + pinned.
require_once __DIR__ . '/includes/auth.php';
require_role('student');
header('Content-Type: application/json; charset=utf-8');

$allowed = ['boarding_house', 'apartment', 'dormitory', 'bedspace'];
$types = array_values(array_intersect((array)($_GET['type'] ?? []), $allowed));
$min = $_GET['min_price'] ?? ''; $max = $_GET['max_price'] ?? '';

$where = ["p.approval_status = 'approved'", "p.is_archived = 0", "p.availability = 'available'",
          "p.latitude IS NOT NULL", "p.longitude IS NOT NULL"];
$params = []; $bind = '';
if ($types) { $where[] = 'p.property_type IN (' . implode(',', array_fill(0, count($types), '?')) . ')'; foreach ($types as $t) { $params[] = $t; $bind .= 's'; } }
if ($min !== '' && is_numeric($min) && $min >= 0) { $where[] = 'p.monthly_price >= ?'; $params[] = (float)$min; $bind .= 'd'; }
if ($max !== '' && is_numeric($max) && $max >= 0) { $where[] = 'p.monthly_price <= ?'; $params[] = (float)$max; $bind .= 'd'; }
if (($_GET['open_rooms'] ?? '') === '1') $where[] = 'p.rooms_available > 0';

$sql = "SELECT p.id, p.name, p.property_type, p.monthly_price, p.address, p.availability, p.rooms_available, p.latitude, p.longitude,
        (SELECT file_path FROM property_images i WHERE i.property_id = p.id ORDER BY i.is_primary DESC, i.sort_order, i.id LIMIT 1) AS image
        FROM properties p WHERE " . implode(' AND ', $where) . " ORDER BY p.created_at DESC LIMIT 500";
$st = $conn->prepare($sql);
if ($params) $st->bind_param($bind, ...$params);
$st->execute();
$rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);

foreach ($rows as &$r) {
    $r['id'] = (int)$r['id']; $r['monthly_price'] = (float)$r['monthly_price']; $r['rooms_available'] = (int)$r['rooms_available'];
    $r['latitude'] = (float)$r['latitude']; $r['longitude'] = (float)$r['longitude'];
    $r['type_label'] = type_label($r['property_type']); $r['price_label'] = peso($r['monthly_price']);
}
echo json_encode(['count' => count($rows), 'properties' => $rows]);
