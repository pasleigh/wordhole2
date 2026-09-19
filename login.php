<?php
(@include_once("./auth.php")) or die("Cannot read auth.php file<BR>");

// Unlock a group for editing in this browser: needs group_id and password
require_post();
$group_id = isset($_POST['group_id']) ? $_POST['group_id'] : '';
if (!ctype_digit((string)$group_id)) {
    json_fail("A group_id is required.", 400);
}
$password = isset($_POST['password']) ? (string)$_POST['password'] : '';

try {
    $login = group_login($db, (int)$group_id, $password);
} catch (Throwable $e) {
    json_fail("Could not log in: " . $e->getMessage());
}
if (!$login['ok']) {
    json_fail($login['message'], $login['status']);
}

echo json_encode(array('is_valid' => 1, 'message' => $login['message'], 'group_id' => (int)$group_id));
exit;
