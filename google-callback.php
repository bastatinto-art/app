<?php
// Step 2: Google sends the user back here with a one-time code. The server swaps it for a signed ID token and verifies it.
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/env.php';
require_once __DIR__ . '/includes/google_jwt.php';
require_once __DIR__ . '/includes/google_flow.php';

$o = $_SESSION['google_oauth'] ?? null;
unset($_SESSION['google_oauth']);                                  // each attempt works only once
$intent = $o['intent'] ?? 'login';
if (!$o || $o['exp'] < time()) google_fail($intent, 'google_failed', 'Your Google sign-in expired. Please try again.');
if (isset($_GET['error'])) google_fail($intent, 'google_cancelled', 'Google sign-in was cancelled.');
if (!isset($_GET['state'], $_GET['code']) || !hash_equals($o['state'], (string)$_GET['state'])) google_fail($intent, 'google_failed', 'Google sign-in could not be verified. Please try again.');

// redirect_uri must be identical to the one used at the start
require_once __DIR__ . '/google-start-uri.php';
$resp = g_http_post('https://oauth2.googleapis.com/token', [
    'code' => (string)$_GET['code'], 'client_id' => (string)env('GOOGLE_CLIENT_ID', ''), 'client_secret' => (string)env('GOOGLE_CLIENT_SECRET', ''),
    'redirect_uri' => google_redirect_uri_value(), 'grant_type' => 'authorization_code', 'code_verifier' => $o['verifier'],
]);
$tok = $resp ? json_decode($resp, true) : null;
if (empty($tok['id_token'])) { error_log('Google token exchange failed: ' . substr((string)$resp, 0, 300)); google_fail($intent, 'google_failed', 'Google sign-in failed. Please try again.'); }

$res = google_verify_id_token($tok['id_token'], (string)env('GOOGLE_CLIENT_ID', ''));
if (!$res['ok']) { error_log('Google token rejected: ' . $res['error']); google_fail($intent, 'google_failed', 'Google sign-in failed: ' . $res['error']); }
if (!hash_equals($o['nonce'], (string)($res['claims']['nonce'] ?? ''))) google_fail($intent, 'google_failed', 'Google sign-in could not be verified (nonce).');

google_process($conn, $res['claims'], $intent, $o);
