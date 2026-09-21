<?php
(@include_once("./config.php")) or die("Cannot read config.php file<BR>");
(@include_once("./create_sqlite_tables.php")) or die("Cannot read create_sqlite_tables.php file<BR>");
(@include_once("./database_functions.php")) or die("Cannot read database_functions.php file<BR>");
(@include_once("./auth.php")) or die("Cannot read auth.php file<BR>");

// A hidden group is left out of the list unless its code is given (as include_code): it is still open by its link
$include_code = isset($_POST['include_code']) ? trim((string)$_POST['include_code']) : '';

try {
    // has_password: whether editing has been switched on for the group; can_edit: whether this browser has unlocked it
    $groups = db_rows($db, "SELECT g.id, g.code, g.name, COUNT(i.id) AS round_count,
                                   (g.edit_password_hash IS NOT NULL) AS has_password, g.hidden
                            FROM w_groups g
                            LEFT JOIN w_index i ON i.group_id = g.id
                            WHERE g.hidden = 0 OR g.code = :include_code
                            GROUP BY g.id
                            ORDER BY g.name COLLATE NOCASE, g.id",
        array(':include_code' => $include_code));
    $editable = editor_group_ids($db);
    foreach ($groups as &$group) {
        $group['has_password'] = (bool)$group['has_password'];
        $group['hidden'] = (bool)$group['hidden'];
        $group['can_edit'] = in_array((int)$group['id'], $editable, true);
    }
    unset($group);
} catch (Throwable $e) {
    json_fail("Could not load the groups: " . $e->getMessage());
}

echo json_encode(array(
    'groups' => $groups,
    'message' => "Success",
    'is_valid' => 1
));
exit;
