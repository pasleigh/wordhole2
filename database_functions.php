<?php
(@include_once("./config.php")) or die("Cannot read config.php file<BR>");
(@include_once("./create_sqlite_tables.php")) or die("Cannot read create_sqlite_tables.php file<BR>");

// Bind :name => value pairs to a prepared statement, choosing the SQLite type from the PHP type
function db_bind($stmt, $params)
{
    foreach ($params as $name => $value) {
        if ($value === null) {
            $type = SQLITE3_NULL;
        } elseif (is_int($value) || is_bool($value)) {
            $type = SQLITE3_INTEGER;
            $value = (int)$value;
        } elseif (is_float($value)) {
            $type = SQLITE3_FLOAT;
        } else {
            $type = SQLITE3_TEXT;
        }
        $stmt->bindValue($name, $value, $type);
    }
}

// Run a SELECT and return the first row as an associative array, or false if there is none
function db_row($db, $query, $params = array())
{
    $stmt = $db->prepare($query);
    db_bind($stmt, $params);
    $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    $stmt->close();
    return $row;
}

// Run a SELECT and return all the rows as associative arrays
function db_rows($db, $query, $params = array())
{
    $stmt = $db->prepare($query);
    db_bind($stmt, $params);
    $res = $stmt->execute();
    $rows = array();
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $rows[] = $row;
    }
    $stmt->close();
    return $rows;
}

// Run an INSERT/UPDATE/DELETE and return the id of the last row inserted on this connection
function db_run($db, $query, $params = array())
{
    $stmt = $db->prepare($query);
    db_bind($stmt, $params);
    $stmt->execute();
    $stmt->close();
    return $db->lastInsertRowID();
}

// End a request that expects JSON with an error message the page can show
function json_fail($message, $http_status = 500)
{
    http_response_code($http_status);
    header('Content-Type: application/json');
    echo json_encode(array('is_valid' => 0, 'message' => $message));
    exit;
}

/*
  $round_data[] = array(
        'start_date'=>$start_date,
        'start_date_d-m-Y'=>$start_date2,
        'start_wordle'=>$start_wordle,
        'par'=>$par,
        'results'=>$results,
        'name'=>$worksheet_name
    );
$results[] = array(
    'first_name'=>$first_name,
    'family_name'=>$family_name,
    'scores'=>$scores
);
*/
// Store the results of a workbook for one group. All or nothing: if anything fails nothing is saved.
// Returns the group as array('id'=>, 'code'=>, 'name'=>)
function import_workbook_data($db, $group_code, $group_name, $round_data)
{
    $db->exec("BEGIN IMMEDIATE");
    try {
        $group_id = GetGroupID($db, $group_code, $group_name);
        push_all_round_data_database($db, $group_id, $round_data);
        $db->exec("COMMIT");
    } catch (Throwable $e) {
        $db->exec("ROLLBACK");
        throw $e;
    }
    return db_row($db, "SELECT id, code, name FROM w_groups WHERE id = :id", array(':id' => $group_id));
}

function push_all_round_data_database($db, $group_id, $round_data)
{
    $ret_value = true;
    $all_rounds_final_scores = array();
    for ($i = 0; $i < count($round_data); $i++) {
        $this_round = $round_data[$i];
        $sheet_name = $this_round['name'];
        $tmp = explode(" ", $sheet_name);
        if ($tmp[0] == 'Round') {
            $round_num = intval($tmp[1]);
            $wordle_start_date = $this_round['start_date'];
            $wordle_start_date_d_m_Y = $this_round['start_date_d-m-Y'];
            $wordle_start_num = $this_round['start_wordle'];
            $par = $this_round['par'];

            $round_id = GetRoundID($db, $group_id, $round_num, $wordle_start_num, $wordle_start_date, $par);

            $results = $this_round['results'];

            //echo('Results<BR>');
            //var_dump($results);
            $this_round_final_scores = array();
            for ($j = 0; $j < count($results); $j++) {
                $result = $results[$j];
                $first_name = $result['first_name'];
                $family_name = $result['family_name'];
                $person_id = GetPersonID($db, $group_id, $first_name, $family_name);
                $scores = $result['scores'];
                $total = 0;
                for ($k = 0; $k < count($scores); $k++) {
                    $score = $scores[$k];
                    if ($score === null) {
                        break;
                    }
                    $hole_num = $k + 1;
                    $wordle_num = $wordle_start_num + $k;
                    $total += $score - $par;
                    $update_scores = false;
                    if($i === 0){
                        // Update if its the current i.e the first round
                        $update_scores = true;
                    }
                    $result_id = SubmitScore($db, $round_id, $person_id, $hole_num, $wordle_num, $score, $total, $update_scores);
                }
                // Do something with the total
                $this_round_final_scores[] = array(
                    'first_name' => $first_name,
                    'family_name' => $family_name,
                    'final_score' => $total,
                    'last_hole_num' => $k
                );
            }
            // Store the means fo theis
            $mean_scores = $this_round['mean_scores'];
            $wordle_words = $this_round['wordle_words'];
            for ($k = 0; $k < count($mean_scores); $k++) {
                $result_id = SubmitWordle($db, $group_id, $wordle_start_num+$k, $mean_scores[$k], $wordle_words[$k]);
            }
        }
        $all_rounds_final_scores[$round_num] = $this_round_final_scores;
    }

    return $ret_value;
}

