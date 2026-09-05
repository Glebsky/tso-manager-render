import { createRouter, createWebHistory } from 'vue-router';
import { t } from './lang';

const routes = [
    // 1. Root Route: Public Market Analytics
    {
        path: '/',
        name: 'market-public',
        component: () => import('./views/PublicMarketAnalytics.vue'),
        meta: { guest: true, publicLayout: true, rawTitle: 'TSO Market Analytics' },
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
        meta: { titleKey: 'nav.dashboard' },
    },
    {
        path: '/admin/accounts',
        name: 'accounts',
        component: () => import('./views/Accounts.vue'),
        meta: { titleKey: 'nav.accounts' },
    },
    {
        path: '/admin/accounts/:id',
        name: 'account-detail',
        component: () => import('./views/AccountDetail.vue'),
        meta: { titleKey: 'nav.accounts' },
    },
    {
        path: '/admin/tasks',
        name: 'tasks',
        component: () => import('./views/Tasks.vue'),
        meta: { titleKey: 'nav.task_planner' },
    },
    {
        path: '/admin/logs',
        name: 'logs',
        component: () => import('./views/Logs.vue'),
        meta: { titleKey: 'nav.logs' },
    },
    {
        path: '/admin/settings',
        name: 'settings',
        component: () => import('./views/Settings.vue'),
        meta: { titleKey: 'nav.settings' },
    },
    {
        path: '/admin/market',
        name: 'market',
        component: () => import('./views/MarketAnalytics.vue'),
        meta: { titleKey: 'nav.market_analytics' },
    },
    {
        path: '/admin/register',
        name: 'register',
        component: () => import('./views/Register.vue'),
        meta: { guest: true, titleKey: 'register.title' },
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

router.afterEach((to) => {
    if (to.meta?.rawTitle) {
        document.title = to.meta.rawTitle;
    } else if (to.name === 'market-public') {
        document.title = 'TSO Market Analytics';
    } else if (to.meta?.titleKey) {
        const pageTitle = t(to.meta.titleKey);
        document.title = pageTitle ? `${pageTitle} · TSO Manager` : 'TSO Manager';
    } else {
        document.title = 'TSO Manager';
    }
});

export default router;
