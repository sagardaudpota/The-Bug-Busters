<?php
// index_grid_blue.php
// Prevent Caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Cricket Team Grid - Blue</title>
  
  <!-- Bootstrap 5 CSS -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <!-- jQuery -->
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <!-- Google Font -->
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700&display=swap" rel="stylesheet">
  
  <style>
    body {
      margin: 0;
      padding: 0;
      background: transparent;
      background-size: cover;
      font-family: Arial, sans-serif;
    }
    .team-container {
      max-width: 1200px;
      margin: 100px auto;
      background-color: rgba(255, 255, 255, 0.9);
      border: 3px solid #000;
      border-radius: 12px;
      padding: 20px 20px 30px 20px;
      box-shadow: 0 0 15px rgba(0, 0, 0, 0.5);
    }
    .team-title {
      text-align: center;
      font-size: 2rem;
      font-weight: bold;
      color: #000;
      margin-bottom: 20px;
    }
    .player-card {
      background: #fff;
      border: 2px solid #000;
      border-radius: 8px;
      text-align: center;
      padding: 10px;
      transition: transform 0.2s, box-shadow 0.2s;
    }
    .player-card:hover {
      transform: scale(1.03);
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
    }
    .player-img {
      width: 100%;
      height: auto;
      border-radius: 4px;
      margin-bottom: 8px;
    }
    .player-name {
      font-size: 1rem;
      font-weight: bold;
      color: #000;
      margin: 0;
    }
  </style>
  
  <script>
    // Global variable to hold previous players data and active design value.
    var prevPlayersData = "";
    var expectedDesign = "grid_blue"; // Expected active design for this page
    var prevActiveDesign = expectedDesign;

    // Function to fetch players data from players_data.json.
    function fetchPlayersData() {
      $.ajax({
        url: 'players_data.json',
        method: 'GET',
        cache: false,
        dataType: 'json',
        success: function(data) {
          var newPlayersData = JSON.stringify(data);
          if(newPlayersData !== prevPlayersData) {
            prevPlayersData = newPlayersData;
            $('.team-title').text(data.team);
            let html = '';
            data.players.forEach(function(player) {
              html += `
                <div class="col-3 col-md-2">
                  <div class="player-card">
                    <img class="player-img" src="${player.image}" alt="${player.name}">
                    <div class="player-name">${player.name}</div>
                  </div>
                </div>
              `;
            });
            $('.players-grid').html(html);
          }
        }
      });
    }

    // Function to check active design from get_score.php.
    function checkActiveDesign() {
      $.ajax({
        url: 'get_score.php',
        method: 'GET',
        cache: false,
        dataType: 'json',
        success: function(data) {
          var activeDesign = data.active_design || expectedDesign;
          if(activeDesign !== prevActiveDesign) {
            prevActiveDesign = activeDesign;
            if(activeDesign !== expectedDesign) {
              window.location.href = "index_" + activeDesign + ".php";
            }
          }
        },
        error: function() {
          console.error("Failed to fetch active design.");
        }
      });
    }

    $(document).ready(function() {
      fetchPlayersData();
      setInterval(fetchPlayersData, 4000);
      setInterval(checkActiveDesign, 4000);
    });
  </script>
  
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body>
  <div class="team-container">
    <h2 class="team-title">Team Name</h2>
    <div class="row g-4 justify-content-center players-grid" id="players-grid">
      <!-- Player cards will be loaded here dynamically -->
    </div>
  </div>
</body>
</html>
