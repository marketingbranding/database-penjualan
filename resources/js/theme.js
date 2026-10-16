const THEME_STORAGE_KEY = 'theme';
const THEME_PREFERENCES = ['light', 'dark', 'system'];

const getSystemTheme = () => (
    window.matchMedia?.('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'
);

const getStoredTheme = () => {
    try {
        const storedTheme = window.localStorage.getItem(THEME_STORAGE_KEY);

        return THEME_PREFERENCES.includes(storedTheme) ? storedTheme : 'system';
    } catch {
        return 'system';
    }
};

const getEffectiveTheme = (preference) => preference === 'system' ? getSystemTheme() : preference;

const isReducedMotion = () => window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;

const updateThemeControls = (preference, theme) => {
    document.querySelectorAll('[data-theme-choice]').forEach((control) => {
        const isSelected = control.dataset.themeChoice === preference;

        control.setAttribute('aria-pressed', isSelected ? 'true' : 'false');
        control.setAttribute('data-active', isSelected ? 'true' : 'false');
    });

    document.querySelectorAll('[data-theme-toggle]').forEach((control) => {
        const nextTheme = theme === 'dark' ? 'light' : 'dark';

        control.setAttribute('aria-pressed', theme === 'dark' ? 'true' : 'false');
        control.setAttribute('aria-label', nextTheme === 'dark' ? 'Aktifkan mode gelap' : 'Aktifkan mode terang');
        control.setAttribute('title', nextTheme === 'dark' ? 'Aktifkan mode gelap' : 'Aktifkan mode terang');
    });
};

let activeTheme = document.documentElement.dataset.theme || 'light';

const applyTheme = (preference, { transition = true, announce = true } = {}) => {
    const theme = getEffectiveTheme(preference);
    const previousTheme = document.documentElement.dataset.theme;

    if (transition && previousTheme !== theme && !isReducedMotion()) {
        document.documentElement.classList.add('theme-transition');
        window.setTimeout(() => document.documentElement.classList.remove('theme-transition'), 200);
    }

    document.documentElement.dataset.theme = theme;
    document.documentElement.style.colorScheme = theme;
    activeTheme = theme;
    updateThemeControls(preference, theme);

    if (announce && previousTheme !== theme) {
        window.dispatchEvent(new CustomEvent('themechange', { detail: { theme } }));
    }
};

const setTheme = (preference) => {
    if (!THEME_PREFERENCES.includes(preference)) {
        return;
    }

    try {
        window.localStorage.setItem(THEME_STORAGE_KEY, preference);
    } catch {
        // The active theme still applies when storage is unavailable.
    }

    applyTheme(preference);
};

const getTheme = () => getStoredTheme();

const toggleTheme = () => setTheme(activeTheme === 'dark' ? 'light' : 'dark');

const initializeTheme = () => {
    const mediaQuery = window.matchMedia?.('(prefers-color-scheme: dark)');

    applyTheme(getStoredTheme(), { transition: false, announce: false });

    document.addEventListener('click', (event) => {
        const choice = event.target.closest('[data-theme-choice]');
        const toggle = event.target.closest('[data-theme-toggle]');

        if (choice) {
            setTheme(choice.dataset.themeChoice);
        } else if (toggle) {
            toggleTheme();
        }
    }, true);

    window.addEventListener('storage', (event) => {
        if (event.key === THEME_STORAGE_KEY) {
            applyTheme(THEME_PREFERENCES.includes(event.newValue) ? event.newValue : 'system');
        }
    });

    mediaQuery?.addEventListener?.('change', () => {
        if (getStoredTheme() === 'system') {
            applyTheme('system');
        }
    });
};

window.OasisTheme = {
    getTheme,
    getEffectiveTheme: () => activeTheme,
    setTheme,
    toggleTheme,
};

export default initializeTheme;
