<template>
    <div>
        <!-- Page Header -->
        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-3xl font-bold text-white">{{ t('settings.title') }}</h1>
                <p class="text-white/40 mt-1">{{ t('settings.subtitle') }}</p>
            </div>
        </div>

        <!-- Configuration Form -->
        <div class="glass-card p-4 sm:p-6 mb-8">
            <div class="flex items-center gap-3 mb-5">
                <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-emerald-500 to-teal-600 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                    </svg>
                </div>
                <h2 class="text-base sm:text-lg font-semibold text-white">{{ t('settings.general') }}</h2>
            </div>

            <!-- Skeleton while settings are loading -->
            <div v-if="loading">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-6 mb-6">
                    <div v-for="i in 2" :key="'settings-skeleton-' + i">
                        <div class="w-32 h-3 rounded skeleton mb-2"></div>
                        <div class="w-full h-[46px] skeleton"></div>
                    </div>
                </div>
                <div class="w-44 h-[46px] skeleton"></div>
            </div>

            <form v-else @submit.prevent="saveSettings">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-6 mb-6">
                    <!-- Sync Interval -->
                    <div>
                        <label class="block text-xs font-medium text-white/40 mb-2 uppercase tracking-wider">{{ t('settings.sync_interval') }}</label>
                        <div class="relative">
                            <select v-model.number="form.sync_interval" class="glass-select w-full">
                                <option :value="0" class="bg-dark-900">{{ t('settings.manual_sync') }}</option>
                                <option :value="5" class="bg-dark-900">{{ t('settings.every_5') }}</option>
                                <option :value="15" class="bg-dark-900">{{ t('settings.every_15') }}</option>
                                <option :value="30" class="bg-dark-900">{{ t('settings.every_30') }}</option>
                                <option :value="60" class="bg-dark-900">{{ t('settings.every_hour') }}</option>
                            </select>
                            <div class="absolute inset-y-0 right-3 flex items-center pointer-events-none">
                                <svg class="w-4 h-4 text-white/30" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                </svg>
                            </div>
                        </div>
                    </div>

                    <!-- Log Retention -->
                    <div>
                        <label class="block text-xs font-medium text-white/40 mb-2 uppercase tracking-wider">{{ t('settings.log_retention') }}</label>
                        <div class="relative">
                            <select v-model.number="form.log_retention_days" class="glass-select w-full">
                                <option :value="0" class="bg-dark-900">{{ t('settings.keep_forever') }}</option>
                                <option :value="7" class="bg-dark-900">{{ t('settings.older_7') }}</option>
                                <option :value="14" class="bg-dark-900">{{ t('settings.older_14') }}</option>
                                <option :value="30" class="bg-dark-900">{{ t('settings.older_30') }}</option>
                                <option :value="90" class="bg-dark-900">{{ t('settings.older_90') }}</option>
                            </select>
                            <div class="absolute inset-y-0 right-3 flex items-center pointer-events-none">
                                <svg class="w-4 h-4 text-white/30" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>

                <button type="submit" :disabled="saving" class="btn-primary w-full sm:w-auto flex items-center justify-center gap-2 disabled:opacity-60">
                    <spinner v-if="saving" size="sm" />
                    <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                    </svg>
                    <span>{{ saving ? t('settings.saving') : t('settings.save') }}</span>
                </button>
            </form>
        </div>

        <!-- Danger Zone -->
        <div class="glass-card p-4 sm:p-6 border-red-500/10 bg-red-500/[0.02]">
            <div class="flex items-center gap-3 mb-5">
                <div class="w-8 h-8 rounded-lg bg-red-500/10 text-red-400 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                    </svg>
                </div>
                <h2 class="text-base sm:text-lg font-semibold text-white">{{ t('settings.danger_zone') }}</h2>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-6">
                <!-- Clear Logs -->
                <div class="p-4 rounded-xl border border-white/5 bg-white/[0.01] flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 sm:gap-4">
                    <div>
                        <h3 class="text-sm font-semibold text-white/90">{{ t('settings.clear_logs') }}</h3>
                        <p class="text-xs text-white/30 mt-1 mb-1 sm:mb-0">{{ t('settings.clear_logs_hint') }}</p>
                    </div>
                    <button @click="clearLogs" :disabled="clearingLogs"
                            class="btn-danger w-full sm:w-auto flex items-center justify-center gap-1.5 py-2.5 px-4 text-xs font-semibold disabled:opacity-60 shrink-0 mt-2 sm:mt-0">
                        <spinner v-if="clearingLogs" size="xs" />
                        <span>{{ clearingLogs ? t('settings.clearing') : t('settings.clear_all_logs') }}</span>
                    </button>
                </div>

                <!-- Deactivate Tasks -->
                <div class="p-4 rounded-xl border border-white/5 bg-white/[0.01] flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 sm:gap-4">
                    <div>
                        <h3 class="text-sm font-semibold text-white/90">{{ t('settings.pause_tasks') }}</h3>
                        <p class="text-xs text-white/30 mt-1 mb-1 sm:mb-0">{{ t('settings.pause_tasks_hint') }}</p>
                    </div>
                    <button @click="stopAllTasks" :disabled="stoppingTasks"
                            class="btn-danger w-full sm:w-auto flex items-center justify-center gap-1.5 py-2.5 px-4 text-xs font-semibold disabled:opacity-60 shrink-0 mt-2 sm:mt-0">
                        <spinner v-if="stoppingTasks" size="xs" />
                        <span>{{ stoppingTasks ? t('settings.deactivating') : t('settings.deactivate_all') }}</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { ref, onMounted } from 'vue';
