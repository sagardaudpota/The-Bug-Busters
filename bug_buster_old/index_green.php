<?php
// index_green.php
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Score Panel | Cricket Software</title>
  <link rel="stylesheet" href="style_green.css">
  <!-- jQuery for Ajax calls -->
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script>
  // ***************** Existing Code - DO NOT CHANGE *****************
  // Processes the ball history array to determine the current over.
  function updateBallHistoryDisplay(history) {
    var currentOver = [];
    var legalBalls = 0;
    // Iterate backward over the history, counting legal deliveries.
    for (var i = history.length - 1; i >= 0; i--) {
      var event = history[i];
      if (event.toLowerCase() !== 'wd' && event.toLowerCase() !== 'nb') {
        legalBalls++;
      }
      currentOver.unshift(event);
      if (legalBalls >= 6) break;
    }
    var totalCircles = currentOver.length;
    if (legalBalls < 6) {
      totalCircles = currentOver.length + (6 - legalBalls);
    }
    var circleHtml = '';
    for (var j = 0; j < currentOver.length; j++) {
      circleHtml += '<div class="circle">' + currentOver[j] + '</div>';
    }
    for (var k = currentOver.length; k < totalCircles; k++) {
      circleHtml += '<div class="circle"></div>';
    }
    $('.move').html(circleHtml);
  }

  function fetchScore() {
    $.ajax({
      url: 'get_score.php',
      method: 'GET',
      dataType: 'json',
      success: function(data) {
        // Update images.
        $('#logo img').attr('src', data.logo);
        $('.p-left img').attr('src', data.left_flag);
        $('.p-right img').attr('src', data.right_flag);
        $('.players img').attr('src', data.arrow_img);

        // Update player names.
        $('.players p').first().text(data.striker);
        $('.players p').last().text(data.non_striker);

        // Update individual runs and balls.
        $('.run p').eq(0).html(data.striker_runs + ' <small>' + data.striker_balls + '</small>');
        $('.run p').eq(1).html(data.non_striker_runs + ' <small>' + data.non_striker_balls + '</small>');

        // Update inning details.
        $('.ining p').html(
          data.team + ' v <span id="opponent">' + data.opponent + '</span> ' +
          '<span id="run-wicket">' + data.team_runs + '-' + data.team_wickets + '</span> ' +
          '<span id="p2">P2</span> ' +
          '<span id="over">' + data.over + '</span> ' +
          '<span id="match">' + data.match_info + '</span>'
        );

        // Update bowler name.
        $('.balls .bowler').text(data.bowler);

        // Update ball-by-ball history.
        updateBallHistoryDisplay(data.ball_history);
      }
    });
  }

  // Routing: poll the active design and redirect if it changes.
  function checkActiveDesign() {
    $.ajax({
      url: 'get_score.php',
      method: 'GET',
      dataType: 'json',
      success: function(data) {
        var activeDesign = data.active_design || "green";
        if (activeDesign !== "green") {
          window.location.href = "index_" + activeDesign + ".php";
        }
      },
      error: function() {
        console.error("Failed to fetch active design.");
      }
    });
  }

  $(document).ready(function(){
    fetchScore();
    setInterval(fetchScore, 5000);
    setInterval(checkActiveDesign, 5000);
  });
  // ***************** End of Existing Code *****************
  </script>

  <!-- ***************** Begin Animation Code - DO NOT CHANGE OTHER Parts ***************** -->
  <script>
    // Global variables to track the last animated event indices.
    var lastAnimatedSixIndex = -1;
    var lastAnimatedFourIndex = -1;
    var lastAnimatedWicketIndex = -1;

    // Function to check for new ball events and trigger animations.
    function checkForAnimationEvent() {
      $.ajax({
        url: 'get_score.php',
        method: 'GET',
        dataType: 'json',
        success: function(data) {
          if(data.ball_history && data.ball_history.length > 0) {
            var lastIndex = data.ball_history.length - 1;
            var lastEvent = data.ball_history[lastIndex];
            if(lastEvent === "6" && lastIndex !== lastAnimatedSixIndex) {
              startSixerAnimation();
              lastAnimatedSixIndex = lastIndex;
            }
            else if(lastEvent === "4" && lastIndex !== lastAnimatedFourIndex) {
              startFourAnimation();
              lastAnimatedFourIndex = lastIndex;
            }
            else if(lastEvent === "W" && lastIndex !== lastAnimatedWicketIndex) {
              startWicketAnimation();
              lastAnimatedWicketIndex = lastIndex;
            }
          }
        }
      });
    }
    
    // Also poll for animation events every 5 seconds.
    setInterval(checkForAnimationEvent, 5000);

    // Animation Functions:

    // Helper: random number generator.
    function random(min, max) {
      return Math.random() * (max - min) + min;
    }

    // Create confetti pieces.
    function createConfetti() {
      const confettiContainer = document.getElementById('confettiContainer');
      confettiContainer.innerHTML = ''; // Clear previous confetti.
      const colors = ['#FFEB3B', '#F44336', '#4CAF50', '#2196F3', '#FF9800', '#9C27B0'];
      const confettiCount = 60;
      for (let i = 0; i < confettiCount; i++) {
        const confetti = document.createElement('div');
        confetti.classList.add('confetti');
        confetti.style.backgroundColor = colors[Math.floor(random(0, colors.length))];
        confetti.style.left = random(0, 100) + '%';
        confetti.style.top = random(0, 100) + '%';
        const tx = random(-200, 200) + 'px';
        const ty = random(150, 350) + 'px';
        confetti.style.setProperty('--tx', tx);
        confetti.style.setProperty('--ty', ty);
        confetti.style.animationDelay = random(0, 0.5) + 's';
        confetti.classList.add('animate-confetti');
        confettiContainer.appendChild(confetti);
      }
    }

    // SIXER Animation.
    function startSixerAnimation() {
      const ball = document.getElementById('ball');
      const text = document.getElementById('animText');
      ball.classList.remove('animate-ball');
      text.classList.remove('animate-text');
      void ball.offsetWidth;
      void text.offsetWidth;
      text.textContent = "SIXER!";
      text.style.color = "#ffeb3b";
      ball.classList.add('animate-ball');
      ball.addEventListener('animationend', function ballHandler() {
        text.classList.add('animate-text');
        ball.removeEventListener('animationend', ballHandler);
        setTimeout(function() {
          text.classList.remove('animate-text');
          ball.classList.remove('animate-ball');
        }, 3000);
      });
      createConfetti();
    }

    // FOUR Animation.
    function startFourAnimation() {
      const ball = document.getElementById('ball');
      const text = document.getElementById('animText');
      ball.classList.remove('animate-ball');
      text.classList.remove('animate-text');
      void ball.offsetWidth;
      void text.offsetWidth;
      text.textContent = "FOUR!";
      text.style.color = "#ffeb3b";
      ball.classList.add('animate-ball');
      ball.addEventListener('animationend', function ballHandler() {
        text.classList.add('animate-text');
        ball.removeEventListener('animationend', ballHandler);
        setTimeout(function() {
          text.classList.remove('animate-text');
          ball.classList.remove('animate-ball');
          text.textContent = "SIXER!";
        }, 3000);
      });
      createConfetti();
    }

    // WICKET Animation.
    function startWicketAnimation() {
      const ball = document.getElementById('ball');
      const text = document.getElementById('animText');
      ball.classList.remove('animate-ball');
      text.classList.remove('animate-text');
      void ball.offsetWidth;
      void text.offsetWidth;
      text.textContent = "WICKET!";
      text.style.color = "red";
      ball.classList.add('animate-ball');
      ball.addEventListener('animationend', function ballHandler() {
        text.classList.add('animate-text');
        ball.removeEventListener('animationend', ballHandler);
        setTimeout(function() {
          text.classList.remove('animate-text');
          ball.classList.remove('animate-ball');
          text.textContent = "SIXER!";
          text.style.color = "#ffeb3b";
        }, 3000);
      });
      createConfetti();
    }
  </script>
  <!-- ***************** End Animation Code ***************** -->

  <!-- ***************** Begin Animation CSS - DO NOT CHANGE ANY OTHER SINGLE THING ***************** -->
  <style>
    /* Animation container added without altering existing CSS */
    #animationContainer {
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100vh;
      pointer-events: none;
      z-index: 300;
    }
    /* Animation elements */
    #ball {
      position: absolute;
      width: 40px;
      height: 40px;
      background: radial-gradient(circle at 30% 30%, #ff5a5a, #a80000);
      border: 2px solid #800000;
      border-radius: 50%;
      box-shadow: inset -2px -2px 8px rgba(255,255,255,0.3), 2px 2px 8px rgba(0,0,0,0.5);
      left: 10%;
      top: 70%;
      z-index: 310;
    }
    #animText {
      position: absolute;
      top: 0;
      left: 50%;
      transform: translate(-50%, 0);
      font-size: 96px;
      margin-top: 3in;
      font-weight: bold;
      color: #ffeb3b;
      filter: drop-shadow(0 0 20px #ffeb3b);
      opacity: 0;
      z-index: 320;
      pointer-events: none;
    }
    #confettiContainer {
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      pointer-events: none;
      z-index: 315;
    }
    .confetti {
      position: absolute;
      width: 8px;
      height: 12px;
      opacity: 0.9;
      background-color: #ffeb3b;
      transform: rotate(0deg);
    }
    @keyframes ballFlight {
      0% { left: 10%; top: 70%; transform: scale(1) rotate(0deg); }
      40% { left: 40%; top: 30%; transform: scale(1.1) rotate(20deg); }
      60% { left: 60%; top: 20%; transform: scale(1.2) rotate(10deg); }
      100% { left: 90%; top: 70%; transform: scale(1) rotate(-10deg); }
    }
    @keyframes textAppear {
      0% { opacity: 0; transform: translate(-50%, 0) scale(0.5); }
      40% { opacity: 1; transform: translate(-50%, 0) scale(1.2); }
      100% { opacity: 0; transform: translate(-50%, 0) scale(1); }
    }
    @keyframes confettiFall {
      0% { opacity: 1; transform: translate(0, 0) rotate(0deg); }
      100% { opacity: 0; transform: translate(var(--tx), var(--ty)) rotate(720deg); }
    }
    .animate-ball { animation: ballFlight 1.8s cubic-bezier(0.68, -0.55, 0.265, 1.55) forwards; }
    .animate-text { animation: textAppear 1.8s ease-out forwards; }
    .animate-confetti { animation: confettiFall 1.8s ease-out forwards; }
  </style>
  <!-- ***************** End Animation CSS ***************** -->
