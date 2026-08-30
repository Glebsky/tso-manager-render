import '../css/app.css';
import './bootstrap';
import { createApp } from 'vue';
import App from './App.vue';
import router from './router';
import lang, { initLang, t, game, gameAny } from './lang';

async function bootstrap() {
    await initLang();

    const app = createApp(App);

    // i18n helpers available in every component template.
    app.config.globalProperties.t = t;
    app.config.globalProperties.game = game;
    app.config.globalProperties.gameAny = gameAny;
    app.config.globalProperties.$lang = lang;

    app.use(router);
    app.mount('#app');
}

bootstrap();

