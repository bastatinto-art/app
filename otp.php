<?php
// Email one-time codes: random, hashed, expiring, single-use, attempt-limited, resend-limited.
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/mail_templates.php';

const OTP_TTL = 300;          // 5 minutes
const OTP_MAX_ATTEMPTS = 5;   // wrong guesses per code
const OTP_COOLDOWN = 60;      // seconds between codes
const OTP_HOURLY_MAX = 5;     // codes per hour per account+purpose

function mask_email(string $email): string {
    [$u, $d] = array_pad(explode('@', $email, 2), 2, '');
    return mb_substr($u, 0, 1) . str_repeat('*', max(2, mb_strlen($u) - 1)) . '@' . $d;
}

function otp_wait_seconds(mysqli $c, string $purpose, string $type, int $id): int {
    $st = $c->prepare("SELECT created_at FROM otp_codes WHERE purpose=? AND account_type=? AND account_id=? ORDER BY id DESC LIMIT 1");
    $st->bind_param('ssi', $purpose, $type, $id); $st->execute();
    $r = $st->get_result()->fetch_row(); $st->close();
    return $r ? max(0, OTP_COOLDOWN - (time() - strtotime($r[0]))) : 0;
}

function otp_has_active(mysqli $c, string $purpose, string $type, int $id): bool {
    $now = date('Y-m-d H:i:s');
    $st = $c->prepare("SELECT 1 FROM otp_codes WHERE purpose=? AND account_type=? AND account_id=? AND used_at IS NULL AND expires_at > ? LIMIT 1");
    $st->bind_param('ssis', $purpose, $type, $id, $now); $st->execute();
    $has = $st->get_result()->num_rows > 0; $st->close();
    return $has;
}

// Returns ['ok'=>true] or ['ok'=>false,'reason'=>'cooldown'|'limit'|'mail','wait'=>seconds]
function otp_issue(mysqli $c, string $purpose, string $type, int $id, string $email, string $name, bool $googleOnly = false): array {
    $wait = otp_wait_seconds($c, $purpose, $type, $id);
    if ($wait > 0) return ['ok' => false, 'reason' => 'cooldown', 'wait' => $wait];

    $since = date('Y-m-d H:i:s', time() - 3600);
    $st = $c->prepare("SELECT COUNT(*) FROM otp_codes WHERE purpose=? AND account_type=? AND account_id=? AND created_at > ?");
    $st->bind_param('ssis', $purpose, $type, $id, $since); $st->execute();
    $n = (int)$st->get_result()->fetch_row()[0]; $st->close();
    if ($n >= OTP_HOURLY_MAX) return ['ok' => false, 'reason' => 'limit', 'wait' => 0];

    $now = date('Y-m-d H:i:s');
    $inv = $c->prepare("UPDATE otp_codes SET used_at=? WHERE purpose=? AND account_type=? AND account_id=? AND used_at IS NULL");   // old codes die
    $inv->bind_param('sssi', $now, $purpose, $type, $id); $inv->execute(); $inv->close();

    $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $hash = password_hash($code, PASSWORD_DEFAULT);
    $exp = date('Y-m-d H:i:s', time() + OTP_TTL);
    $ip = substr($_SERVER['REMOTE_ADDR'] ?? '', 0, 45);
    $ins = $c->prepare("INSERT INTO otp_codes (purpose, account_type, account_id, email, code_hash, expires_at, created_at, ip) VALUES (?,?,?,?,?,?,?,?)");
    $ins->bind_param('ssisssss', $purpose, $type, $id, $email, $hash, $exp, $now, $ip); $ins->execute();
    $oid = $c->insert_id; $ins->close();

    [$subject, $html, $text] = mail_otp($name, $code, $purpose, $googleOnly);
    if (!send_mail($email, $name, $subject, $html, $text)) {
        $dead = $c->prepare("UPDATE otp_codes SET used_at=? WHERE id=?"); $dead->bind_param('si', $now, $oid); $dead->execute();
        return ['ok' => false, 'reason' => 'mail', 'wait' => 0];
    }
    return ['ok' => true];
}

// 'ok' | 'invalid' | 'expired' | 'locked' | 'none'
function otp_verify(mysqli $c, string $purpose, string $type, int $id, string $code): string {
    $code = trim($code);
    $st = $c->prepare("SELECT id, code_hash, attempts, expires_at FROM otp_codes WHERE purpose=? AND account_type=? AND account_id=? AND used_at IS NULL ORDER BY id DESC LIMIT 1");
    $st->bind_param('ssi', $purpose, $type, $id); $st->execute();
    $r = $st->get_result()->fetch_assoc(); $st->close();
    if (!$r) return 'none';
    $now = date('Y-m-d H:i:s');
    $kill = function () use ($c, $r, $now) { $k = $c->prepare("UPDATE otp_codes SET used_at=? WHERE id=?"); $k->bind_param('si', $now, $r['id']); $k->execute(); };
    if (strtotime($r['expires_at']) < time()) { $kill(); return 'expired'; }
    if ((int)$r['attempts'] >= OTP_MAX_ATTEMPTS) { $kill(); return 'locked'; }
    if (preg_match('/^\d{6}$/', $code) && password_verify($code, $r['code_hash'])) {
        $u = $c->prepare("UPDATE otp_codes SET used_at=? WHERE id=? AND used_at IS NULL");      // single use, race-safe
        $u->bind_param('si', $now, $r['id']); $u->execute();
        return $u->affected_rows === 1 ? 'ok' : 'none';
    }
    $a = $c->prepare("UPDATE otp_codes SET attempts = attempts + 1 WHERE id=?"); $a->bind_param('i', $r['id']); $a->execute();
    if ((int)$r['attempts'] + 1 >= OTP_MAX_ATTEMPTS) { $kill(); return 'locked'; }
    return 'invalid';
}

function otp_message(string $status): string {
    return ['invalid' => 'That code is not correct.', 'expired' => 'That code has expired. Request a new one.',
            'locked' => 'Too many wrong attempts. Request a new code.', 'none' => 'No active code. Request a new one.'][$status] ?? 'Verification failed.';
}
