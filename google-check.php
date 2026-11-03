<?php
// Setup checker for Google sign-in. Works only on this computer (localhost). Shows what is missing and the exact
// addresses to paste into Google Cloud Console. Delete this file before putting the site online.
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/env.php';
require_once __DIR__ . '/includes/google_jwt.php';
require_once __DIR__ . '/google-start-uri.php';
if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) { http_response_code(403); exit('Local access only.'); }

$root = realpath(__DIR__ . '/..') ?: '';
$envFile = is_file(__DIR__ . '/.env') ? __DIR__ . '/.env' : (is_file(__DIR__ . '/../.env') ? realpath(__DIR__ . '/../.env') : null);
$id = (string)env('GOOGLE_CLIENT_ID', ''); $secret = (string)env('GOOGLE_CLIENT_SECRET', '');
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$origin = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');

$net = g_http_get('https://www.googleapis.com/oauth2/v3/certs'); $netOk = $net && !empty(json_decode($net, true)['keys']);
$dbOk = true; try { $conn->query("SELECT 1 FROM oauth_links LIMIT 1"); } catch (mysqli_sql_exception $e) { $dbOk = false; }
$colOk = true; try { $conn->query("SELECT email_verified, password_set, avatar_url FROM students LIMIT 1"); } catch (mysqli_sql_exception $e) { $colOk = false; }

$checks = [
  ['The file is named exactly ".env" (not ".env.txt")', $envFile !== null, $envFile ? 'Found: ' . $envFile : (is_file(__DIR__ . '/.env.txt') ? 'You have ".env.txt". Rename it to ".env" (in File Explorer turn on View > Show > File name extensions).' : 'Copy .env.example to a new file named .env in the same folder as login.html.')],
  ['GOOGLE_CLIENT_ID is filled in', $id !== '' && strpos($id, 'apps.googleusercontent.com') !== false, $id !== '' ? 'Starts with ' . substr($id, 0, 12) . '...' : 'Paste the Client ID from Google Cloud Console > Credentials.'],
  ['GOOGLE_CLIENT_SECRET is filled in', $secret !== '' && $secret !== 'your-google-client-secret', $secret !== '' ? 'Present (hidden)' : 'Paste the Client secret from the same Google credential.'],
  ['This server can reach Google over HTTPS', $netOk, $netOk ? 'OK' : 'Failed. Check your internet, and that PHP has the curl or openssl extension on. If it says certificate problem, see HTTP_VERIFY_SSL in .env.example.'],
  ['PHP extensions: curl or openssl', function_exists('curl_init') || extension_loaded('openssl'), 'openssl is needed to verify Google signatures: ' . (extension_loaded('openssl') ? 'on' : 'OFF (enable extension=openssl in php.ini)')],
  ['Database has the Google tables/columns (run sql/07)', $dbOk && $colOk, ($dbOk && $colOk) ? 'OK' : 'Run sql/07_security_google_otp.sql in phpMyAdmin.'],
];
$all = true; foreach ($checks as $c) $all = $all && $c[1];
?><!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Google sign-in check</title><link rel="stylesheet" href="assets/css/student.css"><link rel="stylesheet" href="assets/css/motion.css"></head>
<body><main class="container" style="max-width:760px">
<h1>Google sign-in setup check</h1>
<div class="card pad">
<?php foreach ($checks as [$label, $ok, $note]): ?>
  <p style="margin:10px 0"><strong style="color:<?= $ok ? '#166534' : '#991b1b' ?>"><?= $ok ? '&#10003;' : '&#10007;' ?></strong> <?= e($label) ?><br><span class="muted"><?= e($note) ?></span></p>
<?php endforeach; ?>
</div>
<div class="card pad"><h2>Paste these into Google Cloud Console</h2>
  <p class="muted">Credentials &gt; your OAuth client (type Web application)</p>
  <p><strong>Authorized JavaScript origins</strong><br><code><?= e($origin) ?></code></p>
  <p><strong>Authorized redirect URIs</strong> (must match exactly, letter for letter)<br><code><?= e(google_redirect_uri_value()) ?></code></p>
</div>
<?php if ($all): ?>
  <div class="alert alert-success">Everything looks ready. <a class="btn btn-small" href="google-start.php?intent=login">Try Continue with Google now</a></div>
<?php else: ?>
  <div class="alert alert-error">Fix the items marked &#10007;, then reload this page.</div>
<?php endif; ?>
<p><a href="login.html">Back to login</a> &middot; Delete <code>google-check.php</code> before going online.</p>
</main></body></html>
