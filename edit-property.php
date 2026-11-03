<?php
$editId = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
require __DIR__ . '/includes/property_form.php';
