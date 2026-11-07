/* Navigation and animated background from the Guardian HTML prototype. */
(() => {
    const menuButton = document.getElementById('hamburgerBtn');
    const menu = document.getElementById('mobileMenu');
    if (menuButton && menu) {
        let menuIsOpen = false;
        let closeTimer = null;
        let transitionFrame = null;
        let transitionId = 0;

        const setMenuOpen = open => {
            if (menuIsOpen === open) return;
            menuIsOpen = open;
            const currentTransition = ++transitionId;
            if (closeTimer !== null) window.clearTimeout(closeTimer);
            if (transitionFrame !== null) cancelAnimationFrame(transitionFrame);

            menuButton.classList.toggle('is-active', open);
            menuButton.setAttribute('aria-expanded', String(open));

            if (open) {
                menu.hidden = false;
                transitionFrame = requestAnimationFrame(() => {
                    if (menuIsOpen && currentTransition === transitionId) menu.classList.add('is-open');
                    transitionFrame = null;
                });
            } else {
                menu.classList.remove('is-open');
                closeTimer = window.setTimeout(() => {
                    if (!menuIsOpen && currentTransition === transitionId) menu.hidden = true;
                    closeTimer = null;
                }, 240);
            }
        };

        const closeMenu = () => {
            setMenuOpen(false);
        };
        menuButton.addEventListener('click', () => {
            setMenuOpen(!menuIsOpen);
        });
        document.addEventListener('keydown', event => { if (event.key === 'Escape') closeMenu(); });
        document.addEventListener('click', event => {
            if (!event.target.closest('.top-navbar') && !event.target.closest('#mobileMenu') && menuIsOpen) closeMenu();
        });
    }

    document.addEventListener('click', event => {
        if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
        const link = event.target.closest('a[href]');
        if (!link || link.origin !== window.location.origin || link.target === '_blank' || link.hasAttribute('download')) return;
        if (link.hash && link.pathname === window.location.pathname && link.search === window.location.search) return;
        document.body.classList.add('page-navigating');
    });

    const renderIcons = () => window.lucide?.createIcons();
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', renderIcons, { once: true });
    } else {
        renderIcons();
    }

    const consentModal = document.getElementById('consent-modal');
    if (consentModal) {
        const checkbox = document.getElementById('consent-check');
        const accept = document.getElementById('consent-accept');
        const cancel = document.getElementById('consent-cancel');
        const close = document.getElementById('consent-close');
        let resolveConsent = null;
        let previousFocus = null;
        function finishConsent(accepted) {
            consentModal.classList.add('hidden');
            consentModal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('consent-open');
            checkbox.checked = false; accept.disabled = true;
            const resolve = resolveConsent; resolveConsent = null;
            previousFocus?.focus(); previousFocus = null;
            resolve?.(accepted);
        }
        window.GuardianConsent = {
            request({ title, description, label }) {
                if (resolveConsent) finishConsent(false);
                previousFocus = document.activeElement;
                document.getElementById('consent-title').textContent = title;
                document.getElementById('consent-description').textContent = description;
                document.getElementById('consent-label').textContent = label;
                consentModal.classList.remove('hidden'); consentModal.setAttribute('aria-hidden', 'false');
                document.body.classList.add('consent-open');
                checkbox.focus();
                return new Promise(resolve => { resolveConsent = resolve; });
            },
        };
        checkbox.addEventListener('change', () => { accept.disabled = !checkbox.checked; });
        accept.addEventListener('click', () => { if (checkbox.checked) finishConsent(true); });
        [cancel, close].forEach(button => button.addEventListener('click', () => finishConsent(false)));
        consentModal.addEventListener('click', event => { if (event.target === consentModal) finishConsent(false); });
        document.addEventListener('keydown', event => { if (event.key === 'Escape' && !consentModal.classList.contains('hidden')) finishConsent(false); });
    }

    const hero = document.getElementById('heroRobotContainer');
    if (hero) {
        const tips = [
            '🛡️ Waspadai tautan yang memakai nama bank dengan domain asing seperti .xyz atau .online.',
            '⚠️ Jangan instal file APK yang dikirim melalui pesan atau WhatsApp.',
            '🔒 Bank resmi tidak akan meminta kode OTP atau kata sandi pribadi.',
            '🔎 Gunakan Cek Link dan Cek File untuk memeriksa tautan serta berkas.',
        ];
        let tipIndex = -1;
        hero.addEventListener('click', () => {
            tipIndex = (tipIndex + 1) % tips.length;
            document.getElementById('robotStatusSpeech').textContent = tips[tipIndex];
            const eyes = [document.getElementById('heroEyeLeft'), document.getElementById('heroEyeRight')];
            eyes.forEach(eye => eye?.setAttribute('height', '1.5'));
            window.setTimeout(() => eyes.forEach(eye => eye?.setAttribute('height', '8.5')), 180);
            const svg = document.getElementById('heroRobotSvg');
            svg?.classList.add('hero-react');
            window.setTimeout(() => svg?.classList.remove('hero-react'), 320);
        });
    }

    const canvas = document.getElementById('waveCanvas');
    const context = canvas?.getContext('2d');
    if (!canvas || !context) return;
    let width = 0;
    let height = 0;
    let step = 0;
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const smallScreenQuery = window.matchMedia('(max-width: 640px), (hover: none)');
    let smallScreen = smallScreenQuery.matches;
    if ('IntersectionObserver' in window) {
        const visibleEffects = new IntersectionObserver(entries => {
            entries.forEach(entry => entry.target.classList.toggle('is-in-view', entry.isIntersecting));
        }, { threshold: 0.05 });
        document.querySelectorAll('.stage-container > .glass-card, #externalRobotStage, #heroRobotContainer').forEach(element => visibleEffects.observe(element));
    } else {
        document.querySelectorAll('.stage-container > .glass-card, #externalRobotStage, #heroRobotContainer').forEach(element => element.classList.add('is-in-view'));
    }
    const waves = [
        { top: 'rgba(147,51,234,0)', middle: 'rgba(147,51,234,.25)', bottom: 'rgba(109,40,217,.60)', length: .005, amplitude: 65, speed: .9, phase: 2, y: .62 },
        { top: 'rgba(126,34,206,0)', middle: 'rgba(126,34,206,.45)', bottom: 'rgba(88,28,135,.90)', length: .007, amplitude: 50, speed: 1.2, phase: .5, y: .70 },
    ];
    function resize() {
        smallScreen = smallScreenQuery.matches;
        const ratio = smallScreen ? 1 : Math.min(window.devicePixelRatio || 1, 1.25);
        width = window.innerWidth; height = window.innerHeight;
        canvas.width = width * ratio; canvas.height = height * ratio;
        canvas.style.width = `${width}px`; canvas.style.height = `${height}px`;
        context.setTransform(ratio, 0, 0, ratio, 0, 0);
        renderFrame();
    }
    function drawWave(wave) {
        const base = height * wave.y;
        const gradient = context.createLinearGradient(0, base - wave.amplitude - 20, 0, height);
        gradient.addColorStop(0, wave.top); gradient.addColorStop(.35, wave.middle); gradient.addColorStop(1, wave.bottom);
        context.beginPath(); context.moveTo(0, height); context.lineTo(0, base);
        const pointSpacing = smallScreen ? 8 : 4;
        for (let x = 0; x <= width; x += pointSpacing) {
            const y = base + Math.sin(x * wave.length + step * wave.speed + wave.phase) * wave.amplitude
                + Math.sin(x * wave.length * .4 + step * .6) * wave.amplitude * .35;
            context.lineTo(x, y);
        }
        context.lineTo(width, height); context.closePath(); context.fillStyle = gradient; context.fill();
    }
    function renderFrame() {
        context.fillStyle = '#fff'; context.fillRect(0, 0, width, height);
        if (!reduceMotion && !smallScreen) step += .003;
        (smallScreen ? [waves[1]] : waves).forEach(drawWave);
    }
    let animationFrame = null;
    let canvasVisible = true;
    function animate() {
        animationFrame = null;
        if (document.hidden || !canvasVisible || reduceMotion || smallScreen) return;
        renderFrame();
        animationFrame = requestAnimationFrame(animate);
    }
    function syncAnimation() {
        if (document.hidden || !canvasVisible) {
            if (animationFrame !== null) cancelAnimationFrame(animationFrame);
            animationFrame = null;
            return;
        }
        if (reduceMotion || smallScreen) {
            if (animationFrame !== null) cancelAnimationFrame(animationFrame);
            animationFrame = null;
            renderFrame();
        } else if (animationFrame === null) {
            animationFrame = requestAnimationFrame(animate);
        }
    }
    if ('IntersectionObserver' in window) {
        new IntersectionObserver(([entry]) => { canvasVisible = entry.isIntersecting; syncAnimation(); }).observe(canvas);
    }
    const updatePageVisibility = () => {
        document.body.classList.toggle('page-hidden', document.hidden);
        syncAnimation();
    };
    document.addEventListener('visibilitychange', updatePageVisibility);
    smallScreenQuery.addEventListener?.('change', () => { resize(); syncAnimation(); });
    window.addEventListener('resize', resize, { passive: true });
    resize();
    updatePageVisibility();
})();
