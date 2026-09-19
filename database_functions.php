<?php
(@include_once("./config.php")) or die("Cannot read config.php file<BR>");
(@include_once("./create_sqlite_tables.php")) or die("Cannot read create_sqlite_tables.php file<BR>");

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
function push_all_round_data_database($db, $round_data)
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

            $round_id = GetRoundID($db, $round_num, $wordle_start_num, $wordle_start_date, $par);

            $results = $this_round['results'];

            //echo('Results<BR>');
            //var_dump($results);
            $this_round_final_scores = array();
            for ($j = 0; $j < count($results); $j++) {
                $result = $results[$j];
                $first_name = $result['first_name'];
                $family_name = $result['family_name'];
                $person_id = GetPersonID($db, $first_name, $family_name);
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
                $result_id = SubmitWordle($db, $wordle_start_num+$k, $mean_scores[$k], $wordle_words[$k]);
            }
        }
        $all_rounds_final_scores[$round_num] = $this_round_final_scores;
    }

    return $ret_value;
}

function GetPersonID($db, $first_name, $family_name)
{
    $query = "SELECT * FROM w_people WHERE first_name='$first_name' AND family_name='$family_name'";
    $results = $db->query($query);

    $person = $results->fetchArray();

    //echo("Person: <BR>");
    //var_dump($person);
    if ($person) {
        // return the id
        $id = $person['id'];
    } else {
        $query = "INSERT INTO w_people (first_name, family_name) VALUES ('$first_name', '$family_name')";
        $db->query($query);
        $id = $db->lastInsertRowId();
    }

    return $id;
}

function GetRoundID($db, $round_num, $wordle_start_num, $wordle_start_date, $par)
{
    $query = "SELECT * FROM w_index WHERE round_num='$round_num' AND wordle_start_num='$wordle_start_num' AND wordle_start_date='$wordle_start_date'";
    $results = $db->query($query);

    $round = $results->fetchArray();

    //echo("Person: <BR>");
    //var_dump($person);
    if ($round) {
        // return the id
        $id = $round['id'];
    } else {
        $query = "INSERT INTO w_index (round_num, wordle_start_num, wordle_start_date, par) VALUES ('$round_num', '$wordle_start_num', '$wordle_start_date', '$par')";
        $db->query($query);
        $id = $db->lastInsertRowId();
    }

    return $id;
}

function SubmitScore($db, $round_id, $person_id, $hole_num, $wordle_num, $score, $total, $update_scores)
{
    $query = "SELECT * FROM w_results WHERE round_id='$round_id' AND person_id='$person_id' AND hole_num='$hole_num'";
    $results = $db->query($query);

    $score_rec = $results->fetchArray();
    if ($score_rec) {
        // return the id
        $score_id = $score_rec['id'];
        $query = "UPDATE w_results SET 
                     round_id='$round_id', 
                     person_id='$person_id', 
                     hole_num='$hole_num', 
                     wordle_num='$wordle_num', 
                     score='$score', 
                     total='$total' 
                 WHERE id='$score_id'";
        if($update_scores){
            $db->query($query);
        }
    } else {
        $query = "INSERT INTO w_results (round_id, person_id, hole_num, wordle_num, score, total) 
                    VALUES ('$round_id', '$person_id', '$hole_num', '$wordle_num', '$score', '$total')";
        $db->query($query);
        $score_id = $db->lastInsertRowId();
    }

    return $score_id;
}
function SubmitWordle($db, $wordle_num, $mean_score, $wordle_word)
{
    $query = "SELECT * FROM w_answer WHERE wordle_num='$wordle_num'";
    $results = $db->query($query);

    $score_rec = $results->fetchArray();
    if ($score_rec) {
        // return the id
        $score_id = $score_rec['id'];
        $query = "UPDATE w_answer SET 
                     mean_score='$mean_score', 
                     wordle_answer='$wordle_word' 
                 WHERE wordle_num='$wordle_num'";
        if($mean_score){
            $db->query($query);
        }
    } else {
        $query = "INSERT INTO w_answer (wordle_num, wordle_answer, mean_score) 
                    VALUES ('$wordle_num', '$wordle_word', '$mean_score')";
        $db->query($query);
        $score_id = $db->lastInsertRowId();
    }

    return $score_id;
}