<?php
// index.php - Routing script
// Read the current active design from the JSON data.
$data = json_decode(file_get_contents("score_data.json"), true);
$activeDesign = isset($data['active_design']) ? $data['active_design'] : "green";  // default design
// Determine the expected file name for the active design.
$expectedPage = "index_" . $activeDesign . ".php";
// Get the current file name.
$currentPage = basename($_SERVER['PHP_SELF']);
if ($currentPage !== $expectedPage) {
  // Redirect immediately if the current page does not match the active design.
  header("Location: " . $expectedPage);
  exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Public Scoreboard</title>
  <!-- jQuery for AJAX polling -->
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script>
    // This function polls get_score.php every 5 seconds to check if the active design has changed.
    function checkForDesignChange() {
      $.ajax({
        url: 'get_score.php',
        method: 'GET',
        dataType: 'json',
        success: function(data) {
          var activeDesign = data.active_design || "green";
          var expectedPage = "index_" + activeDesign + ".php";
          // Get the current page name from the URL.
          var currentPage = window.location.pathname.split("/").pop();
          // If the active design differs from the current page, redirect.
          if (currentPage !== expectedPage) {
            window.location.href = expectedPage;
          }
        },
        error: function() {
          console.error("Failed to fetch active design.");
        }
      });
    }
    
    $(document).ready(function(){
      // Poll every 5 seconds.
      setInterval(checkForDesignChange, 5000);
    });
  </script>
  <style>
    /* Optional styles so that the page fills the viewport */
    html, body { margin: 0; padding: 0; height: 100%; }
  </style>
</head>
<body>
  <!-- Your active design page content will be loaded as the current file.
       Since we redirected on initial load, this file already corresponds to the active design. -->
  <?php
  // At this point, the current page is the correct design file.
  // You can include additional common elements here if needed.
  ?>
  <!-- For example, you might include a common footer or a script to update live score -->
</body>
</html>
