<?php
(@include_once("./config.php")) or die("Cannot read config.php file<BR>");
(@include_once("./create_sqlite_tables.php")) or die("Cannot read create_sqlite_tables.php file<BR>");
(@include_once("./database_functions.php")) or die("Cannot read database_functions.php file<BR>");

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

    $people = db_rows($db, "SELECT id, first_name, family_name FROM w_people WHERE group_id = :group_id ORDER BY id",
        array(':group_id' => $group_id));

    foreach ($rounds as $round) {
        $round_id = $round['id'];
        $round_num = $round['round_num'];
        $round_name = "Round " . $round_num;

        // Get the par
        $par = $round['par'];

        // Get the date of the first wordle
        $start_date = $round['wordle_start_date'];
        $start_date2 = $start_date;

        // Get the wordle number of the first one
        $start_wordle = $round['wordle_start_num'];

        // Every score in this round, by person and hole
        $scores_by_person = array();
        $score_rows = db_rows($db, "SELECT person_id, hole_num, score FROM w_results WHERE round_id = :round_id",
            array(':round_id' => $round_id));
        foreach ($score_rows as $score_row) {
            $scores_by_person[$score_row['person_id']][$score_row['hole_num']] = $score_row['score'];
        }

        // People who have no scores in this round are left out
        $results = array();
        foreach ($people as $person) {
            $person_id = $person['id'];
            if (!isset($scores_by_person[$person_id])) {
                continue;
            }
            // 18 holes, null until there is a score
            $scores = array_fill(0, 18, null);
            foreach ($scores_by_person[$person_id] as $hole_num => $score) {
                if ($hole_num >= 1 && $hole_num <= 18) {
                    $scores[$hole_num - 1] = $score;
                }
            }
            $results[] = array(
                'person_id' => $person_id,
                'first_name' => $person['first_name'],
                'family_name' => $person['family_name'],
                'scores' => $scores
            );
        }

        // get the mean and wordle word data
        $wordle_words = array();
        $mean_scores = array();
        $answers = db_rows($db, "SELECT wordle_num, wordle_answer, mean_score FROM w_answer
                                 WHERE group_id = :group_id AND wordle_num >= :first_wordle AND wordle_num < :end_wordle",
            array(':group_id' => $group_id, ':first_wordle' => (int)$start_wordle, ':end_wordle' => (int)$start_wordle + 18));
        foreach ($answers as $answer) {
            $mean_scores[$answer['wordle_num']] = $answer['mean_score'];
            $wordle_words[$answer['wordle_num']] = $answer['wordle_answer'];
        }

        $round_data[] = array(
            'round_id' => $round_id,
            'start_date' => $start_date,
            'start_date_d-m-Y' => $start_date2,
            'start_wordle' => $start_wordle,
            'par' => $par,
            'results' => $results,
            'name' => $round_name,
            'round_num' => $round_num,
            'mean_scores' => $mean_scores,
            'wordle_words' => $wordle_words
        );
    }
} catch (Throwable $e) {
    json_fail("Could not load the results: " . $e->getMessage());
}

$message = "Success";
$is_valid = 1;


$return_data = array(
    'group' => $group,
    'round_data' => $round_data,
    'message' => $message,
    'file_info' => null,
    'is_valid' => $is_valid
);

echo json_encode($return_data);
exit;
