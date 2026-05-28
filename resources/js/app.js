import './bootstrap';
import Alpine from 'alpinejs';
import intersect from '@alpinejs/intersect';
import countUp from './plugins/count-up';
import stagger from './plugins/stagger';

Alpine.plugin(intersect);
Alpine.plugin(countUp);
Alpine.plugin(stagger);

window.Alpine = Alpine;
Alpine.start();
