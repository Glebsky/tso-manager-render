import { createRouter, createWebHistory } from 'vue-router';

const routes = [
    {
        path: '/',
        name: 'dashboard',
        component: () => import('./views/Dashboard.vue'),
    },
    {
        path: '/accounts',
        name: 'accounts',
        component: () => import('./views/Accounts.vue'),
    },
    {
        path: '/accounts/:id',
        name: 'account-detail',
        component: () => import('./views/AccountDetail.vue'),
    },
    {
        path: '/tasks',
        name: 'tasks',
        component: () => import('./views/Tasks.vue'),
    },
    {
        path: '/logs',
        name: 'logs',
        component: () => import('./views/Logs.vue'),
    },
    {
        path: '/settings',
        name: 'settings',
        component: () => import('./views/Settings.vue'),
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

export default router;
