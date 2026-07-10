<template>
    <div>
        <!-- Page Header -->
        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-3xl font-bold text-white">Task Planner</h1>
                <p class="text-white/40 mt-1">Schedule automated actions for your accounts</p>
            </div>
        </div>

        <!-- Add Task Form -->
        <div class="glass-card p-6 mb-8">
            <div class="flex items-center gap-3 mb-5">
                <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-emerald-500 to-teal-600 flex items-center justify-center">
                    <svg class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                </div>
                <h2 class="text-lg font-semibold text-white">Schedule New Task</h2>
            </div>

            <form @submit.prevent="scheduleTask">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
                    <!-- Account selector -->
                    <div>
                        <label class="block text-xs font-medium text-white/40 mb-2 uppercase tracking-wider">Account</label>
                        <div class="relative">
                            <select required v-model="selectedAccountId" @change="onAccountChange" class="glass-select w-full">
                                <option value="" disabled class="bg-dark-900">Select Account</option>
                                <option v-for="acc in accounts" :key="acc.id" :value="acc.id" class="bg-dark-900">
                                    {{ acc.nickname || acc.username }} ({{ (acc.region || '').toUpperCase() }})
                                </option>
                            </select>
                            <div class="absolute inset-y-0 right-3 flex items-center pointer-events-none">
                                <svg class="w-4 h-4 text-white/30" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                </svg>
                            </div>
                        </div>
                    </div>

                    <!-- Task Type -->
                    <div>
                        <label class="block text-xs font-medium text-white/40 mb-2 uppercase tracking-wider">Task Type</label>
                        <div class="relative">
                            <select required v-model="taskType" @change="onTaskTypeChange" class="glass-select w-full">
                                <option value="" disabled class="bg-dark-900">Select Task Type</option>
                                <option value="stop_production" class="bg-dark-900">🛑 Stop Production</option>
                                <option value="start_production" class="bg-dark-900">▶️ Start Production</option>
                                <option value="apply_buff" class="bg-dark-900">⚡ Apply Buff</option>
                                <option value="send_geologist" class="bg-dark-900">⛏️ Send Geologist</option>
                                <option value="send_explorer" class="bg-dark-900">🧭 Send Explorer</option>
                            </select>
                            <div class="absolute inset-y-0 right-3 flex items-center pointer-events-none">
                                <svg class="w-4 h-4 text-white/30" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                </svg>
                            </div>
                        </div>
                    </div>

                    <!-- Scheduled Time -->
                    <div>
                        <label class="block text-xs font-medium text-white/40 mb-2 uppercase tracking-wider">Run At Time</label>
                        <input type="time" required v-model="runAtTime" class="glass-input w-full">
                    </div>
                </div>

                <!-- Dynamic Fields based on Task Type -->
                <div v-if="taskType !== ''" class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-5">
                    
                    <!-- 1. Building Selector -->
                    <div v-if="['stop_production', 'start_production', 'apply_buff'].includes(taskType)">
                        <label class="block text-xs font-medium text-white/40 mb-2 uppercase tracking-wider">Target Building</label>
                        <div class="relative">
                            <select v-model.number="payload.grid" class="glass-select w-full">
                                <option value="" class="bg-dark-900">Select Building</option>
                                <option v-for="b in zone.buildings" :key="b.buildingGrid" :value="b.buildingGrid" class="bg-dark-900">
                                    {{ formatBuildingName(b.buildingName_string) }} (Grid #{{ b.buildingGrid }} Lvl {{ b.upgradeLevel }})
                                </option>
                            </select>
                            <div class="absolute inset-y-0 right-3 flex items-center pointer-events-none">
                                <svg class="w-4 h-4 text-white/30" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                </svg>
                            </div>
                        </div>
                    </div>

                    <!-- 2. Buff Selector -->
                    <div v-if="taskType === 'apply_buff'">
                        <label class="block text-xs font-medium text-white/40 mb-2 uppercase tracking-wider">Buff Item</label>
                        <div class="relative">
                            <select @change="onBuffChange($event.target.value)" class="glass-select w-full">
                                <option value="" class="bg-dark-900">Select Buff</option>
                                <option v-for="bf in zone.buffs" :key="bf.uniqueId1 + '-' + bf.uniqueId2" 
                                        :value="bf.uniqueId1 + '|' + (bf.uniqueID2 || bf.uniqueId2)" class="bg-dark-900">
                                    {{ formatBuffName(bf.name) }} ({{ bf.amount }} left)
                                </option>
                            </select>
                            <div class="absolute inset-y-0 right-3 flex items-center pointer-events-none">
                                <svg class="w-4 h-4 text-white/30" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                </svg>
                            </div>
                        </div>
                    </div>

                    <!-- 3. Specialist Selector -->
                    <div v-if="['send_geologist', 'send_explorer'].includes(taskType)">
                        <label class="block text-xs font-medium text-white/40 mb-2 uppercase tracking-wider">Select Specialist</label>
                        <div class="relative">
                            <select @change="onSpecialistChange($event.target.value)" class="glass-select w-full">
                                <option value="" class="bg-dark-900">Select Specialist</option>
                                <option v-for="sp in filteredSpecialists" :key="sp.uniqueId1 + '-' + sp.uniqueId2" 
                                        :value="sp.uniqueId1 + '|' + (sp.uniqueID2 || sp.uniqueId2)" class="bg-dark-900">
                                    {{ sp.name || (taskType === 'send_geologist' ? 'Geologist' : 'Explorer') }}
                                </option>
                            </select>
                            <div class="absolute inset-y-0 right-3 flex items-center pointer-events-none">
                                <svg class="w-4 h-4 text-white/30" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                </svg>
                            </div>
                        </div>
                    </div>

                    <!-- 4. Search Task Category -->
                    <div v-if="['send_geologist', 'send_explorer'].includes(taskType)">
                        <label class="block text-xs font-medium text-white/40 mb-2 uppercase tracking-wider">Search Type</label>
                        <div class="relative">
                            <select v-model.number="payload.task_type" @change="onSearchTypeChange" class="glass-select w-full">
                                <option v-if="taskType === 'send_geologist'" :value="0" class="bg-dark-900">Deposit Search</option>
                                <optgroup v-if="taskType === 'send_explorer'" label="Explorer Tasks" class="bg-dark-900">
                                    <option :value="1" class="bg-dark-900">Treasure Hunt</option>
                                    <option :value="2" class="bg-dark-900">Adventure Hunt</option>
                                </optgroup>
                            </select>
                            <div class="absolute inset-y-0 right-3 flex items-center pointer-events-none">
                                <svg class="w-4 h-4 text-white/30" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                </svg>
                            </div>
                        </div>
                    </div>

                    <!-- 5. Sub-Task ID Selector -->
                    <div v-if="['send_geologist', 'send_explorer'].includes(taskType)">
                        <label class="block text-xs font-medium text-white/40 mb-2 uppercase tracking-wider">Search Target / Duration</label>
                        <div class="relative">
                            <select v-model.number="payload.sub_task_id" class="glass-select w-full">
                                <option v-for="st in availableSubTasks" :key="st.id" :value="st.id" class="bg-dark-900">
                                    {{ st.name }}
                                </option>
                            </select>
                            <div class="absolute inset-y-0 right-3 flex items-center pointer-events-none">
                                <svg class="w-4 h-4 text-white/30" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>

                <button type="submit" :disabled="scheduling" class="btn-primary flex items-center gap-2 disabled:opacity-50">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    {{ scheduling ? 'Scheduling...' : 'Schedule Task' }}
                </button>
            </form>
        </div>

        <!-- Tasks List -->
        <div>
            <h2 class="text-lg font-semibold text-white flex items-center gap-2 mb-5">
                <svg class="w-5 h-5 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
                Scheduled Tasks
                <span class="badge badge-neutral text-[10px]">{{ tasks.length }}</span>
            </h2>

            <div v-if="tasks.length > 0" class="space-y-6">
                <div v-for="(groupTasks, accountName) in groupedTasks" :key="accountName" class="glass-card overflow-hidden">
                    <!-- Group Header -->
                    <div class="px-5 py-3 border-b border-white/5 bg-white/[0.02]">
                        <h3 class="font-medium text-white/60 flex items-center gap-2">
                            <svg class="w-4 h-4 text-emerald-400/60" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                            </svg>
                            {{ accountName }}
                            <span class="badge badge-neutral text-[10px]">{{ groupTasks.length }} tasks</span>
                        </h3>
                    </div>

                    <!-- Tasks Rows -->
                    <div class="divide-y divide-white/5">
                        <div v-for="t in groupTasks" :key="t.id" class="flex items-center gap-4 px-5 py-4 hover:bg-white/[0.02] transition-all duration-200 group">
                            <!-- Type Icon -->
                            <div class="w-9 h-9 rounded-lg bg-white/5 flex items-center justify-center text-lg flex-shrink-0">
                                {{ typeIcons[t.task_type] || '📋' }}
                            </div>

                            <!-- Task Info -->
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-white/80 group-hover:text-white transition-colors">
                                    {{ typeLabels[t.task_type] || t.task_type }}
                                </p>
                                <div class="flex items-center gap-2 mt-0.5">
                                    <span v-if="t.payload && t.payload.grid" class="text-[10px] text-white/30 font-mono">
                                        Grid #{{ t.payload.grid }}
                                    </span>
                                    <span v-if="t.payload && t.payload.sub_task_id !== undefined" class="text-[10px] text-white/30">
                                        {{ getSubTaskLabel(t.task_type, t.payload.task_type, t.payload.sub_task_id) }}
                                    </span>
                                </div>
                            </div>

                            <!-- Scheduled Time -->
                            <div class="text-right flex-shrink-0">
                                <p class="text-sm text-white/60 font-mono">{{ formatTime(t.run_at_time) }}</p>
                                <p class="text-[10px] text-white/20">Scheduled</p>
                            </div>

                            <!-- Last run result -->
                            <span v-if="t.last_result" 
                                  class="badge text-[10px] flex-shrink-0 max-w-[150px] truncate"
                                  :class="t.last_result.includes('OK') ? 'badge-success' : 'badge-danger'"
                                  :title="t.last_result">
                                {{ t.last_result.includes('OK') ? 'Success' : 'Error' }}
                            </span>

                            <!-- Active Toggle -->
                            <button @click="toggleTask(t)" 
                                    class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors duration-300 flex-shrink-0"
                                    :class="t.is_active ? 'bg-emerald-500' : 'bg-white/10'">
                                <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow-lg transition-transform duration-300"
                                      :class="t.is_active ? 'translate-x-6' : 'translate-x-1'"></span>
                            </button>

                            <!-- Delete -->
                            <button @click="deleteTask(t.id)" 
                                    class="btn-secondary btn-sm text-red-400/60 hover:text-red-400 hover:border-red-500/30 flex-shrink-0">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div v-else class="glass-card p-12 text-center">
                <div class="w-16 h-16 rounded-2xl bg-white/5 flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-white/20" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                </div>
                <h3 class="text-white/60 font-medium mb-1">No Tasks Scheduled</h3>
                <p class="text-white/30 text-sm">Create your first automated task using the form above.</p>
            </div>
        </div>
    </div>
</template>

<script>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import { showToast } from '../toast';

export default {
    name: 'Tasks',
    setup() {
        const tasks = ref([]);
        const accounts = ref([]);
        const scheduling = ref(false);
        const timezone = ref('UTC');

        const selectedAccountId = ref('');
        const taskType = ref('');
        const runAtTime = ref('');
        const zone = ref({ buildings: [], specialists: [], buffs: [] });
        const payload = ref({});

        const typeIcons = {
            stop_production: '🛑',
            start_production: '▶️',
            apply_buff: '⚡',
            send_geologist: '⛏️',
            send_explorer: '🧭'
        };

        const typeLabels = {
            stop_production: 'Stop Production',
            start_production: 'Start Production',
            apply_buff: 'Apply Buff',
            send_geologist: 'Send Geologist',
            send_explorer: 'Send Explorer'
        };

        const loadPlanner = async () => {
            try {
                const [tasksRes, settingsRes] = await Promise.all([
                    axios.get('/api/tasks'),
                    axios.get('/api/settings'),
                ]);
                tasks.value = tasksRes.data.tasks || [];
                accounts.value = tasksRes.data.accounts || [];
                timezone.value = settingsRes.data.timezone || 'UTC';
            } catch (e) {
                showToast('Failed to load planner.', 'error');
            }
        };

        const onAccountChange = () => {
            const acc = accounts.value.find(a => a.id == selectedAccountId.value);
            if (acc && acc.zone_data) {
                try {
                    zone.value = typeof acc.zone_data === 'string'
                        ? JSON.parse(acc.zone_data)
                        : acc.zone_data;
                } catch (e) {
                    zone.value = { buildings: [], specialists: [], buffs: [] };
                }
            } else {
                zone.value = { buildings: [], specialists: [], buffs: [] };
            }
            resetPayload(taskType.value);
        };

        const resetPayload = (type) => {
            payload.value = {};
            if (['stop_production', 'start_production'].includes(type)) {
                payload.value = { grid: '' };
            } else if (type === 'apply_buff') {
                payload.value = { grid: '', unique_id1: '', unique_id2: '' };
            } else if (['send_geologist', 'send_explorer'].includes(type)) {
                payload.value = {
                    unique_id1: '',
                    unique_id2: '',
                    task_type: type === 'send_geologist' ? 0 : 1,
                    sub_task_id: 1
                };
            }
        };

        const onTaskTypeChange = () => {
            resetPayload(taskType.value);
        };

        const onBuffChange = (val) => {
            if (!val) return;
            const parts = val.split('|');
            payload.value.unique_id1 = parseInt(parts[0]);
            payload.value.unique_id2 = parseInt(parts[1] || 0);
        };

        const onSpecialistChange = (val) => {
            if (!val) return;
            const parts = val.split('|');
            payload.value.unique_id1 = parseInt(parts[0]);
            payload.value.unique_id2 = parseInt(parts[1] || 0);
        };

        const onSearchTypeChange = () => {
            payload.value.sub_task_id = 1;
        };

        const filteredSpecialists = computed(() => {
            if (!zone.value.specialists) return [];
            return zone.value.specialists.filter(sp => {
                const typeName = String(sp.type || '').toLowerCase();
                if (taskType.value === 'send_geologist') {
                    return typeName.includes('geologist');
                } else if (taskType.value === 'send_explorer') {
                    return typeName.includes('explorer') || typeName.includes('scout') || typeName.includes('specialist');
                }
                return false;
            });
        });

        const availableSubTasks = computed(() => {
            if (taskType.value === 'send_geologist') {
                return [
                    { id: 1, name: 'Copper Deposit' },
                    { id: 2, name: 'Stone Deposit' },
                    { id: 3, name: 'Coal Deposit' },
                    { id: 4, name: 'Gold Deposit' },
                    { id: 5, name: 'Iron Deposit' },
                    { id: 6, name: 'Marble Deposit' }
                ];
            } else if (taskType.value === 'send_explorer') {
                if (payload.value.task_type === 1) { // Treasure
                    return [
                        { id: 1, name: 'Short Treasure Hunt (6h)' },
                        { id: 2, name: 'Medium Treasure Hunt (12h)' },
                        { id: 3, name: 'Long Treasure Hunt (24h)' },
                        { id: 4, name: 'Very Long Treasure Hunt (36h)' },
                        { id: 5, name: 'Extended Treasure Hunt (48h)' }
                    ];
                } else { // Adventure
                    return [
                        { id: 1, name: 'Short Adventure Hunt' },
                        { id: 2, name: 'Medium Adventure Hunt' },
                        { id: 3, name: 'Long Adventure Hunt' },
                        { id: 4, name: 'Very Long Adventure Hunt' }
                    ];
                }
            }
            return [];
        });

        const groupedTasks = computed(() => {
            const groups = {};
            tasks.value.forEach(t => {
                const name = t.account ? (t.account.nickname || t.account.username) : 'Unknown';
                if (!groups[name]) groups[name] = [];
                groups[name].push(t);
            });
            return groups;
        });

        const getSubTaskLabel = (coreTaskType, category, subId) => {
            const stNames = {
                0: { 1: 'Copper', 2: 'Stone', 3: 'Coal', 4: 'Gold', 5: 'Iron', 6: 'Marble' },
                1: { 1: 'Short Treasure', 2: 'Medium Treasure', 3: 'Long Treasure', 4: 'Very Long Treasure', 5: 'Extended Treasure' },
                2: { 1: 'Short Adventure', 2: 'Medium Adventure', 3: 'Long Adventure', 4: 'Very Long Adventure' }
            };
            const cat = category !== undefined ? category : (coreTaskType === 'send_geologist' ? 0 : 1);
            return stNames[cat]?.[subId] || 'Task #' + subId;
        };

        const formatBuildingName = (name) => {
            if (!name) return 'Building';
            let formatted = name.replace(/(?<!^)(?=[A-Z])/g, ' ');
            formatted = formatted.replace(/_/g, ' ');
            return formatted.replace(/\w\S*/g, (w) => w.replace(/^\w/, (c) => c.toUpperCase()));
        };

        const formatBuffName = (name) => {
            if (!name) return 'Buff';
            let formatted = name.replace(/(?<!^)(?=[A-Z])/g, ' ');
            formatted = formatted.replace(/_/g, ' ');
            return formatted.replace(/\w\S*/g, (w) => w.replace(/^\w/, (c) => c.toUpperCase()));
        };

        const formatTime = (timeStr) => {
            if (!timeStr) return '—';
            const utc = timeStr.substring(0, 5);
            if (timezone.value === 'UTC') return utc;
            try {
                const [h, m] = utc.split(':').map(Number);
                const d = new Date();
                d.setUTCHours(h, m, 0, 0);
                const local = d.toLocaleTimeString('en-GB', {
                    timeZone: timezone.value,
                    hour: '2-digit',
                    minute: '2-digit',
                    hour12: false,
                });
                const tzAbbr = timezone.value.split('/').pop().replace('_', ' ');
                return `${utc} UTC | ${local} ${tzAbbr}`;
            } catch (e) {
                return utc;
            }
        };

        const scheduleTask = async () => {
            scheduling.value = true;
            try {
                const res = await axios.post('/api/tasks', {
                    account_id: selectedAccountId.value,
                    task_type: taskType.value,
                    payload: payload.value,
                    run_at_time: runAtTime.value
                });

                if (res.data.success) {
                    showToast('Task scheduled successfully.');
                    selectedAccountId.value = '';
                    taskType.value = '';
                    runAtTime.value = '';
                    zone.value = { buildings: [], specialists: [], buffs: [] };
                    payload.value = {};
                    loadPlanner();
                }
            } catch (e) {
                showToast(e.response?.data?.message || 'Failed to schedule task.', 'error');
            } finally {
                scheduling.value = false;
            }
        };

        const toggleTask = async (task) => {
            try {
                const res = await axios.post(`/api/tasks/${task.id}/toggle`);
                if (res.data.success) {
                    task.is_active = res.data.task.is_active;
                    showToast(res.data.message);
                }
            } catch (e) {
                showToast('Failed to toggle task state.', 'error');
            }
        };

        const deleteTask = async (id) => {
            if (!confirm('Delete this task?')) return;
            try {
                const res = await axios.delete(`/api/tasks/${id}`);
                if (res.data.success) {
                    showToast('Task deleted.');
                    loadPlanner();
                }
            } catch (e) {
                showToast('Failed to delete task.', 'error');
            }
        };

        onMounted(() => {
            loadPlanner();
        });

        return {
            tasks,
            accounts,
            scheduling,
            timezone,
            selectedAccountId,
            taskType,
            runAtTime,
            zone,
            payload,
            typeIcons,
            typeLabels,
            filteredSpecialists,
            availableSubTasks,
            groupedTasks,
            getSubTaskLabel,
            formatBuildingName,
            formatBuffName,
            formatTime,
            onAccountChange,
            onTaskTypeChange,
            onBuffChange,
            onSpecialistChange,
            onSearchTypeChange,
            scheduleTask,
            toggleTask,
            deleteTask
        };
    }
};
</script>
