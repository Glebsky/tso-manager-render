<template>
    <div class="relative z-10 flex h-full min-h-screen">
        <!-- Background ambient effects -->
        <div class="fixed inset-0 z-0 overflow-hidden pointer-events-none">
            <div class="absolute -top-40 -left-40 w-96 h-96 bg-emerald-500/10 rounded-full blur-3xl"></div>
            <div class="absolute top-1/3 -right-20 w-80 h-80 bg-teal-500/8 rounded-full blur-3xl"></div>
            <div class="absolute -bottom-40 left-1/3 w-96 h-96 bg-emerald-600/5 rounded-full blur-3xl"></div>
        </div>

        <!-- Mobile backdrop overlay -->
        <transition name="fade">
            <div v-if="showSidebar && mobileMenuOpen"
                 @click="mobileMenuOpen = false"
                 class="fixed inset-0 z-40 bg-black/70 backdrop-blur-sm lg:hidden transition-opacity"></div>
        </transition>

        <!-- SIDEBAR DRAWER -->
        <aside v-if="showSidebar"
               :class="[
                   mobileMenuOpen ? 'translate-x-0 shadow-2xl' : '-translate-x-full lg:translate-x-0',
                   'glass-sidebar w-64 flex-shrink-0 flex flex-col h-full fixed left-0 top-0 z-50 lg:z-30 transition-transform duration-300 ease-in-out'
               ]">
            <!-- Logo -->
            <div class="px-6 py-6 border-b border-white/5 flex items-center justify-between">
                <router-link to="/admin" @click="mobileMenuOpen = false" class="flex items-center gap-3 group">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-emerald-500 to-teal-600 flex items-center justify-center shadow-lg shadow-emerald-500/25 group-hover:shadow-emerald-500/40 transition-all duration-300">
                        <svg class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.455-2.456L21.75 6l-1.036.259a3.375 3.375 0 0 0-2.455 2.456ZM16.894 20.567 16.5 21.75l-.394-1.183a2.25 2.25 0 0 0-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 0 0 1.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 0 0 1.423 1.423l1.183.394-1.183.394a2.25 2.25 0 0 0-1.423 1.423Z" />
                        </svg>
                    </div>
                    <div>
                        <h1 class="text-lg font-bold text-white group-hover:text-emerald-400 transition-colors">TSO Manager</h1>
                        <p class="text-xs text-white/30">Administration Panel</p>
                    </div>
                </router-link>

                <!-- Mobile drawer close button -->
                <button @click="mobileMenuOpen = false" class="lg:hidden p-1.5 text-white/40 hover:text-white rounded-lg hover:bg-white/5 transition-colors">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Navigation -->
            <nav class="flex-1 px-4 py-6 space-y-1 overflow-y-auto">
                <p class="px-4 text-[10px] font-semibold uppercase tracking-widest text-white/20 mb-3">Main Menu</p>

                <!-- Main Public Site -->
                <router-link to="/" @click="mobileMenuOpen = false" class="nav-link" :class="{ active: $route.path === '/' }">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
                    </svg>
                    <span class="font-medium">{{ t('nav.home') }}</span>
                </router-link>

                <!-- Dashboard -->
                <router-link to="/admin" @click="mobileMenuOpen = false" class="nav-link" :class="{ active: $route.path === '/admin' }">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25A2.25 2.25 0 0 1 13.5 8.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z" />
                    </svg>
                    <span class="font-medium">Dashboard</span>
                </router-link>

                <!-- Accounts -->
                <router-link to="/admin/accounts" @click="mobileMenuOpen = false" class="nav-link" :class="{ active: $route.path.startsWith('/admin/accounts') }">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                    </svg>
                    <span class="font-medium">Accounts</span>
                </router-link>

                <!-- Task Planner -->
                <router-link to="/admin/tasks" @click="mobileMenuOpen = false" class="nav-link" :class="{ active: $route.path === '/admin/tasks' }">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                    <span class="font-medium">Task Planner</span>
                </router-link>

                <!-- Logs -->
                <router-link to="/admin/logs" @click="mobileMenuOpen = false" class="nav-link" :class="{ active: $route.path === '/admin/logs' }">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 0 1 0 3.75H5.625a1.875 1.875 0 0 1 0-3.75Z" />
                    </svg>
                    <span class="font-medium">Logs</span>
                </router-link>

                <div class="pt-4">
                    <p class="px-4 text-[10px] font-semibold uppercase tracking-widest text-white/20 mb-3">Market Analytics</p>
                </div>

                <!-- Market Analytics -->
                <router-link to="/admin/market" @click="mobileMenuOpen = false" class="nav-link" :class="{ active: $route.path === '/admin/market' }">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18 9 11.25l4.306 4.307a11.95 11.95 0 0 1 5.814-5.518l2.74-1.22m0 0-3.75-1.002m3.75 1.002-1.002 3.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                    <span class="font-medium">Market Analytics</span>
                </router-link>

                <div class="pt-4">
                    <p class="px-4 text-[10px] font-semibold uppercase tracking-widest text-white/20 mb-3">System</p>
                </div>

                <!-- Settings -->
                <router-link to="/admin/settings" @click="mobileMenuOpen = false" class="nav-link" :class="{ active: $route.path === '/admin/settings' }">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                    </svg>
                    <span class="font-medium">Settings</span>
                </router-link>
            </nav>

            <!-- Sidebar footer -->
            <div class="px-4 py-4 border-t border-white/5 mt-auto">
                <div class="mb-3 flex items-center justify-between gap-3 px-2">
                    <div class="min-w-0">
                        <p class="truncate text-xs font-medium text-white/70">{{ authenticatedUser.name }}</p>
                        <p class="truncate text-[10px] text-white/30">{{ authenticatedUser.email }}</p>
                    </div>
                    <button type="button" @click="logout" :disabled="loggingOut"
                            class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg text-white/30 transition hover:bg-red-500/10 hover:text-red-400 disabled:opacity-50"
                            :title="t('nav.logout')">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6A2.25 2.25 0 0 0 5.25 5.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3-3H9m0 0 3-3m-3 3 3 3" />
                        </svg>
                    </button>
                </div>
                <div class="glass-card p-2.5">
                    <div class="flex items-center justify-center gap-2">
                        <div class="w-2 h-2 rounded-full bg-emerald-500 shadow-lg shadow-emerald-500/50 animate-pulse"></div>
                        <span class="text-xs font-medium text-emerald-400">{{ t('nav.system_online') }}</span>
                    </div>
                </div>
            </div>
        </aside>

        <!-- MAIN CONTENT CONTAINER -->
        <main :class="[showSidebar ? 'lg:ml-64' : '', 'flex-1 min-h-full flex flex-col w-full min-w-0']">
            <!-- Header bar -->
            <header v-if="showSidebar" class="h-16 border-b border-white/5 flex items-center justify-between px-4 sm:px-6 lg:px-8 bg-dark-950/40 backdrop-blur-md sticky top-0 z-20">
                <div class="flex items-center gap-3">
                    <!-- Mobile hamburger drawer toggle -->
                    <button @click="mobileMenuOpen = !mobileMenuOpen"
                            class="lg:hidden p-2 rounded-xl bg-white/5 border border-white/10 text-white/70 hover:text-white hover:bg-white/10 transition-all"
                            aria-label="Toggle Navigation Menu">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                        </svg>
                    </button>

                    <span class="text-xs text-white/40 font-medium">{{ t('nav.control_panel') }}</span>
                </div>

                <!-- Right side controls (Clock displays & Language Switcher) -->
                <div class="flex items-center gap-3">
