<?php
(@include_once("./auth.php")) or die("Cannot read auth.php file<BR>");

// Change a group's password: needs group_id, current_password and new_password, and the group to be unlocked
require_post();
$group_id = isset($_POST['group_id']) ? $_POST['group_id'] : '';
if (!ctype_digit((string)$group_id)) {
    json_fail("A group_id is required.", 400);
}
$group_id = (int)$group_id;
$current_password = isset($_POST['current_password']) ? (string)$_POST['current_password'] : '';
$new_password = isset($_POST['new_password']) ? (string)$_POST['new_password'] : '';

try {
    require_group_editor($db, $group_id);

    $group = db_row($db, "SELECT edit_password_hash FROM w_groups WHERE id = :id", array(':id' => $group_id));
    if (!$group || !password_verify($current_password, $group['edit_password_hash'])) {
        usleep(500000);
        json_fail("The current password is not right.", 401);
    }
    $problem = validate_new_password($new_password);
    if ($problem !== null) {
        json_fail($problem, 400);
    }

    set_group_password($db, $group_id, $new_password);
    // Everybody else who had unlocked the group is now locked out; keep this browser logged in
    grant_group($db, $group_id);
} catch (Throwable $e) {
    json_fail("The password could not be changed: " . $e->getMessage());
}

echo json_encode(array('is_valid' => 1, 'message' => "The password has been changed."));
exit;
