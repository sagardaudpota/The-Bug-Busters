/* SCORECAST landing — GSAP loader + page animations.
   Button links live in index.html (#linkUser, #linkAdmin). */

(function () {
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));
  const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;

  document.body.classList.add('is-loading');
  $('#year').textContent = new Date().getFullYear();

  /* ---------- Links + placeholder toast ---------- */
  const userA = $('#linkUser'), adminA = $('#linkAdmin');

  const toast = $('#toast');
  let toastT;
  function say(msg) {
    toast.textContent = msg;
    gsap.killTweensOf(toast);
    gsap.fromTo(toast, { y: 30, opacity: 0 }, { y: 0, opacity: 1, duration: .4, ease: 'power3.out' });
    clearTimeout(toastT);
    toastT = setTimeout(() => gsap.to(toast, { y: 30, opacity: 0, duration: .35 }), 2400);
  }
  [userA, adminA].forEach(a => a.addEventListener('click', e => {
    if (a.getAttribute('href') === '#') {
      e.preventDefault();
      say((a.dataset.placeholder === 'admin' ? 'Admin' : 'User') + ' Screen link is a placeholder — add the URL in assets/landing.js');
    }
  }));

  /* ---------- Initial hidden states (prevents flash) ---------- */
  gsap.set('.nav, .live-chip, .hero-sub', { opacity: 0 });
  gsap.set('.hero-title .word', { yPercent: 110 });
  gsap.set('.card', { opacity: 0, y: 70, rotationX: -12 });
  gsap.set('.foot', { opacity: 0 });

  /* ---------- Loader timeline ---------- */
  const letters = $$('#ldTitle span');
  const pct = $('#ldPct'), fill = $('#ldFill');
  const counter = { v: 0 };
  const loadTime = reduce ? .6 : 2.6;

  gsap.set('#ldCurtain', { yPercent: 100 });
  const L = gsap.timeline({ onComplete: revealPage });
  L.from('.ld-grid', { opacity: 0, scale: 1.2, duration: 1.2, ease: 'power2.out' }, 0)
   .from('#ldBall', { x: () => -innerWidth * .55, rotation: -720, duration: 1.1, ease: 'power3.out' }, 0)
   .to('#ldBall', { y: -26, duration: .22, yoyo: true, repeat: 3, ease: 'power2.out' }, 1.0)
   .from(letters, { yPercent: 120, opacity: 0, rotation: () => gsap.utils.random(-14, 14), duration: .7, ease: 'back.out(2.2)', stagger: .06 }, .35)
   .from('#ldBy', { opacity: 0, y: 14, letterSpacing: '.7em', duration: .9, ease: 'power3.out' }, 1.0)
   .from('.ld-bar, .ld-pct', { opacity: 0, y: 12, duration: .5 }, 1.0)
   .to(fill, { scaleX: 1, duration: loadTime - .4, ease: 'power1.inOut' }, 1.0)
   .to(counter, { v: 100, duration: loadTime - .4, ease: 'power1.inOut', onUpdate: () => { pct.textContent = Math.round(counter.v); } }, 1.0)
   .to('#ldTitle .gold', { textShadow: '0 0 70px rgba(255,210,63,.95)', duration: .5, yoyo: true, repeat: 1 }, loadTime + .4)
   // exit: text lifts away, curtain rises, then the whole loader slides off
   .to('.ld-inner', { y: -40, opacity: 0, duration: .5, ease: 'power2.in' }, loadTime + 1.1)
   .to('#ldCurtain', { yPercent: -100, duration: .7, ease: 'power3.inOut' }, loadTime + 1.2)
   .to('#loader', { yPercent: -100, duration: .8, ease: 'power4.inOut' }, loadTime + 1.55)
   .set('#loader', { display: 'none' });

  /* ---------- Page reveal ---------- */
  function revealPage() {
    document.body.classList.remove('is-loading');
    const P = gsap.timeline({ defaults: { ease: 'power4.out' } });
    P.to('.nav', { opacity: 1, y: 0, duration: .8 }, 0)
     .from('.nav', { y: -24, duration: .8 }, 0)
     .to('.live-chip', { opacity: 1, duration: .6 }, .15)
     .from('.live-chip', { y: 18, scale: .9, duration: .7 }, .15)
     .to('.hero-title .word', { yPercent: 0, duration: 1.1, stagger: .14 }, .25)
     .to('.hero-sub', { opacity: 1, duration: .8 }, .7)
     .from('.hero-sub', { y: 20, duration: .8 }, .7)
     .to('.card', { opacity: 1, y: 0, rotationX: 0, duration: 1.1, stagger: .18 }, .85)
     .from('.card .preview > *', { opacity: 0, y: 14, duration: .6, stagger: .06 }, 1.3)
     .from('.card .feats li', { opacity: 0, x: -14, duration: .5, stagger: .05 }, 1.45)
     .to('.foot', { opacity: 1, duration: .8 }, 1.8);

    if (reduce) return;

    // floating background balls + glows
    gsap.to('.b1', { y: -28, x: 14, rotation: 180, duration: 5, yoyo: true, repeat: -1, ease: 'sine.inOut' });
    gsap.to('.b2', { y: 34, x: -18, rotation: -200, duration: 6, yoyo: true, repeat: -1, ease: 'sine.inOut' });
    gsap.to('.b3', { y: -22, x: 20, rotation: 120, duration: 7, yoyo: true, repeat: -1, ease: 'sine.inOut' });
    gsap.to('.g1', { x: 70, y: 40, duration: 9, yoyo: true, repeat: -1, ease: 'sine.inOut' });
    gsap.to('.g2', { x: -60, y: -50, duration: 10, yoyo: true, repeat: -1, ease: 'sine.inOut' });

    // idle float on the two previews
    gsap.to('.mb-score', { scale: 1.06, duration: 1.1, yoyo: true, repeat: -1, ease: 'sine.inOut' });
    gsap.to('.mini-pad i.s', { y: -3, duration: .8, yoyo: true, repeat: -1, ease: 'sine.inOut' });
    gsap.to('.mini-pad i.w', { y: -3, duration: .8, yoyo: true, repeat: -1, ease: 'sine.inOut', delay: .3 });
  }

  /* ---------- Card tilt + shine (pointer devices only) ---------- */
  if (matchMedia('(hover: hover) and (pointer: fine)').matches && !reduce) {
    $$('[data-tilt]').forEach(card => {
      const rx = gsap.quickTo(card, 'rotationX', { duration: .5, ease: 'power3.out' });
      const ry = gsap.quickTo(card, 'rotationY', { duration: .5, ease: 'power3.out' });
      card.addEventListener('pointermove', e => {
        const r = card.getBoundingClientRect();
        const px = (e.clientX - r.left) / r.width, py = (e.clientY - r.top) / r.height;
        ry((px - .5) * 10); rx((.5 - py) * 10);
        card.style.setProperty('--mx', (px * 100) + '%');
        card.style.setProperty('--my', (py * 100) + '%');
      });
      card.addEventListener('pointerleave', () => { rx(0); ry(0); });
      card.addEventListener('pointerenter', () => gsap.to(card, { y: -8, duration: .4, ease: 'power3.out', overwrite: 'auto' }));
      card.addEventListener('pointerleave', () => gsap.to(card, { y: 0, duration: .5, ease: 'power3.out', overwrite: 'auto' }));
    });
  }
})();
