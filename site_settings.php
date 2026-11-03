<?php
// Site-wide settings (logo + category photos) and secure image saving.
// Every read is wrapped so the site keeps working even before the migration has been run.

const SITE_IMG_KEYS = ['logo_path', 'cat_img_all', 'cat_img_apartment', 'cat_img_bedspace', 'cat_img_dormitory', 'cat_img_boarding_house'];

function site_settings(mysqli $conn): array {
    static $s = null;
    if ($s === null) {
        $s = [];
        try {
            foreach ($conn->query("SELECT setting_key, setting_value FROM site_settings")->fetch_all(MYSQLI_ASSOC) as $r) $s[$r['setting_key']] = $r['setting_value'];
        } catch (mysqli_sql_exception $e) { $s = []; }
    }
    return $s;
}

// Returns "uploads/site/xxx.jpg?v=123" if the setting exists AND the file is really there, else null.
function site_image_url(mysqli $conn, string $key): ?string {
    $v = site_settings($conn)[$key] ?? null;
    if (!$v || !preg_match('#^uploads/site/[a-z_]+-[a-f0-9]+\.(jpg|png|webp)$#', $v)) return null;
    $file = __DIR__ . '/../' . $v;
    return is_file($file) ? $v . '?v=' . filemtime($file) : null;
}

// Validates and stores one uploaded image. Returns an error message, or null on success.
function save_site_image(mysqli $conn, string $key, array $file, int $maxBytes, int $minW, int $minH, int $adminId): ?string {
    if (!in_array($key, SITE_IMG_KEYS, true)) return 'Unknown image slot.';
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return 'Choose an image first.';
    if ($file['error'] !== UPLOAD_ERR_OK) return 'The upload failed. Try a smaller file.';
    if ($file['size'] > $maxBytes) return 'The image is larger than ' . round($maxBytes / 1048576) . ' MB.';
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime] ?? null;
    $info = $ext ? @getimagesize($file['tmp_name']) : false;
    if (!$ext || !$info) return 'Only real JPG, PNG or WebP images are allowed.';
    if ($info[0] < $minW || $info[1] < $minH) return "The image is too small (minimum {$minW} x {$minH} pixels).";
    if ($info[0] > 6000 || $info[1] > 6000) return 'The image is too large (maximum 6000 x 6000 pixels).';

    $dir = __DIR__ . '/../uploads/site/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $name = $key . '-' . bin2hex(random_bytes(10)) . '.' . $ext;       // safe, generated filename
    if (!move_uploaded_file($file['tmp_name'], $dir . $name)) return 'Could not save the image on the server.';
    $path = 'uploads/site/' . $name;

    $old = site_settings($conn)[$key] ?? null;
    $st = $conn->prepare("INSERT INTO site_settings (setting_key, setting_value, updated_by) VALUES (?, ?, ?)
                          ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by)");
    $st->bind_param('ssi', $key, $path, $adminId); $st->execute();
    if ($old && preg_match('#^uploads/site/[a-z_]+-[a-f0-9]+\.(jpg|png|webp)$#', $old)) @unlink(__DIR__ . '/../' . $old);   // replace = remove the old file
    return null;
}
