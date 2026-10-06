import Alpine from 'alpinejs';
import Sortable from 'sortablejs';
import registerPwa from './pwa';

window.Alpine = Alpine;
window.Sortable = Sortable;

registerPwa(Alpine);

Alpine.start();
