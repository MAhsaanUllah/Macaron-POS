import Alpine from 'alpinejs';
import axios from 'axios';
import Chart from 'chart.js/auto';

window.Alpine = Alpine;
window.axios = axios;
window.Chart = Chart;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
const token = document.head.querySelector('meta[name="csrf-token"]');
if (token) {
    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = token.content;
}

document.addEventListener('DOMContentLoaded', () => {
    if (!window.AlpineStarted) {
        window.AlpineStarted = true;
        Alpine.start();
    }
});
