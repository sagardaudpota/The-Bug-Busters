<?php
// update_score.php

$json_file = 'score_data.json';

// Default match state (including match_mode, max_overs, target).
$defaultData = array(
    "striker"           => "Player1",
    "non_striker"       => "Player2",
    "striker_runs"      => 0,
    "striker_balls"     => 0,
    "non_striker_runs"  => 0,
    "non_striker_balls" => 0,
    "team"              => "TeamA",
    "opponent"          => "TeamB",
    "match_info"        => "Match info not set",
    "match_mode"        => "chasing",
    "team_runs"         => 0,
    "team_wickets"      => 0,
    "over"              => 0.0,
    "ball_history"      => array(),
    "bowler"            => "Bowler1",
    "logo"              => "images/logo.png",
    "left_flag"         => "images/pakistan_flag.png",
    "right_flag"        => "images/indian_flag.png",
    "arrow_img"         => "images/arrow.png",
    "max_overs"         => 20,
    "target"            => 0
);

// Helper function for file uploads.
function handleUpload($fieldName, $currentValue) {
    if(isset($_FILES[$fieldName]) && $_FILES[$fieldName]['error'] == 0) {
        $targetDir = "uploads/";
        if (!file_exists($targetDir)) {
            mkdir($targetDir, 0777, true);
        }
        $filename = time() . "_" . basename($_FILES[$fieldName]['name']);
        $targetFile = $targetDir . $filename;
        if (move_uploaded_file($_FILES[$fieldName]['tmp_name'], $targetFile)) {
            return $targetFile;
        }
    }
    return $currentValue;
}

// Process clear score request.
if (isset($_POST['clear']) && $_POST['clear'] == '1') {
    if (file_put_contents($json_file, json_encode($defaultData))) {
        echo "Score cleared.";
    } else {
        http_response_code(500);
        echo "Failed to clear score.";
    }
    exit;
}

// Load current state.
if (file_exists($json_file)) {
    $data = json_decode(file_get_contents($json_file), true);
} else {
    $data = $defaultData;
}

// Process undo request.
if (isset($_POST['undo']) && $_POST['undo'] == '1') {
    if (empty($data['ball_history'])) {
         echo "Nothing to undo.";
         exit;
    }
    $last_entry = array_pop($data['ball_history']);
    $last_event = $last_entry['full'];
    
    if ($last_event === "W") {
         $data['team_wickets'] = max(0, $data['team_wickets'] - 1);
         if (substr($data['striker'], -6) === " (out)") {
             $data['striker'] = str_replace(" (out)", "", $data['striker']);
             $data['striker_runs'] = 0;
             $data['striker_balls'] = max(0, $data['striker_balls'] - 1);
         } else if (substr($data['non_striker'], -6) === " (out)") {
             $data['non_striker'] = str_replace(" (out)", "", $data['non_striker']);
             $data['non_striker_runs'] = 0;
             $data['non_striker_balls'] = max(0, $data['non_striker_balls'] - 1);
         }
         $currentOver = $data['over'];
         $completedOvers = floor($currentOver);
         $currentBalls = round(($currentOver - $completedOvers) * 10);
         $newBalls = max(0, $currentBalls - 1);
         $data['over'] = $completedOvers + ($newBalls / 10);
    } else if (is_numeric($last_event)) {
         $run = intval($last_event);
         $data['striker_runs'] = max(0, $data['striker_runs'] - $run);
         $data['team_runs'] = max(0, $data['team_runs'] - $run);
         $data['striker_balls'] = max(0, $data['striker_balls'] - 1);
         $currentOver = $data['over'];
         $completedOvers = floor($currentOver);
         $currentBalls = round(($currentOver - $completedOvers) * 10);
         $newBalls = max(0, $currentBalls - 1);
         $data['over'] = $completedOvers + ($newBalls / 10);
    } else if (strpos($last_event, "wd") === 0 || strpos($last_event, "nb") === 0) {
         $parts = explode("+", $last_event);
         $extraRuns = 1;
         if (count($parts) > 1) {
             $extraRuns += intval($parts[1]);
         }
         $data['team_runs'] = max(0, $data['team_runs'] - $extraRuns);
         if (strpos($last_event, "nb") === 0 && count($parts) > 1) {
             $data['striker_runs'] = max(0, $data['striker_runs'] - intval($parts[1]));
         }
    } else if (strpos($last_event, "bye") === 0 || strpos($last_event, "lbye") === 0) {
         $parts = explode("+", $last_event);
         $runs = 0;
         if(count($parts) > 1){
              $runs = intval($parts[1]);
         }
         $data['team_runs'] = max(0, $data['team_runs'] - $runs);
         $data['striker_balls'] = max(0, $data['striker_balls'] - 1);
         $currentOver = $data['over'];
         $completedOvers = floor($currentOver);
         $currentBalls = round(($currentOver - $completedOvers) * 10);
         $newBalls = max(0, $currentBalls - 1);
         $data['over'] = $completedOvers + ($newBalls / 10);
    }
    if (file_put_contents($json_file, json_encode($data))) {
         echo "Last ball undone.";
    } else {
         http_response_code(500);
         echo "Failed to undo.";
    }
    exit;
}

