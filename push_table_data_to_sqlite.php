<?php
(@include_once("./config.php")) or die("Cannot read config.php file<BR>");
(@include_once("./create_sqlite_tables.php")) or die("Cannot read create_sqlite_tables.php file<BR>");
(@include_once("./database_functions.php")) or die("Cannot read database_functions.php file<BR>");


//$query = "SELECT * FROM w_index WHERE 1";
//$index_results = $db->query($query);

$round_data = $_POST['data'];
$round_data = json_decode($round_data);
$round_id = $_POST['round_id'];
$round_num = $_POST['round_num'];
$wordle_start_num = $_POST['wordle_start_num'];
$par = $_POST['par'];

$message = "Success";
$is_valid = 1;
$queries = Array();
for($i = 0; $i < count($round_data) ; $i++){
    $this_person = $round_data[$i];
    $this_person_id = $this_person[22];
    $this_person_total = $this_person[21];

    $total = 0;
    for($j = 0; $j<18; $j++){
        $wordle_num = $wordle_start_num + $j;
        $hole_num = $j+1;
        $score = $this_person[3+$j];
        if($score === "" || $score === null){
            $query = "null";
        }else{
            $total += $score-$par;

            $query = "SELECT * FROM w_results  WHERE round_id=$round_id AND person_id=$this_person_id AND wordle_num=$wordle_num";
            $queries[] = $query;
            $result = $db->query($query);
            $rec = $result->fetchArray();
            $queries[] = var_export($rec,true);
            //if($rec['count'] > 0){
            if($rec){
                // existing so UPDATE or DELETE
                if($score < 0){
                    $id = $rec['id'];
                    $query = "DELETE FROM w_results WHERE id=$id";
                }else{
                    $query = "UPDATE w_results SET score=$score, total=$total WHERE round_id=$round_id AND person_id=$this_person_id AND wordle_num=$wordle_num";
                }
            }else{
                // New so INSERT
                $query = "INSERT INTO w_results (round_id, person_id, hole_num, wordle_num, score, total) VALUES($round_id, $this_person_id, $hole_num, $wordle_num, $score, $total) ";
            }
            $db->query($query);
        }
        $queries[] = $query;
    }
}
$message = $queries;

$return_data = array(
    'message' => $message,
    'is_valid' => $is_valid,
    'round_data' => $round_data,
    'round_id' => $round_id,
    'round_num' => $round_num,
    'wordle_num' => $wordle_num
);

echo json_encode($return_data);
exit;