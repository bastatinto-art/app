<?php
// Old address kept working: the sign-up form now lives on the flip card in login.html
header('Location: login.html?panel=signup' . (isset($_GET['msg']) ? '' : ''));
exit;
