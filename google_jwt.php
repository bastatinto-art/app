<?php
// Verifies a Google ID token on the SERVER: RS256 signature (Google's public keys), issuer, audience, expiry, verified email.
require_once __DIR__ . '/env.php';

function g_b64u(string $s): string { return base64_decode(strtr($s, '-_', '+/') . str_repeat('=', (4 - strlen($s) % 4) % 4)); }
function g_asn1len(int $l): string { if ($l < 128) return chr($l); $b = ltrim(pack('N', $l), "\0"); return chr(0x80 | strlen($b)) . $b; }
function g_asn1(int $tag, string $v): string { return chr($tag) . g_asn1len(strlen($v)) . $v; }
function g_asn1int(string $v): string { $v = ltrim($v, "\0"); if ($v === '' || ord($v[0]) > 127) $v = "\0" . $v; return g_asn1(0x02, $v); }
function g_jwk_to_pem(string $n, string $e): string {
    $rsa = g_asn1(0x30, g_asn1int(g_b64u($n)) . g_asn1int(g_b64u($e)));
    $spki = g_asn1(0x30, hex2bin('300d06092a864886f70d0101010500') . g_asn1(0x03, "\0" . $rsa));
    return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($spki), 64, "\n") . "-----END PUBLIC KEY-----\n";
}

function g_http_get(string $url): ?string {
    $verify = env_bool('HTTP_VERIFY_SSL', true);
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10, CURLOPT_SSL_VERIFYPEER => $verify, CURLOPT_SSL_VERIFYHOST => $verify ? 2 : 0]);
        $r = curl_exec($ch); curl_close($ch);
        return $r === false ? null : $r;
    }
    $r = @file_get_contents($url, false, stream_context_create(['http' => ['timeout' => 10], 'ssl' => ['verify_peer' => $verify, 'verify_peer_name' => $verify]]));
    return $r === false ? null : $r;
}

function g_http_post(string $url, array $fields): ?string {
    $verify = env_bool('HTTP_VERIFY_SSL', true);
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query($fields), CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => $verify, CURLOPT_SSL_VERIFYHOST => $verify ? 2 : 0]);
        $r = curl_exec($ch); curl_close($ch);
        return $r === false ? null : $r;
    }
    $r = @file_get_contents($url, false, stream_context_create(['http' => ['method' => 'POST', 'timeout' => 15, 'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
        'content' => http_build_query($fields)], 'ssl' => ['verify_peer' => $verify, 'verify_peer_name' => $verify]]));
    return $r === false ? null : $r;
}

function g_get_keys(bool $force = false): array {
    $cache = __DIR__ . '/../storage/google_jwks.json';
    if (!$force && is_file($cache) && time() - filemtime($cache) < 21600) {
        $j = json_decode((string)file_get_contents($cache), true);
        if (!empty($j['keys'])) return $j['keys'];
    }
    $raw = g_http_get('https://www.googleapis.com/oauth2/v3/certs');
    $j = $raw ? json_decode($raw, true) : null;
    if (!empty($j['keys'])) { @file_put_contents($cache, $raw); return $j['keys']; }
    return [];
}

// Returns ['ok'=>true,'claims'=>[...]] or ['ok'=>false,'error'=>'...']
function google_verify_id_token(string $jwt, string $clientId): array {
    $fail = fn(string $m) => ['ok' => false, 'error' => $m];
    if ($clientId === '') return $fail('Google sign-in is not configured.');
    $parts = explode('.', $jwt);
    if (count($parts) !== 3 || strlen($jwt) > 4096) return $fail('Malformed token.');
    $header = json_decode(g_b64u($parts[0]), true); $claims = json_decode(g_b64u($parts[1]), true); $sig = g_b64u($parts[2]);
    if (!is_array($header) || !is_array($claims) || ($header['alg'] ?? '') !== 'RS256' || empty($header['kid'])) return $fail('Unsupported token.');

    $key = null;
    foreach ([false, true] as $force) {                       // second pass refreshes Google's keys if the key id is new
        foreach (g_get_keys($force) as $k) if (($k['kid'] ?? '') === $header['kid'] && ($k['kty'] ?? '') === 'RSA') { $key = $k; break 2; }
    }
    if (!$key) return $fail('Could not fetch Google signing keys. Check the server internet connection.');
    $ok = openssl_verify($parts[0] . '.' . $parts[1], $sig, g_jwk_to_pem($key['n'], $key['e']), OPENSSL_ALGO_SHA256);
    if ($ok !== 1) return $fail('Invalid token signature.');

    $now = time();
    if (!in_array($claims['iss'] ?? '', ['https://accounts.google.com', 'accounts.google.com'], true)) return $fail('Wrong token issuer.');
    $aud = $claims['aud'] ?? '';
    if (!(is_array($aud) ? in_array($clientId, $aud, true) : hash_equals($clientId, (string)$aud))) return $fail('Token is for a different application.');
    if (($claims['exp'] ?? 0) < $now - 30) return $fail('Token expired.');
    if (($claims['iat'] ?? 0) > $now + 300) return $fail('Token issued in the future.');
    $verified = $claims['email_verified'] ?? false;
    if (!($verified === true || $verified === 'true')) return $fail('Your Google email is not verified.');
    if (empty($claims['sub']) || !filter_var($claims['email'] ?? '', FILTER_VALIDATE_EMAIL)) return $fail('Token has no usable identity.');
    return ['ok' => true, 'claims' => $claims];
}
