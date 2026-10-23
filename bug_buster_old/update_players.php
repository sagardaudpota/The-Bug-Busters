<?php
// update_players.php

$playersFile = 'players_data.json';

// Retrieve posted data
$team = isset($_POST['team']) ? trim($_POST['team']) : '';
$players = array();

if(isset($_POST['names'])){
    $names = $_POST['names'];
    $imageFiles = $_FILES['images'];

    for($i = 0; $i < count($names); $i++) {
        $playerName = $names[$i];
        $imageName = $imageFiles['name'][$i];
        $imageTmpName = $imageFiles['tmp_name'][$i];

        // Check if an image was uploaded or not
        if (!empty($imageName)) {
            $uploadDir = 'uploads/';
            $uploadPath = $uploadDir . basename($imageName);

            // Move the uploaded file to the destination folder
            if(move_uploaded_file($imageTmpName, $uploadPath)) {
                $imagePath = $uploadPath; // Store uploaded image path
            } else {
                $imagePath = 'images/default.png'; // If upload fails, use default image
            }
        } else {
            $imagePath = 'images/default.png'; // Use default image if no file selected
        }

        // Add player data to the $players array
        $players[] = array(
            'name' => $playerName,
            'image' => $imagePath
        );
    }
}

// Build the JSON data
$data = array(
    'team' => $team,
    'players' => $players
);

// Save data to players_data.json
if (file_put_contents($playersFile, json_encode($data, JSON_PRETTY_PRINT))) {
    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error']);
}
