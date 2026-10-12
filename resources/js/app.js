import Alpine from 'alpinejs';
import registerPwa from './pwa';

window.Alpine = Alpine;

registerPwa(Alpine);

Alpine.start();

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
