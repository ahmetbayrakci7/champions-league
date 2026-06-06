import './bootstrap';

import { createApp } from 'vue';
import { createPinia } from 'pinia';
import App from './components/App.vue';
import { i18n } from './i18n';

document.documentElement.lang = i18n.locale;

createApp(App).use(createPinia()).mount('#app');
