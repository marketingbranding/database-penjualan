import Alpine from 'alpinejs';
import registerPwa from './pwa';
import initializeTheme from './theme';

window.Alpine = Alpine;

initializeTheme();
registerPwa(Alpine);

Alpine.start();

document.addEventListener('toggle', (event) => {
    const menu = event.target;

    if (!(menu instanceof HTMLDetailsElement) || ! menu.matches('.module-row-menu') || ! menu.open) {
        return;
    }

    document.querySelectorAll('.module-row-menu[open]').forEach((openMenu) => {
        if (openMenu !== menu) {
            openMenu.removeAttribute('open');
        }
    });
}, true);

document.addEventListener('click', (event) => {
    if (event.target.closest('.module-row-menu')) {
        return;
    }

    document.querySelectorAll('.module-row-menu[open]').forEach((menu) => menu.removeAttribute('open'));
});

const loadSpatialWorkspace = () => {
    if (document.querySelector('[data-spatial-workspace]')) {
        import('./spatial-workspace');
    }
};

const loadAuthFeatures = () => {
    const guestGrid = document.querySelector('[data-auth-kavling-grid]');
    const welcomeOverlay = document.querySelector('[data-auth-welcome]');

    if (guestGrid) {
        import('./auth/kavling-grid').then(({ initGuestAuth }) => initGuestAuth());
    }

    if (welcomeOverlay) {
        import('./auth/welcome-overlay').then(({ initWelcomeOverlay }) => initWelcomeOverlay());
    }
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', loadSpatialWorkspace, { once: true });
    document.addEventListener('DOMContentLoaded', loadAuthFeatures, { once: true });
} else {
    loadSpatialWorkspace();
    loadAuthFeatures();
}
