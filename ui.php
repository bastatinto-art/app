<?php
// Shared UI pieces (icons, logo, user menu). Presentation only.
function ui_icon(string $name): string {
    static $p = [
        'dashboard' => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
        'search'    => '<circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/>',
        'map'       => '<path d="M9 4L3 6v14l6-2 6 2 6-2V4l-6 2-6-2z"/><path d="M9 4v14M15 6v14"/>',
        'favorites' => '<path d="M12 21s-7-4.6-9.3-9A5.4 5.4 0 0 1 12 6.4 5.4 5.4 0 0 1 21.3 12C19 16.4 12 21 12 21z"/>',
        'requests'  => '<rect x="3" y="4" width="18" height="17" rx="2"/><path d="M8 2v4M16 2v4M3 10h18"/>',
        'messages'  => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/>',
        'profile'   => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
        'properties'=> '<path d="M4 21V5a1 1 0 0 1 1-1h8a1 1 0 0 1 1 1v16"/><path d="M14 9h5a1 1 0 0 1 1 1v11"/><path d="M8 8h2M8 12h2M8 16h2"/><path d="M3 21h18"/>',
        'pin'       => '<path d="M12 21s7-6.2 7-11.5A7 7 0 0 0 5 9.5C5 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/>',
        'eye'       => '<path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
        'chat'      => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
        'home'      => '<path d="M3 11l9-7 9 7"/><path d="M5 10v11h14V10"/><path d="M10 21v-6h4v6"/>',
        'chev'      => '<path d="M9 6l6 6-6 6"/>',
        'caret'     => '<path d="M6 9l6 6 6-6"/>',
        'arrow'     => '<path d="M5 12h14M13 6l6 6-6 6"/>',
        'filter'    => '<path d="M4 6h16M7 12h10M10 18h4"/>',
        'refresh'   => '<path d="M21 12a9 9 0 1 1-3-6.7"/><path d="M21 4v5h-5"/>',
        'cash'      => '<rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/>',
        'image'     => '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="1.8"/><path d="M21 16l-5-5-8 8"/>',
        'upload'    => '<path d="M12 16V4M7 9l5-5 5 5"/><path d="M4 20h16"/>',
        'users'     => '<circle cx="9" cy="8" r="3.5"/><path d="M2 20a7 7 0 0 1 14 0"/><path d="M16 4.5a3.5 3.5 0 0 1 0 7M18 20a7 7 0 0 0-3-5.7"/>',
        'list'      => '<path d="M8 6h13M8 12h13M8 18h13"/><circle cx="4" cy="6" r="1"/><circle cx="4" cy="12" r="1"/><circle cx="4" cy="18" r="1"/>',
        'shield'    => '<path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z"/><path d="M9 12l2 2 4-4"/>',
        'check'     => '<path d="M5 12l5 5L20 7"/>',
        'bed'       => '<path d="M3 18V7"/><path d="M3 14h18v4"/><path d="M21 14v-2a3 3 0 0 0-3-3h-7v5"/><circle cx="7" cy="11" r="1.6"/>',
    ];
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($p[$name] ?? '') . '</svg>';
}

function ui_logo(): string {
    global $conn;
    if (isset($conn) && $conn instanceof mysqli) {
        require_once __DIR__ . '/site_settings.php';
        $url = site_image_url($conn, 'logo_path');
        if ($url) return '<img class="brand-logo" src="' . e(($GLOBALS['ASSET_BASE'] ?? '') . $url) . '" alt="HomeRent logo">';
    }
    return '<svg class="brand-mark" viewBox="0 0 40 40" fill="none" stroke="#fff" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 19L20 5l16 14"/><path d="M8 16v19h24V16"/><path d="M16 35V24h8v11" stroke="#5eb0ff"/></svg>';
}

function ui_initials(string $name): string {
    $w = preg_split('/\s+/', trim($name)) ?: [];
    return strtoupper(mb_substr($w[0] ?? '?', 0, 1) . (isset($w[1]) ? mb_substr($w[1], 0, 1) : ''));
}

// $role: 'student' | 'landlord'; $profileHref: the profile page for that role
function ui_user_menu(string $role, string $profileHref = ''): string {
    $name = (string)($_SESSION['name'] ?? '');
    $first = explode(' ', trim($name))[0] ?: 'Account';
    return '<details class="user-menu"><summary><span class="avatar-sm">' . (!empty($_SESSION['avatar']) ? '<img src="' . e($_SESSION['avatar']) . '" alt="" referrerpolicy="no-referrer">' : e(ui_initials($name))) . '</span>'
        . '<span class="user-meta"><strong>Hi, ' . e($first) . '</strong><small>' . e(ucfirst($role)) . '</small></span>'
        . '<span class="caret">' . ui_icon('caret') . '</span></summary>'
        . '<div class="user-drop">' . ($profileHref ? '<a href="' . e($profileHref) . '">My profile</a>' : '') . '<a href="logout.php">Log out</a></div></details>';
}
