import { TOTAL_PLOTS, drawKavlingScene } from './kavling-grid';

export function initWelcomeOverlay(root = document) {
    const overlay = root.querySelector('[data-auth-welcome]');
    if (!overlay) return;

    const reducedMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
    let shouldShow = false;
    try {
        shouldShow = window.sessionStorage.getItem('oasis:welcome') === '1';
        window.sessionStorage.removeItem('oasis:welcome');
    } catch {
        return;
    }

    if (!shouldShow || reducedMotion) return;

    const canvas = overlay.querySelector('canvas');
    const context = canvas?.getContext('2d');
    const progress = overlay.querySelector('[data-welcome-progress]');
    let frame = 0;
    let started = performance.now();

    const resize = () => {
        const rect = canvas?.getBoundingClientRect();
        if (!canvas || !context || !rect?.width || !rect.height) return;
        const ratio = Math.min(window.devicePixelRatio || 1, 2);
        canvas.width = Math.round(rect.width * ratio);
        canvas.height = Math.round(rect.height * ratio);
        context.setTransform(ratio, 0, 0, ratio, 0, 0);
        canvas.dataset.width = String(rect.width);
        canvas.dataset.height = String(rect.height);
    };

    const close = () => {
        window.cancelAnimationFrame(frame);
        overlay.hidden = true;
        overlay.setAttribute('aria-hidden', 'true');
    };

    const render = (now) => {
        const width = Number(canvas?.dataset.width || 0);
        const height = Number(canvas?.dataset.height || 0);
        if (!context || !width || !height) return;
        const fraction = Math.min(1, (now - started) / 850);
        const lit = Math.floor(fraction * TOTAL_PLOTS);
        drawKavlingScene(context, width, height, {
            lit,
            accentIndex: 24,
            theme: document.documentElement.dataset.theme,
            now,
            animation: { type: 'wave' },
        });
        if (progress) progress.style.setProperty('--welcome-progress', `${Math.round(fraction * 100)}%`);
        if (fraction < 1) {
            frame = window.requestAnimationFrame(render);
        } else {
            window.setTimeout(close, 260);
        }
    };

    overlay.hidden = false;
    overlay.setAttribute('aria-hidden', 'false');
    overlay.addEventListener('click', close);
    window.addEventListener('keydown', close, { once: true });
    window.addEventListener('resize', resize);
    window.addEventListener('themechange', resize);
    resize();
    started = performance.now();
    frame = window.requestAnimationFrame(render);
}
