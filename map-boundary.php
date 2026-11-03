<?php
// Boundary used by the browser maps (same data the server validates against).
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/geo.php';
if (empty($_SESSION['user_id'])) { http_response_code(401); header('Content-Type: application/json'); echo '{"error":"login"}'; exit; }
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, max-age=3600');
echo json_encode(['source' => tayabas_boundary() ? 'polygon' : 'bbox', 'geometry' => tayabas_geometry_for_map()]);
