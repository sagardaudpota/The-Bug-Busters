<?php
// admin.php
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Cricket Score Admin Panel</title>
  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- jQuery -->
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script>
    // Global variables to store current batsman names.
    var currentStriker = "";
    var currentNonStriker = "";
    
    // Function to load score details from the server.
    function loadScore() {
      $.ajax({
        url: 'get_score.php',
        method: 'GET',
        dataType: 'json',
        success: function(data) {
          // Remove any " (out)" from names when updating forms.
          currentStriker = data.striker.replace(" (out)", "");
          currentNonStriker = data.non_striker.replace(" (out)", "");
          $('#scoreDisplay').html(
            '<h3>' + data.team + ' vs ' + data.opponent + '</h3>' +
            '<p><strong>Match Info:</strong> ' + data.match_info + '</p>' +
            '<p><strong>Match Mode:</strong> ' + data.match_mode + '</p>' +
            (data.target > 0 ? '<p><strong>Target:</strong> ' + data.target + '</p>' : '') +
            '<p><strong>Score:</strong> ' + data.team_runs + ' / ' + data.team_wickets + '</p>' +
            '<p><strong>Overs:</strong> ' + data.over + ' (Max: ' + data.max_overs + ')</p>' +
            '<p><strong>Striker:</strong> ' + data.striker + ' - ' + data.striker_runs + ' (' + data.striker_balls + ')</p>' +
            '<p><strong>Non-Striker:</strong> ' + data.non_striker + ' - ' + data.non_striker_runs + ' (' + data.non_striker_balls + ')</p>' +
            '<p><strong>Bowler:</strong> ' + data.bowler + '</p>' +
            '<p><strong>Ball History:</strong> ' + (data.ball_history.length ? data.ball_history.join(', ') : 'No balls played yet') + '</p>'
          );
          // Update separate player name forms.
          $('#currentStriker').val(currentStriker);
          $('#currentNonStriker').val(currentNonStriker);
          $('#currentBowler').val(data.bowler);
        }
      });
    }
    
    $(document).ready(function(){
      loadScore();
      setInterval(loadScore, 3000);
      
      // Live event buttons.
      $('.live-update').click(function(){
        var event = $(this).data('event');
        // For extras (wd, nb, bye, lbye), prompt for runs.
        if(event === 'wd' || event === 'nb' || event === 'bye' || event === 'lbye'){
          var extraRuns = prompt("Enter runs for " + event + " (if any):", "0");
          if(extraRuns !== null){
            extraRuns = parseInt(extraRuns, 10);
            if(extraRuns > 0){
              event = event + '+' + extraRuns;
            }
          }
        }
        // For wicket, prompt using current player names.
        else if(event === 'wicket'){
          var target = prompt("Which batsman is out?\nEnter 1 for " + currentStriker + " or 2 for " + currentNonStriker + ":", "1");
          if(target === "1" || target === "2"){
            var runs_on_wicket = prompt("Enter runs scored on that ball (if any):", "0");
            if(runs_on_wicket !== null && parseInt(runs_on_wicket,10) > 0){
              event = "wicket:" + target + "+" + parseInt(runs_on_wicket,10);
            } else {
              event = "wicket:" + target;
            }
          } else {
            alert("Invalid input for wicket.");
            return;
          }
        }
        $.ajax({
          url: 'update_score.php',
          method: 'POST',
          data: { live_event: event },
          success: function(response) {
            loadScore();
          },
          error: function() {
            alert('Error processing event.');
          }
        });
      });
      
      // Swap batsmen button.
      $('#swapButton').click(function(){
        $.ajax({
          url: 'update_score.php',
          method: 'POST',
          data: { swap: '1' },
          success: function(response) {
            loadScore();
          },
          error: function(){
            alert('Error swapping batsmen.');
          }
        });
      });
      
      // Clear score button.
      $('#clearScoreButton').click(function(){
        if(confirm("Are you sure you want to clear the score and start a new match?")){
          $.ajax({
            url: 'update_score.php',
            method: 'POST',
            data: { clear: '1' },
            success: function(response) {
              alert("Score cleared. Starting a new match.");
              loadScore();
            },
            error: function(){
              alert('Error clearing score.');
            }
          });
        }
      });
      
      // Undo last ball button.
      $('#undoButton').click(function(){
        $.ajax({
          url: 'update_score.php',
          method: 'POST',
          data: { undo: '1' },
          success: function(response) {
            alert(response);
            loadScore();
          },
          error: function(){
            alert('Error undoing last ball.');
          }
        });
      });
      
      // Change Inning button.
      $('#changeInningButton').click(function(){
        if(confirm("Change Inning?\nThis will set the target and reset the score for the second innings.")){
          $.ajax({
            url: 'update_score.php',
            method: 'POST',
            data: { change_inning: '1' },
            success: function(response) {
              alert(response);
              loadScore();
            },
            error: function(){
              alert('Error changing inning.');
            }
          });
        }
      });
      
      // Update Match Details form (without player names).
      $('#detailsForm').submit(function(e){
        e.preventDefault();
        var formData = new FormData(this);
        formData.append('update_details', '1');
        $.ajax({
          url: 'update_score.php',
          method: 'POST',
          data: formData,
          processData: false,
          contentType: false,
          success: function(response) {
            alert("Match details updated.");
            loadScore();
          },
          error: function() {
            alert("Error updating match details.");
          }
        });
      });
      
      // Set Custom Match State form.
      $('#stateForm').submit(function(e){
        e.preventDefault();
        var formData = $(this).serialize() + "&set_state=1";
        $.ajax({
          url: 'update_score.php',
          method: 'POST',
          data: formData,
          success: function(response) {
            alert("Match state updated.");
            loadScore();
          },
          error: function() {
            alert("Error updating match state.");
          }
        });
      });
      
      // Update Striker Name form.
      $('#updateStrikerForm').submit(function(e){
        e.preventDefault();
        var formData = $(this).serialize() + "&update_striker=1";
        $.ajax({
          url: 'update_score.php',
          method: 'POST',
          data: formData,
          success: function(response) {
            alert("Striker name updated.");
            loadScore();
          },
          error: function() {
            alert("Error updating striker name.");
          }
        });
      });
      
      // Update Non-Striker Name form.
      $('#updateNonStrikerForm').submit(function(e){
        e.preventDefault();
        var formData = $(this).serialize() + "&update_non_striker=1";
        $.ajax({
          url: 'update_score.php',
          method: 'POST',
          data: formData,
          success: function(response) {
            alert("Non-Striker name updated.");
            loadScore();
          },
          error: function() {
            alert("Error updating non-striker name.");
          }
        });
      });
      
      // Update Bowler Name form.
      $('#updateBowlerForm').submit(function(e){
        e.preventDefault();
        var formData = $(this).serialize() + "&update_bowler=1";
        $.ajax({
          url: 'update_score.php',
          method: 'POST',
          data: formData,
          success: function(response) {
            alert("Bowler name updated.");
            loadScore();
          },
          error: function() {
            alert("Error updating bowler name.");
          }
        });
      });
    });
  </script>
