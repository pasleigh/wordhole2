<?php
(@include_once("./database_functions.php")) or die("Cannot read database_functions.php file<BR>");

const NUM_HOLES = 18;
// Typing X in the score table means "failed to complete in 6" and - means "did not send in a result".
// They are stored as these numbers, as in the Excel workbooks.
const SCORE_FAILED = 6.9999;
const SCORE_NOT_SENT = 7.0001;

// The scores that can be entered on the site
function is_valid_entered_score($value)
{
    foreach (array(1, 2, 3, 4, 5, 6, SCORE_FAILED, SCORE_NOT_SENT) as $allowed) {
        if (abs($value - $allowed) < 0.000001) {
            return true;
        }
    }
    return false;
}

// A round of a group as the page wants it: par, start, the people playing with their 18 scores,
// and the mean score and solution of each wordle.
// $round is a row of w_index.
function load_round_data($db, $group_id, $round)
{
    $round_id = $round['id'];
    $start_wordle = $round['wordle_start_num'];

    // Every score in this round, by person and hole
    $scores_by_person = array();
    $score_rows = db_rows($db, "SELECT person_id, hole_num, score FROM w_results WHERE round_id = :round_id",
        array(':round_id' => $round_id));
    foreach ($score_rows as $score_row) {
        $scores_by_person[$score_row['person_id']][$score_row['hole_num']] = $score_row['score'];
    }

    // The people playing this round: those listed for it and any who have scores in it
    $people = db_rows($db, "SELECT id, first_name, family_name FROM w_people
                            WHERE group_id = :group_id
                              AND (id IN (SELECT person_id FROM w_round_players WHERE round_id = :round_id)
                                   OR id IN (SELECT person_id FROM w_results WHERE round_id = :round_id))
                            ORDER BY id",
        array(':group_id' => $group_id, ':round_id' => $round_id));

    $results = array();
    foreach ($people as $person) {
        // 18 holes, null until there is a score
        $scores = array_fill(0, NUM_HOLES, null);
        if (isset($scores_by_person[$person['id']])) {
            foreach ($scores_by_person[$person['id']] as $hole_num => $score) {
                if ($hole_num >= 1 && $hole_num <= NUM_HOLES) {
                    $scores[$hole_num - 1] = $score;
                }
            }
        }
        $results[] = array(
            'person_id' => $person['id'],
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
        array(':group_id' => $group_id, ':first_wordle' => (int)$start_wordle, ':end_wordle' => (int)$start_wordle + NUM_HOLES));
    foreach ($answers as $answer) {
        $mean_scores[$answer['wordle_num']] = $answer['mean_score'];
        $wordle_words[$answer['wordle_num']] = $answer['wordle_answer'];
    }

    return array(
        'round_id' => $round_id,
        'start_date' => $round['wordle_start_date'],
        'start_date_d-m-Y' => $round['wordle_start_date'],
        'start_wordle' => $start_wordle,
        'par' => $round['par'],
        'results' => $results,
        'name' => "Round " . $round['round_num'],
        'round_num' => $round['round_num'],
        'mean_scores' => $mean_scores,
        'wordle_words' => $wordle_words
    );
}

// Put a person's running total (sum of score minus par so far) on each of their scores in a round
function recalculate_person_totals($db, $round_id, $person_id, $par)
{
    $rows = db_rows($db, "SELECT id, score FROM w_results WHERE round_id = :round_id AND person_id = :person_id ORDER BY hole_num",
        array(':round_id' => $round_id, ':person_id' => $person_id));
    $total = 0;
    foreach ($rows as $row) {
        $total += $row['score'] - $par;
        db_run($db, "UPDATE w_results SET total = :total WHERE id = :id", array(':total' => $total, ':id' => $row['id']));
    }
}

// Store the mean of everybody's scores for one hole of a round, with the wordle the hole is for.
// Scores of 6.9999 (failed) and 7.0001 (not sent) count in the mean, as they do in the workbook.
function recalculate_hole_mean($db, $group_id, $round, $hole_num)
{
    $mean = db_row($db, "SELECT AVG(score) AS mean FROM w_results WHERE round_id = :round_id AND hole_num = :hole_num",
        array(':round_id' => $round['id'], ':hole_num' => $hole_num));
    db_run($db, "INSERT INTO w_answer (group_id, wordle_num, wordle_answer, mean_score) VALUES (:group_id, :wordle_num, '', :mean)
                 ON CONFLICT (group_id, wordle_num) DO UPDATE SET mean_score = excluded.mean_score",
        array(':group_id' => $group_id, ':wordle_num' => $round['wordle_start_num'] + $hole_num - 1, ':mean' => $mean['mean']));
}
