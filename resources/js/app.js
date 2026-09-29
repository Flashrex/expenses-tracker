import Alpine from 'alpinejs';
import reviewQueue from './review-queue';

window.Alpine = Alpine;
Alpine.data('reviewQueue', reviewQueue);
Alpine.start();
