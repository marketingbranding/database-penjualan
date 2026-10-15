const STORAGE_KEY = 'oasis:login-sound';
const MIN_INTERVAL_MS = 40;
const MAX_CASCADE_NOTES = 6;
const PENTATONIC_SEMITONES = [0, 2, 4, 7, 9];

export function frequencyForRank(rank = 0, root = 220) {
    const safeRank = Math.max(0, Math.floor(Number(rank) || 0));
    const octave = Math.floor(safeRank / PENTATONIC_SEMITONES.length);
    const semitone = PENTATONIC_SEMITONES[safeRank % PENTATONIC_SEMITONES.length] + octave * 12;

    return root * (2 ** (semitone / 12));
}

export function cascadeNoteCount(delta, max = MAX_CASCADE_NOTES) {
    return Math.min(max, Math.max(0, Math.floor(Number(delta) || 0)));
}

export function shouldPlayCountChange(previous, next, lastPlayedAt = -Infinity, now = 0, interval = MIN_INTERVAL_MS) {
    const delta = Number(next) - Number(previous);

    if (delta === 0 || now - lastPlayedAt < interval) {
        return { type: 'none', count: 0 };
    }

    return delta > 0
        ? { type: delta === 1 ? 'tok' : 'cascade', count: delta }
        : { type: 'remove', count: Math.abs(delta) };
}

export function fullGridTrigger(previous, next, armed = true, total = 49) {
    if (next < total) {
        return { play: false, armed: true };
    }

    return { play: previous < total && armed, armed: false };
}

const readPreference = () => {
    try {
        return window.localStorage.getItem(STORAGE_KEY) !== 'off';
    } catch {
        return true;
    }
};

const savePreference = (enabled) => {
    try {
        window.localStorage.setItem(STORAGE_KEY, enabled ? 'on' : 'off');
    } catch {
        // Sound still works for the current page if storage is unavailable.
    }
};

class LoginSound {
    constructor(root = document) {
        this.root = root;
        this.enabled = readPreference();
        this.context = null;
        this.master = null;
        this.compressor = null;
        this.lastPlayedAt = -Infinity;
        this.fullGridArmed = true;
        this.timers = new Set();
        this.bindToggle();
        this.bindGestures();
        this.updateToggle();
    }

    bindToggle() {
        this.root.querySelectorAll('[data-login-sound-toggle]').forEach((toggle) => {
            toggle.addEventListener('click', () => {
                this.setEnabled(!this.enabled, true);
            });
        });
    }

    bindGestures() {
        const unlock = (event) => {
            if (event.target?.closest?.('[data-login-sound-toggle]')) return;
            if (this.enabled) this.unlock();
        };
        this.root.addEventListener('keydown', unlock, { capture: true, passive: true });
        this.root.addEventListener('pointerdown', unlock, { capture: true, passive: true });
        this.root.addEventListener('click', unlock, { capture: true, passive: true });
        document.addEventListener('visibilitychange', () => {
            if (!this.context) return;
            if (document.hidden) {
                this.context.suspend().catch(() => {});
            } else if (this.enabled) {
                this.context.resume().catch(() => {});
            }
        });
    }

    setEnabled(enabled, fromGesture = false) {
        this.enabled = enabled;
        savePreference(enabled);
        if (enabled && fromGesture) {
            this.unlock();
        }
        if (!enabled) {
            this.close();
        }
        this.updateToggle();
    }

    updateToggle() {
        this.root.querySelectorAll('[data-login-sound-toggle]').forEach((toggle) => {
            toggle.setAttribute('aria-pressed', this.enabled ? 'true' : 'false');
            toggle.setAttribute('aria-label', this.enabled ? 'Matikan suara' : 'Nyalakan suara');
            toggle.setAttribute('title', this.enabled ? 'Matikan suara' : 'Nyalakan suara');
        });
    }

    unlock() {
        if (!this.enabled || this.context || typeof window.AudioContext === 'undefined') {
            return false;
        }

        try {
            this.context = new window.AudioContext();
            this.compressor = this.context.createDynamicsCompressor();
            this.compressor.threshold.value = -24;
            this.compressor.knee.value = 18;
            this.compressor.ratio.value = 8;
            this.compressor.attack.value = 0.003;
            this.compressor.release.value = 0.15;
            this.master = this.context.createGain();
            this.master.gain.value = 1;
            this.master.connect(this.compressor);
            this.compressor.connect(this.context.destination);
            this.context.resume().catch(() => {});

            return true;
        } catch {
            this.close();

            return false;
        }
    }

