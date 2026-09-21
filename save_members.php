<?php
(@include_once("./auth.php")) or die("Cannot read auth.php file<BR>");

// Change who is in a group: group_id and a JSON payload (all parts optional)
//   {"rename":  [{"person_id": 3, "first_name": "Sue", "family_name": "Merrill"}, ...],
//    "add":     [{"first_name": "Ann", "family_name": "Smith", "in_round": true}, ...],
//    "remove":  [person ids],                 only people with no scores in any round
//    "round_id": 12,                          the round the next two lists are about
//    "round_add": [person ids], "round_remove": [person ids]}   who is playing that round
// Everything is applied together or not at all. Needs the group's password (being logged in to the group).
require_post();
if (!isset($_POST['group_id']) || !ctype_digit((string)$_POST['group_id'])) {
    json_fail("group_id is required.", 400);
}
$group_id = (int)$_POST['group_id'];
require_group_editor($db, $group_id);

$payload = isset($_POST['payload']) ? json_decode($_POST['payload'], true) : null;
if (!is_array($payload)) {
    json_fail("payload is not valid.", 400);
}
$renames = isset($payload['rename']) && is_array($payload['rename']) ? $payload['rename'] : array();
$additions = isset($payload['add']) && is_array($payload['add']) ? $payload['add'] : array();
$removals = isset($payload['remove']) && is_array($payload['remove']) ? $payload['remove'] : array();
$round_add = isset($payload['round_add']) && is_array($payload['round_add']) ? $payload['round_add'] : array();
$round_remove = isset($payload['round_remove']) && is_array($payload['round_remove']) ? $payload['round_remove'] : array();
$round_id = isset($payload['round_id']) ? $payload['round_id'] : null;
if (count($renames) + count($additions) + count($removals) + count($round_add) + count($round_remove) > 500) {
    json_fail("Too many changes in one go.", 400);
}

// A name as typed: extra spaces removed, at most 60 characters. A first name is needed, a family name is optional.
function clean_member_name($first_name, $family_name)
{
    $clean = array();
    foreach (array($first_name, $family_name) as $name) {
        if (!is_string($name)) {
            throw new InvalidArgumentException("A member's name must be text.");
        }
        $name = trim(preg_replace('/\s+/u', ' ', $name));
        if (mb_strlen($name) > 60 || preg_match('/[\x00-\x1f]/', $name)) {
            throw new InvalidArgumentException("A member's name can be at most 60 characters.");
        }
        $clean[] = $name;
    }
    if ($clean[0] === '') {
        throw new InvalidArgumentException("Every member needs a first name.");
    }
    return $clean;
}

function member_label($first_name, $family_name)
{
    return trim($first_name . ' ' . $family_name);
}

