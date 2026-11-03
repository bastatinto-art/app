<?php
// Shared session, CSRF, flash and role-guard helpers. Included by every protected page.
require_once __DIR__ . '/../connect.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_field(): string {
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}
function csrf_verify(): void {
    $ok = isset($_POST['csrf'], $_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $_POST['csrf']);
    if (!$ok) { http_response_code(403); die('Invalid or expired form. Go back, refresh the page and try again.'); }
}

function flash_set(string $type, string $msg): void { $_SESSION['flash'][] = [$type, $msg]; }
function flash_pull(): array { $f = $_SESSION['flash'] ?? []; unset($_SESSION['flash']); return $f; }

// Only allows redirects to local pages (blocks open-redirect via return fields)
function safe_local_url(?string $url, string $fallback): string {
    if ($url && preg_match('#^[A-Za-z0-9_\-]+\.php(\?[A-Za-z0-9_\-=&%\[\]\.\+]*)?$#', $url)) return $url;
    return $fallback;
}

function require_role(string $role): void {
    if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== $role) {
        if (!empty($_SESSION['user_id'])) {   // logged in with a different role
            $home = ['student' => 'student-dashboard.php', 'landlord' => 'landlord-dashboard.php'];
            header('Location: ' . ($home[$_SESSION['role']] ?? 'login.html'));
        } else {
            header('Location: login.html?err=login_required');
        }
        exit;
    }
    // Re-check on every request: account exists, is active, email is verified, and the password was not changed after this login.
    $tables = ['student' => 'students', 'landlord' => 'landlords'];
    if (isset($tables[$role])) {
        global $conn;
        $st = $conn->prepare("SELECT account_status, email_verified, UNIX_TIMESTAMP(password_changed_at) AS pca FROM {$tables[$role]} WHERE id = ?");
        $st->bind_param('i', $_SESSION['user_id']);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        $reason = null;
        if (!$row || $row['account_status'] !== 'active') $reason = 'inactive';
        elseif ((int)$row['email_verified'] !== 1) $reason = 'unverified';
        elseif ($row['pca'] !== null && (int)$row['pca'] > (int)($_SESSION['auth_at'] ?? 0)) $reason = 'expired';
        if ($reason) {
            session_unset(); session_destroy();
            header('Location: login.html?err=' . $reason); exit;
        }
    }
}

function peso($n): string { return '₱' . number_format((float)$n, 0); }
function type_label(string $t): string {
    return ['boarding_house' => 'Boarding house', 'apartment' => 'Apartment', 'dormitory' => 'Dormitory', 'bedspace' => 'Bed Spacer'][$t] ?? $t;
}