// Process swap request.
if (isset($_POST['swap']) && $_POST['swap'] == '1') {
    $tempName = $data['striker'];
    $data['striker'] = $data['non_striker'];
    $data['non_striker'] = $tempName;
    
    $tempRuns = $data['striker_runs'];
    $data['striker_runs'] = $data['non_striker_runs'];
    $data['non_striker_runs'] = $tempRuns;
    
    $tempBalls = $data['striker_balls'];
    $data['striker_balls'] = $data['non_striker_balls'];
    $data['non_striker_balls'] = $tempBalls;
    
    if (file_put_contents($json_file, json_encode($data))) {
        echo "Batsmen swapped.";
    } else {
        http_response_code(500);
        echo "Failed to swap batsmen.";
    }
    exit;
}

// Process match details update (excluding player names).
if (isset($_POST['update_details']) && $_POST['update_details'] == '1') {
    $data['team']        = (isset($_POST['team']) && trim($_POST['team']) !== "") ? $_POST['team'] : $data['team'];
    $data['opponent']    = (isset($_POST['opponent']) && trim($_POST['opponent']) !== "") ? $_POST['opponent'] : $data['opponent'];
    $data['match_info']  = (isset($_POST['match_info']) && trim($_POST['match_info']) !== "") ? $_POST['match_info'] : $data['match_info'];
    if(isset($_POST['match_mode']) && trim($_POST['match_mode']) !== ""){
         $data['match_mode'] = $_POST['match_mode'];
    }
    if(isset($_POST['max_overs']) && trim($_POST['max_overs']) !== ""){
         $data['max_overs'] = floatval($_POST['max_overs']);
    }
    if(isset($_POST['active_design']) && trim($_POST['active_design']) !== ""){
         $data['active_design'] = $_POST['active_design'];
    }
    
    $data['logo']        = handleUpload('logo_file', $data['logo']);
    $data['left_flag']   = handleUpload('left_flag_file', $data['left_flag']);
    $data['right_flag']  = handleUpload('right_flag_file', $data['right_flag']);
    $data['arrow_img']   = handleUpload('arrow_img_file', $data['arrow_img']);
    
    if (file_put_contents($json_file, json_encode($data))) {
        echo "Match details updated.";
    } else {
        http_response_code(500);
        echo "Failed to update match details.";
    }
    exit;
}

// Process separate update for striker name.
if(isset($_POST['update_striker']) && $_POST['update_striker'] == '1'){
    $oldName = isset($_POST['current_striker']) ? trim($_POST['current_striker']) : "";
    $newName = isset($_POST['new_striker']) ? trim($_POST['new_striker']) : "";
    $storedStriker = str_replace(" (out)", "", $data['striker']);
    if($storedStriker === $oldName && $newName !== ""){
         $data['striker'] = $newName;
         if(file_put_contents($json_file, json_encode($data))){
              echo "Striker name updated.";
         } else {
              echo "Failed to update striker name.";
         }
    } else {
         echo "Striker name did not match.";
    }
    exit;
}