</head>
<body>
  <div class="container mt-5">
    <h1 class="mb-4 text-center">Cricket Score Admin Panel</h1>
    
    <!-- Live Score Update Section -->
    <div class="card mb-4">
      <div class="card-header">Live Score Update Panel</div>
      <div class="card-body">
        <div id="scoreDisplay" class="mb-4"></div>
        <div class="mb-3">
          <h5>Update Ball Event</h5>
          <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-secondary live-update" data-event="0">0</button>
            <button type="button" class="btn btn-secondary live-update" data-event="1">1</button>
            <button type="button" class="btn btn-secondary live-update" data-event="2">2</button>
            <button type="button" class="btn btn-secondary live-update" data-event="3">3</button>
            <button type="button" class="btn btn-secondary live-update" data-event="4">4</button>
            <button type="button" class="btn btn-secondary live-update" data-event="6">6</button>
          </div>
        </div>
        <div class="mb-3">
          <h5>Extras, Byes, Leg Byes & Wicket</h5>
          <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-warning live-update" data-event="wd">Wide</button>
            <button type="button" class="btn btn-warning live-update" data-event="nb">No Ball</button>
            <button type="button" class="btn btn-secondary live-update" data-event="bye">Byes</button>
            <button type="button" class="btn btn-secondary live-update" data-event="lbye">Leg Byes</button>
            <button type="button" class="btn btn-danger live-update" data-event="wicket">Wicket</button>
          </div>
        </div>
        <div class="mb-3 d-flex flex-wrap gap-2">
          <button type="button" class="btn btn-dark" id="undoButton">Undo Last Ball</button>
          <button type="button" class="btn btn-info" id="swapButton">Swap Batsmen</button>
          <button type="button" class="btn btn-primary" id="changeInningButton">Change Inning</button>
          <button type="button" class="btn btn-danger" id="clearScoreButton">Clear Score</button>
        </div>
      </div>
    </div>
    
    <!-- Match Details Update Section (without player names) -->
    <div class="card mb-4">
      <div class="card-header">Update Match Details</div>
      <div class="card-body">
        <form id="detailsForm" enctype="multipart/form-data">
          <div class="row mb-3">
            <div class="col-md-6">
              <label for="team" class="form-label">Team (Batting):</label>
              <input type="text" class="form-control" id="team" name="team" required>
            </div>
            <div class="col-md-6">
              <label for="opponent" class="form-label">Opponent:</label>
              <input type="text" class="form-control" id="opponent" name="opponent" required>
            </div>
          </div>
          <div class="mb-3">
            <label for="match_info" class="form-label">Match Info:</label>
            <input type="text" class="form-control" id="match_info" name="match_info" placeholder="e.g., Match 14 - THE OVAL" required>
          </div>
          <div class="mb-3">
            <label for="match_mode" class="form-label">Match Mode:</label>
            <select class="form-select" id="match_mode" name="match_mode">
              <option value="target">Giving Target</option>
              <option value="chasing" selected>Chasing Target</option>
            </select>
          </div>
          <div class="mb-3">
            <label for="max_overs" class="form-label">Match Overs Limit:</label>
            <input type="number" class="form-control" id="max_overs" name="max_overs" step="0.1" placeholder="e.g., 20">
          </div>
          <div class="row mb-3">
            <div class="col-md-6">
              <label for="logo_file" class="form-label">Upload Logo (or leave blank):</label>
              <input type="file" class="form-control" id="logo_file" name="logo_file">
            </div>
            <div class="col-md-6">
              <label for="left_flag_file" class="form-label">Upload Left Flag (or leave blank):</label>
              <input type="file" class="form-control" id="left_flag_file" name="left_flag_file">
            </div>
          </div>
          <div class="row mb-3">
            <div class="col-md-6">
              <label for="right_flag_file" class="form-label">Upload Right Flag (or leave blank):</label>
              <input type="file" class="form-control" id="right_flag_file" name="right_flag_file">
            </div>
            <div class="col-md-6">
              <label for="arrow_img_file" class="form-label">Upload Arrow Image (or leave blank):</label>
              <input type="file" class="form-control" id="arrow_img_file" name="arrow_img_file">
            </div>
          </div>
          <!-- New Active Design Dropdown -->
          <div class="mb-3">
  <label for="active_design" class="form-label">Active Design:</label>
  <select class="form-select" id="active_design" name="active_design">
    <option value="blue">Index Blue</option>
    <option value="blue_need">Index Blue Need</option>
    <option value="green" selected>Index Green</option>
    <option value="green_need">Index Green Need</option>
    <option value="grid_green">Grid Green</option>
    <option value="grid_blue">Grid Blue</option>
  </select>
