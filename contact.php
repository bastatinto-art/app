<?php
// Helpers for landlord contact details (phone + Facebook link).

// Returns ['ok' => bool, 'value' => string|null, 'error' => string]. Empty input = optional = null.
function clean_facebook_url(string $raw): array {
    $raw = trim($raw);
    if ($raw === '') return ['ok' => true, 'value' => null, 'error' => ''];
    if (!preg_match('#^https?://#i', $raw)) $raw = 'https://' . $raw;
    $bad = ['ok' => false, 'value' => null, 'error' => 'Enter a valid Facebook profile or page link (for example https://facebook.com/yourpage).'];
    if (mb_strlen($raw) > 255 || !filter_var($raw, FILTER_VALIDATE_URL)) return $bad;
    $parts = parse_url($raw);
    $host = strtolower($parts['host'] ?? '');
    if (isset($parts['user']) || isset($parts['pass'])) return $bad;
    $okHost = $host === 'facebook.com' || $host === 'fb.com' || $host === 'fb.me'
        || str_ends_with($host, '.facebook.com') || str_ends_with($host, '.fb.com');
    if (!$okHost) return $bad;
    return ['ok' => true, 'value' => 'https://' . substr($raw, strpos($raw, '://') + 3), 'error' => ''];
}

function tel_href(string $phone): string {
    return 'tel:' . preg_replace('/[^0-9+]/', '', $phone);
}
