<?php
// Shared by google-start.php and google-callback.php so both use the exact same redirect URI.
require_once __DIR__ . '/includes/env.php';
function google_redirect_uri_value(): string {
    $fixed = env('GOOGLE_REDIRECT_URI');
    if ($fixed) return $fixed;
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/') . '/google-callback.php';
}
