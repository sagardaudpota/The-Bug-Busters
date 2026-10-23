/* =========================================================
   SCORE PANEL — shared script (needs GSAP loaded first)
   ---------------------------------------------------------
   PUBLIC API (window.Score)
     Score.set({...})              patch the state and re-render
     Score.ball('0'|'1'..'6'|'W')  add a ball (6 / 4 / W play fx)
     Score.say('ticker text')      push a ticker message
     Score.wide()  Score.noBall()  Score.legBye()  Score.deadBall()
     Score.out('bowled'|'caught'|'lbw'|'runout'|'stumped', {duck:'golden'|'duck', sub:'c X b Y'})
     Score.review('out'|'notout')  Score.hatTrick()
     Score.milestone(50|100|150|200, {name, balls})
     Score.maiden()  Score.powerplay(true|false)  Score.overSummary()

   KEYBOARD (press "/" on screen to see this legend)
     1 six   2 four   3 wicket   4 next ticker   5 dot ball   6 clear over
     W wide  N no ball (+free hit)  L leg bye  D dead ball
     B bowled  C caught  I lbw  R run out  S stumped
     G golden duck  U duck  V review→out  X review→not out  H hat-trick
     F fifty  T century  J double century  M maiden  P powerplay  O over summary
   URL params:  ?ticker=Your text here  &opp=IND
   ========================================================= */