<!--                    <div class="hidden sm:flex items-center gap-4 text-xs font-mono">-->
<!--                        <div v-if="serverTimeStr" class="flex items-center gap-1.5 px-3 py-1 rounded-lg bg-emerald-500/10 border border-emerald-500/20 text-emerald-400" title="Server Time">-->
<!--                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>-->
<!--                            <span>Server: {{ serverTimeStr }}</span>-->
<!--                        </div>-->
<!--                        <div v-if="localTimeStr" class="text-white/40 text-[11px]" title="Local Time">-->
<!--                            {{ localTimeStr }}-->
<!--                        </div>-->
<!--                    </div>-->
                    <LanguageSwitcher />
                </div>
            </header>

            <div :class="[showSidebar ? 'p-4 sm:p-6 lg:p-8' : ($route.meta.guest ? 'p-0' : 'p-4 md:p-8'), 'flex-1 overflow-y-auto w-full min-w-0']">
                <router-view v-slot="{ Component }">
                    <transition name="page" mode="out-in">
                        <component :is="Component" />
                    </transition>
                </router-view>
            </div>
        </main>
    </div>

    <!-- TOAST CONTAINER (outside main flex to ensure top-level stacking) -->
    <div class="fixed top-5 right-5 z-[9999] max-w-sm w-full pointer-events-none px-4 sm:px-0">
        <transition-group name="toast" tag="div" class="flex flex-col gap-3">
            <div v-for="t in toasts" :key="t.id"
                 class="glass-card p-4 pointer-events-auto shadow-2xl transition-all duration-300 w-full"
                 :class="{
                     'border-emerald-500/30 bg-emerald-500/10': t.type === 'success',
                     'border-red-500/30 bg-red-500/10': t.type === 'error',
                     'border-amber-500/30 bg-amber-500/10': t.type === 'warning'
                 }">
                <div class="flex items-start gap-3">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0"
                         :class="{
                             'bg-emerald-500/20 text-emerald-400': t.type === 'success',
                             'bg-red-500/20 text-red-400': t.type === 'error',
                             'bg-amber-500/20 text-amber-400': t.type === 'warning'
                         }">
                        <svg v-if="t.type === 'success'" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                        </svg>
                        <svg v-else class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                        </svg>
                    </div>
                    <p class="text-sm font-medium pt-1.5"
                       :class="{
                           'text-emerald-300': t.type === 'success',
                           'text-red-300': t.type === 'error',
                           'text-amber-300': t.type === 'warning'
                       }">
                        {{ t.message }}
                    </p>
                </div>
            </div>
        </transition-group>
    </div>
