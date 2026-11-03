<?php
// Friendly blue HomeRent emails. Returns [subject, html, text].
function mail_layout(string $heading, string $inner): string {
    $h = htmlspecialchars($heading, ENT_QUOTES, 'UTF-8');
    return '<!DOCTYPE html><html><body style="margin:0;background:#eaf2fd;font-family:Segoe UI,Arial,sans-serif;color:#16284a">'
      . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eaf2fd;padding:24px 12px"><tr><td align="center">'
      . '<table role="presentation" width="480" cellpadding="0" cellspacing="0" style="max-width:480px;width:100%;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 6px 20px rgba(30,64,175,.12)">'
      . '<tr><td style="background:#0b3a8c;background-image:linear-gradient(95deg,#072a66,#1456c9);padding:22px 28px;color:#ffffff;font-size:24px;font-weight:700">&#127968; Home<span style="color:#7cc0ff">Rent</span></td></tr>'
      . '<tr><td style="padding:28px"><h1 style="margin:0 0 12px;font-size:20px;color:#0b3a8c">' . $h . '</h1>' . $inner . '</td></tr>'
      . '<tr><td style="padding:16px 28px;background:#f4f8ff;color:#64748b;font-size:12px">You received this email because of activity on your HomeRent account. If this wasn\'t you, you can ignore it.</td></tr>'
      . '</table></td></tr></table></body></html>';
}

function mail_otp(string $name, string $code, string $purpose, bool $googleOnly = false): array {
    $subjects = ['register' => 'Verify Your HomeRent Account', 'reset' => 'Password Reset Request', 'change_password' => 'Your HomeRent Verification Code'];
    $intro = ['register' => 'Welcome to HomeRent! Use this code to verify your email address.',
              'reset' => 'We received a request to reset your password. Use this code to continue.',
              'change_password' => 'Use this code to confirm your password change.'][$purpose] ?? 'Your verification code:';
    $n = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    $inner = "<p>Hi $n,</p><p>$intro</p>"
      . '<p style="text-align:center;margin:22px 0"><span style="display:inline-block;font-size:32px;letter-spacing:8px;font-weight:700;color:#0b3a8c;background:#eaf2ff;border:1px solid #bfd6ff;border-radius:12px;padding:12px 22px">' . $code . '</span></p>'
      . '<p>This code expires in <strong>5 minutes</strong> and can be used once. Never share it with anyone, HomeRent will never ask for it.</p>';
    if ($googleOnly) $inner .= '<p style="background:#eaf2ff;border-radius:10px;padding:12px;font-size:13px"><strong>Note:</strong> your HomeRent account signs in with Google, and Google manages that password. To recover your Google account use <a href="https://accounts.google.com/signin/recovery">Google Account Recovery</a>. This code only lets you create an extra HomeRent password.</p>';
    return [$subjects[$purpose] ?? 'Your HomeRent Verification Code', mail_layout($subjects[$purpose] ?? 'Verification code', $inner),
            "Hi $name,\n$intro\n\nYour code: $code\nIt expires in 5 minutes and can be used once. Never share it.\n"];
}

function mail_password_changed(string $name): array {
    $n = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    $inner = "<p>Hi $n,</p><p>Your HomeRent password was just changed. All other devices have been signed out.</p>"
      . '<p><strong>If this was not you</strong>, reset your password right away using &ldquo;Forgot password?&rdquo; on the login page and contact the HomeRent administrator.</p>';
    return ['Your HomeRent Password Has Been Changed', mail_layout('Password changed', $inner),
            "Hi $name,\nYour HomeRent password was just changed. If this was not you, reset it right away with 'Forgot password?' and contact the administrator.\n"];
}
