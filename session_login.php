<?php
// One place that starts a logged-in session for students/landlords (password login and Google login).
function hr_login(string $role, int $id, string $name, ?string $avatar): string {
    session_regenerate_id(true);
    $_SESSION = [
        'user_id' => $id, 'role' => $role, 'name' => $name,
        'avatar' => $avatar && preg_match('#^https://[a-z0-9.\-]*googleusercontent\.com/#i', $avatar) ? $avatar : '',
        'auth_at' => time(),          // lets a password change sign out older sessions
        'show_welcome' => true,       // welcome.php shows once, then clears this
    ];
    return 'welcome.php';
}
function hr_table(string $type): string { return $type === 'landlord' ? 'landlords' : 'students'; }
