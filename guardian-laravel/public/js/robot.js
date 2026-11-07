/* Guardian mascot motion and optional Web Audio feedback. */
(function () {
  const states = {
    idle: { label: 'Mode Siaga', color: '#c38eff', glow: 'glow-blue', mouth: 'M 155 260 Q 200 285 245 260' },
    scanning: { label: 'Sedang menganalisis...', color: '#fbbf24', glow: 'glow-amber', mouth: 'M 160 266 Q 200 266 240 266' },
    danger: { label: 'Waspada: indikator terdeteksi', color: '#ef4444', glow: 'glow-red', mouth: 'M 155 280 Q 200 245 245 280' },
    safe: { label: 'Pemeriksaan selesai', color: '#34d399', glow: 'glow-green', mouth: 'M 150 255 Q 200 300 250 255' },
  };
  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const lowPowerScreen = window.matchMedia('(max-width: 640px), (hover: none)').matches;
  let audioEnabled = true;
  let audioContext = null;
  let particles = [];
  let frame = null;

  function setState(name, message) {
    const state = states[name] || states.idle;
    const text = document.getElementById('speech-text');
    const eyeLeft = document.getElementById('left-eye');
    const eyeRight = document.getElementById('right-eye');
    const mouth = document.getElementById('smooth-mouth');
    if (message && text) text.textContent = message;
    const screen = document.getElementById('guardian-screen');
    if (screen) screen.setAttribute('fill', name === 'danger' ? '#450a0a' : name === 'safe' ? '#064e3b' : name === 'scanning' ? '#3b0764' : '#231340');
    if (eyeLeft && eyeRight) {
      if (name === 'danger') {
        eyeLeft.innerHTML = '<path d="M 135 185 L 167 225 M 167 185 L 135 225" stroke="#f87171" stroke-width="12" stroke-linecap="round"/>';
        eyeRight.innerHTML = '<path d="M 233 185 L 265 225 M 265 185 L 233 225" stroke="#f87171" stroke-width="12" stroke-linecap="round"/>';
      } else if (name === 'safe') {
        eyeLeft.innerHTML = '<path d="M 130 185 L 165 205 L 130 225" stroke="#34d399" stroke-width="12" stroke-linecap="round" stroke-linejoin="round"/>';
        eyeRight.innerHTML = '<path d="M 270 185 L 235 205 L 270 225" stroke="#34d399" stroke-width="12" stroke-linecap="round" stroke-linejoin="round"/>';
      } else if (name === 'scanning') {
        eyeLeft.innerHTML = '<circle cx="151" cy="205" r="22" fill="#c38eff"/>';
        eyeRight.innerHTML = '<circle cx="249" cy="205" r="22" fill="#c38eff"/>';
      } else {
        eyeLeft.innerHTML = '<rect x="135" y="175" width="32" height="60" rx="16" fill="#c38eff"/>';
        eyeRight.innerHTML = '<rect x="233" y="175" width="32" height="60" rx="16" fill="#c38eff"/>';
      }
    }
    if (mouth) { mouth.setAttribute('d', state.mouth); mouth.setAttribute('stroke', state.color); }
    const title = document.getElementById('robot-title');
    if (title) {
      title.textContent = name === 'idle' ? title.dataset.idleTitle : state.label;
      title.className = `guardian-status-pill guardian-status-${name || 'idle'}`;
    }
    const status = document.getElementById('status-text');
    if (status) status.textContent = state.label;
    if (name === 'safe') {
      const mascot = document.getElementById('robot-svg');
      mascot?.classList.remove('guardian-safe-reaction');
      void mascot?.getBoundingClientRect();
      mascot?.classList.add('guardian-safe-reaction');
      burst('#34d399'); pulse(); playTone(780);
      window.setTimeout(() => mascot?.classList.remove('guardian-safe-reaction'), 1800);
    }
  }

  function pulse() {
    const mascot = document.getElementById('robot-svg');
    if (!mascot) return;
    mascot.classList.remove('robot-pulsing');
    void mascot.getBoundingClientRect();
    mascot.classList.add('robot-pulsing');
    window.setTimeout(() => mascot.classList.remove('robot-pulsing'), 1500);
  }

  function playTone(frequency = 520) {
    if (!audioEnabled) return;
    try {
      audioContext ||= new (window.AudioContext || window.webkitAudioContext)();
      if (audioContext.state === 'suspended') audioContext.resume();
      const oscillator = audioContext.createOscillator();
      const gain = audioContext.createGain();
      oscillator.type = 'sine'; oscillator.frequency.value = frequency;
      gain.gain.setValueAtTime(.045, audioContext.currentTime);
      gain.gain.exponentialRampToValueAtTime(.001, audioContext.currentTime + .16);
      oscillator.connect(gain); gain.connect(audioContext.destination);
      oscillator.start(); oscillator.stop(audioContext.currentTime + .16);
    } catch (_) { /* Audio is an optional enhancement. */ }
  }

  const soundButton = document.getElementById('guardian-sound-toggle');
  soundButton?.addEventListener('click', () => {
    audioEnabled = !audioEnabled;
    soundButton.setAttribute('aria-pressed', String(audioEnabled));
    soundButton.innerHTML = audioEnabled
      ? '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"></polygon><path d="M15.54 8.46a5 5 0 0 1 0 7.07"></path></svg><span>Suara Aktif</span>'
      : '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"></polygon><line x1="23" y1="9" x2="17" y2="15"></line><line x1="17" y1="9" x2="23" y2="15"></line></svg><span>Suara Nonaktif</span>';
    if (audioEnabled) playTone(660);
  });

  const stage = document.getElementById('guardian-avatar-wrap');
  const canvas = document.getElementById('guardian-particles');
  const ctx = canvas?.getContext('2d');
  let stageVisible = !stage;
  if (stage && 'IntersectionObserver' in window) {
    stageVisible = false;
    new IntersectionObserver(([entry]) => {
      stageVisible = entry.isIntersecting;
      stage.classList.toggle('is-in-view', stageVisible);
      if (!stageVisible && frame !== null) {
        cancelAnimationFrame(frame);
        frame = null;
        particles = [];
        ctx?.clearRect(0, 0, canvas.width, canvas.height);
      }
    }, { threshold: 0.05 }).observe(stage);
  } else {
    stage?.classList.add('is-in-view');
  }
  function resizeCanvas() {
    if (!canvas || !stage) return;
    const rect = stage.getBoundingClientRect();
    const ratio = lowPowerScreen ? 1 : Math.min(window.devicePixelRatio || 1, 2);
    canvas.width = rect.width * ratio; canvas.height = rect.height * ratio;
    canvas.style.width = `${rect.width}px`; canvas.style.height = `${rect.height}px`;
    ctx?.setTransform(ratio, 0, 0, ratio, 0, 0);
  }
  function burst(color = '#c084fc') {
    if (!canvas || !stage || reducedMotion) return;
    const rect = stage.getBoundingClientRect();
    for (let i = 0; i < 26; i++) {
      const angle = Math.random() * Math.PI * 2;
      const speed = 1 + Math.random() * 2.2;
      particles.push({ x: rect.width / 2, y: rect.height / 2, vx: Math.cos(angle) * speed, vy: Math.sin(angle) * speed, life: 1, size: 2 + Math.random() * 3, color });
    }
    if (!frame) frame = requestAnimationFrame(drawParticles);
  }
  function drawParticles() {
    frame = null;
    if (!ctx || !canvas) return;
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    particles = particles.filter(p => p.life > 0);
    particles.forEach(p => {
      p.x += p.vx; p.y += p.vy; p.life -= .018;
      ctx.globalAlpha = Math.max(0, p.life); ctx.fillStyle = p.color;
      ctx.beginPath(); ctx.arc(p.x, p.y, p.size, 0, Math.PI * 2); ctx.fill();
    });
    ctx.globalAlpha = 1;
    if (particles.length) frame = requestAnimationFrame(drawParticles);
    else ctx.clearRect(0, 0, canvas.width, canvas.height);
  }

  function react() {
    const left = document.getElementById('left-eye');
    const right = document.getElementById('right-eye');
    const mouth = document.getElementById('smooth-mouth');
    if (!left || !right || !mouth) return;
    const moods = ['wink', 'surprised', 'happy'];
    const mood = moods[Math.floor(Math.random() * moods.length)];
    const eyeMarkup = '<rect x="135" y="175" width="32" height="60" rx="16" fill="#c38eff"/>';
    const eyeMarkupRight = '<rect x="233" y="175" width="32" height="60" rx="16" fill="#c38eff"/>';
    if (mood === 'surprised') {
      left.innerHTML = '<circle cx="151" cy="205" r="22" fill="#c38eff"/>';
      right.innerHTML = '<circle cx="249" cy="205" r="22" fill="#c38eff"/>';
      mouth.setAttribute('d', 'M 185 265 C 185 250, 215 250, 215 265 C 215 280, 185 280, 185 265 Z');
    } else if (mood === 'wink') {
      left.innerHTML = '<path d="M 135 205 Q 151 193 167 205" stroke="#c38eff" stroke-width="9" stroke-linecap="round"/>';
      right.innerHTML = eyeMarkupRight;
      mouth.setAttribute('d', 'M 158 260 Q 200 286 242 260');
    } else {
      left.innerHTML = eyeMarkup; right.innerHTML = eyeMarkupRight;
      mouth.setAttribute('d', 'M 155 260 Q 200 292 245 260');
    }
    pulse(); burst(); playTone(mood === 'surprised' ? 780 : 560);
    const svg = document.getElementById('robot-svg');
    svg?.classList.add('guardian-reaction-pop');
    window.setTimeout(() => {
      left.innerHTML = eyeMarkup; right.innerHTML = eyeMarkupRight;
      mouth.setAttribute('d', 'M 155 260 Q 200 285 245 260');
      svg?.classList.remove('guardian-reaction-pop');
    }, 900);
  }
  document.getElementById('mascot-toggle-menu')?.addEventListener('click', react);
  window.addEventListener('resize', resizeCanvas, { passive: true });
  resizeCanvas();

  if (!reducedMotion && !lowPowerScreen) {
    window.setInterval(() => {
      if (!stageVisible || document.hidden) return;
      const eyes = [document.getElementById('left-eye'), document.getElementById('right-eye')];
      eyes.forEach(eye => { if (eye) { eye.style.transformBox = 'fill-box'; eye.style.transformOrigin = 'center'; eye.style.transform = 'scaleY(.12)'; } });
      window.setTimeout(() => eyes.forEach(eye => { if (eye) eye.style.transform = ''; }), 140);
    }, 4600);
    document.addEventListener('pointermove', event => {
      if (!stageVisible || document.hidden) return;
      const face = document.getElementById('guardian-face');
      const svg = document.getElementById('robot-svg');
      if (!face || !svg) return;
      const rect = svg.getBoundingClientRect();
      const dx = Math.max(-2, Math.min(2, (event.clientX - (rect.left + rect.width / 2)) / 75));
      const dy = Math.max(-2, Math.min(2, (event.clientY - (rect.top + rect.height / 2)) / 75));
      face.style.transform = `translate(${dx * 3}px, ${dy * 3}px)`;
    }, { passive: true });
  }

  window.Robot = { setState, pulse, react, toggleMenu: () => {}, burst };
})();
