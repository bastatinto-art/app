<?php
// ONE-TIME SETUP: downloads the real Tayabas City boundary from OpenStreetMap (Nominatim)
// and saves it to data/tayabas-boundary.json. Only runs from this computer (localhost).
// Open  http://localhost/<your folder>/setup-boundary.php  once, then DELETE this file.
header('Content-Type: text/plain; charset=utf-8');
$ip = $_SERVER['REMOTE_ADDR'] ?? 'cli';
if (PHP_SAPI !== 'cli' && !in_array($ip, ['127.0.0.1', '::1'], true)) { http_response_code(403); exit("Local access only.\n"); }

$url = 'https://nominatim.openstreetmap.org/search?' . http_build_query([
    'q' => 'Tayabas City, Quezon, Philippines', 'format' => 'jsonv2', 'polygon_geojson' => 1,
    'polygon_threshold' => 0.0003, 'limit' => 5, 'countrycodes' => 'ph',
]);
$ua = 'HomeRentCapstone/1.0 (student capstone project)';
$body = null;
if (function_exists('curl_init')) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_USERAGENT => $ua, CURLOPT_TIMEOUT => 30, CURLOPT_SSL_VERIFYPEER => false]);
    $body = curl_exec($ch); curl_close($ch);
}
if (!$body && ini_get('allow_url_fopen')) {
    $body = @file_get_contents($url, false, stream_context_create(['http' => ['header' => "User-Agent: $ua\r\n", 'timeout' => 30]]));
}
if (!$body) exit("Could not download. Check your internet connection (and that php.ini has extension=curl or allow_url_fopen=On).\n");

$results = json_decode($body, true) ?: [];
foreach ($results as $r) {
    $g = $r['geojson'] ?? null;
    if ($g && in_array($g['type'] ?? '', ['Polygon', 'MultiPolygon'], true)) {
        if (!is_dir(__DIR__ . '/data')) mkdir(__DIR__ . '/data', 0755, true);
        file_put_contents(__DIR__ . '/data/tayabas-boundary.json', json_encode($g));
        echo "Saved boundary for: " . ($r['display_name'] ?? 'Tayabas') . "\n";
        echo "File: data/tayabas-boundary.json (" . round(strlen(json_encode($g)) / 1024) . " KB)\n\nDone. Now DELETE setup-boundary.php.\n";
        exit;
    }
}
echo "No polygon found in the results. Try again later, or see README-MESSAGES-MAP.md for the manual option.\n";
