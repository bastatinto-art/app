<?php
// Plain helpers shared by the admin portal. Guarded so they never clash with includes/auth.php.
if (!function_exists('e')) { function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); } }
if (!function_exists('csrf_token')) {
    function csrf_token(): string { if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32)); return $_SESSION['csrf']; }
    function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">'; }
    function csrf_verify(): void {
        $ok = isset($_POST['csrf'], $_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $_POST['csrf']);
        if (!$ok) { http_response_code(403); die('Invalid or expired form. Go back, refresh the page and try again.'); }
    }
}
if (!function_exists('flash_set')) {
    function flash_set(string $type, string $msg): void { $_SESSION['flash'][] = [$type, $msg]; }
    function flash_pull(): array { $f = $_SESSION['flash'] ?? []; unset($_SESSION['flash']); return $f; }
}
if (!function_exists('peso')) { function peso($n): string { return '₱' . number_format((float)$n, 0); } }
if (!function_exists('type_label')) {
    function type_label(string $t): string {
        return ['boarding_house' => 'Boarding House', 'apartment' => 'Apartment', 'dormitory' => 'Dormitory', 'bedspace' => 'Bed Spacer'][$t] ?? $t;
    }
}
