/* =========================================================
   SCORE PANEL — shared script (needs GSAP loaded first)
   Public API:  Score.set({...})  Score.ball('6'|'4'|'W'|'1'|'0'|'wd')
                Score.say('ticker text')  Score.fx('six'|'four'|'wicket')
   Hotkeys:  1 six   2 four   3 wicket   4 next ticker message
             5 add dot ball  6 reset over
   URL params:  ?ticker=Your text here  &opp=IND
   ========================================================= */
(function () {
  const $ = (s, r = document) => r.querySelector(s);
  const stage = $('#stage');
  const mode = document.body.dataset.mode || 'live'; // 'live' | 'need'

  /* ---------- Fit stage to any viewport / OBS source size ---------- */
  function fit() {
    const k = Math.min(innerWidth / 1920, innerHeight / 1080);
    stage.style.transform = 'scale(' + k + ')';
  }
  addEventListener('resize', fit); fit();

  /* ---------- State ---------- */
  const state = {
    teamA: 'PAK', teamB: 'IND',
    runs: 12, wickets: 0, overs: 0, balls: 2, phase: 'P2', venue: 'Marhaba Stadium',
    striker: { name: 'Striker', r: 12, b: 2 },
    non: { name: 'Non-Striker', r: 0, b: 0 },
    bowler: { name: 'Shafi', fig: '0-12' },
    over: ['6', '6'],
    need: 149, needBalls: 46, rrr: null,
    ticker: [
      ['PLACEHOLDER', 'Your live message goes here — sponsor, toss update or match note'],
      ['TOSS', 'Pakistan won the toss and elected to bat first'],
      ['PARTNERSHIP', 'Placeholder text — edit in script.js or use Score.say()'],
    ],
    tickIdx: 0
  };
  const q = new URLSearchParams(location.search);
  if (q.get('opp')) state.teamB = q.get('opp').toUpperCase();
  if (q.get('ticker')) state.ticker.unshift(['INFO', q.get('ticker')]);

  /* ---------- Render ---------- */
  function dotClass(v) {
    if (v === '6') return 'six';
    if (v === '4') return 'four';
    if (v === 'W') return 'wicket';
    if (/wd|nb|lb|b/i.test(v) && v.length > 1) return 'extra';
    return v === '0' || v === '' ? '' : 'run';
  }
  function render() {
    set('#opponent', state.teamB);
    set('#opponent2', state.teamB);
    set('#teamA', state.teamA);
    set('#run-wicket', state.runs + '-' + state.wickets);
    set('#p2', state.phase);
    $('#over').innerHTML = state.overs + '.' + state.balls + '<small>OV</small>';
    set('#match', state.venue);
    set('#sName', state.striker.name); set('#sRuns', state.striker.r); set('#sBalls', state.striker.b);
    set('#nName', state.non.name);     set('#nRuns', state.non.r);     set('#nBalls', state.non.b);

    if (mode === 'live') {
      set('#bowlerName', state.bowler.name);
      set('#bowlerFig', state.bowler.fig);
    } else {
      set('#needRuns', state.need);
      set('#needBalls', state.needBalls);
      const rrr = state.needBalls ? (state.need / state.needBalls * 6).toFixed(2) : '—';
      set('#rrr', rrr);
    }
    const wrap = $('#overBalls');
    if (wrap) {
      wrap.innerHTML = '';
      for (let i = 0; i < 6; i++) {
        const v = state.over[i];
        const d = document.createElement('div');
        d.className = 'dot ' + (v ? dotClass(v) : '');
        d.textContent = v && v !== '0' ? v : (v === '0' ? '•' : '');
        wrap.appendChild(d);
      }
    }
  }
  function set(sel, val) { const e = $(sel); if (e) e.textContent = val; }

  /* ---------- Ticker bar (placeholder text above scorecard) ---------- */
  const tText = $('#tickerText');
  function showTicker(animate) {
    if (!tText) return;
    const [tag, msg] = state.ticker[state.tickIdx % state.ticker.length];
    tText.innerHTML = '<b>' + tag + '</b>' + msg;
    const view = tText.parentElement.clientWidth;
    const w = tText.scrollWidth + 44;
    gsap.killTweensOf(tText);
    gsap.set(tText, { x: 0, opacity: 1 });
    if (animate) gsap.from(tText, { y: 28, opacity: 0, duration: .6, ease: 'power3.out' });
    if (w > view) { // long text → slow marquee
      gsap.to(tText, { x: -(w - view + 30), duration: (w - view) / 70 + 1, delay: 1.2, ease: 'none' });
    }
  }
  function nextTicker() {
    if (!tText) return;
    gsap.to(tText, { y: -28, opacity: 0, duration: .4, ease: 'power2.in', onComplete: () => {
      state.tickIdx++; gsap.set(tText, { y: 0 }); showTicker(true);
    }});
  }
  let tickTimer = null;
  function autoTicker() { clearInterval(tickTimer); tickTimer = setInterval(nextTicker, 7000); }

  /* ---------- Effects ---------- */
  const fx = $('#fx'), ball = $('#ball'), shadow = $('#shadow'),
        txt = $('#animText'), sub = $('#sub'), flash = $('#flash');
  let tl = null;

  function setText(word, color, glow) {
    txt.innerHTML = word.split('').map(c => '<span>' + (c === ' ' ? '&nbsp;' : c) + '</span>').join('');
    txt.style.color = color;
    txt.style.textShadow = '0 0 50px ' + glow + ', 0 10px 0 rgba(0,0,0,.35)';
  }
  function reset() {
    if (tl) tl.kill();
    fx.querySelectorAll('.confetti,.ring,.stump,.bail').forEach(n => n.remove());
    gsap.set([ball, shadow, txt, sub, flash], { clearProps: 'all' });
    gsap.set([ball, shadow, txt, sub, flash], { opacity: 0 });
    document.body.classList.remove('wicket-mode');
  }
  function burst(n, colors, cx, cy, spread, grav) {
    for (let i = 0; i < n; i++) {
      const c = document.createElement('div');
      c.className = 'confetti';
      c.style.background = colors[i % colors.length];
      fx.appendChild(c);
      const a = Math.random() * Math.PI * 2, d = gsap.utils.random(120, spread);
      gsap.fromTo(c, { x: cx, y: cy, opacity: 1, rotation: 0, scale: gsap.utils.random(.7, 1.3) },
        { x: cx + Math.cos(a) * d, y: cy + Math.sin(a) * d * .7 - 80, rotation: gsap.utils.random(-540, 540),
          duration: .7, ease: 'power2.out',
          onComplete() {
            gsap.to(c, { y: '+=' + grav, x: '+=' + gsap.utils.random(-60, 60), opacity: 0,
              rotation: '+=360', duration: gsap.utils.random(1.2, 2), ease: 'power1.in', onComplete: () => c.remove() });
          } });
    }
  }
  function ring(cx, cy, color) {
    const r = document.createElement('div');
    r.className = 'ring'; r.style.borderColor = color; fx.appendChild(r);
    gsap.fromTo(r, { x: cx - 20, y: cy - 20, width: 40, height: 40, opacity: .9 },
      { x: cx - 360, y: cy - 360, width: 720, height: 720, opacity: 0, duration: 1.1, ease: 'power2.out', onComplete: () => r.remove() });
  }
  function slam(word, color, glow, subText) {
    setText(word, color, glow);
    sub.textContent = subText || '';
    const letters = txt.querySelectorAll('span');
    const t = gsap.timeline();
    t.set(txt, { opacity: 1 })
     .fromTo(letters, { y: -260, scale: 2.4, opacity: 0, rotation: () => gsap.utils.random(-25, 25) },
        { y: 0, scale: 1, opacity: 1, rotation: 0, duration: .55, ease: 'back.out(2.2)', stagger: .06 })
     .fromTo(sub, { opacity: 0, y: 20, letterSpacing: '30px' }, { opacity: 1, y: 0, letterSpacing: '12px', duration: .6, ease: 'power3.out' }, '-=.3')
     .to(txt, { scale: 1.06, duration: .12, yoyo: true, repeat: 1 }, '-=.1')
     .to([txt, sub], { opacity: 0, y: -40, duration: .5, ease: 'power2.in' }, '+=1.6');
    return t;
  }

  function six() {
    reset();
    const W = 1920;
    tl = gsap.timeline({ onComplete: reset });
    // ball arcs high over the stage
    tl.set(ball, { x: 260, y: 880, opacity: 1, scale: 1 })
      .set(shadow, { x: 250, y: 930, opacity: .6 })
      .to(ball, { x: W - 300, duration: 1.5, ease: 'none' }, 0)
      .to(ball, { y: 120, duration: .75, ease: 'power2.out' }, 0)
      .to(ball, { y: 760, duration: .75, ease: 'power2.in' }, .75)
      .to(ball, { scale: 2.3, duration: .75, ease: 'power2.out' }, 0)
      .to(ball, { scale: .8, duration: .75, ease: 'power2.in' }, .75)
      .to(ball, { rotation: 900, duration: 1.5, ease: 'none' }, 0)
      .to(shadow, { x: W - 310, duration: 1.5, ease: 'none' }, 0)
      .to(ball, { opacity: 0, duration: .15 }, 1.5)
      .to(flash, { opacity: .55, duration: .08, yoyo: true, repeat: 1 }, 1.05)
      .add(() => {
        burst(90, ['#ffd23f', '#e31857', '#2f8fe0', '#19c37d', '#ffffff'], 960, 460, 700, 520);
        ring(960, 500, '#ffd23f');
      }, 1.05)
      .add(slam('SIXER!', '#ffd23f', 'rgba(255,210,63,.8)', 'Maximum'), 1.05)
      .to('.board', { scale: 1.025, transformOrigin: '50% 100%', duration: .15, yoyo: true, repeat: 1 }, 1.1);
  }

  function four() {
    reset();
    tl = gsap.timeline({ onComplete: reset });
    tl.set(ball, { x: 220, y: 920, opacity: 1, scale: .9 })
      .to(ball, { x: 1700, duration: 1.05, ease: 'power1.out' }, 0)
      .to(ball, { rotation: 1100, duration: 1.05, ease: 'none' }, 0)
      .to(ball, { y: 900, duration: .17, yoyo: true, repeat: 5, ease: 'sine.inOut' }, 0)
      .to(ball, { opacity: 0, duration: .12 }, 1.05)
      .add(() => {
        ring(1700, 930, '#2f8fe0');
        burst(55, ['#2f8fe0', '#ffffff', '#8fd0ff'], 1700, 930, 520, 380);
      }, 1.0)
      .add(slam('FOUR!', '#7fc4ff', 'rgba(47,143,224,.85)', 'Boundary'), 1.0)
      .to('.board', { y: -6, duration: .12, yoyo: true, repeat: 1 }, 1.05);
  }

  function wicket() {
    reset();
    document.body.classList.add('wicket-mode');
    const sx = 960, sy = 640;
    const stumps = [];
    [-36, 0, 36].forEach(dx => {
      const s = document.createElement('div'); s.className = 'stump';
      fx.appendChild(s); stumps.push([s, dx]);
      gsap.set(s, { x: sx + dx, y: sy, opacity: 0 });
    });
    const bails = [-18, 18].map(dx => {
      const b = document.createElement('div'); b.className = 'bail';
      fx.appendChild(b); gsap.set(b, { x: sx + dx - 19, y: sy - 8, opacity: 0 }); return [b, dx];
    });
    tl = gsap.timeline({ onComplete: reset });
    tl.to(flash, { opacity: .5, background: '#e31857', duration: .1, yoyo: true, repeat: 1 }, 0)
      .to(stumps.map(s => s[0]), { opacity: 1, duration: .15 }, 0)
      .to(bails.map(b => b[0]), { opacity: 1, duration: .15 }, 0)
      .set(ball, { x: 260, y: 760, opacity: 1, scale: 1 }, 0)
      .to(ball, { x: sx - 8, y: sy + 60, duration: .55, ease: 'power2.in', rotation: 720 }, 0)
      .add(() => {
        stumps.forEach(([s, dx], i) => gsap.to(s, { x: sx + dx + (i - 1) * 140, y: '+=' + gsap.utils.random(120, 220),
          rotation: gsap.utils.random(-160, 160), opacity: 0, duration: 1.1, ease: 'power2.out' }));
        bails.forEach(([b, dx]) => gsap.to(b, { x: sx + dx * 9, y: sy - 260, rotation: gsap.utils.random(-540, 540),
          opacity: 0, duration: 1.2, ease: 'power2.out' }));
        burst(40, ['#e31857', '#ff6b8f', '#ffffff'], sx, sy, 420, 300);
      }, .55)
      .to(ball, { x: sx + 420, y: sy + 250, opacity: 0, duration: .6, ease: 'power2.out' }, .55)
      .add(slam('WICKET!', '#ff3d6b', 'rgba(227,24,87,.85)', 'Dismissal'), .6);
  }

  const fxMap = { six, four, wicket };
  function runFx(type) { (fxMap[type] || six)(); }

  /* ---------- Scoring helpers ---------- */
  function addBall(v) {
    v = String(v);
    if (state.over.length >= 6) state.over = [];
    state.over.push(v);
    const n = parseInt(v, 10);
    if (v === 'W') state.wickets++;
    else if (!isNaN(n)) {
      state.runs += n; state.striker.r += n; state.striker.b++;
      if (mode === 'need') { state.need = Math.max(0, state.need - n); }
    }
    if (mode === 'need') state.needBalls = Math.max(0, state.needBalls - 1);
    state.balls = state.over.length % 6; if (state.balls === 0 && state.over.length === 6) { state.overs++; }
    render();
    if ($('#overBalls')) gsap.fromTo('#overBalls .dot:nth-child(' + state.over.length + ')', { scale: 0 }, { scale: 1, duration: .5, ease: 'back.out(3)' });
    gsap.fromTo('#run-wicket', { scale: 1.25 }, { scale: 1, duration: .45, ease: 'back.out(3)' });
    if (v === '6') runFx('six'); else if (v === '4') runFx('four'); else if (v === 'W') runFx('wicket');
  }

  /* ---------- Public API ---------- */
  window.Score = {
    state,
    set(patch) { Object.assign(state, patch); render(); },
    ball: addBall,
    say(msg, tag) { state.ticker.unshift([tag || 'UPDATE', msg]); state.tickIdx = 0; showTicker(true); autoTicker(); },
    fx: runFx
  };

  /* ---------- Hotkeys ---------- */
  addEventListener('keydown', e => {
    if (e.key === '1') addBall('6');
    if (e.key === '2') addBall('4');
    if (e.key === '3') addBall('W');
    if (e.key === '4') nextTicker();
    if (e.key === '5') addBall('0');
    if (e.key === '6') { state.over = []; state.balls = 0; render(); }
  });

  /* ---------- Intro ---------- */
  render();
  showTicker(false);
  autoTicker();
  gsap.set([ball, shadow, txt, sub, flash], { opacity: 0 });
  const intro = gsap.timeline({ defaults: { ease: 'power4.out' } });
  intro.from('#logo', { x: -60, opacity: 0, duration: .8 })
       .from('.ticker', { y: 40, opacity: 0, scaleX: .85, duration: .8 }, .1)
       .from('.flag.left', { xPercent: -120, duration: .9 }, .2)
       .from('.flag.right', { xPercent: 120, duration: .9 }, .2)
       .from('.core', { scaleX: .2, opacity: 0, duration: .9, transformOrigin: '50% 50%' }, .35)
       .from('.bat, .live > *, .score > *', { y: 16, opacity: 0, stagger: .06, duration: .5 }, .8);
})();
