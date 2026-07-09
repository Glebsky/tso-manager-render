<template>
    <div>
        <!-- Page Header -->
        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-3xl font-bold text-white">Bot Logs</h1>
                <p class="text-white/40 mt-1">Review system logs and task execution outcomes</p>
            </div>
            <button @click="loadLogs(1)" :disabled="loading" class="btn-secondary flex items-center gap-2">
                <svg class="w-4 h-4" :class="{ 'animate-spin': loading }" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182" />
                </svg>
                Refresh
            </button>
        </div>

        <!-- Filter Bar -->
        <div class="glass-card p-4 mb-6 grid grid-cols-1 sm:grid-cols-3 gap-4">
            <!-- Account Selector -->
            <div>
                <label class="block text-xs font-medium text-white/40 mb-2 uppercase tracking-wider">Filter by Account</label>
                <div class="relative">
                    <select v-model="filter.accountId" @change="onFilterChange" class="glass-select w-full">
                        <option value="" class="bg-dark-900">All Accounts</option>
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
                <label class="block text-xs font-medium text-white/40 mb-2 uppercase tracking-wider">Filter by Severity</label>
                <div class="relative">
                    <select v-model="filter.level" @change="onFilterChange" class="glass-select w-full">
                        <option value="" class="bg-dark-900">All Severities</option>
                        <option value="info" class="bg-dark-900">Info</option>
                        <option value="success" class="bg-dark-900">Success</option>
                        <option value="warning" class="bg-dark-900">Warning</option>
                        <option value="error" class="bg-dark-900">Error</option>
                    </select>
                    <div class="absolute inset-y-0 right-3 flex items-center pointer-events-none">
                        <svg class="w-4 h-4 text-white/30" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <!-- Logs Listing -->
        <div class="glass-card overflow-hidden mb-6">
            <div v-if="logs.length > 0" class="divide-y divide-white/5">
                <log-entry v-for="log in logs" :key="log.id" :log="log" />
            </div>
            <div v-else class="p-12 text-center text-white/30">
                <p>No log records matching filters found.</p>
            </div>
        </div>

        <!-- Pagination Controls -->
        <div v-if="pagination.last_page > 1" class="flex items-center justify-between">
            <p class="text-xs text-white/30">
                Showing Page {{ pagination.current_page }} of {{ pagination.last_page }}
            </p>
            <div class="flex items-center gap-2">
                <button :disabled="pagination.current_page === 1 || loading" @click="loadLogs(pagination.current_page - 1)"
                        class="btn-secondary btn-sm flex items-center gap-1 hover:border-emerald-500/30 hover:text-emerald-400 disabled:opacity-50">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                    </svg>
                    Previous
                </button>
                <button :disabled="pagination.current_page === pagination.last_page || loading" @click="loadLogs(pagination.current_page + 1)"
                        class="btn-secondary btn-sm flex items-center gap-1 hover:border-emerald-500/30 hover:text-emerald-400 disabled:opacity-50">
                    Next
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                    </svg>
                </button>
            </div>
        </div>
    </div>
</template>

<script>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import LogEntry from '../components/LogEntry.vue';
import { showToast } from '../toast';

export default {
    name: 'Logs',
    components: { LogEntry },
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

        const loadLogs = async (page = 1) => {
            loading.value = true;
            try {
                const res = await axios.get('/api/logs', {
                    params: {
                        page,
                        account_id: filter.value.accountId,
                        level: filter.value.level
                    }
                });

                logs.value = res.data.logs.data || [];
                accounts.value = res.data.accounts || [];
                pagination.value = {
                    current_page: res.data.logs.current_page || 1,
                    last_page: res.data.logs.last_page || 1
                };
            } catch (e) {
                showToast('Failed to load logs.', 'error');
            } finally {
                loading.value = false;
            }
        };

        const onFilterChange = () => {
            loadLogs(1);
        };

        onMounted(() => {
            loadLogs(1);
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
