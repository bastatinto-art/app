# HomeRent - Student side setup (XAMPP)

## 1. Copy files
Put everything in `C:\xampp\htdocs\homerent\` next to your existing login.html, register.html, images and icons.
`connect.php` replaces your old one (same host/user/db, adds safe error handling).

## 2. Database
Run `sql/01_student_side.sql` in phpMyAdmin (database homerent_db). It only adds columns and tables.
First run `SHOW CREATE TABLE students;` and confirm the primary key is `id`. If not, stop and tell me.

## 3. Fix registration (messages never showed because PHP can't run in .html)
1. Rename `register.html` -> `register.php` (the PHP message block inside it will now work).
2. Your old handler `register.php` is replaced by `register-process.php` (included).
3. In the renamed register.php change the form to: `<form action="register-process.php" method="POST" ...>`
4. Add `name="confirm_password"` to the confirm password input.
5. Add to the message block: `elseif ($_GET['msg'] === 'invalid') echo "Check your details (password needs 8+ characters).";`
6. In the two "Login" links nothing changes. Optionally fix "Mansuna" in login.html to your correct spelling.

## 4. Connect login.html (3 edits, design untouched)
a) Form tag:  `<form>`  ->  `<form action="login.php" method="POST">`
b) Inputs:
   `<input type="text" placeholder="Username or Student ID">` -> `<input type="email" name="email" placeholder="Email address" required>`
   `<input type="password" placeholder="Password">` -> `<input type="password" name="password" placeholder="Password" required>`
   Add inside the form: `<input type="hidden" name="portal" id="portal" value="user">`
c) Script: DELETE the `.btn-login` click handler that calls alert(...). In the tab click handler add:
   `document.getElementById('portal').value = this.textContent.includes('Admin') ? 'admin' : 'user';`
   And at the end of the script add:
```js
var msgs = {invalid:'Incorrect email or password.',empty:'Enter your email and password.',inactive:'This account is deactivated.',
  admin_unavailable:'The admin portal is not available yet.',login_required:'Please log in first.'};
var p = new URLSearchParams(location.search), k = p.get('err');
if (k && msgs[k]) alert(msgs[k]); if (p.get('msg') === 'logged_out') alert('You have been logged out.');
```
(Swap alert() for a styled message box if you prefer.)

## 5. Test
Register a student at register.php, log in at login.html -> student-dashboard.php.
The listings stay empty until the landlord side creates properties (approval_status must be 'approved').
