<?php
// Minimal .env loader. Looks for .env in the project folder, then one folder above it (safer: outside htdocs).
function env(string $key, $default = null) {
    static $vars = null;
    if ($vars === null) {
        $vars = [];
        foreach ([__DIR__ . '/../.env', __DIR__ . '/../../.env'] as $file) {
            if (!is_readable($file)) continue;
            foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                $line = trim($line);
                if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) continue;
                [$k, $v] = explode('=', $line, 2);
                $v = trim($v);
                if ($v !== '' && ($v[0] === '"' || $v[0] === "'")) $v = trim($v, "\"'");
                else $v = trim(preg_replace('/\s+#.*$/', '', $v));   // strip trailing comments
                $vars[trim($k)] = $v;
            }
            break;
        }
    }
    if (isset($vars[$key]) && $vars[$key] !== '') return $vars[$key];
    $g = getenv($key);
    return ($g !== false && $g !== '') ? $g : $default;
}
function env_bool(string $key, bool $default): bool {
    $v = env($key); if ($v === null) return $default;
    return in_array(strtolower((string)$v), ['1', 'true', 'yes', 'on'], true);
}
