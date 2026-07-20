import './bootstrap';
import { createApp } from 'vue';
import App from './App.vue';
import router from './router';
import lang, { t, game, gameAny } from './lang';

const app = createApp(App);

// i18n helpers available in every component template.
app.config.globalProperties.t = t;
app.config.globalProperties.game = game;
app.config.globalProperties.gameAny = gameAny;
app.config.globalProperties.$lang = lang;

app.use(router);
app.mount('#app');
