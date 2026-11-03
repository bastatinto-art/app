# Messaging + Map update

## 1. Copy files (keep sub-folders; replace when asked)
NEW:  student-messages.php, landlord-messages.php, student-map.php, messages-api.php, map-data.php, map-boundary.php,
      setup-boundary.php, includes/messages_lib.php, includes/messages_view.php, includes/geo.php,
      assets/js/messages.js, assets/js/hr-map.js, assets/css/messages.css, assets/css/map.css, sql/03_messages_and_map.sql
REPLACED: connect.php, student-dashboard.php, landlord-dashboard.php, property-details.php, student-profile.php,
      landlord-profile.php, student-inquiries.php, landlord-inquiries.php (now redirect to Messages),
      includes/student_header.php, includes/landlord_header.php, includes/property_form.php, assets/css/student.css
Also create an empty folder named  data  next to login.html.

## 2. Database
Run sql/03_messages_and_map.sql in phpMyAdmin (homerent_db). It adds latitude/longitude to properties, creates
conversations + messages, and COPIES your old inquiries (and replies) into conversations. Nothing is deleted.

## 3. Real Tayabas City boundary (one time, needs internet)
Open  http://localhost/<your folder>/setup-boundary.php  once. It downloads the official OpenStreetMap boundary
into data/tayabas-boundary.json. Then DELETE setup-boundary.php.
Until you do this, the app uses a rough rectangle around Tayabas as a fallback.

## 4. Existing properties have no pin
Landlords must edit each existing property and pin it (a pin is now required on save). Only pinned, approved,
available properties appear on the student map.

## 5. Internet
Leaflet and map tiles load from the internet (cdnjs + OpenStreetMap). No API key needed.
