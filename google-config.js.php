<?php
// Tells the login page whether Google sign-in is configured (so the buttons can hide if not). No secrets here.
require_once __DIR__ . '/includes/env.php';
header('Content-Type: application/javascript; charset=utf-8');
header('Cache-Control: no-store');
echo 'window.HR_AUTH=' . json_encode(['googleReady' => (string)env('GOOGLE_CLIENT_ID', '') !== '' && (string)env('GOOGLE_CLIENT_SECRET', '') !== '']) . ';';
