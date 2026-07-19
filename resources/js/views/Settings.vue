<template>
    <div>
        <!-- Page Header -->
        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-3xl font-bold text-white">System Settings</h1>
                <p class="text-white/40 mt-1">Configure global synchronization parameters and manage database states</p>
            </div>
        </div>

        <!-- Configuration Form -->
        <div class="glass-card p-6 mb-8">
            <div class="flex items-center gap-3 mb-5">
                <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-emerald-500 to-teal-600 flex items-center justify-center">
                    <svg class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                    </svg>
                </div>
                <h2 class="text-lg font-semibold text-white">General Parameters</h2>
            </div>

            <form @submit.prevent="saveSettings">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <!-- Sync Interval -->
                    <div>
                        <label class="block text-xs font-medium text-white/40 mb-2 uppercase tracking-wider">Sync Interval (Minutes)</label>
                        <div class="relative">
                            <select v-model.number="form.sync_interval" class="glass-select w-full">
                                <option :value="0" class="bg-dark-900">Manual Sync Only</option>
                                <option :value="5" class="bg-dark-900">Every 5 minutes</option>
                                <option :value="15" class="bg-dark-900">Every 15 minutes</option>
                                <option :value="30" class="bg-dark-900">Every 30 minutes</option>
                                <option :value="60" class="bg-dark-900">Every hour</option>
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
                        <label class="block text-xs font-medium text-white/40 mb-2 uppercase tracking-wider">Log Retention Policy</label>
                        <div class="relative">
                            <select v-model.number="form.log_retention_days" class="glass-select w-full">
                                <option :value="0" class="bg-dark-900">Keep Forever</option>
                                <option :value="7" class="bg-dark-900">Delete older than 7 days</option>
                                <option :value="14" class="bg-dark-900">Delete older than 14 days</option>
                                <option :value="30" class="bg-dark-900">Delete older than 30 days</option>
                                <option :value="90" class="bg-dark-900">Delete older than 90 days</option>
                            </select>
                            <div class="absolute inset-y-0 right-3 flex items-center pointer-events-none">
                                <svg class="w-4 h-4 text-white/30" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>

                <button type="submit" :disabled="saving" class="btn-primary flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                    </svg>
                    {{ saving ? 'Saving...' : 'Save Settings' }}
                </button>
            </form>
        </div>

        <!-- Danger Zone -->
        <div class="glass-card p-6 border-red-500/10 bg-red-500/[0.02]">
            <div class="flex items-center gap-3 mb-5">
                <div class="w-8 h-8 rounded-lg bg-red-500/10 text-red-400 flex items-center justify-center">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                    </svg>
                </div>
                <h2 class="text-lg font-semibold text-white">Danger Zone</h2>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Clear Logs -->
                <div class="p-4 rounded-xl border border-white/5 bg-white/[0.01] flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-semibold text-white/90">Clear System Logs</h3>
                        <p class="text-xs text-white/30 mt-1">This deletes all logged events from the database.</p>
                    </div>
                    <button @click="clearLogs" :disabled="clearingLogs"
                            class="btn-danger flex items-center gap-1.5">
                        Clear All Logs
                    </button>
                </div>

                <!-- Deactivate Tasks -->
                <div class="p-4 rounded-xl border border-white/5 bg-white/[0.01] flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-semibold text-white/90">Pause All Scheduled Tasks</h3>
                        <p class="text-xs text-white/30 mt-1">Temporarily turn off all queued activities.</p>
                    </div>
                    <button @click="stopAllTasks" :disabled="stoppingTasks"
                            class="btn-danger flex items-center gap-1.5">
                        Deactivate All
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import { showToast } from '../toast';

export default {
    name: 'Settings',
    setup() {
        const saving = ref(false);
        const clearingLogs = ref(false);
        const stoppingTasks = ref(false);

        const form = ref({
            sync_interval: 30,
            log_retention_days: 30
        });

        const loadSettings = async () => {
            try {
                const res = await axios.get('/api/settings');
                form.value = res.data || {
                    sync_interval: 30,
                    log_retention_days: 30
                };
            } catch (e) {
                showToast('Failed to load settings.', 'error');
            }
        };

        const saveSettings = async () => {
            saving.value = true;
            try {
                const res = await axios.put('/api/settings', form.value);
                if (res.data.success) {
                    showToast('Settings saved.');
                }
            } catch (e) {
                showToast('Failed to save settings.', 'error');
            } finally {
                saving.value = false;
            }
        };

        const clearLogs = async () => {
            if (!confirm('This deletes all database logs permanently. Proceed?')) return;
            clearingLogs.value = true;
            try {
                const res = await axios.delete('/api/settings/logs');
                if (res.data.success) {
                    showToast('All system logs cleared successfully.');
                }
            } catch (e) {
                showToast('Failed to clear logs.', 'error');
            } finally {
                clearingLogs.value = false;
            }
        };

        const stopAllTasks = async () => {
            if (!confirm('This pauses all active tasks. Proceed?')) return;
            stoppingTasks.value = true;
            try {
                const res = await axios.post('/api/settings/tasks/stop');
                if (res.data.success) {
                    showToast('All tasks deactivated.');
                }
            } catch (e) {
                showToast('Failed to deactivate tasks.', 'error');
            } finally {
                stoppingTasks.value = false;
            }
        };

        onMounted(() => {
            loadSettings();
        });

        return {
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