$db->exec("BEGIN IMMEDIATE");
try {
    $members = array(); // person id => array(first, family), as it will be after the changes
    foreach (db_rows($db, "SELECT id, first_name, family_name FROM w_people WHERE group_id = :group_id", array(':group_id' => $group_id)) as $row) {
        $members[$row['id']] = array($row['first_name'], $row['family_name']);
    }

    // Removals: only people who have never scored, so no results can be lost
    $removed = array();
    foreach ($removals as $person_id) {
        if (!is_int($person_id) || !isset($members[$person_id])) {
            throw new InvalidArgumentException("One of the members to remove is not in this group.");
        }
        $played = db_row($db, "SELECT COUNT(DISTINCT round_id) AS n FROM w_results WHERE person_id = :id", array(':id' => $person_id));
        if ($played['n'] > 0) {
            throw new InvalidArgumentException(member_label($members[$person_id][0], $members[$person_id][1])
                . " has scores in " . $played['n'] . ($played['n'] == 1 ? " round" : " rounds") . ", so cannot be removed.");
        }
        $removed[$person_id] = true;
    }
    foreach (array_keys($removed) as $person_id) {
        db_run($db, "DELETE FROM w_round_players WHERE person_id = :id", array(':id' => $person_id));
        db_run($db, "DELETE FROM w_people WHERE id = :id AND group_id = :group_id", array(':id' => $person_id, ':group_id' => $group_id));
        unset($members[$person_id]);
    }

    // Renames: first move every renamed member out of the way so that two members can swap names
    $new_names = array();
    foreach ($renames as $change) {
        $person_id = isset($change['person_id']) ? $change['person_id'] : null;
        if (!is_int($person_id) || !isset($members[$person_id])) {
            throw new InvalidArgumentException("One of the members to rename is not in this group.");
        }
        $new_names[$person_id] = clean_member_name(isset($change['first_name']) ? $change['first_name'] : null,
            isset($change['family_name']) ? $change['family_name'] : '');
    }
    foreach (array_keys($new_names) as $person_id) {
        db_run($db, "UPDATE w_people SET first_name = :name, family_name = '' WHERE id = :id", array(':name' => '~renaming ' . $person_id, ':id' => $person_id));
    }
    foreach ($new_names as $person_id => $name) {
        $members[$person_id] = $name;
    }

    // New members
    $added = array(); // list of array(first, family, in_round)
    foreach ($additions as $change) {
        $name = clean_member_name(isset($change['first_name']) ? $change['first_name'] : null,
            isset($change['family_name']) ? $change['family_name'] : '');
        $added[] = array($name[0], $name[1], !empty($change['in_round']));
    }

    // Nobody in the group may share a name (capital letters do not make a name different)
    $seen = array();
    $all_names = array_values($members);
    foreach ($added as $new) {
        $all_names[] = array($new[0], $new[1]);
    }
    foreach ($all_names as $name) {
        $key = mb_strtolower(member_label($name[0], $name[1]));
        if (isset($seen[$key])) {
            throw new InvalidArgumentException("There would be two members called " . member_label($name[0], $name[1]) . ".");
        }
        $seen[$key] = true;
    }

    foreach ($new_names as $person_id => $name) {
        db_run($db, "UPDATE w_people SET first_name = :first, family_name = :family WHERE id = :id",
            array(':first' => $name[0], ':family' => $name[1], ':id' => $person_id));
    }
    $added_ids_for_round = array();
    foreach ($added as $new) {
        $person_id = db_run($db, "INSERT INTO w_people (group_id, first_name, family_name) VALUES (:group_id, :first, :family)",
            array(':group_id' => $group_id, ':first' => $new[0], ':family' => $new[1]));
        $members[$person_id] = array($new[0], $new[1]);
        if ($new[2]) {
            $added_ids_for_round[] = $person_id;
        }
    }

    // Who is playing the round
    if ($round_id !== null || count($round_add) || count($round_remove) || count($added_ids_for_round)) {
        if (!is_int($round_id) || !db_row($db, "SELECT id FROM w_index WHERE id = :round_id AND group_id = :group_id",
                array(':round_id' => $round_id, ':group_id' => $group_id))) {
            if (count($round_add) || count($round_remove) || count($added_ids_for_round)) {
                throw new InvalidArgumentException("That round does not belong to this group.");
            }
        } else {
            foreach (array_merge($round_add, $added_ids_for_round) as $person_id) {
                if (!is_int($person_id) || !isset($members[$person_id])) {
                    throw new InvalidArgumentException("One of the players to add is not in this group.");
                }
                add_round_player($db, $round_id, $person_id);
            }
            foreach ($round_remove as $person_id) {
                if (!is_int($person_id) || !isset($members[$person_id])) {
                    throw new InvalidArgumentException("One of the players to take out is not in this group.");
                }
                $scores = db_row($db, "SELECT COUNT(*) AS n FROM w_results WHERE round_id = :round_id AND person_id = :id",
                    array(':round_id' => $round_id, ':id' => $person_id));
                if ($scores['n'] > 0) {
                    throw new InvalidArgumentException(member_label($members[$person_id][0], $members[$person_id][1])
                        . " has scores in this round, so cannot be taken out of it. Clear their scores first.");
                }
                db_run($db, "DELETE FROM w_round_players WHERE round_id = :round_id AND person_id = :id",
                    array(':round_id' => $round_id, ':id' => $person_id));
            }
        }
    }

    $db->exec("COMMIT");
} catch (InvalidArgumentException $e) {
    $db->exec("ROLLBACK");
    json_fail($e->getMessage(), 400);
} catch (Throwable $e) {
    $db->exec("ROLLBACK");
    json_fail("The members could not be saved: " . $e->getMessage());
}

echo json_encode(array('is_valid' => 1, 'message' => "Saved."));
exit;