    close() {
        this.timers.forEach((timer) => window.clearTimeout(timer));
        this.timers.clear();
        if (this.context) {
            this.context.close().catch(() => {});
        }
        this.context = null;
        this.master = null;
        this.compressor = null;
    }

    handleCountChange(previous, next, now = performance.now()) {
        const change = shouldPlayCountChange(previous, next, this.lastPlayedAt, now);
        const fullGrid = fullGridTrigger(previous, next, this.fullGridArmed);
        this.fullGridArmed = fullGrid.armed;

        if (fullGrid.play && this.context && this.enabled && !document.hidden) {
            this.playFullGrid();
        }

        if (change.type === 'none' || !this.context || !this.enabled || document.hidden) {
            return;
        }

        this.lastPlayedAt = now;
        if (change.type === 'cascade') {
            this.playCascade(previous, next);
        } else {
            this.playTok(next - 1, change.type === 'remove');
        }

    }

    playTok(rank, quiet = false) {
        if (!this.context || !this.master || document.hidden) return;
        const start = this.context.currentTime;
        const oscillator = this.context.createOscillator();
        const gain = this.context.createGain();
        oscillator.type = 'triangle';
        oscillator.frequency.setValueAtTime(frequencyForRank(rank), start);
        gain.gain.setValueAtTime(0.0001, start);
        gain.gain.exponentialRampToValueAtTime(quiet ? 0.035 : 0.07, start + 0.006);
        gain.gain.exponentialRampToValueAtTime(0.0001, start + (quiet ? 0.065 : 0.08));
        oscillator.connect(gain).connect(this.master);
        oscillator.start(start);
        oscillator.stop(start + 0.09);
    }

    playCascade(previous, next) {
        const count = cascadeNoteCount(next - previous);
        for (let offset = 0; offset < count; offset++) {
            const timer = window.setTimeout(() => {
                this.timers.delete(timer);
                this.playTok(previous + offset);
            }, offset * 12);
            this.timers.add(timer);
        }
    }

    playFullGrid() {
        if (!this.context || !this.master || document.hidden) return;
        const start = this.context.currentTime;
        [0, 2, 4].forEach((offset, index) => {
            const oscillator = this.context.createOscillator();
            const gain = this.context.createGain();
            oscillator.type = 'sine';
            oscillator.frequency.value = frequencyForRank(25 + offset, 196);
            gain.gain.setValueAtTime(0.0001, start + index * 0.04);
            gain.gain.exponentialRampToValueAtTime(0.055, start + index * 0.04 + 0.01);
            gain.gain.exponentialRampToValueAtTime(0.0001, start + 0.24);
            oscillator.connect(gain).connect(this.master);
            oscillator.start(start + index * 0.04);
            oscillator.stop(start + 0.26);
        });
    }

    playError() {
        if (!this.context || !this.master || document.hidden) return;
        this.playTone(110, 0.12, 0.045, 'sine');
    }

    playSubmit() {
        if (!this.context || !this.master || document.hidden) return;
        this.playTone(180, 0.07, 0.05, 'triangle');
    }

    playTap() {
        if (!this.context || !this.master || document.hidden) return;
        this.playTone(240, 0.045, 0.025, 'sine');
    }

    playWelcome() {
        if (!this.context || !this.master || document.hidden) return;
        [0, 4, 7].forEach((offset, index) => this.playTone(frequencyForRank(offset + 12, 220), 0.32, 0.035, 'sine', index * 0.035));
    }

    playTone(frequency, duration, volume, type, delay = 0) {
        const start = this.context.currentTime + delay;
        const oscillator = this.context.createOscillator();
        const gain = this.context.createGain();
        oscillator.type = type;
        oscillator.frequency.value = frequency;
        gain.gain.setValueAtTime(0.0001, start);
        gain.gain.exponentialRampToValueAtTime(volume, start + 0.008);
        gain.gain.exponentialRampToValueAtTime(0.0001, start + duration);
        oscillator.connect(gain).connect(this.master);
        oscillator.start(start);
        oscillator.stop(start + duration + 0.01);
    }
}

let sharedSound = null;

export function initLoginSound(root = document) {
    sharedSound ??= new LoginSound(root);

    return sharedSound;
}

export function getLoginSound() {
    return sharedSound;
}

export { MAX_CASCADE_NOTES, MIN_INTERVAL_MS };
