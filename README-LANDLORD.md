# Landlord side setup
1. Copy the new/changed files into your project folder (same folder as login.html), keeping the sub-folders:
   - includes/auth.php (REPLACES the old one), includes/landlord_header.php, includes/landlord_footer.php, includes/property_form.php
   - assets/css/landlord.css
   - uploads/properties/.htaccess  (create folders uploads\properties if missing)
   - landlord-dashboard.php, manage-properties.php, add-property.php, edit-property.php,
     landlord-requests.php, landlord-inquiries.php, landlord-profile.php
2. Run sql/02_landlord_side.sql in phpMyAdmin (adds landlords.phone).
3. Register a Landlord account at register.php, log in -> landlord-dashboard.php.
4. New listings are "Awaiting approval". Until the Admin side exists, approve one manually in phpMyAdmin:
   UPDATE properties SET approval_status='approved' WHERE id = <the property id>;
   Then it appears for students.
