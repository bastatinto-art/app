<?php
// Everything that happens AFTER Google's signed ID token has been verified on the server.
require_once __DIR__ . '/session_login.php';

function google_back_url(string $intent): string {
    if ($intent === 'link') return ($_SESSION['role'] ?? '') === 'landlord' ? 'landlord-profile.php' : 'student-profile.php';
    return $intent === 'login' ? 'login.html' : 'login.html?panel=signup';
}
function google_fail(string $intent, string $code, string $msg, array $extra = []): void {
    if ($intent === 'link') { flash_set('error', $msg); header('Location: ' . google_back_url('link')); exit; }
    $q = ($intent === 'login' ? '?err=' : '?panel=signup&rmsg=') . $code;
    foreach ($extra as $k => $v) $q .= '&' . $k . '=' . urlencode($v);
    header('Location: login.html' . $q); exit;
}

function google_create_account(mysqli $conn, string $type, string $sub, string $email, string $name, ?string $pic): int {
    $table = hr_table($type);
    $rand = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);   // unusable password: this account signs in with Google
    $now = date('Y-m-d H:i:s');
    try {
        $conn->begin_transaction();
        $i = $conn->prepare("INSERT INTO $table (fullname, email, password, email_verified, email_verified_at, password_set, avatar_url) VALUES (?, ?, ?, 1, ?, 0, ?)");
        $i->bind_param('sssss', $name, $email, $rand, $now, $pic); $i->execute();
        $id = (int)$conn->insert_id;
        $l = $conn->prepare("INSERT INTO oauth_links (provider, provider_user_id, account_type, account_id, email) VALUES ('google', ?, ?, ?, ?)");
        $l->bind_param('ssis', $sub, $type, $id, $email); $l->execute();
        $conn->commit();
        return $id;
    } catch (mysqli_sql_exception $e) { $conn->rollback(); throw $e; }
}

/**
 * $intent: 'login' | 'student' | 'landlord' | 'link'.  $o = the saved OAuth start state (for 'link': who started it).
 * Always ends with a redirect.
 */
