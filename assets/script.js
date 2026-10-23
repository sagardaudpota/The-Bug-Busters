function random(min, max) {
    return Math.random() * (max - min) + min;
}

function createConfetti() {
    const confettiContainer = document.getElementById('confettiContainer');
    if (!confettiContainer) return;
    confettiContainer.innerHTML = '';
    const colors = ['#FFEB3B', '#F44336', '#4CAF50', '#2196F3', '#FF9800', '#9C27B0'];
    for (let i = 0; i < 60; i++) {
        const confetti = document.createElement('div');
        confetti.classList.add('confetti');
        confetti.style.backgroundColor = colors[Math.floor(random(0, colors.length))];
        confetti.style.left = random(0, 100) + '%';
        confetti.style.top = random(0, 100) + '%';
        confetti.style.setProperty('--tx', random(-200, 200) + 'px');
        confetti.style.setProperty('--ty', random(150, 350) + 'px');
        confetti.style.animationDelay = random(0, 0.5) + 's';
        confetti.classList.add('animate-confetti');
        confettiContainer.appendChild(confetti);
    }
}

function triggerAnimation(type) {
    const ball = document.getElementById('ball');
    const text = document.getElementById('animText') || document.getElementById('sixerText');
    if (!ball || !text) return;

    ball.classList.remove('animate-ball');
    text.classList.remove('animate-text');
    void ball.offsetWidth;
    void text.offsetWidth;

    if (type === 'six') {
        text.textContent = "SIXER!";
        text.style.color = "#ffeb3b";
    } else if (type === 'four') {
        text.textContent = "FOUR!";
        text.style.color = "#ffeb3b";
    } else if (type === 'wicket') {
        text.textContent = "WICKET!";
        text.style.color = "red";
    }

    ball.classList.add('animate-ball');
    ball.addEventListener('animationend', function ballHandler() {
        text.classList.add('animate-text');
        ball.removeEventListener('animationend', ballHandler);
        setTimeout(function () {
            text.classList.remove('animate-text');
            ball.classList.remove('animate-ball');
            text.textContent = "SIXER!";
            text.style.color = "#ffeb3b";
        }, 3000);
    }, { once: true });

    createConfetti();
}

// Testing Hotkeys (Press 1, 2, or 3 on your keyboard)
document.addEventListener('keydown', function (event) {
    if (event.key === '1') triggerAnimation('six');
    if (event.key === '2') triggerAnimation('four');
    if (event.key === '3') triggerAnimation('wicket');
});