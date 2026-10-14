export const GRID_SIZE = 7;
export const TOTAL_PLOTS = GRID_SIZE * GRID_SIZE;

const BLOCK_GAP = 0.43;
const BOARD_DEPTH = 10;
const THEME_SCENE = {
    light: {
        boardTop: '#dde1d0', boardLeft: '#b3baa3', boardRight: '#9ea68d',
        deadTop: '#fdfdfb', deadLeft: '#d2d7c4', deadRight: '#b9c0aa',
        limeTop: '#9ccc4e', limeLeft: '#88b940', limeRight: '#6fa030',
        orangeTop: '#f4a65c', orangeLeft: '#ec9a48', orangeRight: '#d3802d',
        shadow: 'rgba(29,34,25,.18)',
    },
    dark: {
        boardTop: '#232a20', boardLeft: '#161c13', boardRight: '#0f130d',
        deadTop: '#2a3224', deadLeft: '#1b2117', deadRight: '#12170f',
        limeTop: '#9ccc4e', limeLeft: '#88b940', limeRight: '#6fa030',
        orangeTop: '#f4a65c', orangeLeft: '#ec9a48', orangeRight: '#d3802d',
        shadow: 'rgba(0,0,0,.42)',
    },
};

const clamp = (value, min, max) => Math.min(max, Math.max(min, value));

export function countLitPlots(emailLength = 0, passwordLength = 0, total = TOTAL_PLOTS) {
    return clamp(Number(emailLength) || 0, 0, total) + clamp(Number(passwordLength) || 0, 0, total) > total
        ? total
        : clamp(Number(emailLength) || 0, 0, total) + clamp(Number(passwordLength) || 0, 0, total);
}

export function buildPlotOrder(size = GRID_SIZE) {
    return Array.from({ length: size * size }, (_, index) => ({
        index,
        x: index % size,
        y: Math.floor(index / size),
    }))
        .sort((a, b) => (a.x + a.y) - (b.x + b.y) || a.x - b.x)
        .map((plot, rank) => ({ ...plot, rank }));
}

export function projectIsometric(x, y, z, scale, originX, originY) {
    return {
        x: originX + (x - y) * scale,
        y: originY + (x + y) * scale / 2 - z,
    };
}

const polygon = (context, points, fill) => {
    context.beginPath();
    context.moveTo(points[0].x, points[0].y);
    points.slice(1).forEach((point) => context.lineTo(point.x, point.y));
    context.closePath();
    context.fillStyle = fill;
    context.fill();
};

const sceneSize = (width, height) => {
    const scale = clamp(Math.min(width / 14, height / 10), 18, 46);

    return {
        scale,
        originX: width / 2,
        originY: Math.max(30, height * 0.16),
    };
};

const drawBoard = (context, scene, theme) => {
    const { scale, originX, originY, gridSize = GRID_SIZE } = scene;
    const boundary = [
        projectIsometric(-0.5, -0.5, 0, scale, originX, originY),
        projectIsometric(gridSize - 0.5, -0.5, 0, scale, originX, originY),
        projectIsometric(gridSize - 0.5, gridSize - 0.5, 0, scale, originX, originY),
        projectIsometric(-0.5, gridSize - 0.5, 0, scale, originX, originY),
    ];
    const lowerBoundary = boundary.map((point) => ({ ...point, y: point.y + BOARD_DEPTH }));

    context.save();
    context.filter = 'blur(10px)';
    polygon(context, boundary.map((point) => ({ x: point.x, y: point.y + BOARD_DEPTH + 9 })), theme.shadow);
    context.restore();
    polygon(context, [boundary[3], boundary[2], lowerBoundary[2], lowerBoundary[3]], theme.boardLeft);
    polygon(context, [boundary[1], boundary[2], lowerBoundary[2], lowerBoundary[1]], theme.boardRight);
    polygon(context, boundary, theme.boardTop);
};

const blockPoints = (x, y, height, scene) => {
    const { scale, originX, originY } = scene;
    const top = [
        projectIsometric(x, y - BLOCK_GAP, height, scale, originX, originY),
        projectIsometric(x + BLOCK_GAP, y, height, scale, originX, originY),
        projectIsometric(x, y + BLOCK_GAP, height, scale, originX, originY),
        projectIsometric(x - BLOCK_GAP, y, height, scale, originX, originY),
    ];
    const bottom = top.map((point) => ({ ...point, y: point.y + height }));

    return { top, bottom };
};

