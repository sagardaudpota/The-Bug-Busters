<?php
// get_score.php

$json_file = 'score_data.json';

if (file_exists($json_file)) {
    $data = json_decode(file_get_contents($json_file), true);
    if (isset($data['ball_history']) && is_array($data['ball_history'])) {
         $displayHistory = array();
         foreach ($data['ball_history'] as $entry) {
             if (is_array($entry) && isset($entry['display'])) {
                 $displayHistory[] = $entry['display'];
             } else {
                 $displayHistory[] = $entry;
             }
         }
         $data['ball_history'] = $displayHistory;
    }
    header('Content-Type: application/json');
    echo json_encode($data);
} else {
    $defaultData = array(
       "striker" => "Player1",
       "non_striker" => "Player2",
       "striker_runs" => 0,
       "striker_balls" => 0,
       "non_striker_runs" => 0,
       "non_striker_balls" => 0,
       "team" => "TeamA",
       "opponent" => "TeamB",
       "match_info" => "Match info not set",
       "match_mode" => "chasing",
       "team_runs" => 0,
       "team_wickets" => 0,
       "over" => 0.0,
       "ball_history" => array(),
       "bowler" => "Bowler1",
       "logo" => "images/logo.png",
       "left_flag" => "images/pakistan_flag.png",
       "right_flag" => "images/indian_flag.png",
       "arrow_img" => "images/arrow.png",
       "max_overs" => 20,
       "target" => 0,
       "active_design" => "green" // Default design option.
    );
    header('Content-Type: application/json');
    echo json_encode($defaultData);
}
?>
