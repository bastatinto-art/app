<?php
// Change-password with OTP, shared by Student, Landlord and Admin.
// Stage 1: current password -> Stage 2: email code -> Stage 3: new password. State lives in the SESSION (not in the URL).
require_once __DIR__ . '/otp.php';

function pw_stage(string $key): int {
    $s = $_SESSION[$key] ?? null;
    if (!$s) return 1;
    if ($s['stage'] === 3 && time() - ($s['ok_at'] ?? 0) > 600) { unset($_SESSION[$key]); return 1; }
    return (int)$s['stage'];
}

/**
 * $c: key, type (student|landlord|admin), id, email, name, minLen, self,
 *     hashSql ("SELECT password FROM x WHERE id=?"), updateSql ("UPDATE x SET password=?, ...=? WHERE id=?" -> hash, datetime, id),
 *     after (callable run after success)
 * Handles POST (then redirects). Returns the current stage on GET.
 */
function pw_flow(mysqli $conn, array $c): int {
    $key = $c['key'];
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return pw_stage($key);
    csrf_verify();
    $act = $_POST['action'] ?? '';
    $stage = pw_stage($key);
    $redir = function () use ($c) { header('Location: ' . $c['self']); exit; };

    if ($act === 'cancel') { unset($_SESSION[$key]); flash_set('success', 'Password change cancelled.'); $redir(); }

    if ($act === 'current' && $stage === 1) {
        if (($_SESSION[$key . '_lock'] ?? 0) > time()) { flash_set('error', 'Too many wrong passwords. Try again in a few minutes.'); $redir(); }
        $q = $conn->prepare($c['hashSql']); $q->bind_param('i', $c['id']); $q->execute();
        $hash = $q->get_result()->fetch_row()[0] ?? ''; $q->close();
        if (!password_verify($_POST['current_password'] ?? '', $hash)) {
            $_SESSION[$key . '_fails'] = ($_SESSION[$key . '_fails'] ?? 0) + 1;
            if ($_SESSION[$key . '_fails'] >= 5) { $_SESSION[$key . '_lock'] = time() + 600; $_SESSION[$key . '_fails'] = 0; }
            flash_set('error', 'Your current password is incorrect.'); $redir();
        }
        $_SESSION[$key . '_fails'] = 0;
        $r = otp_issue($conn, 'change_password', $c['type'], $c['id'], $c['email'], $c['name']);
        if (!$r['ok'] && $r['reason'] === 'mail') { flash_set('error', 'We could not send the email. Please try again later.'); $redir(); }
        if (!$r['ok'] && $r['reason'] === 'limit') { flash_set('error', 'Too many codes requested. Try again in an hour.'); $redir(); }
        $_SESSION[$key] = ['stage' => 2];
        flash_set('success', 'We sent a 6-digit code to ' . mask_email($c['email']) . '.'); $redir();
    }

    if ($stage === 2 && $act === 'resend') {
        $r = otp_issue($conn, 'change_password', $c['type'], $c['id'], $c['email'], $c['name']);
        if ($r['ok']) flash_set('success', 'A new code was sent. The previous code no longer works.');
        elseif ($r['reason'] === 'cooldown') flash_set('error', 'Please wait ' . $r['wait'] . ' seconds before requesting another code.');
        elseif ($r['reason'] === 'limit') flash_set('error', 'Too many codes requested. Try again in an hour.');
        else flash_set('error', 'We could not send the email. Please try again later.');
        $redir();
    }

    if ($stage === 2 && $act === 'otp') {
        $res = otp_verify($conn, 'change_password', $c['type'], $c['id'], $_POST['code'] ?? '');
        if ($res === 'ok') { $_SESSION[$key] = ['stage' => 3, 'ok_at' => time()]; flash_set('success', 'Code verified. Choose your new password.'); }
        else { flash_set('error', otp_message($res)); }
        $redir();
    }

    if ($stage === 3 && $act === 'new') {
        $new = $_POST['new_password'] ?? '';
        if (strlen($new) < $c['minLen']) { flash_set('error', 'New password must be at least ' . $c['minLen'] . ' characters.'); $redir(); }
        if ($new !== ($_POST['confirm_password'] ?? '')) { flash_set('error', 'New password and confirmation do not match.'); $redir(); }
        $hash = password_hash($new, PASSWORD_DEFAULT); $now = date('Y-m-d H:i:s');
        $u = $conn->prepare($c['updateSql']); $u->bind_param('ssi', $hash, $now, $c['id']); $u->execute();
        unset($_SESSION[$key]);
        if (!empty($c['after'])) ($c['after'])();
        [$subject, $html, $text] = mail_password_changed($c['name']);
        send_mail($c['email'], $c['name'], $subject, $html, $text);
        flash_set('success', 'Password changed. Other devices were signed out and we emailed you a confirmation.');
        header('Location: ' . ($c['doneUrl'] ?? $c['self'])); exit;
    }
    $redir();
}