</template>

<script>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { useRoute } from 'vue-router';
import { toasts } from './toast';
import { intlLocale, t } from './lang';
import axios from 'axios';
import LanguageSwitcher from './components/LanguageSwitcher.vue';

export default {
    name: 'App',
    components: {
        LanguageSwitcher
    },
    setup() {
        const route = useRoute();
        const mobileMenuOpen = ref(false);
        const localTimeStr = ref('');
        const serverTimeStr = ref('');
        const serverOffset = ref(0);
        const authenticatedUser = ref(window.__AUTH_USER__ || {});
        const loggingOut = ref(false);
        let timer = null;

        watch(() => route.path, () => {
            mobileMenuOpen.value = false;
        });

        const isAuthenticated = computed(() => {
            return !!(authenticatedUser.value.id && !route.meta.guest);
        });

        const showSidebar = computed(() => {
            return !!(authenticatedUser.value.id && route.path.startsWith('/admin') && !route.meta.guest && !route.meta.publicLayout && !route.meta.hideSidebar);
        });

        const logout = async () => {
            if (loggingOut.value) return;

            loggingOut.value = true;

            try {
                await axios.post('/admin/logout');
            } catch (e) {
                await axios.post('/logout');
            } finally {
                window.location.assign('/admin/login');
            }
        };

        const updateClocks = () => {
            try {
                const now = new Date();

                // Local Time Formatting
                localTimeStr.value = now.toLocaleDateString(intlLocale, {
                    day: '2-digit',
                    month: '2-digit',
                    year: 'numeric'
                }) + ' ' + now.toLocaleTimeString(intlLocale, {
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit'
                });

                // Server Time Formatting
                let offset = serverOffset.value;
                if (typeof offset !== 'number' || isNaN(offset)) {
                    offset = 0;
                }

                const serverTime = new Date(now.getTime() + offset);

                if (isNaN(serverTime.getTime())) {
                    serverTimeStr.value = localTimeStr.value;
                } else {
                    serverTimeStr.value = serverTime.toLocaleDateString(intlLocale, {
                        day: '2-digit',
                        month: '2-digit',
                        year: 'numeric'
                    }) + ' ' + serverTime.toLocaleTimeString(intlLocale, {
                        hour: '2-digit',
                        minute: '2-digit',
                        second: '2-digit'
                    });
                }
            } catch (err) {
                console.error('Clock update error:', err);
            }
        };

        const syncServerTime = async () => {
            try {
                const res = await axios.get('/api/settings');
                if (res.data && res.data.server_time) {
                    const serverTimeMs = Date.parse(res.data.server_time);
                    serverOffset.value = serverTimeMs - Date.now();
                }
            } catch (e) {
                console.error('Failed to sync server time:', e);
            }
        };

        onMounted(async () => {
            if (isAuthenticated.value) {
                await syncServerTime();
            }
            updateClocks();
            timer = setInterval(updateClocks, 1000);
        });

        onUnmounted(() => {
            if (timer) clearInterval(timer);
        });

        return {
            t,
            mobileMenuOpen,
            toasts,
            localTimeStr,
            serverTimeStr,
            authenticatedUser,
            loggingOut,
            logout,
            isAuthenticated,
            showSidebar
        };
    }
};
</script>

<style>
/* Any global adjustments can go here */
[v-cloak] {
    display: none;
}

/* Toast Animations (Smooth slide from right-top) */
.toast-enter-active,
.toast-leave-active {
    transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
}
.toast-enter-from {
    transform: translateX(120%) translateY(-20px);
    opacity: 0;
}
.toast-leave-to {
    transform: translateX(120%);
    opacity: 0;
}
.toast-leave-active {
    position: absolute;
    width: 100%;
}

/* Page Transition Animations */
.page-enter-active,
.page-leave-active {
    transition: opacity 0.2s ease, transform 0.2s ease;
}
.page-enter-from {
    opacity: 0;
    transform: translateY(8px);
}
.page-leave-to {
    opacity: 0;
    transform: translateY(-8px);
}
</style>
