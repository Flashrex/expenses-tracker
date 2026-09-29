import Alpine from 'alpinejs';
import donutChart from './donut-chart';
import entriesCard from './entries-card';
import reviewQueue from './review-queue';
import trendChart from './trend-chart';
import uploadDropzone from './upload-dropzone';

window.Alpine = Alpine;
Alpine.data('reviewQueue', reviewQueue);
Alpine.data('donutChart', donutChart);
Alpine.data('trendChart', trendChart);
Alpine.data('entriesCard', entriesCard);
Alpine.data('uploadDropzone', uploadDropzone);
Alpine.start();
