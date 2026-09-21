<?php
(@include_once("./config.php")) or die("Cannot read config.php file<BR>");
(@include_once("./create_sqlite_tables.php")) or die("Cannot read create_sqlite_tables.php file<BR>");
(@include_once("./database_functions.php")) or die("Cannot read database_functions.php file<BR>");
(@include_once("./round_functions.php")) or die("Cannot read round_functions.php file<BR>");
(@include_once("./auth.php")) or die("Cannot read auth.php file<BR>");

// Results for one group: group_id says which
$group_id = isset($_REQUEST['group_id']) ? $_REQUEST['group_id'] : '';
if (!ctype_digit((string)$group_id)) {
    json_fail("A group_id is required.", 400);
}
$group_id = (int)$group_id;

try {
    $group = db_row($db, "SELECT id, code, name FROM w_groups WHERE id = :group_id", array(':group_id' => $group_id));
    if (!$group) {
        json_fail("There is no group with id $group_id.", 404);
    }

    $round_data = array();

    $rounds = db_rows($db, "SELECT * FROM w_index WHERE group_id = :group_id ORDER BY round_num DESC",
        array(':group_id' => $group_id));

    foreach ($rounds as $round) {
        $round_data[] = load_round_data($db, $group_id, $round);
    }

    // Everybody who has played in this group (the people playing each round are in that round's results)
    $people = db_rows($db, "SELECT p.id, p.first_name, p.family_name,
                                   (SELECT COUNT(DISTINCT r.round_id) FROM w_results r WHERE r.person_id = p.id) AS rounds_played
                            FROM w_people p WHERE p.group_id = :group_id ORDER BY p.id",
        array(':group_id' => $group_id));

    // Whether this browser has unlocked the group for editing
    $can_edit = is_group_editor($db, $group_id);
} catch (Throwable $e) {
    json_fail("Could not load the results: " . $e->getMessage());
}

$message = "Success";
$is_valid = 1;


$return_data = array(
    'group' => $group,
    'can_edit' => $can_edit,
    'people' => $people,
    'round_data' => $round_data,
    'message' => $message,
    'file_info' => null,
    'is_valid' => $is_valid
);

echo json_encode($return_data);
exit;
