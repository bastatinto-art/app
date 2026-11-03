<?php
// Step 1: send the browser to Google's REAL account chooser (OAuth 2.0 authorization-code flow with PKCE).
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/env.php';
require_once __DIR__ . '/includes/auth_page.php';

$intent = $_GET['intent'] ?? 'login';
if (!in_array($intent, ['login', 'student', 'landlord', 'link'], true)) { header('Location: login.html'); exit; }
$client = (string)env('GOOGLE_CLIENT_ID', ''); $secret = (string)env('GOOGLE_CLIENT_SECRET', '');
if ($client === '' || $secret === '') {
    auth_page_start('Google sign-in', 'Google sign-in is not connected yet', 'Google only opens its sign-in for websites that have a Client ID and Client Secret. The website owner must add them to the .env file.');
    $local = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
    if ($local) echo '<p class="center"><a class="btn" href="google-check.php">Open the setup checker</a></p>';
    echo '<p class="center"><a href="login.html">Back to login</a></p>'; auth_page_end(); exit;
}
if ($intent === 'link' && (empty($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['student', 'landlord'], true))) { header('Location: login.html?err=login_required'); exit; }

$b64u = fn(string $b) => rtrim(strtr(base64_encode($b), '+/', '-_'), '=');
$verifier = $b64u(random_bytes(32));
$_SESSION['google_oauth'] = [
    'state' => bin2hex(random_bytes(16)), 'nonce' => bin2hex(random_bytes(16)), 'verifier' => $verifier, 'intent' => $intent,
    'user' => (int)($_SESSION['user_id'] ?? 0), 'role' => $_SESSION['role'] ?? '', 'exp' => time() + 600,
];
require_once __DIR__ . '/google-start-uri.php';
$params = [
    'client_id' => $client, 'redirect_uri' => google_redirect_uri_value(), 'response_type' => 'code', 'scope' => 'openid email profile',
    'state' => $_SESSION['google_oauth']['state'], 'nonce' => $_SESSION['google_oauth']['nonce'],
    'code_challenge' => $b64u(hash('sha256', $verifier, true)), 'code_challenge_method' => 'S256',
    'prompt' => 'select_account',               // always show Google's account chooser
];
header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986));
exit;
