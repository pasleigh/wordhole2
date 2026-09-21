<?php
(@include_once("./auth.php")) or die("Cannot read auth.php file<BR>");
/**
 * @var $db // from create_sqlite_tables
 */
// Super admin only: rename a group and/or hide it from (or show it in) the group list.
// Send admin_password, group_id and name and/or hidden (1 = hide, 0 = show).
// A hidden group is not deleted: its results are kept and its link still works.
require_post();
require_super_admin($db, isset($_POST['admin_password']) ? (string)$_POST['admin_password'] : '');

if (!isset($_POST['group_id']) || !ctype_digit((string)$_POST['group_id'])) {
    json_fail("group_id is required.", 400);
}
$group_id = (int)$_POST['group_id'];
$has_name = isset($_POST['name']);
$has_hidden = isset($_POST['hidden']);
if (!$has_name && !$has_hidden) {
    json_fail("There is nothing to change.", 400);
}

$name = null;
if ($has_name) {
    $name = trim(preg_replace('/\s+/u', ' ', (string)$_POST['name']));
    if ($name === '' || mb_strlen($name) > 100 || preg_match('/[\x00-\x1f]/', $name)) {
        json_fail("The group name must be 1 to 100 characters.", 400);
    }
}
$hidden = null;
if ($has_hidden) {
    if ($_POST['hidden'] !== '0' && $_POST['hidden'] !== '1') {
        json_fail("hidden must be 0 or 1.", 400);
    }
    $hidden = (int)$_POST['hidden'];
}

$db->exec("BEGIN IMMEDIATE");
try {
    if (!db_row($db, "SELECT id FROM w_groups WHERE id = :id", array(':id' => $group_id))) {
        throw new InvalidArgumentException("There is no such group.");
    }
    if ($name !== null) {
        db_run($db, "UPDATE w_groups SET name = :name WHERE id = :id", array(':name' => $name, ':id' => $group_id));
    }
    if ($hidden !== null) {
        db_run($db, "UPDATE w_groups SET hidden = :hidden WHERE id = :id", array(':hidden' => $hidden, ':id' => $group_id));
    }
    $group = db_row($db, "SELECT id, code, name, hidden FROM w_groups WHERE id = :id", array(':id' => $group_id));
    $db->exec("COMMIT");
} catch (InvalidArgumentException $e) {
    $db->exec("ROLLBACK");
    json_fail($e->getMessage(), 404);
} catch (Throwable $e) {
    $db->exec("ROLLBACK");
    json_fail("The group could not be changed: " . $e->getMessage());
}

$group['hidden'] = (bool)$group['hidden'];
echo json_encode(array('is_valid' => 1, 'message' => "Saved.", 'group' => $group));
exit;