</head>
<body>
  <!-- Animation container (added new markup without altering existing code) -->
  <div id="animationContainer">
    <div id="ball"></div>
    <div id="animText">SIXER!</div>
    <div id="confettiContainer"></div>
  </div>

  <div id="logo">
    <img src="images/logo.png" alt="Cricket Software">
  </div>

  <div class="panel">
    <div class="p-left">
      <img src="images/pakistan_flag.png" alt="Left Flag">
    </div>

    <div class="p-mid">
      <div class="players">
        <p>Babar Azam</p>
        <p>Sagar Habib</p>
      </div>

      <div class="run">
        <p><b>37 <small>25</small></b></p>
        <p><b>40 <small>26</small></b></p>
      </div>

      <div class="ining">
        <p>
          PAK v <span id="opponent">IND</span> 
          <span id="run-wicket">18-0</span>
          <span id="p2">P2</span> 
          <span id="over">16</span>
          <span id="match">Match 14 - THE OVAL</span>
        </p>
      </div>

      <div class="balls">
        <p class="bowler">AXAR</p>
        <div class="move">
          <!-- Ball-by-ball circles will be updated here dynamically -->
          <div class="circle"></div>
          <div class="circle"></div>
          <div class="circle"></div>
          <div class="circle"></div>
          <div class="circle"></div>
          <div class="circle"></div>
        </div>
      </div>
    </div>

    <div class="p-right">
      <img src="images/indian_flag.png" alt="Right Flag">
    </div>
  </div>
  <!--<div class="marquee-container">
      <div class="marquee-text">🔥 Thank you for watching! To purchase this software, contact +92 335 3938699. 🔥</div>
  </div>-->
</body>
</html>
