<?php
(@include_once("./config.php")) or die("Cannot read config.php file<BR>");
(@include_once("./create_sqlite_tables.php")) or die("Cannot read create_sqlite_tables.php file<BR>");
(@include_once("./database_functions.php")) or die("Cannot read database_functions.php file<BR>");
(@include_once("./auth.php")) or die("Cannot read auth.php file<BR>");

try {
    // has_password: whether editing has been switched on for the group; can_edit: whether this browser has unlocked it
    $groups = db_rows($db, "SELECT g.id, g.code, g.name, COUNT(i.id) AS round_count,
                                   (g.edit_password_hash IS NOT NULL) AS has_password
                            FROM w_groups g
                            LEFT JOIN w_index i ON i.group_id = g.id
                            GROUP BY g.id
                            ORDER BY g.name COLLATE NOCASE, g.id");
    $editable = editor_group_ids($db);
    foreach ($groups as &$group) {
        $group['has_password'] = (bool)$group['has_password'];
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
