<?php
(@include_once("./auth.php")) or die("Cannot read auth.php file<BR>");

// Create a group without uploading a workbook: code (its unique identifier), name and password.
// Only the super admin can do this, so the super admin's password must be sent as admin_password.
// The person creating the group is logged in to it.
require_post();
require_super_admin($db, isset($_POST['admin_password']) ? (string)$_POST['admin_password'] : '');
$code = isset($_POST['code']) ? trim((string)$_POST['code']) : '';
$name = isset($_POST['name']) ? trim(preg_replace('/\s+/', ' ', (string)$_POST['name'])) : '';
$password = isset($_POST['password']) ? (string)$_POST['password'] : '';

if (!valid_group_code($code)) {
    json_fail("The group code must be 1 to 40 letters, numbers, spaces, dots, dashes or underscores.", 400);
}
if (mb_strlen($name) > 100) {
    json_fail("The group name is too long (100 characters at most).", 400);
}
$problem = validate_new_password($password);
if ($problem !== null) {
    json_fail($problem, 400);
}
if ($name === '') {
    $name = $code;
}

$db->exec("BEGIN IMMEDIATE");
try {
    if (db_row($db, "SELECT id FROM w_groups WHERE code = :code", array(':code' => $code))) {
        throw new InvalidArgumentException("There is already a group with the code \"$code\". Choose a different code.");
    }
    $group_id = db_run($db, "INSERT INTO w_groups (code, name, created_at, edit_password_hash) VALUES (:code, :name, :created, :hash)",
        array(':code' => $code, ':name' => $name, ':created' => date('Y-m-d H:i:s'), ':hash' => password_hash($password, PASSWORD_DEFAULT)));
    $db->exec("COMMIT");
} catch (InvalidArgumentException $e) {
    $db->exec("ROLLBACK");
    json_fail($e->getMessage(), 409);
} catch (Throwable $e) {
    $db->exec("ROLLBACK");
    json_fail("The group could not be created: " . $e->getMessage());
}

grant_group($db, $group_id);

echo json_encode(array('is_valid' => 1, 'message' => "Group created.", 'group' => array('id' => $group_id, 'code' => $code, 'name' => $name)));
exit;