const drawBlock = (context, x, y, height, colors, scene) => {
    const points = blockPoints(x, y, height, scene);
    polygon(context, [points.top[2], points.top[3], points.bottom[3], points.bottom[2]], colors.left);
    polygon(context, [points.top[1], points.top[2], points.bottom[2], points.bottom[1]], colors.right);
    polygon(context, points.top, colors.top);
};

const easeOut = (value) => 1 - ((1 - clamp(value, 0, 1)) ** 3);

export function drawKavlingScene(context, width, height, state = {}) {
    const theme = THEME_SCENE[state.theme === 'dark' ? 'dark' : 'light'];
    const gridSize = state.compact ? 5 : GRID_SIZE;
    const scene = { ...sceneSize(width, height), gridSize };
    const visibleTotal = gridSize * gridSize;
    const lit = clamp(state.compact ? Math.round((state.lit ?? 0) * visibleTotal / TOTAL_PLOTS) : state.lit ?? 0, 0, visibleTotal);
    const hoveredIndex = state.hoveredIndex ?? -1;
    const now = state.now ?? 0;
    const animation = state.animation;
    const order = buildPlotOrder(gridSize);

    context.clearRect(0, 0, width, height);
    drawBoard(context, scene, theme);

    order.forEach(({ index, x, y, rank }) => {
        let fraction = rank < lit ? 1 : 0;

        if (animation?.type === 'transition') {
            if (animation.to > animation.from && rank >= animation.from && rank < animation.to) {
                const delay = (rank - animation.from) * 12;
                fraction = easeOut((now - animation.started - delay) / 180);
            } else if (animation.to < animation.from && rank >= animation.to && rank < animation.from) {
                const delay = (animation.from - 1 - rank) * 12;
                fraction = 1 - easeOut((now - animation.started - delay) / 180);
            } else {
                fraction = rank < animation.to ? 1 : 0;
            }
        }

        if (state.errorFlash) {
            fraction = 1;
        }

        const nearby = state.hoveredIndex >= 0
            && Math.hypot(x - state.hoveredX, y - state.hoveredY) <= 2.3;
        const hoverLift = nearby ? 34 * Math.max(0, 1 - Math.hypot(x - state.hoveredX, y - state.hoveredY) / 2.3) : 0;
        const waveLift = animation?.type === 'wave' ? 12 * (0.5 + 0.5 * Math.sin(now / 180 + rank * 0.33)) : 0;
        const deadHeight = 14 + ((index * 17) % 7) - 3;
        const litHeight = rank === lit - 1 ? 46 : 24 + ((index * 7) % 11);
        const height = deadHeight + (litHeight - deadHeight) * clamp(fraction, 0, 1) + hoverLift + waveLift;
        const isOrange = state.errorFlash || rank === (state.accentIndex ?? lit - 1) || index === hoveredIndex;
        const colors = isOrange
            ? { top: theme.orangeTop, left: theme.orangeLeft, right: theme.orangeRight }
            : fraction > 0
                ? { top: theme.limeTop, left: theme.limeLeft, right: theme.limeRight }
                : { top: theme.deadTop, left: theme.deadLeft, right: theme.deadRight };

        drawBlock(context, x, y, height, colors, scene);
    });
}

const readTheme = () => document.documentElement.dataset.theme === 'dark' ? 'dark' : 'light';

const getFormInputs = (form) => {
    const email = form?.querySelector('input[type="email"], input[name="email"]');
    const passwords = [...(form?.querySelectorAll('input[type="password"], input[name="password"]') ?? [])];

    return { email, password: passwords[0] ?? null };
};

const inputLengths = (inputs) => ({
    email: inputs.email?.value?.length ?? 0,
    password: inputs.password?.value?.length ?? 0,
});

const raf = (callback) => window.requestAnimationFrame(callback);

