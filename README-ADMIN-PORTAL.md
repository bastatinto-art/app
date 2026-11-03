# Separate Admin Portal - install

1. phpMyAdmin > homerent_db > SQL: run  sql/05_admin_and_site_settings.sql  then  sql/06_admin_portal.sql
2. Copy into your project folder (keep sub-folders):
   NEW folder  admin/            (all files inside)
   NEW         includes/helpers.php, includes/site_settings.php
   CHANGED     includes/ui.php, includes/auth.php, login.php, login.html, student-dashboard.php, student-map.php,
               property-details.php, assets/css/student.css, assets/css/admin.css, assets/css/messages.css, assets/js/hr-map.js
   Create folder  uploads/site/  and put the supplied uploads/site/.htaccess in it.
3. DELETE these old files if you copied them earlier (admin now lives only in /admin/):
   admin-dashboard.php, admin-appearance.php, create-admin.php, includes/admin_header.php, includes/admin_footer.php
4. Create the first administrator (only works on this computer, only once):
   open  http://localhost/<project>/admin/setup.php  -> choose username, email, password (12+ chars).
   Then DELETE admin/setup.php.
5. Admin login:  http://localhost/<project>/admin/
6. Logo + category photos: Admin > Logo Management / Category Images.
