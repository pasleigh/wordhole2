<?php
(@include_once("./config.php")) or die("Cannot read config.php file<BR>");
(@include_once("./create_sqlite_tables.php")) or die("Cannot read create_sqlite_tables.php file<BR>");
(@include_once("./database_functions.php")) or die("Cannot read database_functions.php file<BR>");


$query = "SELECT * FROM w_index WHERE 1 ORDER BY round_num DESC ";
$index_results = $db->query($query);

while ($round = $index_results->fetchArray()) {
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

    $results = array();

    $query = "SELECT * FROM w_people WHERE 1";
    $people_results = $db->query($query);


    while ($people_rec = $people_results->fetchArray()) {
        $person_id = $people_rec['id'];
        $first_name = $people_rec['first_name'];
        $family_name = $people_rec['family_name'];

        $query = "SELECT COUNT(*) as count FROM w_results WHERE round_id=$round_id AND person_id=$person_id";
        $round_results = $db->query($query);
        $round_recs = $round_results->fetchArray();
        $numScores = $round_recs['count'];

        if ($numScores > 0) {
            for ($i = 1; $i <= $numScores; $i++) {
                $query = "SELECT * FROM w_results WHERE round_id=$round_id AND person_id=$person_id AND hole_num=$i";
                $round_results = $db->query($query);
                $round_rec = $round_results->fetchArray();

                if($round_rec) {
                    $hole_num = $round_rec['hole_num'];
                    $hole_num = $hole_num - 1;
                    $score = $round_rec['score'];
                    $scores[$hole_num] = $score;
                }
            }
            // fill the 18 holes with null
            for ($i = $numScores; $i < 18; $i++) {
                $scores[$i] = null;
            }
            $results[] = array(
                'person_id' => $person_id,
                'first_name' => $first_name,
                'family_name' => $family_name,
                'scores' => $scores
            );
        }
    }

    // get the mean and wordle word data
    $wordle_words = array();
    $mean_scores = array();
    for ($i = 0; $i < 18; $i++) {
        $wordle_num = $start_wordle+$i;
        $query = "SELECT * FROM w_answer WHERE wordle_num=$wordle_num";
        $answers_results = $db->query($query);
        $answers_rec = $answers_results->fetchArray();
        if($answers_rec) {
            $mean_score = $answers_rec['mean_score'];
            $wordle_word = $answers_rec['wordle_answer'];
            $mean_scores[$wordle_num] = $mean_score;
            $wordle_words[$wordle_num] = $wordle_word;
        }
    }


    $round_data[] = array(
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

$message = "Success";
$is_valid = 1;


$return_data = array(
    'round_data' => $round_data,
    'message' => $message,
    'file_info' => null,
    'is_valid' => $is_valid
);

echo json_encode($return_data);
exit;