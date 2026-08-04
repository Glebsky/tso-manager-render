<template>
    <div>
        <!-- Page Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6 sm:mb-8">
            <div>
                <h1 class="text-2xl sm:text-3xl font-bold text-white">{{ t('logs.title') }}</h1>
                <p class="text-xs sm:text-sm text-white/40 mt-1">{{ t('logs.subtitle') }}</p>
            </div>
            <button @click="loadLogs(1)" :disabled="loading" class="btn-secondary flex items-center justify-center gap-2 py-2 px-4 text-xs sm:text-sm self-start sm:self-auto shrink-0">
                <svg class="w-4 h-4" :class="{ 'animate-spin': loading }" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182" />
                </svg>
                <span>{{ t('common.refresh') }}</span>
            </button>
        </div>

        <!-- Filter Bar -->
        <div class="glass-card p-4 mb-6 grid grid-cols-1 sm:grid-cols-2 gap-4">
            <!-- Account Selector -->
            <div>
                <label class="block text-xs font-medium text-white/40 mb-2 uppercase tracking-wider">{{ t('logs.filter_account') }}</label>
                <div class="relative">
                    <select v-model="filter.accountId" @change="onFilterChange" class="glass-select w-full">
                        <option value="" class="bg-dark-900">{{ t('logs.all_accounts') }}</option>
                        <option v-for="acc in accounts" :key="acc.id" :value="acc.id" class="bg-dark-900">
                            {{ acc.nickname || acc.username }}
                        </option>
                    </select>
                    <div class="absolute inset-y-0 right-3 flex items-center pointer-events-none">
                        <svg class="w-4 h-4 text-white/30" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Log Level -->
            <div>
                <label class="block text-xs font-medium text-white/40 mb-2 uppercase tracking-wider">{{ t('logs.filter_severity') }}</label>
                <div class="relative">
                    <select v-model="filter.level" @change="onFilterChange" class="glass-select w-full">
                        <option value="" class="bg-dark-900">{{ t('logs.all_severities') }}</option>
                        <option value="info" class="bg-dark-900">{{ t('logs.level_info') }}</option>
                        <option value="success" class="bg-dark-900">{{ t('logs.level_success') }}</option>
                        <option value="warning" class="bg-dark-900">{{ t('logs.level_warning') }}</option>
                        <option value="error" class="bg-dark-900">{{ t('logs.level_error') }}</option>
                    </select>
                    <div class="absolute inset-y-0 right-3 flex items-center pointer-events-none">
                        <svg class="w-4 h-4 text-white/30" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <!-- Top Pagination Controls -->
        <div v-if="pagination.last_page > 1" class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 mb-4">
            <p class="text-xs text-white/30">
                {{ t('logs.page_of', { current: pagination.current_page, last: pagination.last_page }) }}
            </p>
            <div class="flex items-center gap-2 w-full sm:w-auto">
                <button :disabled="pagination.current_page === 1 || loading" @click="loadLogs(pagination.current_page - 1)"
                        class="btn-secondary btn-sm flex-1 sm:flex-none flex items-center justify-center gap-1 hover:border-emerald-500/30 hover:text-emerald-400 disabled:opacity-50">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                    </svg>
                    <span>{{ t('common.previous') }}</span>
                </button>
                <button :disabled="pagination.current_page === pagination.last_page || loading" @click="loadLogs(pagination.current_page + 1)"
                        class="btn-secondary btn-sm flex-1 sm:flex-none flex items-center justify-center gap-1 hover:border-emerald-500/30 hover:text-emerald-400 disabled:opacity-50">
                    <span>{{ t('common.next') }}</span>
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                    </svg>
                </button>
            </div>
        </div>

        <!-- Logs Listing -->
        <div class="glass-card overflow-hidden mb-6 relative w-full">
            <!-- Skeleton placeholders on first load -->
            <div v-if="loading && logs.length === 0" class="divide-y divide-white/5">
                <div v-for="i in 8" :key="'log-skeleton-' + i" class="p-4 flex items-center gap-4">
                    <div class="w-16 h-5 rounded-full skeleton flex-shrink-0"></div>
                    <div class="flex-1 min-w-0">
                        <div class="w-2/3 h-3 rounded skeleton mb-2"></div>
                        <div class="w-1/3 h-2 rounded skeleton"></div>
                    </div>
                    <div class="w-20 h-3 rounded skeleton"></div>
                </div>
            </div>
            <div v-else-if="logs.length > 0" class="divide-y divide-white/5">
                <log-entry v-for="log in logs" :key="log.id" :log="log" />
            </div>
            <div v-else class="p-12 text-center text-white/30">
                <p>{{ t('logs.empty') }}</p>
            </div>
            <!-- Overlay while refreshing / paginating so content doesn't jump -->
            <loading-overlay :show="loading && logs.length > 0" :label="t('logs.loading')" />
        </div>

        <!-- Pagination Controls -->
        <div v-if="pagination.last_page > 1" class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
            <p class="text-xs text-white/30">
                {{ t('logs.page_of', { current: pagination.current_page, last: pagination.last_page }) }}
            </p>
            <div class="flex items-center gap-2">
                <button :disabled="pagination.current_page === 1 || loading" @click="loadLogs(pagination.current_page - 1)"
                        class="btn-secondary btn-sm flex items-center gap-1 hover:border-emerald-500/30 hover:text-emerald-400 disabled:opacity-50">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                    </svg>
                    {{ t('common.previous') }}
                </button>
                <button :disabled="pagination.current_page === pagination.last_page || loading" @click="loadLogs(pagination.current_page + 1)"
                        class="btn-secondary btn-sm flex items-center gap-1 hover:border-emerald-500/30 hover:text-emerald-400 disabled:opacity-50">
                    {{ t('common.next') }}
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                    </svg>
                </button>
            </div>
        </div>
    </div>
</template>

<script>
import { ref, onMounted, onUnmounted } from 'vue';
import { t } from '../lang';
import axios from 'axios';
import LogEntry from '../components/LogEntry.vue';
import LoadingOverlay from '../components/LoadingOverlay.vue';
import { showToast } from '../toast';

export default {
    name: 'Logs',
    components: { LogEntry, LoadingOverlay },
    setup() {
        const loading = ref(false);
        const logs = ref([]);
        const accounts = ref([]);
        const filter = ref({
            accountId: '',
            level: ''
        });
        const pagination = ref({
            current_page: 1,
            last_page: 1
        });
        let timer = null;

        const loadLogs = async (page = 1, background = false) => {
            if (!background) loading.value = true;
            try {
                const res = await axios.get('/api/logs', {
                    params: {
                        page,
                        account_id: filter.value.accountId,
                        level: filter.value.level
                    }
                });

                logs.value = res.data.data || [];
                accounts.value = res.data.accounts || [];
                pagination.value = {
                    current_page: res.data.meta?.current_page || 1,
                    last_page: res.data.meta?.last_page || 1
                };
            } catch (e) {
                if (!background) showToast(t('logs.load_failed'), 'error');
            } finally {
                if (!background) loading.value = false;
            }
        };

        const onFilterChange = () => {
            loadLogs(1);
        };

        onMounted(() => {
            loadLogs(1);
            timer = setInterval(() => {
                loadLogs(pagination.value.current_page, true);
            }, 10000);
        });

        onUnmounted(() => {
            if (timer) clearInterval(timer);
        });

        return {
            loading,
            logs,
            accounts,
            filter,
            pagination,
            loadLogs,
            onFilterChange
        };
    }
};
</script>
