# Google sign-in, email OTP, animations, flip login, welcome screen

## 1. Database
phpMyAdmin > homerent_db > SQL: run sql/07_security_google_otp.sql  (after 01-06).
Existing accounts stay as they are and are marked "email verified" (they registered before verification existed).

## 2. Copy files (keep sub-folders)
NEW:  .env.example, .htaccess, storage/ (folder + .htaccess), google-config.js.php, google-auth.php, google-complete.php,
      google-confirm-link.php, google-unlink.php, verify-email.php, forgot-password.php, reset-password.php,
      change-password.php, welcome.php,
      includes/: env.php mailer.php mail_templates.php otp.php google_jwt.php session_login.php auth_page.php password_flow.php
      assets/css/motion.css, assets/css/auth-pages.css, assets/js/google-btn.js
CHANGED: login.html, login.php, register.php, register-process.php, includes/auth.php, includes/ui.php,
      includes/student_header.php, includes/landlord_header.php, student-profile.php, landlord-profile.php,
      assets/css/student.css, admin/profile.php, admin/login.php, admin/includes/auth.php, admin/includes/header.php

## 3. Email (SMTP) setup  -> .env
1. Copy .env.example to a new file named exactly  .env  (same folder as login.html).
2. Easiest: a Gmail address that belongs to HomeRent. Turn on 2-Step Verification, then
   Google Account > Security > 2-Step Verification > App passwords > create one named "HomeRent".
   Paste the 16 characters into SMTP_PASS. NEVER use the normal Gmail password.
3. Fill SMTP_USER, MAIL_FROM. Keep SMTP_HOST=smtp.gmail.com, SMTP_PORT=587, SMTP_SECURE=tls.
4. If XAMPP says the TLS certificate cannot be verified, set SMTP_VERIFY_PEER=false for local testing only.
5. Quick local test WITHOUT real email: set MAIL_DRIVER=log. Codes are then written to storage/mail.log. Never use "log" on a real site.
.env is blocked from the web by .htaccess. Do not upload it anywhere.

## 4. Google Cloud Console setup
1. https://console.cloud.google.com > create a project "HomeRent".
2. APIs & Services > OAuth consent screen: External, app name HomeRent, your email. Add yourself (and testers) under Test users while in Testing mode.
3. APIs & Services > Credentials > Create credentials > OAuth client ID > Web application.
   Authorized JavaScript origins:   http://localhost
   Authorized redirect URIs:        http://localhost/homerent/google-auth.php     (use your real folder name)
4. Copy the Client ID into .env as GOOGLE_CLIENT_ID.  (There is no client secret in this flow.)
5. For a real domain later, add https://yourdomain and https://yourdomain/google-auth.php in the same two lists.
If GOOGLE_CLIENT_ID is empty, the Google buttons simply stay hidden.

## 5. Testing OTP
- Sign up on login.html (flip to Sign up) -> you land on verify-email.php -> enter the emailed code -> log in.
- Wrong code: 5 tries then the code is dead. Resend: 1 per minute, 5 per hour. Codes last 5 minutes and work once.
- Forgot Password link on the login card -> code -> new password -> notification email.
- Profile > Change password -> current password -> emailed code -> new password. Other devices are signed out.
- Admin: admin/profile.php uses the same steps (12+ character password).
