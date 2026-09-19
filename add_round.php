<?php
(@include_once("./auth.php")) or die("Cannot read auth.php file<BR>");
(@include_once("./round_functions.php")) or die("Cannot read round_functions.php file<BR>");

// Start a new round: group_id, round_num, start_date (YYYY-MM-DD), start_wordle, par,
// person_ids (JSON list of people already in the group who are playing) and
// new_players (JSON list of names such as "Ann Smith" to add to the group and to the round)
require_post();
if (!isset($_POST['group_id']) || !ctype_digit((string)$_POST['group_id'])) {
    json_fail("group_id is required.", 400);
}
$group_id = (int)$_POST['group_id'];
require_group_editor($db, $group_id);

foreach (array('round_num', 'start_wordle', 'par') as $field) {
    if (!isset($_POST[$field]) || !ctype_digit((string)$_POST[$field])) {
        json_fail("$field must be a whole number.", 400);
    }
}
$round_num = (int)$_POST['round_num'];
$start_wordle = (int)$_POST['start_wordle'];
$par = (int)$_POST['par'];
$person_ids = isset($_POST['person_ids']) ? json_decode($_POST['person_ids'], true) : array();
$new_players = isset($_POST['new_players']) ? json_decode($_POST['new_players'], true) : array();
if (!is_array($person_ids) || !is_array($new_players)) {
    json_fail("The list of players is not valid.", 400);
}

if ($round_num < 1 || $round_num > 9999) {
    json_fail("The round number must be between 1 and 9999.", 400);
}
if ($start_wordle < 1 || $start_wordle > 99999) {
    json_fail("The first Wordle number is not valid.", 400);
}
if ($par < 1 || $par > 6) {
    json_fail("Par must be between 1 and 6.", 400);
}
$start_date = DateTime::createFromFormat('!Y-m-d', isset($_POST['start_date']) ? (string)$_POST['start_date'] : '');
if (!$start_date || $start_date->format('Y-m-d') !== $_POST['start_date']) {
    json_fail("The start date is not valid.", 400);
}

// "Ann Smith" -> first name Ann, family name Smith; one name only -> no family name
$names = array();
foreach ($new_players as $line) {
    $line = trim(preg_replace('/\s+/', ' ', (string)$line));
    if ($line === '') {
        continue;
    }
    if (mb_strlen($line) > 80) {
        json_fail("A player's name is too long.", 400);
    }
    $parts = explode(' ', $line, 2);
    $names[] = array($parts[0], isset($parts[1]) ? $parts[1] : '');
}

$db->exec("BEGIN IMMEDIATE");
try {
    if (db_row($db, "SELECT id FROM w_index WHERE group_id = :group_id AND round_num = :round_num",
        array(':group_id' => $group_id, ':round_num' => $round_num))) {
        throw new InvalidArgumentException("Round $round_num already exists in this group.");
    }

    $players = array();
    foreach ($person_ids as $person_id) {
        if (!is_int($person_id) || !db_row($db, "SELECT id FROM w_people WHERE id = :id AND group_id = :group_id",
                array(':id' => $person_id, ':group_id' => $group_id))) {
            throw new InvalidArgumentException("One of the players is not in this group.");
        }
        $players[$person_id] = true;
    }
    foreach ($names as $name) {
        $players[GetPersonID($db, $group_id, $name[0], $name[1])] = true;
    }
    if (count($players) === 0) {
        throw new InvalidArgumentException("A round needs at least one player.");
    }

    $round_id = db_run($db, "INSERT INTO w_index (group_id, round_num, wordle_start_num, wordle_start_date, par)
                             VALUES (:group_id, :round_num, :start_wordle, :start_date, :par)",
        array(':group_id' => $group_id, ':round_num' => $round_num, ':start_wordle' => $start_wordle,
            ':start_date' => $start_date->format('d-m-Y'), ':par' => $par));
    foreach (array_keys($players) as $person_id) {
        add_round_player($db, $round_id, $person_id);
    }

    $round = db_row($db, "SELECT * FROM w_index WHERE id = :id", array(':id' => $round_id));
    $new_round = load_round_data($db, $group_id, $round);
    $db->exec("COMMIT");
} catch (InvalidArgumentException $e) {
    $db->exec("ROLLBACK");
    json_fail($e->getMessage(), 400);
} catch (Throwable $e) {
    $db->exec("ROLLBACK");
    json_fail("The round could not be created: " . $e->getMessage());
}

echo json_encode(array('is_valid' => 1, 'message' => "Round created.", 'round' => $new_round));
exit;
