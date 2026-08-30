import { createRouter, createWebHistory } from 'vue-router';

const routes = [
    // 1. Root Route: Public Market Analytics
    {
        path: '/',
        name: 'market-public',
        component: () => import('./views/PublicMarketAnalytics.vue'),
        meta: { guest: true, publicLayout: true },
    },
    {
        path: '/market/public',
        redirect: '/',
    },
    {
        path: '/public/market',
        redirect: '/',
    },

    // 2. Admin Routes (/admin/*)
    {
        path: '/admin',
        name: 'dashboard',
        component: () => import('./views/Dashboard.vue'),
    },
    {
        path: '/admin/accounts',
        name: 'accounts',
        component: () => import('./views/Accounts.vue'),
    },
    {
        path: '/admin/accounts/:id',
        name: 'account-detail',
        component: () => import('./views/AccountDetail.vue'),
    },
    {
        path: '/admin/tasks',
        name: 'tasks',
        component: () => import('./views/Tasks.vue'),
    },
    {
        path: '/admin/logs',
        name: 'logs',
        component: () => import('./views/Logs.vue'),
    },
    {
        path: '/admin/settings',
        name: 'settings',
        component: () => import('./views/Settings.vue'),
    },
    {
        path: '/admin/market',
        name: 'market',
        component: () => import('./views/MarketAnalytics.vue'),
    },
    {
        path: '/admin/register',
        name: 'register',
        component: () => import('./views/Register.vue'),
        meta: { guest: true },
    },

    // 3. Fallback redirects for legacy non-prefixed routes
    {
        path: '/accounts',
        redirect: '/admin/accounts',
    },
    {
        path: '/accounts/:id',
        redirect: to => `/admin/accounts/${to.params.id}`,
    },
    {
        path: '/tasks',
        redirect: '/admin/tasks',
    },
    {
        path: '/logs',
        redirect: '/admin/logs',
    },
    {
        path: '/settings',
        redirect: '/admin/settings',
    },
    {
        path: '/market',
        redirect: '/admin/market',
    },
    {
        path: '/register',
        redirect: '/admin/register',
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

router.beforeEach((to, from, next) => {
    const isAuthenticated = !!(window.__AUTH_USER__ && window.__AUTH_USER__.id);
    if (to.name === 'register' && isAuthenticated) {
        next({ name: 'dashboard' });
    } else {
        next();
    }
});

export default router;
