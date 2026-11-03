<?php
// Small SMTP mailer (no libraries). Credentials come from .env, never from code or the browser.
require_once __DIR__ . '/env.php';

function mail_clean(string $s): string { return trim(str_replace(["\r", "\n"], ' ', $s)); }

function send_mail(string $to, string $toName, string $subject, string $html, string $text): bool {
    $to = mail_clean($to);
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return false;
    if (env('MAIL_DRIVER', 'smtp') === 'log') {            // LOCAL TESTING ONLY: writes the email to storage/mail.log
        @file_put_contents(__DIR__ . '/../storage/mail.log', "=== " . date('c') . " to $to | $subject\n$text\n\n", FILE_APPEND);
        return true;
    }
    $host = env('SMTP_HOST'); $port = (int)env('SMTP_PORT', 587); $secure = strtolower((string)env('SMTP_SECURE', 'tls'));
    $user = env('SMTP_USER'); $pass = env('SMTP_PASS');
    $from = mail_clean((string)env('MAIL_FROM', $user)); $fromName = mail_clean((string)env('MAIL_FROM_NAME', 'HomeRent'));
    if (!$host || !$user || !$pass || !filter_var($from, FILTER_VALIDATE_EMAIL)) { error_log('Mailer: SMTP is not configured in .env'); return false; }

    $ctx = stream_context_create(['ssl' => ['verify_peer' => env_bool('SMTP_VERIFY_PEER', true), 'verify_peer_name' => env_bool('SMTP_VERIFY_PEER', true), 'SNI_enabled' => true]]);
    $fp = @stream_socket_client(($secure === 'ssl' ? 'ssl://' : 'tcp://') . "$host:$port", $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) { error_log("Mailer: cannot connect ($errno $errstr)"); return false; }
    stream_set_timeout($fp, 15);

    $read = function () use ($fp): string {
        $out = '';
        while (($line = fgets($fp, 515)) !== false) { $out .= $line; if (strlen($line) < 4 || $line[3] === ' ') break; }
        return $out;
    };
    $cmd = function (string $c, array $ok) use ($fp, $read): bool {
        if ($c !== '') fwrite($fp, $c . "\r\n");
        $r = $read();
        $good = in_array((int)substr($r, 0, 3), $ok, true);
        if (!$good) error_log('Mailer: unexpected reply: ' . trim($r));
        return $good;
    };
    try {
        if (!$cmd('', [220]) || !$cmd('EHLO homerent.local', [250])) return false;
        if ($secure === 'tls') {
            if (!$cmd('STARTTLS', [220])) return false;
            if (!@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) { error_log('Mailer: STARTTLS failed (see SMTP_VERIFY_PEER in .env.example)'); return false; }
            if (!$cmd('EHLO homerent.local', [250])) return false;
        }
        if (!$cmd('AUTH LOGIN', [334]) || !$cmd(base64_encode($user), [334]) || !$cmd(base64_encode($pass), [235])) return false;
        if (!$cmd("MAIL FROM:<$from>", [250]) || !$cmd("RCPT TO:<$to>", [250, 251]) || !$cmd('DATA', [354])) return false;

        $b = 'hr_' . bin2hex(random_bytes(8));
        $hdr = "Date: " . date('r') . "\r\nFrom: =?UTF-8?B?" . base64_encode($fromName) . "?= <$from>\r\nTo: <$to>\r\n"
             . "Subject: =?UTF-8?B?" . base64_encode(mail_clean($subject)) . "?=\r\nMIME-Version: 1.0\r\n"
             . "Content-Type: multipart/alternative; boundary=\"$b\"\r\n";
        $body = "--$b\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($text))
              . "--$b\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($html))
              . "--$b--\r\n";
        fwrite($fp, $hdr . "\r\n" . $body . "\r\n.\r\n");
        $ok = in_array((int)substr($read(), 0, 3), [250], true);
        fwrite($fp, "QUIT\r\n");
        return $ok;
    } finally { @fclose($fp); }
}