function google_process(mysqli $conn, array $cl, string $intent, array $o): void {
    $sub = (string)$cl['sub']; $email = strtolower($cl['email']);
    $name = mb_substr(trim((string)($cl['name'] ?? '')) ?: explode('@', $email)[0], 0, 100);
    $pic = (isset($cl['picture']) && preg_match('#^https://#', $cl['picture'])) ? mb_substr($cl['picture'], 0, 500) : null;

    $st = $conn->prepare("SELECT account_type, account_id FROM oauth_links WHERE provider = 'google' AND provider_user_id = ?");
    $st->bind_param('s', $sub); $st->execute(); $link = $st->get_result()->fetch_assoc(); $st->close();

    // ---- connect Google to the account that is logged in right now ----
    if ($intent === 'link') {
        $role = $_SESSION['role'] ?? ''; $uid = (int)($_SESSION['user_id'] ?? 0);
        if (!$uid || !in_array($role, ['student', 'landlord'], true) || $uid !== (int)$o['user'] || $role !== $o['role']) { header('Location: login.html?err=login_required'); exit; }
        $g = $conn->prepare("SELECT email FROM " . hr_table($role) . " WHERE id = ?"); $g->bind_param('i', $uid); $g->execute();
        $mine = strtolower((string)($g->get_result()->fetch_row()[0] ?? '')); $g->close();
        if ($link && $link['account_type'] === $role && (int)$link['account_id'] === $uid) { flash_set('success', 'This Google account is already connected.'); header('Location: ' . google_back_url('link')); exit; }
        if ($link) google_fail('link', '', 'That Google account is already connected to a different HomeRent account.');
        if ($mine !== $email) google_fail('link', '', 'Choose the Google account that uses the same email as your HomeRent account.');
        try {
            $i = $conn->prepare("INSERT INTO oauth_links (provider, provider_user_id, account_type, account_id, email) VALUES ('google', ?, ?, ?, ?)");
            $i->bind_param('ssis', $sub, $role, $uid, $email); $i->execute();
        } catch (mysqli_sql_exception $e) { google_fail('link', '', 'Your account already has a Google account connected. Disconnect it first.'); }
        flash_set('success', 'Google account connected.'); header('Location: ' . google_back_url('link')); exit;
    }

    $want = in_array($intent, ['student', 'landlord'], true) ? $intent : null;     // role the user chose BEFORE starting Google

    // ---- Google account already connected: log in (role never changes) ----
    if ($link) {
        if ($want && $link['account_type'] !== $want) google_fail($intent, 'google_role', '', ['rt' => $link['account_type']]);
        $a = $conn->prepare("SELECT id, fullname, account_status, avatar_url FROM " . hr_table($link['account_type']) . " WHERE id = ?");
        $a->bind_param('i', $link['account_id']); $a->execute(); $acct = $a->get_result()->fetch_assoc(); $a->close();
        if (!$acct) google_fail($intent, 'google_failed', '');
        if ($acct['account_status'] !== 'active') google_fail($intent, 'inactive', '');
        header('Location: ' . hr_login($link['account_type'], (int)$acct['id'], $acct['fullname'], $acct['avatar_url'] ?: $pic)); exit;
    }

    $found = [];
    foreach (['student' => 'students', 'landlord' => 'landlords'] as $type => $table) {
        $q = $conn->prepare("SELECT id, fullname, account_status, email_verified, avatar_url FROM $table WHERE email = ? LIMIT 1");
        $q->bind_param('s', $email); $q->execute();
        if ($r = $q->get_result()->fetch_assoc()) $found[] = $r + ['type' => $type];
        $q->close();
    }
    if (count($found) > 1) google_fail($intent, 'google_conflict', '');

    if (!$found) {
        if ($want) {                                           // new person who chose their role first: create the account now
            try { $id = google_create_account($conn, $want, $sub, $email, $name, $pic); }
            catch (mysqli_sql_exception $e) { error_log('google create: ' . $e->getMessage()); google_fail($intent, 'google_failed', ''); }
            header('Location: ' . hr_login($want, $id, $name, $pic)); exit;
        }
        $_SESSION['google_pending'] = ['sub' => $sub, 'email' => $email, 'name' => $name, 'picture' => $pic, 'exp' => time() + 900];
        header('Location: google-complete.php'); exit;         // clicked "log in" but has no account: ask Student or Landlord
    }

    $a = $found[0];
    if ($want && $a['type'] !== $want) google_fail($intent, 'google_role', '', ['rt' => $a['type']]);   // never switches roles
    if ($a['account_status'] !== 'active') google_fail($intent, 'inactive', '');
    $table = hr_table($a['type']);

    if ((int)$a['email_verified'] === 1) {                     // real, verified account: prove ownership with its password first
        $_SESSION['google_link_pending'] = ['type' => $a['type'], 'id' => (int)$a['id'], 'sub' => $sub, 'email' => $email, 'picture' => $pic, 'exp' => time() + 900, 'fails' => 0];
        header('Location: google-confirm-link.php'); exit;
    }
    // Never-verified sign-up: Google proves this person owns the email; the old (unverified) password is discarded.
    $rand = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT); $now = date('Y-m-d H:i:s');
    try {
        $conn->begin_transaction();
        $u = $conn->prepare("UPDATE $table SET password = ?, password_set = 0, email_verified = 1, email_verified_at = ?, avatar_url = COALESCE(avatar_url, ?) WHERE id = ?");
        $u->bind_param('sssi', $rand, $now, $pic, $a['id']); $u->execute();
        $i = $conn->prepare("INSERT INTO oauth_links (provider, provider_user_id, account_type, account_id, email) VALUES ('google', ?, ?, ?, ?)");
        $i->bind_param('ssis', $sub, $a['type'], $a['id'], $email); $i->execute();
        $conn->commit();
    } catch (mysqli_sql_exception $e) { $conn->rollback(); google_fail($intent, 'google_failed', ''); }
    header('Location: ' . hr_login($a['type'], (int)$a['id'], $a['fullname'], $a['avatar_url'] ?: $pic)); exit;
}
