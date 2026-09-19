<?php
(@include_once("./config.php")) or die("Cannot read config.php file<BR>");
(@include_once("./create_sqlite_tables.php")) or die("Cannot read create_sqlite_tables.php file<BR>");
(@include_once("./database_functions.php")) or die("Cannot read database_functions.php file<BR>");


// Save the scores edited in the table on the page. The round is identified by its database id and
// must belong to the group shown, and so must everyone whose scores are being saved.
if (!isset($_POST['data'], $_POST['round_id'], $_POST['group_id'])
    || !ctype_digit((string)$_POST['round_id']) || !ctype_digit((string)$_POST['group_id'])) {
    json_fail("data, round_id and group_id are required.", 400);
}
$round_data = json_decode($_POST['data']);
if (!is_array($round_data)) {
    json_fail("data is not valid.", 400);
}
$round_id = (int)$_POST['round_id'];
$group_id = (int)$_POST['group_id'];

$message = "Success";
$is_valid = 1;
$queries = Array();

$db->exec("BEGIN IMMEDIATE");
try {
    $round = db_row($db, "SELECT id, round_num, wordle_start_num, par FROM w_index WHERE id = :round_id AND group_id = :group_id",
        array(':round_id' => $round_id, ':group_id' => $group_id));
    if (!$round) {
        throw new InvalidArgumentException("Round $round_id is not a round of group $group_id.");
    }
    $wordle_start_num = $round['wordle_start_num'];
    $par = $round['par'];

    for($i = 0; $i < count($round_data) ; $i++){
        $this_person = $round_data[$i];
        $this_person_id = isset($this_person[22]) ? $this_person[22] : null;
        if ($this_person_id === null || $this_person_id === "") {
            // The mean and solution rows at the bottom of the table are not people
            continue;
        }
        if (!ctype_digit((string)$this_person_id)
            || !db_row($db, "SELECT id FROM w_people WHERE id = :person_id AND group_id = :group_id",
                array(':person_id' => (int)$this_person_id, ':group_id' => $group_id))) {
            throw new InvalidArgumentException("Person $this_person_id is not in group $group_id.");
        }
        $this_person_id = (int)$this_person_id;

        $total = 0;
        for($j = 0; $j<18; $j++){
            $wordle_num = $wordle_start_num + $j;
            $hole_num = $j+1;
            $score = $this_person[3+$j];
            if($score === "" || $score === null){
                $query = "null";
            }else{
                if(!is_numeric($score)){
                    throw new InvalidArgumentException("Score \"$score\" is not a number.");
                }
                $score = $score + 0;
                $total += $score-$par;

                $rec = db_row($db, "SELECT id FROM w_results WHERE round_id = :round_id AND person_id = :person_id AND wordle_num = :wordle_num",
                    array(':round_id' => $round_id, ':person_id' => $this_person_id, ':wordle_num' => $wordle_num));
                $queries[] = var_export($rec,true);
                if($rec){
                    // existing so UPDATE or DELETE
                    if($score < 0){
                        $query = "DELETE FROM w_results WHERE id = :id";
                        db_run($db, $query, array(':id' => $rec['id']));
                    }else{
                        $query = "UPDATE w_results SET score = :score, total = :total WHERE id = :id";
                        db_run($db, $query, array(':score' => $score, ':total' => $total, ':id' => $rec['id']));
                    }
                }else{
                    // New so INSERT
                    $query = "INSERT INTO w_results (round_id, person_id, hole_num, wordle_num, score, total) VALUES (:round_id, :person_id, :hole_num, :wordle_num, :score, :total)";
                    db_run($db, $query, array(':round_id' => $round_id, ':person_id' => $this_person_id, ':hole_num' => $hole_num,
                        ':wordle_num' => $wordle_num, ':score' => $score, ':total' => $total));
                }
            }
            $queries[] = $query;
        }
    }
    $db->exec("COMMIT");
} catch (InvalidArgumentException $e) {
    $db->exec("ROLLBACK");
    json_fail($e->getMessage(), 400);
} catch (Throwable $e) {
    $db->exec("ROLLBACK");
    json_fail("The scores could not be saved: " . $e->getMessage());
}
$message = $queries;

$return_data = array(
    'message' => $message,
    'is_valid' => $is_valid,
    'round_data' => $round_data,
    'round_id' => $round_id,
    'group_id' => $group_id,
    'round_num' => $round['round_num']
);

echo json_encode($return_data);
exit;
