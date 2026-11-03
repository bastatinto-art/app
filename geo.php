<?php
// Tayabas City boundary check, used on the SERVER so pins can't be forged from the browser.
// Uses data/tayabas-boundary.json (real OSM polygon, created by setup-boundary.php).
// If that file is missing it falls back to a rough bounding box.
const TAYABAS_BBOX = ['south' => 13.93, 'west' => 121.50, 'north' => 14.12, 'east' => 121.74];

function tayabas_boundary(): ?array {
    static $geom = false;
    if ($geom !== false) return $geom;
    $geom = null;
    $file = __DIR__ . '/../data/tayabas-boundary.json';
    if (is_readable($file)) {
        $j = json_decode((string)file_get_contents($file), true);
        if (is_array($j)) {
            if (($j['type'] ?? '') === 'Feature') $j = $j['geometry'] ?? null;
            if (is_array($j) && in_array($j['type'] ?? '', ['Polygon', 'MultiPolygon'], true) && !empty($j['coordinates'])) $geom = $j;
        }
    }
    return $geom;
}

function tayabas_geometry_for_map(): array {
    $g = tayabas_boundary();
    if ($g) return $g;
    $b = TAYABAS_BBOX;
    return ['type' => 'Polygon', 'coordinates' => [[
        [$b['west'], $b['south']], [$b['east'], $b['south']], [$b['east'], $b['north']], [$b['west'], $b['north']], [$b['west'], $b['south']],
    ]]];
}

function ring_contains(array $ring, float $lng, float $lat): bool {
    $in = false; $n = count($ring);
    for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
        $xi = $ring[$i][0]; $yi = $ring[$i][1]; $xj = $ring[$j][0]; $yj = $ring[$j][1];
        if ((($yi > $lat) !== ($yj > $lat)) && ($lng < ($xj - $xi) * ($lat - $yi) / (($yj - $yi) ?: 1e-12) + $xi)) $in = !$in;
    }
    return $in;
}

function point_in_tayabas(float $lat, float $lng): bool {
    if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) return false;
    $g = tayabas_boundary();
    if (!$g) {
        $b = TAYABAS_BBOX;
        return $lat >= $b['south'] && $lat <= $b['north'] && $lng >= $b['west'] && $lng <= $b['east'];
    }
    $polys = $g['type'] === 'Polygon' ? [$g['coordinates']] : $g['coordinates'];
    foreach ($polys as $rings) {
        if (!ring_contains($rings[0], $lng, $lat)) continue;
        $inHole = false;
        for ($k = 1; $k < count($rings); $k++) if (ring_contains($rings[$k], $lng, $lat)) { $inHole = true; break; }
        if (!$inHole) return true;
    }
    return false;
}
