import { createRouter, createWebHistory } from 'vue-router';
import Dashboard from './views/Dashboard.vue';
import Accounts from './views/Accounts.vue';
import Tasks from './views/Tasks.vue';
import Logs from './views/Logs.vue';
import Settings from './views/Settings.vue';

const routes = [
    {
        path: '/',
        name: 'dashboard',
        component: Dashboard,
    },
    {
        path: '/accounts',
        name: 'accounts',
        component: Accounts,
    },
    {
        path: '/tasks',
        name: 'tasks',
        component: Tasks,
    },
    {
        path: '/logs',
        name: 'logs',
        component: Logs,
    },
    {
        path: '/settings',
        name: 'settings',
        component: Settings,
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

export default router;
