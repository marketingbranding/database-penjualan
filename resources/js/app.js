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

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', loadSpatialWorkspace, { once: true });
} else {
    loadSpatialWorkspace();
}
