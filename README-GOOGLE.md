# Real Google sign-in (OAuth 2.0 authorization-code flow)

How it works: the button on login.html opens google-start.php, which sends the browser to Google's real account chooser.
Google returns to google-callback.php with a one-time code; the SERVER swaps it for a signed ID token (using the secret in .env)
and verifies signature, issuer, audience, expiry, nonce, verified email and Google's permanent id ("sub").

## Files
NEW: google-start.php, google-callback.php, google-start-uri.php, includes/google_flow.php
CHANGED: login.html, google-complete.php, google-config.js.php, includes/google_jwt.php, includes/mail_templates.php, includes/otp.php,
         forgot-password.php, reset-password.php, change-password.php, student-profile.php, landlord-profile.php, assets/css/student.css, .env.example
DELETE (old version, no longer used): google-auth.php, assets/js/google-btn.js

## Google Cloud Console (one time)
1. https://console.cloud.google.com > project selector > New project > name "HomeRent".
2. APIs & Services > OAuth consent screen (Google Auth Platform > Branding/Audience):
   User type External, app name HomeRent, support email, developer email. Scopes: openid, email, profile (the defaults).
   While the app is in "Testing", add every Google account you will test with under Test users.
3. APIs & Services > Credentials > Create credentials > OAuth client ID > Application type: Web application.
   Authorized JavaScript origins:  http://localhost
   Authorized redirect URIs:       http://localhost/homerent/google-callback.php     (use your real folder name; must match exactly)
4. Copy the Client ID and Client secret into .env (GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET). Never put the secret in HTML or JavaScript.
5. Production: add your https origin (https://yourdomain.com) and https://yourdomain.com/google-callback.php to the same two lists,
   publish the consent screen ("In production"), and set GOOGLE_REDIRECT_URI in the server's .env.

## Behaviour
- Login side button = log in to the existing account (its type never changes). No account yet: you are asked Student or Landlord.
- Sign-up side: pick Student or Landlord FIRST, then "Sign up with Google as ..." creates that kind of account directly.
- Same email already registered as the OTHER type: refused with an explanation (no duplicate, no role change).
- Same email, existing password account: the HomeRent password must be typed once to link Google.
- Never-verified sign-up with that email: Google proves ownership, the old password is discarded.
- Google-only accounts: password is managed by Google (recovery link shown); they may add a HomeRent password with Forgot password.
- Admin portal: untouched. No Google anywhere in /admin.
