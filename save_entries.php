<?php
(@include_once("./auth.php")) or die("Cannot read auth.php file<BR>");
(@include_once("./round_functions.php")) or die("Cannot read round_functions.php file<BR>");

// Save changes made in the scorecard or the daily entry: group_id, round_id and a JSON payload
//   {"scores": [{"person_id": 3, "hole_num": 5, "value": 4 | null}, ...],
//    "words":  [{"hole_num": 5, "word": "CRANE"}, ...]}
// A value of null removes the score. Totals and means are worked out here, from what is stored.
require_post();
foreach (array('group_id', 'round_id') as $field) {
    if (!isset($_POST[$field]) || !ctype_digit((string)$_POST[$field])) {
        json_fail("$field is required.", 400);
    }
}
$group_id = (int)$_POST['group_id'];
$round_id = (int)$_POST['round_id'];
$payload = isset($_POST['payload']) ? json_decode($_POST['payload'], true) : null;
if (!is_array($payload)) {
    json_fail("payload is not valid.", 400);
}
$score_changes = isset($payload['scores']) && is_array($payload['scores']) ? $payload['scores'] : array();
$word_changes = isset($payload['words']) && is_array($payload['words']) ? $payload['words'] : array();
if (count($score_changes) + count($word_changes) > 2000) {
    json_fail("Too many changes in one go.", 400);
}

require_group_editor($db, $group_id);

$db->exec("BEGIN IMMEDIATE");
try {
    $round = db_row($db, "SELECT * FROM w_index WHERE id = :round_id AND group_id = :group_id",
        array(':round_id' => $round_id, ':group_id' => $group_id));
    if (!$round) {
        throw new InvalidArgumentException("That round does not belong to this group.");
    }

    $people_in_group = array();
    foreach (db_rows($db, "SELECT id FROM w_people WHERE group_id = :group_id", array(':group_id' => $group_id)) as $person) {
        $people_in_group[$person['id']] = true;
    }

    $changed_people = array();
    $changed_holes = array();

    foreach ($score_changes as $change) {
        $person_id = isset($change['person_id']) ? $change['person_id'] : null;
        $hole_num = isset($change['hole_num']) ? $change['hole_num'] : null;
        $value = array_key_exists('value', $change) ? $change['value'] : null;

        if (!is_int($person_id) || !isset($people_in_group[$person_id])) {
            throw new InvalidArgumentException("Person " . json_encode($person_id) . " is not in this group.");
        }
        if (!is_int($hole_num) || $hole_num < 1 || $hole_num > NUM_HOLES) {
            throw new InvalidArgumentException("Hole " . json_encode($hole_num) . " is not between 1 and " . NUM_HOLES . ".");
        }
        if ($value !== null && (!is_numeric($value) || !is_valid_entered_score($value))) {
            throw new InvalidArgumentException("Score " . json_encode($value) . " is not one of 1 to 6, X or -.");
        }

        add_round_player($db, $round_id, $person_id);
        $existing = db_row($db, "SELECT id FROM w_results WHERE round_id = :round_id AND person_id = :person_id AND hole_num = :hole_num",
            array(':round_id' => $round_id, ':person_id' => $person_id, ':hole_num' => $hole_num));
        if ($value === null) {
            if ($existing) {
                db_run($db, "DELETE FROM w_results WHERE id = :id", array(':id' => $existing['id']));
            }
        } elseif ($existing) {
            db_run($db, "UPDATE w_results SET score = :score WHERE id = :id", array(':score' => $value + 0, ':id' => $existing['id']));
        } else {
            db_run($db, "INSERT INTO w_results (round_id, person_id, hole_num, wordle_num, score, total) VALUES (:round_id, :person_id, :hole_num, :wordle_num, :score, 0)",
                array(':round_id' => $round_id, ':person_id' => $person_id, ':hole_num' => $hole_num,
                    ':wordle_num' => $round['wordle_start_num'] + $hole_num - 1, ':score' => $value + 0));
        }
        $changed_people[$person_id] = true;
        $changed_holes[$hole_num] = true;
    }

    foreach ($word_changes as $change) {
        $hole_num = isset($change['hole_num']) ? $change['hole_num'] : null;
        $word = isset($change['word']) && is_string($change['word']) ? strtoupper(trim($change['word'])) : null;

        if (!is_int($hole_num) || $hole_num < 1 || $hole_num > NUM_HOLES) {
            throw new InvalidArgumentException("Hole " . json_encode($hole_num) . " is not between 1 and " . NUM_HOLES . ".");
        }
        if ($word === null || !preg_match('/^[A-Z]{0,12}$/', $word)) {
            throw new InvalidArgumentException("A word can only have letters.");
        }
        db_run($db, "INSERT INTO w_answer (group_id, wordle_num, wordle_answer) VALUES (:group_id, :wordle_num, :word)
                     ON CONFLICT (group_id, wordle_num) DO UPDATE SET wordle_answer = excluded.wordle_answer",
            array(':group_id' => $group_id, ':wordle_num' => $round['wordle_start_num'] + $hole_num - 1, ':word' => $word));
    }

    foreach (array_keys($changed_people) as $person_id) {
        recalculate_person_totals($db, $round_id, $person_id, $round['par']);
    }
    foreach (array_keys($changed_holes) as $hole_num) {
        recalculate_hole_mean($db, $group_id, $round, $hole_num);
    }

    $saved_round = load_round_data($db, $group_id, $round);
    $db->exec("COMMIT");
} catch (InvalidArgumentException $e) {
    $db->exec("ROLLBACK");
    json_fail($e->getMessage(), 400);
} catch (Throwable $e) {
    $db->exec("ROLLBACK");
    json_fail("The changes could not be saved: " . $e->getMessage());
}

echo json_encode(array('is_valid' => 1, 'message' => "Saved.", 'round' => $saved_round));
exit;