// Process separate update for non-striker name.
if(isset($_POST['update_non_striker']) && $_POST['update_non_striker'] == '1'){
    $oldName = isset($_POST['current_non_striker']) ? trim($_POST['current_non_striker']) : "";
    $newName = isset($_POST['new_non_striker']) ? trim($_POST['new_non_striker']) : "";
    $storedNonStriker = str_replace(" (out)", "", $data['non_striker']);
    if($storedNonStriker === $oldName && $newName !== ""){
         $data['non_striker'] = $newName;
         if(file_put_contents($json_file, json_encode($data))){
              echo "Non-striker name updated.";
         } else {
              echo "Failed to update non-striker name.";
         }
    } else {
         echo "Non-striker name did not match.";
    }
    exit;
}

// Process separate update for bowler name.
if(isset($_POST['update_bowler']) && $_POST['update_bowler'] == '1'){
    $oldName = isset($_POST['current_bowler']) ? trim($_POST['current_bowler']) : "";
    $newName = isset($_POST['new_bowler']) ? trim($_POST['new_bowler']) : "";
    if($oldName === $data['bowler'] && $newName !== ""){
         $data['bowler'] = $newName;
         if(file_put_contents($json_file, json_encode($data))){
              echo "Bowler name updated.";
         } else {
              echo "Failed to update bowler name.";
         }
    } else {
         echo "Bowler name did not match.";
    }
    exit;
}

// Process set custom match state request (numeric values only).
if(isset($_POST['set_state']) && $_POST['set_state'] == '1'){
    if(isset($_POST['over']) && trim($_POST['over']) !== ""){
         $data['over'] = floatval($_POST['over']);
    }
    if(isset($_POST['team_runs']) && trim($_POST['team_runs']) !== ""){
         $data['team_runs'] = intval($_POST['team_runs']);
    }
    if(isset($_POST['team_wickets']) && trim($_POST['team_wickets']) !== ""){
         $data['team_wickets'] = intval($_POST['team_wickets']);
    }
    if(isset($_POST['striker_runs']) && trim($_POST['striker_runs']) !== ""){
         $data['striker_runs'] = intval($_POST['striker_runs']);
    }
    if(isset($_POST['striker_balls']) && trim($_POST['striker_balls']) !== ""){
         $data['striker_balls'] = intval($_POST['striker_balls']);
    }
    if(isset($_POST['non_striker_runs']) && trim($_POST['non_striker_runs']) !== ""){
         $data['non_striker_runs'] = intval($_POST['non_striker_runs']);
    }
    if(isset($_POST['non_striker_balls']) && trim($_POST['non_striker_balls']) !== ""){
         $data['non_striker_balls'] = intval($_POST['non_striker_balls']);
    }
    if(isset($_POST['target']) && trim($_POST['target']) !== ""){
         $data['target'] = intval($_POST['target']);
    }
    if(isset($_POST['ball_history']) && trim($_POST['ball_history']) != ''){
         $ball_history = explode(',', $_POST['ball_history']);
         $temp = array();
         foreach($ball_history as $item){
              $item = trim($item);
              if($item !== ""){
                  $temp[] = array("display"=>$item, "full"=>$item);
              }
         }
         if(!empty($temp)){
              $data['ball_history'] = $temp;
         }
    }
    if(file_put_contents($json_file, json_encode($data))){
         echo "Match state updated.";
    } else {
         echo "Failed to update match state.";
    }
    exit;
}

// Process Change Inning request.
if(isset($_POST['change_inning']) && $_POST['change_inning'] == '1'){
    $data['target'] = $data['team_runs'] + 1;
    $temp = $data['team'];
    $data['team'] = $data['opponent'];
    $data['opponent'] = $temp;
    $temp = $data['left_flag'];
    $data['left_flag'] = $data['right_flag'];
    $data['right_flag'] = $temp;
    // Reset striker and non-striker to default values.
    $data['striker'] = "Striker";
    $data['non_striker'] = "Non-Striker";
    $data['striker_runs'] = 0;
    $data['striker_balls'] = 0;
    $data['non_striker_runs'] = 0;
    $data['non_striker_balls'] = 0;
    // Reset overall score, wickets, overs, and ball history.
    $data['team_runs'] = 0;
    $data['team_wickets'] = 0;
    $data['over'] = 0.0;
    $data['ball_history'] = array();
    $data['match_mode'] = "chasing";
    
    if(file_put_contents($json_file, json_encode($data))){
         echo "Inning changed.";
    } else {
         echo "Failed to change inning.";
    }
    exit;
}