</div>

          <button type="submit" class="btn btn-primary">Update Match Details</button>
        </form>
      </div>
    </div>
    
    <!-- Update Player Names Section -->
    <div class="card mb-4">
      <div class="card-header">Update Player Names</div>
      <div class="card-body">
        <div class="row mb-3">
          <div class="col-md-4">
            <form id="updateStrikerForm">
              <label for="currentStriker" class="form-label">Current Striker (readonly):</label>
              <input type="text" class="form-control" id="currentStriker" name="current_striker" readonly>
              <label for="newStriker" class="form-label mt-2">New Striker Name:</label>
              <input type="text" class="form-control" id="newStriker" name="new_striker" required>
              <button type="submit" class="btn btn-outline-primary mt-2">Update Striker</button>
            </form>
          </div>
          <div class="col-md-4">
            <form id="updateNonStrikerForm">
              <label for="currentNonStriker" class="form-label">Current Non-Striker (readonly):</label>
              <input type="text" class="form-control" id="currentNonStriker" name="current_non_striker" readonly>
              <label for="newNonStriker" class="form-label mt-2">New Non-Striker Name:</label>
              <input type="text" class="form-control" id="newNonStriker" name="new_non_striker" required>
              <button type="submit" class="btn btn-outline-primary mt-2">Update Non-Striker</button>
            </form>
          </div>
          <div class="col-md-4">
            <form id="updateBowlerForm">
              <label for="currentBowler" class="form-label">Current Bowler (readonly):</label>
              <input type="text" class="form-control" id="currentBowler" name="current_bowler" readonly>
              <label for="newBowler" class="form-label mt-2">New Bowler Name:</label>
              <input type="text" class="form-control" id="newBowler" name="new_bowler" required>
              <button type="submit" class="btn btn-outline-primary mt-2">Update Bowler</button>
            </form>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Set Custom Match State Section -->
    <div class="card">
      <div class="card-header">Set Custom Match State</div>
      <div class="card-body">
        <form id="stateForm">
          <div class="row mb-3">
            <div class="col-md-3">
              <label for="over" class="form-label">Overs (e.g., 2.3):</label>
              <input type="number" class="form-control" id="over" name="over" step="0.1">
            </div>
            <div class="col-md-3">
              <label for="team_runs" class="form-label">Team Runs:</label>
              <input type="number" class="form-control" id="team_runs" name="team_runs">
            </div>
            <div class="col-md-3">
              <label for="team_wickets" class="form-label">Team Wickets:</label>
              <input type="number" class="form-control" id="team_wickets" name="team_wickets">
            </div>
            <div class="col-md-3">
              <label for="target" class="form-label">Target:</label>
              <input type="number" class="form-control" id="target" name="target">
            </div>
          </div>
          <div class="row mb-3">
            <div class="col-md-6">
              <label for="striker_runs" class="form-label">Striker Runs:</label>
              <input type="number" class="form-control" id="striker_runs" name="striker_runs">
            </div>
            <div class="col-md-6">
              <label for="striker_balls" class="form-label">Striker Balls:</label>
              <input type="number" class="form-control" id="striker_balls" name="striker_balls">
            </div>
          </div>
          <div class="row mb-3">
            <div class="col-md-6">
              <label for="non_striker_runs" class="form-label">Non-Striker Runs:</label>
              <input type="number" class="form-control" id="non_striker_runs" name="non_striker_runs">
            </div>
            <div class="col-md-6">
              <label for="non_striker_balls" class="form-label">Non-Striker Balls:</label>
              <input type="number" class="form-control" id="non_striker_balls" name="non_striker_balls">
            </div>
          </div>
          <div class="mb-3">
            <label for="ball_history" class="form-label">Ball History (comma separated, optional):</label>
            <input type="text" class="form-control" id="ball_history" name="ball_history">
          </div>
          <button type="submit" class="btn btn-primary">Update Match State</button>
        </form>
      </div>
    </div>
    



    <div class="card mt-4">
  <div class="card-header">Update Player Grid Data</div>
  <div class="card-body">
  <div class="container">
    <form id="updatePlayersForm" action="update_players.php" method="POST" enctype="multipart/form-data">
      <div class="mb-3">
        <label for="teamName" class="form-label">Team Name</label>
        <input type="text" class="form-control" id="teamName" name="team" placeholder="Enter team name" required>
      </div>

      <div id="playersContainer">
        <?php for($i = 0; $i < 11; $i++): ?>
        <div class="row mb-3 player-row">
          <div class="col-md-6">
            <label class="form-label">Player Name</label>
            <input type="text" class="form-control" name="names[]" placeholder="Enter player name" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Player Image</label>
            <input type="file" class="form-control" name="images[]">
          </div>
        </div>
        <?php endfor; ?>
      </div>

      <button type="submit" class="btn btn-primary">Update Player Grid Data</button>
    </form>
  </div>
</div>

<script>
  // AJAX Form Submission
  document.getElementById('updatePlayersForm').onsubmit = function(e) {
    e.preventDefault(); // Prevent form from refreshing the page

    const formData = new FormData(this);

    fetch('update_players.php', {
      method: 'POST',
      body: formData
    }).then(response => response.json())
    .then(data => {
      if (data.status === 'success') {
        alert('Player Grid Data Successfully Updated!');
      } else {
        alert('Failed to update data!');
      }
    });
  };
</script>

</div>
   
  </div>
  <!-- Bootstrap Bundle with Popper -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