import { t } from '../lang';
import { settingsApi } from '../services/api/settings';
import { showToast } from '../toast';
import Spinner from '../components/Spinner.vue';

export default {
    name: 'Settings',
    components: { Spinner },
    setup() {
        const loading = ref(true);
        const saving = ref(false);
        const clearingLogs = ref(false);
        const stoppingTasks = ref(false);

        const form = ref({
            sync_interval: 30,
            log_retention_days: 30
        });

        const loadSettings = async () => {
            try {
                const res = await settingsApi.fetchSettings();
                const data = res?.data || res || {};
                form.value = {
                    sync_interval: Number(data.sync_interval ?? 30),
                    log_retention_days: Number(data.log_retention_days ?? 30)
                };
            } catch (e) {
                showToast(t('settings.load_failed'), 'error');
            } finally {
                loading.value = false;
            }
        };

        const saveSettings = async () => {
            saving.value = true;
            try {
                const payload = {
                    sync_interval: Number(form.value.sync_interval),
                    log_retention_days: Number(form.value.log_retention_days)
                };
                const res = await settingsApi.updateSettings(payload);
                const data = res.data || res.settings || res;
                if (data.sync_interval !== undefined) {
                    form.value.sync_interval = Number(data.sync_interval);
                }
                if (data.log_retention_days !== undefined) {
                    form.value.log_retention_days = Number(data.log_retention_days);
                }
                showToast(t('settings.saved'));
            } catch (e) {
                showToast(t('settings.save_failed'), 'error');
            } finally {
                saving.value = false;
            }
        };

        const clearLogs = async () => {
            if (!confirm(t('settings.confirm_clear_logs'))) return;
            clearingLogs.value = true;
            try {
                await settingsApi.clearLogs();
                showToast(t('settings.logs_cleared'));
            } catch (e) {
                showToast(t('settings.clear_failed'), 'error');
            } finally {
                clearingLogs.value = false;
            }
        };

        const stopAllTasks = async () => {
            if (!confirm(t('settings.confirm_pause_tasks'))) return;
            stoppingTasks.value = true;
            try {
                await settingsApi.stopAllTasks();
                showToast(t('settings.tasks_deactivated'));
            } catch (e) {
                showToast(t('settings.deactivate_failed'), 'error');
            } finally {
                stoppingTasks.value = false;
            }
        };

        onMounted(() => {
            loadSettings();
        });

        return {
            loading,
            saving,
            clearingLogs,
            stoppingTasks,
            form,
            saveSettings,
            clearLogs,
            stopAllTasks
        };
    }
};
</script>