// Process live event.
if (isset($_POST['live_event'])) {
    $event = $_POST['live_event'];
    $legalBall = false;
    $isWicket = false;
    
    if (strpos($event, "wicket:") === 0) {
         $parts = explode(":", $event);
         $targetAndRuns = $parts[1];
         $extra_runs = 0;
         if (strpos($targetAndRuns, "+") !== false) {
              list($target, $extra_runs_str) = explode("+", $targetAndRuns);
              $target = trim($target);
              $extra_runs = intval($extra_runs_str);
         } else {
              $target = trim($targetAndRuns);
         }
         $data['team_wickets'] += 1;
         $isWicket = true;
         if ($extra_runs > 0) {
              $data['team_runs'] += $extra_runs;
         }
         if ($target === "1") {
              $data['striker'] = $data['striker'] . " (out)";
              $data['striker_runs'] = 0;
              $data['striker_balls'] = 0;
         } else if ($target === "2") {
              $data['non_striker'] = $data['non_striker'] . " (out)";
              $data['non_striker_runs'] = 0;
              $data['non_striker_balls'] = 0;
         }
         $event = "W";
         $legalBall = true;
    }
    else if (is_numeric($event)) {
         $run = intval($event);
         $data['striker_runs'] += $run;
         $data['team_runs'] += $run;
         $legalBall = true;
    }
    else if (strpos($event, "nb") === 0 || strpos($event, "wd") === 0) {
         $parts = explode("+", $event);
         $additional = 0;
         if (count($parts) > 1) {
             $additional = intval($parts[1]);
         }
         $extraRuns = 1 + $additional;
         $data['team_runs'] += $extraRuns;
         if (strpos($event, "nb") === 0) {
             $data['striker_runs'] += $additional;
         }
    }
    else if (strpos($event, "bye") === 0 || strpos($event, "lbye") === 0) {
         $parts = explode("+", $event);
         $runs = 0;
         if(count($parts) > 1){
              $runs = intval($parts[1]);
         }
         $data['team_runs'] += $runs;
         $legalBall = true;
    }
    
    if ($legalBall) {
         if (!$isWicket) {
              $data['striker_balls'] += 1;
         }
         $currentOver = $data['over'];
         $completedOvers = floor($currentOver);
         $currentBalls = round(($currentOver - $completedOvers) * 10);
         $totalLegalBalls = $completedOvers * 6 + $currentBalls + 1;
         $newCompleted = floor($totalLegalBalls / 6);
         $newBalls = $totalLegalBalls % 6;
         $data['over'] = $newCompleted + ($newBalls / 10);
    }
    
    if (strpos($event, "wd") === 0) {
         $displayEvent = "wd";
    } else if (strpos($event, "nb") === 0) {
         $displayEvent = "nb";
    } else if (strpos($event, "bye") === 0) {
         $displayEvent = "bye";
    } else if (strpos($event, "lbye") === 0) {
         $displayEvent = "lbye";
    } else {
         $displayEvent = $event;
    }
    $ball_entry = array("display" => $displayEvent, "full" => $event);
    $data['ball_history'][] = $ball_entry;
    
    if ($legalBall) {
         $currentOver = $data['over'];
         $completedOvers = floor($currentOver);
         $currentBalls = round(($currentOver - $completedOvers) * 10);
         if ($currentBalls == 0 && $data['over'] != 0) {
              $data['ball_history'] = array();
         }
    }
    
    if (file_put_contents($json_file, json_encode($data))) {
         echo "Live update processed.";
    } else {
         http_response_code(500);
         echo "Failed to update score.";
    }
    exit;
}
?>
