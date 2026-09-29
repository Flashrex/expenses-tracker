import Alpine from 'alpinejs';
import donutChart from './donut-chart';
import reviewQueue from './review-queue';

window.Alpine = Alpine;
Alpine.data('reviewQueue', reviewQueue);
Alpine.data('donutChart', donutChart);
Alpine.start();