(function () {
  gsap.config({ force3D: false });      // avoid blurry GPU layers
  const $ = (s, r = document) => r.querySelector(s);
  const stage = $('#stage');
  const mode = document.body.dataset.mode || 'live'; // 'live' | 'need'

  /* ---------- Fit stage to viewport, centred ---------- */
  function fit() {
    const k = Math.min(innerWidth / 1920, innerHeight / 1080);
    const x = Math.round((innerWidth - 1920 * k) / 2), y = Math.round((innerHeight - 1080 * k) / 2);
    stage.style.zoom = k;                 // real layout scaling = crisp text
    stage.style.left = (x / k) + 'px';    // offsets are in zoomed units
    stage.style.top = (y / k) + 'px';
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
    need: 149, needBalls: 46,
    freeHit: false, pp: false,
    ticker: [
      ['PLACEHOLDER', 'Your live message goes here — sponsor, toss update or match note'],
      ['TOSS', 'Pakistan won the toss and elected to bat first'],
      ['PARTNERSHIP', 'Placeholder text — edit in script.js or use Score.say()'],
    ],
    tickIdx: 0
  };
  let lastMatchId = null, lastEventSeq = null;
  let aiWasVisible = false, lastAiSignature = '';
  const q = new URLSearchParams(location.search);
  if (q.get('opp')) state.teamB = q.get('opp').toUpperCase();
  if (q.get('ticker')) state.ticker.unshift(['INFO', q.get('ticker')]);

  const isExtra = v => v === 'wd' || v === 'nb';
  const legalCount = arr => arr.filter(x => !isExtra(x)).length;

  /* ---------- Render ---------- */
  function dotClass(v) {
    if (v === '6') return 'six';
    if (v === '4') return 'four';
    if (v === 'W') return 'wicket';
    if (v.length > 1) return 'extra';
    return v === '0' || v === '' ? '' : 'run';
  }
  function set(sel, val) { const e = $(sel); if (e) e.textContent = val; }
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
      set('#rrr', state.needBalls ? (state.need / state.needBalls * 6).toFixed(2) : '—');
    }
    const wrap = $('#overBalls');
    if (wrap) {
      wrap.innerHTML = '';
      const n = Math.max(6, Math.min(state.over.length, 7));
      const shown = state.over.slice(-n);
      for (let i = 0; i < n; i++) {
        const v = shown[i];
        const d = document.createElement('div');
        d.className = 'dot ' + (v ? dotClass(v) : '');
        d.textContent = v && v !== '0' ? v : (v === '0' ? '•' : '');
        wrap.appendChild(d);
      }
    }
  }

  // The scorer panel is the source of truth. Polling updates the display in place;
  // only a newly committed scoring event starts an effect, so reconnects never replay it.
  function syncFromAdmin(d) {
    const matchChanged = lastMatchId !== d.match_id;
    const incomingSeq = Number(d.event_seq || 0);
    const newEvent = !matchChanged && lastEventSeq !== null && incomingSeq > lastEventSeq;
    const oldOverLength = state.over.length;
    const hist = Array.isArray(d.ball_history) ? d.ball_history.map(x => String(x)) : [];
    const ov = Number(d.over || 0), whole = Math.floor(ov);
    Object.assign(state, {
      teamA: d.team || '', teamB: d.opponent || '', runs: Number(d.team_runs || 0),
      wickets: Number(d.team_wickets || 0), overs: whole, balls: Math.round((ov - whole) * 10),
      venue: d.match_info || '', striker: { name: clean(d.striker), r: Number(d.striker_runs || 0), b: Number(d.striker_balls || 0) },
      non: { name: clean(d.non_striker), r: Number(d.non_striker_runs || 0), b: Number(d.non_striker_balls || 0) },
      bowler: { name: d.bowler || '', fig: '' }, over: hist,
      need: Math.max(0, Number(d.target || 0) - Number(d.team_runs || 0)),
      needBalls: Math.max(0, Number(d.max_overs || 0) * 6 - (whole * 6 + Math.round((ov - whole) * 10)))
    });
    const imgPath = p => p ? (String(p).startsWith('http') || String(p).startsWith('/') ? p : '../admin/' + p) : '';
    [[ $('#logo img'), d.logo, 'assets/images/logo.png' ],
     [ $('.flag.left img'), d.left_flag, 'assets/images/pakistan_flag.png' ],
     [ $('.flag.right img'), d.right_flag, 'assets/images/indian_flag.png' ]].forEach(([el, value, fallback]) => {
       if (!el || !value) return;
       const src = imgPath(value);
       if (el.getAttribute('src') !== src) {
         el.onerror = () => { el.onerror = null; el.src = fallback; };
         el.src = src;
       }
     });
    render();
    syncAIAnalytics(d, matchChanged);
    if (matchChanged) { lastMatchId = d.match_id; lastEventSeq = incomingSeq; }
    else if (newEvent) {
      const feed = Array.isArray(d.event_feed) ? d.event_feed.filter(item => Number(item.seq) > lastEventSeq) : [];
      if (feed.length) feed.forEach(item => queueLiveEvent(item.event, item.effects || [], hist, oldOverLength));
      else queueLiveEvent(d.last_event, d.last_effects || [], hist, oldOverLength);
      lastEventSeq = incomingSeq;
    }
  }

  function aiText(value, limit) {
    let text = String(value || '').replace(/[`*_>#]/g, '').replace(/\s+/g, ' ').trim();
    if (/^(as an ai|here is|here are|certainly[,!]|based on the provided data[,!:])/i.test(text)) return '';
    if (text.length > limit) text = text.slice(0, limit - 1).replace(/\s+\S*$/, '') + '…';
    return text;
  }
  function syncAIAnalytics(matchData, matchChanged) {
    const panel = $('#aiInsight');
    if (!panel) return;
    const report = matchData.ai_analysis;
    const shouldShow = matchData.ai_analysis_visible === true && report && report.match_id === matchData.match_id;
    if (!shouldShow) {
      if (aiWasVisible) {
        panel.setAttribute('aria-hidden', 'true');
        gsap.killTweensOf(panel);
        gsap.killTweensOf('.ai-orb i');
        gsap.to(panel, { autoAlpha: 0, x: 42, scale: .96, duration: .42, ease: 'power2.in' });
      }
      aiWasVisible = false;
      lastAiSignature = '';
      return;
    }

    const signature = String(matchData.match_id) + ':' + JSON.stringify(report);
    if (signature !== lastAiSignature || matchChanged) {
      const candidates = [];
      const chase = report.chase_analysis;
      if (chase && chase.selected) candidates.push({ tag: 'CHASE ANALYSIS', title: 'CHASE READ', summary: aiText(chase.assessment || chase.summary, 165), points: [
        aiText([chase.current_run_rate && ('Current rate ' + chase.current_run_rate), chase.required_run_rate && ('Required ' + chase.required_run_rate)].filter(Boolean).join(' · '), 110)
      ] });
      const players = report.player_insights;
      if (players && players.selected) candidates.push({ tag: 'PLAYER INSIGHT', title: 'PLAYER WATCH', summary: aiText(players.summary, 165), points: (players.items || []).slice(0, 2).map(item => aiText([item.title, item.detail].filter(Boolean).join(' · '), 110)) });
      const turning = report.turning_points;
      if (turning && turning.selected) candidates.push({ tag: 'TURNING POINTS', title: 'MATCH MOMENTUM', summary: aiText(turning.summary, 165), points: (turning.items || []).slice(0, 2).map(item => aiText([item.over, item.event, item.impact].filter(Boolean).join(' · '), 110)) });
      const post = report.post_match_report;
      if (post && post.selected) candidates.push({ tag: 'MATCH REPORT', title: aiText(post.headline, 48) || 'MATCH SUMMARY', summary: aiText(post.summary, 165), points: (post.key_takeaways || []).slice(0, 2).map(item => aiText(item, 110)) });
      const focus = candidates.find(item => item.summary || item.points.some(Boolean));
      if (!focus) {
        matchData.ai_analysis_visible = false;
        panel.setAttribute('aria-hidden', 'true');
        gsap.set(panel, { autoAlpha: 0, x: 42, scale: .96 });
        aiWasVisible = false;
        return;
      }
      set('#aiInsightTag', focus.tag);
      set('#aiInsightTitle', focus.title);
      set('#aiInsightSummary', focus.summary);
      set('#aiInsightTeams', [matchData.team, matchData.opponent].filter(Boolean).join('  v  '));
      const points = candidates.flatMap(item => item.points.filter(Boolean).map(point => item === focus ? point : item.tag + ' · ' + point)).slice(0, 3);
      const list = $('#aiInsightPoints');
      list.replaceChildren();
      points.forEach(point => { const li = document.createElement('li'); li.textContent = point; list.appendChild(li); });
      lastAiSignature = signature;
    }

    panel.setAttribute('aria-hidden', 'false');
    if (!aiWasVisible) {
      gsap.killTweensOf(panel);
      gsap.fromTo(panel, { autoAlpha: 0, x: 64, scale: .92 }, { autoAlpha: 1, x: 0, scale: 1, duration: .78, ease: 'back.out(1.25)' });
      gsap.fromTo(['#aiInsightTag', '#aiInsightTitle', '#aiInsightSummary', '#aiInsightPoints li'], { y: 16, opacity: 0 }, { y: 0, opacity: 1, duration: .48, stagger: .075, delay: .16, ease: 'power3.out' });
      gsap.fromTo('.ai-orb', { scale: .7, rotation: -25 }, { scale: 1, rotation: 0, duration: .8, ease: 'elastic.out(1,.45)', delay: .18 });
      gsap.to('.ai-orb i', { scale: 1.25, opacity: .7, duration: .95, repeat: -1, yoyo: true, ease: 'sine.inOut', delay: .8 });
    }
    aiWasVisible = true;
  }

  const adminFxQueue = [];
  let adminFxBusy = false;
  function queueLiveEvent(raw, effects, history, oldOverLength) {
    if (raw === 'sync') return;
    const wicketEffects = String(raw || '').startsWith('wicket:') && effects.length > 0;
    if (!wicketEffects) adminFxQueue.push({ type: 'score_event', event: raw, history, oldOverLength });
    effects.forEach(effect => adminFxQueue.push(effect));
    playNextAdminFx();
  }
  function playNextAdminFx() {
    if (adminFxBusy || !adminFxQueue.length) return;
    adminFxBusy = true;
    const effect = adminFxQueue.shift(), previousTimeline = tl;
    if (effect.type === 'score_event') playScoringEvent(effect.event, effect.history, effect.oldOverLength);
    else if (effect.type === 'review') review(effect.result);
    else if (effect.type === 'dismissal') dismiss(effect.kind, { duck: effect.duck || '', sub: effect.sub || '' });
    else if (effect.type === 'milestone') milestone(Number(effect.runs), effect);
    else if (effect.type === 'hat_trick') hatTrick(effect.bowler);
    const activeTimeline = tl !== previousTimeline ? tl : null;
    const duration = activeTimeline && activeTimeline.duration ? activeTimeline.duration() : .65;
    setTimeout(() => { adminFxBusy = false; playNextAdminFx(); }, Math.max(250, duration * 1000 + 180));
  }

  function playScoringEvent(rawValue, history, oldOverLength) {
    const raw = String(rawValue || ''), ev = raw.split(':').pop().split('+')[0];
    if (ev === '6') six(); else if (ev === '4') four(); else if (raw.startsWith('wicket:')) dismiss('bowled');
    else if (ev === 'wd') wide(true); else if (ev === 'nb') noBall(true); else if (ev === 'lbye' || ev === 'bye') legBye(true);
    else if (history.length > oldOverLength) {
      const dot = $('#overBalls .dot:last-child');
      if (dot) gsap.fromTo(dot, { scale: 0 }, { scale: 1, duration: .5, ease: 'back.out(3)' });
      gsap.fromTo('#run-wicket', { scale: 1.25 }, { scale: 1, duration: .45, ease: 'back.out(3)' });
      }
  }

  function clean(v) { return String(v || '').replace(' (out)', ''); }
  async function pollAdminScore() {
    try {
      const r = await fetch('../admin/get_score.php?_=' + Date.now(), { cache: 'no-store' });
      if (!r.ok) throw new Error('Score endpoint unavailable');
      syncFromAdmin(await r.json());
    } catch (e) { /* Keep the last rendered score during brief network interruptions. */ }
  }
  pollAdminScore();
  setInterval(pollAdminScore, 3000);

  /* ---------- Ticker bar ---------- */
  const tText = $('#tickerText');
  function showTicker(animate) {
    if (!tText) return;
    const [tag, msg] = state.ticker[state.tickIdx % state.ticker.length];
    tText.innerHTML = '<b>' + tag + '</b>' + msg;
    const view = tText.parentElement.clientWidth;
    const w = tText.scrollWidth + 44;
    gsap.killTweensOf(tText);
    gsap.set(tText, { x: 0, y: 0, opacity: 1 });
    if (animate) gsap.from(tText, { y: 28, opacity: 0, duration: .6, ease: 'power3.out' });
    if (w > view) {
      gsap.to(tText, { x: -(w - view + 30), duration: (w - view) / 70 + 1, delay: 1.2, ease: 'none' });
    }
  }
  function nextTicker() {
    if (!tText) return;
    gsap.to(tText, { y: -28, opacity: 0, duration: .4, ease: 'power2.in', onComplete: () => {
      state.tickIdx++; showTicker(true);
    }});
  }
  let tickTimer = null;
  function autoTicker() { clearInterval(tickTimer); tickTimer = setInterval(nextTicker, 7000); }

  /* =========================================================
     EFFECTS ENGINE
     ========================================================= */
  const fx = $('#fx'), ball = $('#ball'), shadow = $('#shadow'),
        txt = $('#animText'), sub = $('#sub'), flash = $('#flash');
  let tl = null;
  const TEMP = '.bn,.mile,.drs,.hand,.crease,.pad,.dline,.confetti,.ring,.stump,.bail';

  function reset() {
    if (tl) tl.kill();
    fx.querySelectorAll(TEMP).forEach(n => n.remove());
    gsap.set([ball, shadow, txt, sub, flash], { clearProps: 'all' });
    gsap.set([ball, shadow, txt, sub, flash], { opacity: 0 });
    document.body.classList.remove('wicket-mode');
  }
  function setText(word, color, glow) {
    txt.innerHTML = word.split('').map(c => '<span>' + (c === ' ' ? '&nbsp;' : c) + '</span>').join('');
    txt.style.color = color;
    txt.style.textShadow = '0 0 50px ' + glow + ', 0 10px 0 rgba(0,0,0,.35)';
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
  function glyph(t, x, y, size, color) {
    const g = document.createElement('div');
    g.className = 'hand'; g.textContent = t; g.style.fontSize = size + 'px';
    if (color) g.style.color = color;
    fx.appendChild(g);
    gsap.set(g, { x, y, xPercent: -50, yPercent: -50, opacity: 0 });
    return g;
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

  /* Reusable lower-third banner */
  function banner(o) {
    const el = document.createElement('div');
    el.className = 'bn';
    el.style.setProperty('--c', o.color || '#ffd23f');
    el.innerHTML =
      '<div class="bn-tag">' + (o.tag || '') + '</div>' +
      '<div class="bn-main">' + (o.icon ? '<span class="bn-ico">' + o.icon + '</span>' : '') +
      '<span class="bn-title">' + o.title + '</span></div>' +
      (o.sub ? '<div class="bn-sub">' + o.sub + '</div>' : '');
    fx.appendChild(el);
    gsap.set(el, { xPercent: -50, y: o.y || 640 });
    const t = gsap.timeline();
    if (o.stamp) {
      t.fromTo(el, { opacity: 0, scale: 2.8, rotation: -14 }, { opacity: 1, scale: 1, rotation: -3, duration: .45, ease: 'power4.in' })
       .to(el, { x: '+=6', duration: .05, yoyo: true, repeat: 3 });
    } else {
      t.fromTo(el, { opacity: 0, scaleX: .2, clipPath: 'inset(0 50% 0 50%)' },
        { opacity: 1, scaleX: 1, clipPath: 'inset(0 0% 0 0%)', duration: .5, ease: 'expo.out', clearProps: 'clipPath' })
       .from(el.querySelector('.bn-tag'), { x: -40, opacity: 0, duration: .35, ease: 'power3.out' }, '-=.3')
       .from(el.querySelector('.bn-title'), { y: 30, opacity: 0, duration: .4, ease: 'back.out(2)' }, '-=.3');
      if (o.sub) t.from(el.querySelector('.bn-sub'), { opacity: 0, y: 10, duration: .35 }, '-=.2');
    }
    t.to(el, { opacity: 0, y: '-=30', duration: .45, ease: 'power2.in' }, '+=' + (o.hold || 1.8));
    return t;
  }

  /* ---------- Free hit badge ---------- */
  function showFreeHit() {
    state.freeHit = true;
    if ($('.freehit')) return;
    const el = document.createElement('div');
    el.className = 'freehit'; el.textContent = 'FREE HIT';
    $('.dock').appendChild(el);
    gsap.fromTo(el, { scale: 0, rotation: -12, opacity: 0 }, { scale: 1, rotation: 0, opacity: 1, duration: .6, ease: 'back.out(3)' });
    gsap.to(el, { boxShadow: '0 0 40px 10px rgba(255,210,63,.95)', duration: .6, yoyo: true, repeat: -1, ease: 'sine.inOut' });
  }
  function hideFreeHit() {
    state.freeHit = false;
    const el = $('.freehit'); if (!el) return;
    gsap.killTweensOf(el);
    gsap.to(el, { scale: 0, opacity: 0, duration: .35, ease: 'back.in(2)', onComplete: () => el.remove() });
  }

  /* ---------- SIX ---------- */
  function six() {
    reset();
    const W = 1920;
    tl = gsap.timeline({ onComplete: reset });
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

  /* ---------- FOUR ---------- */
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

  /* ---------- WIDE ---------- */
  function wide(silent) {
    if (!silent) addBall('wd', true);
    reset();
    const g = glyph('↔', 960, 450, 260, '#4aa8ff');
    g.style.textShadow = '0 0 40px rgba(74,168,255,.9)';
    tl = gsap.timeline({ onComplete: reset });
    tl.set(ball, { x: 240, y: 780, opacity: 1, scale: 1 })
      .to(ball, { x: 1580, duration: .9, ease: 'power1.in' }, 0)
      .to(ball, { y: 430, duration: .9, ease: 'power2.out' }, 0)
      .to(ball, { rotation: 1000, scale: 1.6, duration: .9, ease: 'none' }, 0)
      .to(ball, { opacity: 0, duration: .12 }, .85)
      .fromTo(g, { opacity: 0, scaleX: .1, scaleY: 1 }, { opacity: 1, scaleX: 1, duration: .8, ease: 'elastic.out(1,.5)' }, .3)
      .to(g, { opacity: 0, duration: .4 }, 2.2)
      .add(banner({ tag: 'EXTRA', title: 'WIDE BALL', sub: '+1 RUN · BALL TO BE RE-BOWLED', color: '#4aa8ff', icon: '↔' }), .45)
      .to('.board', { x: 8, duration: .06, yoyo: true, repeat: 3 }, .5);
  }

  /* ---------- NO BALL + FREE HIT ---------- */
  function noBall(silent) {
    if (!silent) addBall('nb', true);
    reset();
    const line = document.createElement('div');
    line.className = 'crease'; fx.appendChild(line);
    gsap.set(line, { x: 610, y: 800, scaleX: 0, transformOrigin: '50% 50%' });
    tl = gsap.timeline({ onComplete: reset });
    tl.to(line, { scaleX: 1, duration: .45, ease: 'expo.out' }, 0)
      .set(ball, { x: 300, y: 720, opacity: 1, scale: 1 }, 0)
      .to(ball, { x: 860, y: 790, duration: .5, ease: 'power2.in', rotation: 540 }, 0)
      .to(ball, { opacity: 0, duration: .15 }, .5)
      .to(line, { backgroundColor: '#ff3355', boxShadow: '0 0 40px 10px rgba(255,51,85,.9)', duration: .12, yoyo: true, repeat: 5 }, .5)
      .to(flash, { opacity: .35, backgroundColor: '#ff9d2e', duration: .08, yoyo: true, repeat: 3 }, .5)
      .add(banner({ tag: 'EXTRA · OVERSTEP', title: 'NO BALL', sub: '+1 RUN · FREE HIT NEXT', color: '#ff9d2e', icon: '!', hold: 2.1 }), .55)
      .add(() => showFreeHit(), 1.0)
      .to(line, { opacity: 0, duration: .4 }, 2.4);
  }

  /* ---------- LEG BYE / DEAD BALL ---------- */
  function legBye(silent) {
    if (!silent) addBall('lb', true);
    reset();
    tl = gsap.timeline({ onComplete: reset });
    tl.set(ball, { x: 260, y: 800, opacity: 1 }, 0)
      .to(ball, { x: 1050, y: 860, duration: .55, ease: 'power2.out', rotation: 480 }, 0)
      .to(ball, { x: 1500, y: 930, opacity: 0, duration: .6, ease: 'power1.out' }, .55)
      .add(banner({ tag: 'EXTRA', title: 'LEG BYE', sub: '+1 RUN', color: '#9bb0c9', icon: 'LB', hold: 1.5 }), .35);
  }
  function deadBall() {
    reset();
    tl = gsap.timeline({ onComplete: reset });
    tl.set(ball, { x: 300, y: 760, opacity: 1 }, 0)
      .to(ball, { x: 760, y: 880, duration: .5, ease: 'power2.out' }, 0)
      .to(ball, { x: 790, duration: .6, ease: 'power4.out' }, .5)
      .to(ball, { opacity: 0, duration: .4 }, 1.3)
      .to(flash, { opacity: .25, backgroundColor: '#6a7389', duration: .12, yoyo: true, repeat: 1 }, .45)
      .add(banner({ tag: 'UMPIRE SIGNAL', title: 'DEAD BALL', sub: 'NO BALL COUNTED · NO RUNS', color: '#8a93a6', icon: '✋', hold: 1.7 }), .4);
  }

  /* ---------- DISMISSALS (bowled, caught, lbw, run out, stumped) ---------- */
  function makeStumps(sx, sy) {
    const stumps = [], bails = [];
    [-36, 0, 36].forEach(dx => {
      const s = document.createElement('div'); s.className = 'stump';
      fx.appendChild(s); gsap.set(s, { x: sx + dx, y: sy, opacity: 0 }); stumps.push([s, dx]);
    });
    [-18, 18].forEach(dx => {
      const b = document.createElement('div'); b.className = 'bail';
      fx.appendChild(b); gsap.set(b, { x: sx + dx - 19, y: sy - 8, opacity: 0 }); bails.push([b, dx]);
    });
    return { stumps, bails };
  }
  function breakStumps(S, sx, sy, scatter) {
    S.stumps.forEach(([s, dx], i) => {
      if (scatter) gsap.to(s, { x: sx + dx + (i - 1) * 140, y: '+=' + gsap.utils.random(120, 220),
        rotation: gsap.utils.random(-160, 160), opacity: 0, duration: 1.1, ease: 'power2.out' });
      else gsap.to(s, { opacity: 0, duration: .5, delay: .9 });
    });
    S.bails.forEach(([b, dx]) => gsap.to(b, { x: sx + dx * 9, y: sy - 260, rotation: gsap.utils.random(-540, 540),
      opacity: 0, duration: 1.2, ease: 'power2.out' }));
  }
  const DIS = {
    bowled:  ['BOWLED',   'b Shafi',         '🏏'],
    caught:  ['CAUGHT',   'c Rizwan b Shafi','🧤'],
    lbw:     ['LBW',      'leg before wicket','☝'],
    runout:  ['RUN OUT',  'direct hit',      '⚡'],
    stumped: ['STUMPED',  'st Rizwan b Shafi','🧤']
  };

  function dismiss(kind, o) {
    o = o || {}; kind = DIS[kind] ? kind : 'bowled';
    reset();
    document.body.classList.add('wicket-mode');
    const sx = 960, sy = 640, lab = DIS[kind];
    const T = gsap.timeline({ onComplete: reset }); tl = T;
    let at = .6;
    T.to(flash, { opacity: .5, backgroundColor: '#e31857', duration: .1, yoyo: true, repeat: 1 }, 0);

    if (kind === 'caught') {
      at = 1.2;
      T.set(ball, { x: 300, y: 820, opacity: 1, scale: 1 }, 0)
       .to(ball, { x: 1350, duration: 1.15, ease: 'none', rotation: 900 }, 0)
       .to(ball, { y: 230, duration: .58, ease: 'power2.out' }, 0)
       .to(ball, { y: 540, duration: .57, ease: 'power2.in' }, .58)
       .to(ball, { scale: 2, duration: .58, ease: 'power2.out' }, 0)
       .to(ball, { scale: 1.1, duration: .57, ease: 'power2.in' }, .58);
      const h = glyph('🧤', 1350, 520, 130);
      T.add(() => { ring(1362, 540, '#ffd23f'); burst(35, ['#ffd23f', '#ffffff', '#e31857'], 1362, 540, 380, 300); }, 1.15)
       .fromTo(h, { opacity: 0, scale: .2 }, { opacity: 1, scale: 1, duration: .45, ease: 'back.out(3)' }, 1.1)
       .to(ball, { opacity: 0, duration: .1 }, 1.2)
       .to(h, { opacity: 0, duration: .4 }, 2.5);
    } else if (kind === 'lbw') {
      at = 1.35;
      const pad = document.createElement('div'); pad.className = 'pad'; fx.appendChild(pad);
      gsap.set(pad, { x: sx - 45, y: sy - 20, opacity: 0 });
      const ln = document.createElement('div'); ln.className = 'dline'; fx.appendChild(ln);
      gsap.set(ln, { x: sx + 45, y: sy + 60, scaleX: 0, transformOrigin: '0% 50%' });
      const S = makeStumps(sx + 330, sy);
      T.to(pad, { opacity: 1, duration: .2 }, 0)
       .to(S.stumps.map(s => s[0]), { opacity: .45, duration: .3 }, 0)
       .to(S.bails.map(b => b[0]), { opacity: .45, duration: .3 }, 0)
       .set(ball, { x: 240, y: 760, opacity: 1, scale: 1 }, 0)
       .to(ball, { x: sx - 40, y: sy + 70, duration: .55, ease: 'power2.in', rotation: 600 }, 0)
       .to(ball, { opacity: 0, duration: .1 }, .55)
       .to(pad, { backgroundColor: 'rgba(255,51,85,.85)', boxShadow: '0 0 40px 10px rgba(255,51,85,.8)', duration: .12, yoyo: true, repeat: 3 }, .55)
       .to(ln, { scaleX: 1, duration: .6, ease: 'power2.out' }, .7)
       .to(S.stumps.map(s => s[0]), { opacity: 1, duration: .15, yoyo: true, repeat: 3 }, 1.2)
       .to([pad, ln], { opacity: 0, duration: .4 }, 2.4)
       .to(S.stumps.map(s => s[0]).concat(S.bails.map(b => b[0])), { opacity: 0, duration: .4 }, 2.4);
    } else {
      const S = makeStumps(sx, sy);
      const fromX = kind === 'runout' ? 1580 : 260;
      const hit = kind === 'bowled' ? .55 : .45;
      at = hit + .05;
      T.to(S.stumps.map(s => s[0]), { opacity: 1, duration: .15 }, 0)
       .to(S.bails.map(b => b[0]), { opacity: 1, duration: .15 }, 0)
       .set(ball, { x: fromX, y: 760, opacity: 1, scale: 1 }, 0)
       .to(ball, { x: sx - 8, y: sy + 60, duration: hit, ease: 'power2.in', rotation: 720 }, 0)
       .add(() => {
         breakStumps(S, sx, sy, kind === 'bowled');
         burst(40, ['#e31857', '#ff6b8f', '#ffffff'], sx, sy, 420, 300);
         if (kind !== 'bowled') ring(sx, sy + 60, '#ffd23f');
       }, hit)
       .to(ball, { x: sx + (kind === 'runout' ? -420 : 420), y: sy + 250, opacity: 0, duration: .6, ease: 'power2.out' }, hit);
    }

    const big = o.duck === 'golden' ? ['GOLDEN DUCK', '#ffd23f', 'rgba(255,210,63,.9)', 'Out first ball']
              : o.duck === 'duck'   ? ['DUCK', '#ffb35c', 'rgba(255,150,40,.85)', 'Out for zero']
              : ['WICKET!', '#ff3d6b', 'rgba(227,24,87,.85)', 'Dismissal'];
    T.add(slam(big[0], big[1], big[2], big[3]), at);
    T.add(banner({
      tag: o.duck === 'golden' ? 'GOLDEN DUCK' : o.duck === 'duck' ? 'DUCK' : 'DISMISSAL',
      title: lab[0], sub: o.sub || lab[1], color: o.duck === 'golden' ? '#ffd23f' : '#ff3d6b', icon: lab[2], y: 700, hold: 2.2
    }), at + .55);

    if (o.duck) {
      const d = glyph('🦆', 960, 290, 150);
      d.style.filter = 'drop-shadow(0 0 30px ' + (o.duck === 'golden' ? '#ffd23f' : '#ffb35c') + ')';
      T.fromTo(d, { opacity: 1, y: -200 }, { y: 290, duration: 1, ease: 'bounce.out' }, at)
       .to(d, { rotation: 12, duration: .2, yoyo: true, repeat: 5 }, at + 1)
       .to(d, { opacity: 0, duration: .4 }, at + 2.7);
      if (o.duck === 'golden') T.add(() => burst(70, ['#ffd23f', '#fff3a8', '#ffffff'], 960, 330, 600, 440), at + .5);
    }
  }
  function out(kind, o) { addBall('W', true); dismiss(kind, o); }

  /* ---------- DRS REVIEW ---------- */
  function review(result) {
    reset();
    const isOut = result !== 'notout';
    const col = isOut ? '#ff3d6b' : '#19c37d';
    const box = document.createElement('div');
    box.className = 'drs'; box.style.setProperty('--c', '#4aa8ff');
    box.innerHTML = '<div class="drs-ring"></div><div class="drs-t">REVIEW</div><div class="drs-s">DECISION PENDING…</div>';
    fx.appendChild(box);
    gsap.set(box, { xPercent: -50, yPercent: -50 });
    const ringEl = box.querySelector('.drs-ring'), t = box.querySelector('.drs-t'), s = box.querySelector('.drs-s');
    tl = gsap.timeline({ onComplete: reset });
    tl.fromTo(box, { opacity: 0, scale: .4 }, { opacity: 1, scale: 1, duration: .5, ease: 'back.out(2)' })
      .to(ringEl, { rotation: 1080, duration: 2.4, ease: 'power2.inOut' }, .2)
      .to(t, { opacity: .35, duration: .3, yoyo: true, repeat: 5 }, .5)
      .add(() => {
        box.style.setProperty('--c', col);
        t.textContent = isOut ? 'OUT' : 'NOT OUT';
        t.style.color = col;
        s.textContent = isOut ? 'DECISION UPHELD' : 'DECISION OVERTURNED';
        ring(960, 470, col);
        burst(isOut ? 40 : 70, isOut ? ['#e31857', '#ff6b8f', '#fff'] : ['#19c37d', '#8dffd0', '#fff'], 960, 470, 520, 400);
      }, 2.6)
      .to(flash, { opacity: .45, backgroundColor: col, duration: .1, yoyo: true, repeat: 1 }, 2.6)
      .fromTo(t, { scale: 2.2, opacity: 0 }, { scale: 1, opacity: 1, duration: .5, ease: 'back.out(3)' }, 2.62)
      .to(box, { opacity: 0, scale: 1.1, duration: .5, ease: 'power2.in' }, 4.6);
  }

  /* ---------- HAT-TRICK ---------- */
  function hatTrick(bowlerName) {
    reset();
    tl = gsap.timeline({ onComplete: reset });
    tl.to(flash, { opacity: .5, backgroundColor: '#8a4dff', duration: .1, yoyo: true, repeat: 3 }, 0)
      .add(slam('HAT-TRICK!', '#c58cff', 'rgba(170,110,255,.95)', 'Three in three'), 0)
      .add(() => burst(120, ['#c58cff', '#ffd23f', '#ffffff', '#e31857'], 960, 470, 760, 540), .3)
      .add(banner({ tag: 'BOWLER', title: (bowlerName || state.bowler.name).toUpperCase(), sub: 'THREE WICKETS IN THREE BALLS', color: '#c58cff', icon: '🎩', y: 700, hold: 2.2 }), .7);
    [.3, .65, 1.0].forEach(tm => tl.add(() => ring(960, 470, '#c58cff'), tm));
  }

  /* ---------- MILESTONES ---------- */
  function milestone(n, o) {
    o = o || {}; reset();
    const cfg = n >= 200 ? { l: 'DOUBLE CENTURY', c: '#ff3d6b' }
              : n >= 150 ? { l: '150 UP', c: '#c58cff' }
              : n >= 100 ? { l: 'CENTURY', c: '#ffd23f' }
              : { l: 'FIFTY', c: '#7fc4ff' };
    const balls = o.balls || Math.max(state.striker.b, Math.round(n * .7));
    const el = document.createElement('div');
    el.className = 'mile'; el.style.setProperty('--c', cfg.c);
    el.innerHTML = '<div class="mile-rays"></div><div class="mile-bat">🏏</div><div class="mile-num">0</div>' +
      '<div class="mile-l">' + cfg.l + '</div><div class="mile-n">' + (o.name || state.striker.name) + '</div>' +
      '<div class="mile-s">' + n + ' RUNS · ' + balls + ' BALLS</div>';
    fx.appendChild(el);
    gsap.set(el, { xPercent: -50 });
    const num = el.querySelector('.mile-num'), obj = { v: 0 };
    const big = n >= 100;
    tl = gsap.timeline({ onComplete: reset });
    tl.fromTo(el, { opacity: 0, scale: .3 }, { opacity: 1, scale: 1, duration: .8, ease: 'elastic.out(1,.6)' })
      .to(el.querySelector('.mile-rays'), { rotation: 120, duration: 4.2, ease: 'none' }, 0)
      .to(obj, { v: n, duration: 1.2, ease: 'power2.out', onUpdate: () => { num.textContent = Math.round(obj.v); } }, .1)
      .from(el.querySelector('.mile-bat'), { rotation: -140, y: -90, opacity: 0, duration: .7, ease: 'back.out(2)' }, .2)
      .from(el.querySelector('.mile-l'), { y: 40, opacity: 0, letterSpacing: '40px', duration: .7, ease: 'power3.out' }, .5)
      .from([el.querySelector('.mile-n'), el.querySelector('.mile-s')], { y: 24, opacity: 0, duration: .5, stagger: .12 }, .8)
      .add(() => {
        burst(big ? 170 : 80, [cfg.c, '#ffffff', '#ffd23f', '#e31857'], 960, 440, big ? 820 : 640, 560);
        ring(960, 440, cfg.c);
        if (big) { ring(300, 600, cfg.c); ring(1620, 600, cfg.c); burst(60, [cfg.c, '#fff'], 300, 600, 420, 400); burst(60, [cfg.c, '#fff'], 1620, 600, 420, 400); }
      }, .7)
      .to(flash, { opacity: .4, backgroundColor: cfg.c, duration: .1, yoyo: true, repeat: 1 }, .7)
      .to(el, { opacity: 0, y: -40, duration: .5, ease: 'power2.in' }, 4.2);
  }

  /* ---------- MAIDEN / POWERPLAY / OVER SUMMARY ---------- */
  function maiden() {
    reset();
    tl = gsap.timeline({ onComplete: reset });
    tl.add(banner({ tag: 'OVER COMPLETE', title: 'MAIDEN', sub: 'SIX DOT BALLS', color: '#19c37d', icon: '●', stamp: true, hold: 1.8, y: 620 }), 0)
      .add(() => ring(960, 700, '#19c37d'), .4);
  }
  function powerplay(on) {
    state.pp = typeof on === 'boolean' ? on : !state.pp;
    reset();
    tl = gsap.timeline({ onComplete: reset });
    tl.add(banner({
      tag: state.pp ? 'FIELDING RESTRICTIONS' : 'RESTRICTIONS LIFTED',
      title: state.pp ? 'POWERPLAY' : 'POWERPLAY OVER',
      sub: state.pp ? 'ONLY TWO FIELDERS OUTSIDE THE CIRCLE' : 'FIELDERS FREE TO SPREAD',
      color: state.pp ? '#19c37d' : '#ff9d2e', icon: state.pp ? '⚡' : '■', hold: 2.0
    }), 0);
  }
  function overSummary() {
    reset();
    const runs = state.over.reduce((a, v) => a + (v === 'W' ? 0 : (isNaN(parseInt(v, 10)) ? 1 : parseInt(v, 10))), 0);
    const wk = state.over.filter(v => v === 'W').length;
    tl = gsap.timeline({ onComplete: reset });
    tl.add(banner({
      tag: 'END OF OVER ' + state.overs,
      title: runs + ' RUNS' + (wk ? ' · ' + wk + ' W' : ''),
      sub: state.over.map(v => v === '0' ? '•' : v.toUpperCase()).join('&nbsp;&nbsp;&nbsp;'),
      color: '#7fc4ff', hold: 2.6, y: 640
    }), 0);
  }

  const fxMap = { six, four, wicket: () => dismiss('bowled') };
  function runFx(type) { (fxMap[type] || six)(); }

  /* ---------- Scoring ---------- */
  function addBall(v, silent) {
    v = String(v);
    if (legalCount(state.over) >= 6) state.over = [];
    state.over.push(v);
    const n = parseInt(v, 10);
    if (v === 'W') { state.wickets++; state.striker.b++; }
    else if (v === 'wd' || v === 'nb' || v === 'lb') {
      state.runs++;
      if (mode === 'need') state.need = Math.max(0, state.need - 1);
    } else if (!isNaN(n)) {
      state.runs += n; state.striker.r += n; state.striker.b++;
      if (mode === 'need') state.need = Math.max(0, state.need - n);
    }
    if (!isExtra(v)) {
      if (mode === 'need') state.needBalls = Math.max(0, state.needBalls - 1);
      if (state.freeHit) hideFreeHit();
    }
    const l = legalCount(state.over);
    let isMaiden = false;
    if (l >= 6) {
      state.overs++; state.balls = 0;
      isMaiden = state.over.length === 6 && state.over.every(x => x === '0');
    } else state.balls = l;
    render();
    const last = $('#overBalls .dot:last-child');
    if (last) gsap.fromTo(last, { scale: 0 }, { scale: 1, duration: .5, ease: 'back.out(3)' });
    gsap.fromTo('#run-wicket', { scale: 1.25 }, { scale: 1, duration: .45, ease: 'back.out(3)' });
    if (silent) return;
    if (v === '6') six(); else if (v === '4') four(); else if (v === 'W') dismiss('bowled');
    else if (isMaiden) maiden();
  }

  /* ---------- Public API ---------- */
  window.Score = {
    state,
    set(patch) { Object.assign(state, patch); render(); },
    ball: addBall,
    say(msg, tag) { state.ticker.unshift([tag || 'UPDATE', msg]); state.tickIdx = 0; showTicker(true); autoTicker(); },
    fx: runFx,
    wide, noBall, legBye, deadBall, out, review, hatTrick, milestone, maiden, powerplay, overSummary
  };

  /* ---------- Keyboard (testing) ---------- */
  const KEYS = [
    ['1', 'Six', () => addBall('6')],
    ['2', 'Four', () => addBall('4')],
    ['3', 'Wicket (bowled)', () => addBall('W')],
    ['4', 'Next ticker message', nextTicker],
    ['5', 'Dot ball', () => addBall('0')],
    ['6', 'Clear over', () => { state.over = []; state.balls = 0; render(); }],
    ['w', 'Wide', wide],
    ['n', 'No ball + free hit', noBall],
    ['l', 'Leg bye', legBye],
    ['d', 'Dead ball', deadBall],
    ['b', 'Bowled', () => out('bowled')],
    ['c', 'Caught', () => out('caught')],
    ['i', 'LBW', () => out('lbw')],
    ['r', 'Run out', () => out('runout')],
    ['s', 'Stumped', () => out('stumped')],
    ['g', 'Golden duck', () => out('bowled', { duck: 'golden' })],
    ['u', 'Duck', () => out('caught', { duck: 'duck' })],
    ['v', 'Review → out', () => review('out')],
    ['x', 'Review → not out', () => review('notout')],
    ['h', 'Hat-trick', hatTrick],
    ['f', 'Fifty', () => milestone(50)],
    ['t', 'Century', () => milestone(100)],
    ['j', 'Double century', () => milestone(200)],
    ['m', 'Maiden over', maiden],
    ['p', 'Powerplay on / off', () => powerplay()],
    ['o', 'Over summary', overSummary]
  ];
  const keyMap = {};
  KEYS.forEach(k => { keyMap[k[0]] = k[2]; });

  /* Legend panel (toggle with "/") */
  const legend = document.createElement('div');
  legend.className = 'legend';
  legend.innerHTML = '<h4>TEST KEYS</h4>' + KEYS.map(k => '<div><kbd>' + k[0].toUpperCase() + '</kbd><span>' + k[1] + '</span></div>').join('') +
    '<div class="lg-foot"><kbd>/</kbd><span>Hide this panel</span></div>';
  stage.appendChild(legend);
  gsap.set(legend, { opacity: 0, x: 30, pointerEvents: 'none' });
  let legendOn = false;
  function toggleLegend() {
    legendOn = !legendOn;
    gsap.to(legend, { opacity: legendOn ? 1 : 0, x: legendOn ? 0 : 30, duration: .4, ease: 'power3.out' });
  }

  addEventListener('keydown', e => {
    if (e.ctrlKey || e.metaKey || e.altKey) return;
    if (e.key === '/') { e.preventDefault(); toggleLegend(); return; }
    const fn = keyMap[e.key.toLowerCase()];
    if (fn) { e.preventDefault(); fn(); }
  });

  /* ---------- Intro ---------- */
  render();
  showTicker(false);
  autoTicker();
  gsap.set([ball, shadow, txt, sub, flash], { opacity: 0 });
  const intro = gsap.timeline({ defaults: { ease: 'power4.out' },
    onComplete: () => gsap.set('#logo, .ticker, .flag, .core, .bat, .live > *, .score > *', { clearProps: 'transform,opacity' }) });
  intro.from('#logo', { x: -60, opacity: 0, duration: .8 })
       .from('.ticker', { y: 40, opacity: 0, scaleX: .85, duration: .8 }, .1)
       .from('.flag.left', { xPercent: -120, duration: .9 }, .2)
       .from('.flag.right', { xPercent: 120, duration: .9 }, .2)
       .from('.core', { scaleX: .2, opacity: 0, duration: .9, transformOrigin: '50% 50%' }, .35)
       .from('.bat, .live > *, .score > *', { y: 16, opacity: 0, stagger: .06, duration: .5 }, .8);
})();