class KavlingGridController {
    constructor(panel) {
        this.panel = panel;
        this.canvas = panel.querySelector('canvas');
        this.context = this.canvas?.getContext('2d');
        this.shell = panel.closest('[data-auth-shell]');
        this.form = panel.closest('[data-auth-shell]')?.querySelector('.guest-card form');
        this.inputs = getFormInputs(this.form);
        this.reducedMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)');
        this.hoverMedia = window.matchMedia?.('(hover: hover)');
        this.frame = 0;
        this.resizeObserver = null;
        this.state = {
            lit: 0,
            hoveredIndex: -1,
            hoveredX: -100,
            hoveredY: -100,
            animation: null,
            errorFlash: false,
            submitting: false,
            theme: readTheme(),
            compact: false,
        };
    }

    mount() {
        if (!this.context || !this.canvas) {
            return;
        }

        this.panel.classList.add('is-ready');
        this.shell?.classList.add('has-grid');
        this.bind();
        this.resizeObserver = new ResizeObserver(() => this.resize());
        this.resizeObserver.observe(this.panel.querySelector('[data-kavling-stage]') ?? this.panel);
        this.resize();
        this.updateFromInputs(false);

        window.setTimeout(() => this.updateFromInputs(false), 100);
        window.setTimeout(() => this.updateFromInputs(false), 500);

        if (this.panel.dataset.hasErrors === 'true') {
            this.showErrorState();
        }

        window.addEventListener('themechange', this.onThemeChange);
        document.addEventListener('visibilitychange', this.onVisibilityChange);
        this.focusInitialField();
    }

    bind() {
        const update = () => this.updateFromInputs(true);
        this.form?.addEventListener('input', update);
        this.form?.addEventListener('change', update);
        this.form?.addEventListener('blur', update, true);
        this.form?.addEventListener('submit', this.onSubmit);
        this.panel.addEventListener('pointermove', this.onPointerMove);
        this.panel.addEventListener('pointerleave', this.onPointerLeave);
        this.panel.addEventListener('pointerdown', this.onPointerDown);
        this.form?.querySelectorAll('[data-password-toggle]').forEach((button) => {
            button.addEventListener('click', () => this.togglePassword(button));
        });
    }

    resize() {
        const stage = this.panel.querySelector('[data-kavling-stage]');
        const rect = stage?.getBoundingClientRect();

        if (!rect?.width || !rect.height) {
            return;
        }

        const ratio = Math.min(window.devicePixelRatio || 1, 2);
        this.canvas.width = Math.round(rect.width * ratio);
        this.canvas.height = Math.round(rect.height * ratio);
        this.canvas.style.width = `${rect.width}px`;
        this.canvas.style.height = `${rect.height}px`;
        this.context.setTransform(ratio, 0, 0, ratio, 0, 0);
        this.width = rect.width;
        this.height = rect.height;
        this.state.compact = rect.width < 768;
        this.render();
    }

    updateFromInputs(animate = true) {
        const lengths = inputLengths(this.inputs);
        const next = countLitPlots(lengths.email, lengths.password);
        const previous = this.state.lit;

        this.panel.dataset.lit = String(next);
        this.panel.dataset.total = String(TOTAL_PLOTS);
        const count = this.panel.querySelector('[data-kavling-count]');
        const hint = this.panel.querySelector('[data-kavling-hint]');
        if (count) count.textContent = `${next} / ${TOTAL_PLOTS} kavling`;
        if (hint) hint.textContent = next > 0
            ? `${lengths.email} huruf email + ${lengths.password} karakter kata sandi = ${next} kavling menyala.`
            : 'Gerakkan kursor di atas kavling. Setiap huruf yang kamu ketik akan membangun satu kavling.';

        this.state.lit = next;
        if (animate && previous !== next && !this.isReducedMotion()) {
            this.state.animation = { type: 'transition', from: previous, to: next, started: performance.now() };
            this.schedule();
        } else {
            this.state.animation = null;
            this.render();
        }
    }

    showErrorState() {
        this.state.errorFlash = true;
        this.state.animation = null;
        this.panel.closest('.guest-card')?.classList.add('is-error');
        this.render();
        window.setTimeout(() => {
            this.state.errorFlash = false;
            this.state.lit = 0;
            this.render();
            this.panel.closest('.guest-card')?.classList.remove('is-error');
        }, 400);
    }

    focusInitialField() {
        window.setTimeout(() => {
            const focusId = this.panel.dataset.focus;
            const input = focusId ? document.getElementById(focusId) : this.inputs.email;
            input?.focus({ preventScroll: true });
        }, 0);
    }

    onSubmit = (event) => {
        if (this.form?.dataset.submitting === 'true') {
            event.preventDefault();
            return;
        }

        this.form.dataset.submitting = 'true';
        if (this.form.dataset.loginForm === 'true') {
            try {
                window.sessionStorage.setItem('oasis:welcome', '1');
            } catch {
                // Login remains functional when storage is unavailable.
            }
        }

        const button = this.form.querySelector('[data-login-submit]');
        if (button) {
            button.disabled = true;
            button.innerHTML = '<span class="guest-spinner" aria-hidden="true"></span><span>Memeriksa…</span>';
        }
        this.state.submitting = true;
        this.state.lit = TOTAL_PLOTS;
        this.state.animation = { type: 'wave' };
        this.schedule();
    };

    togglePassword(button) {
        const input = document.getElementById(button.dataset.passwordTarget);
        if (!input) return;
        const visible = input.type === 'text';
        input.type = visible ? 'password' : 'text';
        button.setAttribute('aria-pressed', visible ? 'false' : 'true');
        button.setAttribute('aria-label', visible ? 'Tampilkan kata sandi' : 'Sembunyikan kata sandi');
        button.title = visible ? 'Tampilkan kata sandi' : 'Sembunyikan kata sandi';
    }

    onPointerMove = (event) => {
        if (!this.hoverMedia?.matches || this.state.submitting) return;
        const index = this.pointerIndex(event);
        if (index < 0) return;
        this.state.hoveredIndex = index;
        const gridSize = this.state.compact ? 5 : GRID_SIZE;
        this.state.hoveredX = index % gridSize;
        this.state.hoveredY = Math.floor(index / gridSize);
        this.render();
    };

    onPointerLeave = () => {
        this.state.hoveredIndex = -1;
        this.render();
    };

    onPointerDown = (event) => {
        if (event.pointerType !== 'touch') return;
        const index = this.pointerIndex(event);
        if (index < 0) return;
        this.state.hoveredIndex = index;
        const gridSize = this.state.compact ? 5 : GRID_SIZE;
        this.state.hoveredX = index % gridSize;
        this.state.hoveredY = Math.floor(index / gridSize);
        this.render();
        window.setTimeout(() => {
            this.state.hoveredIndex = -1;
            this.render();
        }, 300);
    };

    pointerIndex(event) {
        const rect = this.canvas.getBoundingClientRect();
        const { scale, originX, originY } = sceneSize(rect.width, rect.height);
        const gridSize = this.state.compact ? 5 : GRID_SIZE;
        const dx = event.clientX - rect.left - originX;
        const dy = event.clientY - rect.top - originY;
        const screenY = dy / (scale / 2);
        const screenX = dx / scale;
        const x = Math.round((screenX + screenY) / 2);
        const y = Math.round((screenY - screenX) / 2);

        return x >= 0 && x < gridSize && y >= 0 && y < gridSize ? y * gridSize + x : -1;
    }

    onThemeChange = (event) => {
        this.state.theme = event.detail?.theme === 'dark' ? 'dark' : readTheme();
        this.render();
    };

    onVisibilityChange = () => {
        if (document.hidden) {
            window.cancelAnimationFrame(this.frame);
            this.frame = 0;
        } else if (this.state.submitting || this.state.animation) {
            this.schedule();
        } else {
            this.render();
        }
    };

    isReducedMotion() {
        return Boolean(this.reducedMotion?.matches);
    }

    schedule() {
        if (this.frame || document.hidden) return;
        this.frame = raf((now) => {
            this.frame = 0;
            this.render(now);
            if (this.state.submitting) {
                this.schedule();
            } else if (this.state.animation) {
                const elapsed = now - this.state.animation.started;
                if (this.state.animation.type === 'transition' && elapsed >= 180 + Math.abs(this.state.animation.to - this.state.animation.from) * 12) {
                    this.state.animation = null;
                    this.render(now);
                } else {
                    this.schedule();
                }
            }
        });
    }

    render(now = performance.now()) {
        if (!this.context || !this.width || !this.height) return;
        drawKavlingScene(this.context, this.width, this.height, { ...this.state, now });
    }
}

export function initGuestAuth(root = document) {
    const panel = root.querySelector('[data-auth-kavling-grid]');
    if (!panel) return null;
    const controller = new KavlingGridController(panel);
    controller.mount();
    return controller;
}