// Find the group with this code, creating it if it is new. A non-blank name replaces the stored name;
// a new group with no name is called by its code.
function GetGroupID($db, $code, $name = '')
{
    $name = trim($name);
    $group = db_row($db, "SELECT id, name FROM w_groups WHERE code = :code", array(':code' => $code));

    if ($group) {
        $id = $group['id'];
        if ($name !== '' && $name !== $group['name']) {
            db_run($db, "UPDATE w_groups SET name = :name WHERE id = :id", array(':name' => $name, ':id' => $id));
        }
    } else {
        if ($name === '') {
            $name = $code;
        }
        $id = db_run($db, "INSERT INTO w_groups (code, name, created_at) VALUES (:code, :name, :created)",
            array(':code' => $code, ':name' => $name, ':created' => date('Y-m-d H:i:s')));
    }

    return $id;
}

function GetPersonID($db, $group_id, $first_name, $family_name)
{
    $person = db_row($db, "SELECT id FROM w_people WHERE group_id = :group_id AND first_name = :first_name AND family_name = :family_name",
        array(':group_id' => $group_id, ':first_name' => $first_name, ':family_name' => $family_name));

    if ($person) {
        // return the id
        $id = $person['id'];
    } else {
        $id = db_run($db, "INSERT INTO w_people (group_id, first_name, family_name) VALUES (:group_id, :first_name, :family_name)",
            array(':group_id' => $group_id, ':first_name' => $first_name, ':family_name' => $family_name));
    }

    return $id;
}

function GetRoundID($db, $group_id, $round_num, $wordle_start_num, $wordle_start_date, $par)
{
    $round = db_row($db, "SELECT id FROM w_index WHERE group_id = :group_id AND round_num = :round_num AND wordle_start_num = :wordle_start_num AND wordle_start_date = :wordle_start_date",
        array(':group_id' => $group_id, ':round_num' => $round_num, ':wordle_start_num' => $wordle_start_num, ':wordle_start_date' => $wordle_start_date));

    if ($round) {
        // return the id
        $id = $round['id'];
    } else {
        $id = db_run($db, "INSERT INTO w_index (group_id, round_num, wordle_start_num, wordle_start_date, par) VALUES (:group_id, :round_num, :wordle_start_num, :wordle_start_date, :par)",
            array(':group_id' => $group_id, ':round_num' => $round_num, ':wordle_start_num' => $wordle_start_num, ':wordle_start_date' => $wordle_start_date, ':par' => $par));
    }

    return $id;
}

// $round_id and $person_id both belong to one group, so a result needs no group of its own
function SubmitScore($db, $round_id, $person_id, $hole_num, $wordle_num, $score, $total, $update_scores)
{
    $score_rec = db_row($db, "SELECT id FROM w_results WHERE round_id = :round_id AND person_id = :person_id AND hole_num = :hole_num",
        array(':round_id' => $round_id, ':person_id' => $person_id, ':hole_num' => $hole_num));

    if ($score_rec) {
        // return the id
        $score_id = $score_rec['id'];
        if($update_scores){
            db_run($db, "UPDATE w_results SET wordle_num = :wordle_num, score = :score, total = :total WHERE id = :id",
                array(':wordle_num' => $wordle_num, ':score' => $score, ':total' => $total, ':id' => $score_id));
        }
    } else {
        $score_id = db_run($db, "INSERT INTO w_results (round_id, person_id, hole_num, wordle_num, score, total) VALUES (:round_id, :person_id, :hole_num, :wordle_num, :score, :total)",
            array(':round_id' => $round_id, ':person_id' => $person_id, ':hole_num' => $hole_num, ':wordle_num' => $wordle_num, ':score' => $score, ':total' => $total));
    }

    return $score_id;
}

// The mean score is a property of a group's own results, so wordle answers are stored per group
function SubmitWordle($db, $group_id, $wordle_num, $mean_score, $wordle_word)
{
    $wordle_word = (string)$wordle_word;
    $score_rec = db_row($db, "SELECT id FROM w_answer WHERE group_id = :group_id AND wordle_num = :wordle_num",
        array(':group_id' => $group_id, ':wordle_num' => $wordle_num));

    if ($score_rec) {
        // return the id
        $score_id = $score_rec['id'];
        if($mean_score){
            db_run($db, "UPDATE w_answer SET mean_score = :mean_score, wordle_answer = :wordle_answer WHERE id = :id",
                array(':mean_score' => $mean_score, ':wordle_answer' => $wordle_word, ':id' => $score_id));
        }
    } else {
        $score_id = db_run($db, "INSERT INTO w_answer (group_id, wordle_num, wordle_answer, mean_score) VALUES (:group_id, :wordle_num, :wordle_answer, :mean_score)",
            array(':group_id' => $group_id, ':wordle_num' => $wordle_num, ':wordle_answer' => $wordle_word, ':mean_score' => $mean_score));
    }

    return $score_id;
}
