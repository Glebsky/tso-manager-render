<template>
    <div>
        <!-- Page Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6 sm:mb-8">
            <div>
                <h1 class="text-2xl sm:text-3xl font-bold text-white">{{ t('dashboard.title') }}</h1>
                <p class="text-xs sm:text-sm text-white/40 mt-1">{{ currentDate }}</p>
            </div>
            <div class="flex items-center gap-3 shrink-0">
                <button @click="loadData" :disabled="loading" class="btn-secondary flex items-center gap-2 text-xs sm:text-sm py-2 px-4 disabled:opacity-50">
                    <svg class="w-4 h-4" :class="{ 'animate-spin': loading }" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182" />
                    </svg>
                    <span>{{ t('common.refresh') }}</span>
                </button>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-5 mb-8">
            <!-- Skeleton Stats -->
            <template v-if="loading && stats.total_accounts === 0">
                <div v-for="n in 4" :key="'skel-stat-' + n" class="glass-card p-4 sm:p-5">
                    <div class="flex items-center justify-between mb-3">
                        <div class="w-10 h-10 rounded-xl skeleton"></div>
                        <div class="w-12 h-5 rounded-full skeleton"></div>
                    </div>
                    <div class="w-16 h-8 rounded skeleton mb-1"></div>
                    <div class="w-32 h-3 rounded skeleton"></div>
                </div>
            </template>
            <!-- Loaded Stats -->
            <template v-else>
                <!-- Total Accounts -->
                <div class="glass-card p-4 sm:p-5 group hover:border-white/20 transition-all duration-500 animate-fade-in-up min-w-0" style="animation-delay: 0ms">
                    <div class="flex items-center justify-between mb-3">
                        <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center shadow-lg shadow-blue-500/20 shrink-0">
                            <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                            </svg>
                        </div>
                        <span class="badge badge-info text-[10px]">{{ t('dashboard.badge_total') }}</span>
                    </div>
                    <p class="text-2xl font-bold text-white truncate">{{ stats.total_accounts || 0 }}</p>
                    <p class="text-xs text-white/30 mt-1 truncate">{{ t('dashboard.registered_accounts') }}</p>
                </div>

                <!-- Active Tasks -->
                <div class="glass-card p-4 sm:p-5 group hover:border-white/20 transition-all duration-500 animate-fade-in-up min-w-0" style="animation-delay: 80ms">
                    <div class="flex items-center justify-between mb-3">
                        <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-emerald-500 to-teal-600 flex items-center justify-center shadow-lg shadow-emerald-500/20 shrink-0">
                            <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                            </svg>
                        </div>
                        <span class="badge badge-success text-[10px]">{{ t('dashboard.badge_active') }}</span>
                    </div>
                    <p class="text-2xl font-bold text-white truncate">{{ stats.active_tasks || 0 }}</p>
                    <p class="text-xs text-white/30 mt-1 truncate">{{ t('dashboard.scheduled_tasks') }}</p>
                </div>

                <!-- Today's Actions -->
                <div class="glass-card p-4 sm:p-5 group hover:border-white/20 transition-all duration-500 animate-fade-in-up min-w-0" style="animation-delay: 160ms">
                    <div class="flex items-center justify-between mb-3">
                        <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-amber-500 to-orange-600 flex items-center justify-center shadow-lg shadow-amber-500/20 shrink-0">
                            <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m3.75 13.5 10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75Z" />
                            </svg>
                        </div>
                        <span class="badge badge-warning text-[10px]">{{ t('dashboard.badge_today') }}</span>
                    </div>
                    <p class="text-2xl font-bold text-white truncate">{{ stats.today_actions || 0 }}</p>
                    <p class="text-xs text-white/30 mt-1 truncate">{{ t('dashboard.actions_executed') }}</p>
                </div>

                <!-- Errors -->
                <div class="glass-card p-4 sm:p-5 group hover:border-white/20 transition-all duration-500 animate-fade-in-up min-w-0" style="animation-delay: 240ms">
                    <div class="flex items-center justify-between mb-3">
                        <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-red-500 to-rose-600 flex items-center justify-center shadow-lg shadow-red-500/20 shrink-0">
                            <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                            </svg>
                        </div>
                        <span class="badge badge-danger text-[10px]">{{ t('dashboard.badge_errors') }}</span>
                    </div>
                    <p class="text-2xl font-bold text-white truncate">{{ stats.errors || 0 }}</p>
                    <p class="text-xs text-white/30 mt-1 truncate">{{ t('dashboard.error_count') }}</p>
                </div>
            </template>
        </div>

        <!-- Accounts Overview -->
        <div class="mb-8">
            <div class="flex items-center justify-between mb-5">
                <h2 class="text-base sm:text-lg font-semibold text-white flex items-center gap-2">
                    <svg class="w-5 h-5 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                    </svg>
                    <span>{{ t('dashboard.accounts_overview') }}</span>
                </h2>
                <router-link to="/admin/accounts" class="text-xs sm:text-sm text-emerald-400 hover:text-emerald-300 transition-colors flex items-center gap-1 font-medium">
                    <span>{{ t('dashboard.view_all') }}</span>
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                    </svg>
                </router-link>
            </div>

            <!-- Skeleton Accounts -->
            <div v-if="loading && accounts.length === 0" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-5">
                <div v-for="n in 3" :key="'skel-acc-' + n" class="glass-card overflow-hidden">
                    <div class="h-[3px] skeleton"></div>
                    <div class="p-5">
                        <div class="flex items-start justify-between mb-4">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl skeleton"></div>
                                <div>
                                    <div class="w-24 h-4 rounded skeleton mb-1"></div>
                                    <div class="w-32 h-3 rounded skeleton"></div>
                                </div>
                            </div>
                        </div>
                        <div class="flex gap-2 mb-4">
                            <div class="w-16 h-5 rounded-full skeleton"></div>
                            <div class="w-20 h-5 rounded-full skeleton"></div>
                        </div>
                        <div class="pt-3 border-t border-white/5 flex gap-2">
                            <div class="w-16 h-7 rounded-lg skeleton"></div>
                            <div class="w-20 h-7 rounded-lg skeleton"></div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Loaded Accounts -->
            <div v-else-if="accounts.length > 0" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-5">
                <account-card v-for="(acc, idx) in accounts.slice(0, 6)" :key="acc.id" :account="acc"
                              class="animate-fade-in-up"
                              :style="{ animationDelay: (idx * 80) + 'ms' }"
                              @sync-success="loadData" @delete-success="loadData" @action-success="loadData" />
            </div>
            <div v-else class="glass-card p-8 sm:p-12 text-center">
                <div class="w-16 h-16 rounded-2xl bg-white/5 flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-white/20" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                    </svg>
                </div>
                <h3 class="text-white/60 font-medium mb-1">{{ t('dashboard.no_accounts') }}</h3>
                <p class="text-white/30 text-xs sm:text-sm mb-4 max-w-sm mx-auto">{{ t('dashboard.no_accounts_hint') }}</p>
                <router-link to="/admin/accounts" class="btn-primary inline-flex items-center gap-2 py-2 px-4 text-xs sm:text-sm">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    <span>{{ t('dashboard.add_account') }}</span>
                </router-link>
            </div>
        </div>

        <!-- Recent Logs -->
        <div>
            <div class="flex items-center justify-between mb-5">
                <h2 class="text-base sm:text-lg font-semibold text-white flex items-center gap-2">
                    <svg class="w-5 h-5 text-teal-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 0 1 0 3.75H5.625a1.875 1.875 0 0 1 0-3.75Z" />
                    </svg>
                    <span>{{ t('dashboard.recent_activity') }}</span>
                </h2>
                <router-link to="/admin/logs" class="text-xs sm:text-sm text-teal-400 hover:text-teal-300 transition-colors flex items-center gap-1 font-medium">
                    <span>{{ t('dashboard.view_all_logs') }}</span>
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                    </svg>
                </router-link>
            </div>

            <div class="glass-card overflow-hidden w-full">
                <!-- Skeleton Logs -->
                <div v-if="loading && logs.length === 0" class="p-5 space-y-3">
                    <div v-for="n in 5" :key="'skel-log-' + n" class="flex items-center gap-3 py-3 border-b border-white/5 last:border-0">
                        <div class="w-16 h-5 rounded-full skeleton"></div>
                        <div class="flex-1">
                            <div class="w-48 h-3 rounded skeleton mb-1"></div>
                            <div class="w-32 h-2 rounded skeleton"></div>
                        </div>
                        <div class="w-20 h-3 rounded skeleton"></div>
                    </div>
                </div>
                <!-- Loaded Logs -->
                <div v-else-if="logs.length > 0" class="divide-y divide-white/5">
                    <log-entry v-for="log in logs.slice(0, 10)" :key="log.id" :log="log" />
                </div>
                <div v-else class="p-8 text-center">
                    <p class="text-white/30 text-xs sm:text-sm">{{ t('dashboard.no_logs') }}</p>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { ref, computed, onMounted } from 'vue';
import { intlLocale } from '../lang';
import axios from 'axios';
import AccountCard from '../components/AccountCard.vue';
import LogEntry from '../components/LogEntry.vue';

export default {
    name: 'Dashboard',
    components: { AccountCard, LogEntry },
    setup() {
        const loading = ref(false);
        const accounts = ref([]);
        const logs = ref([]);
        const stats = ref({
            total_accounts: 0,
            active_tasks: 0,
            today_actions: 0,
            errors: 0
        });

        const currentDate = computed(() => {
            const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            return new Date().toLocaleDateString(intlLocale, options);
        });

        const loadData = async () => {
            loading.value = true;
            try {
                const res = await axios.get('/api/dashboard');
                accounts.value = res.data.accounts || [];
                logs.value = res.data.logs || [];
                stats.value = res.data.stats || {
                    total_accounts: 0,
                    active_tasks: 0,
                    today_actions: 0,
                    errors: 0
                };
            } catch (e) {
                console.error('Failed to load dashboard data:', e);
            } finally {
                loading.value = false;
            }
        };

        onMounted(() => {
            loadData();
        });

        return {
            loading,
            accounts,
            logs,
            stats,
            currentDate,
            loadData
        };
    }
};
</script>
