<?php
// player_grid_green.php
// Prevent Caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Cricket Team Grid</title>
  <!-- Bootstrap 5 CSS -->
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Google Font -->
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700&display=swap" rel="stylesheet">
  <style>
    body {
      background: transparent;
      background-size: cover;
      background-position: center;
      margin: 0;
      padding: 0;
      font-family: 'Montserrat', sans-serif;
      position: relative;
      color: #fff;
    }
    body::before {
      content: "";
      position: absolute;
      top: 0; left: 0; right: 0; bottom: 0;
      z-index: 0;
    }
    .team-container {
      position: relative;
      z-index: 1;
      margin: 100px auto;
      max-width: 1200px;
      background: rgba(0, 0, 0, 0.85);
      border: 2px solid #03e201;
      border-radius: 20px;
      padding: 30px 40px;
      box-shadow: 0 0 30px rgba(3, 226, 1, 0.8);
    }
    .team-header {
      text-align: center;
      font-size: 2.5rem;
      font-weight: 700;
      letter-spacing: 2px;
      margin-bottom: 30px;
      color: #03e201;
      text-transform: uppercase;
    }
    .player-card {
      background: #1a1a1a;
      border: 1px solid #03e201;
      border-radius: 15px;
      text-align: center;
      padding: 20px 10px;
      margin-bottom: 20px;
      transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    .player-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 8px 15px rgba(3, 226, 1, 0.6);
    }
    .player-img {
      width: 100px;
      height: 100px;
      border-radius: 50%;
      border: 2px solid #03e201;
      margin-bottom: 15px;
      object-fit: cover;
    }
    .player-name {
      font-size: 1.1rem;
      font-weight: 700;
      color: #fff;
      margin: 0;
    }
    @media (max-width: 576px) {
      .team-container {
        margin: 20px;
        padding: 20px;
      }
      .team-header {
        font-size: 2rem;
      }
      .player-card {
        padding: 15px 5px;
      }
      .player-img {
        width: 80px;
        height: 80px;
      }
      .player-name {
        font-size: 1rem;
      }
    }
  </style>
  <script>
    // Function to fetch players data from players_data.json.
    function fetchPlayersData() {
      $.ajax({
        url: 'players_data.json',
        method: 'GET',
        dataType: 'json',
        cache: false, // Prevent cache in AJAX request
        success: function(data) {
          $('.team-header').text(data.team);
          var html = '';
          data.players.forEach(function(player) {
            html += '<div class="col-3 col-md-2">';
            html += '  <div class="player-card">';
            html += '    <img class="player-img" src="' + player.image + '" alt="' + player.name + '">';
            html += '    <div class="player-name">' + player.name + '</div>';
            html += '  </div>';
            html += '</div>';
          });
          $('.players-grid').html(html);
        }
      });
    }
    
    // Routing: Check if the active design is still "grid_green".
    function checkActiveDesign() {
      $.ajax({
        url: 'get_score.php',
        method: 'GET',
        dataType: 'json',
        cache: false, // Prevent cache in AJAX request
        success: function(data) {
          var activeDesign = data.active_design || "green";
          // For player grid, assume active design should be "grid_green".
          if (activeDesign !== "grid_green") {
            window.location.href = "index_" + activeDesign + ".php";
          }
        },
        error: function() {
          console.error("Failed to fetch active design.");
        }
      });
    }

    $(document).ready(function(){
      fetchPlayersData();
      setInterval(fetchPlayersData, 4000);
      setInterval(checkActiveDesign, 4000);
    });
  </script>
</head>
<body>
  <div class="team-container">
    <div class="team-header">Team Name</div>
    <div class="row g-4 justify-content-center players-grid">
      <!-- Player cards will be loaded here -->
    </div>
  </div>
</body>
</html>