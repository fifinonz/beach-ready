import { createApp } from 'vue';
import App from './App.vue';
import { router } from './router.js';
import { checkInstallHint, registerServiceWorker } from './pwa.js';

createApp(App).use(router).mount('#app');

registerServiceWorker();
checkInstallHint();
