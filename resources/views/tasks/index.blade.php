@extends('layouts.app')

@section('title', 'Task Planner')

@section('content')
    {{-- Page Header --}}
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-3xl font-bold text-white">Task Planner</h1>
            <p class="text-white/40 mt-1">Schedule automated actions for your accounts</p>
        </div>
    </div>

    {{-- Add Task Form with Alpine.js --}}
    <div class="glass-card p-6 mb-8" x-data="taskPlanner()">
        <div class="flex items-center gap-3 mb-5">
            <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-emerald-500 to-teal-600 flex items-center justify-center">
                <svg class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
            </div>
            <h2 class="text-lg font-semibold text-white">Schedule New Task</h2>
        </div>

        <form action="{{ route('tasks.store') }}" method="POST">
            @csrf
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
                {{-- Account selector --}}
                <div>
                    <label class="block text-xs font-medium text-white/40 mb-2 uppercase tracking-wider">Account</label>
                    <div class="relative">
                        <select name="account_id" required x-model="selectedAccountId" @change="onAccountChange()" class="glass-select w-full">
                            <option value="" disabled class="bg-dark-900">Select Account</option>
                            @if(isset($accounts))
                                @foreach($accounts as $acc)
                                    <option value="{{ $acc->id }}" class="bg-dark-900">
                                        {{ $acc->nickname ?? $acc->username }} ({{ strtoupper($acc->region ?? 'N/A') }})
                                    </option>
                                @endforeach
                            @endif
                        </select>
                        <div class="absolute inset-y-0 right-3 flex items-center pointer-events-none">
                            <svg class="w-4 h-4 text-white/30" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                            </svg>
                        </div>
                    </div>
                </div>

                {{-- Task Type --}}
                <div>
                    <label class="block text-xs font-medium text-white/40 mb-2 uppercase tracking-wider">Task Type</label>
                    <div class="relative">
                        <select name="task_type" required x-model="taskType" class="glass-select w-full">
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

                {{-- Scheduled Time --}}
                <div>
                    <label class="block text-xs font-medium text-white/40 mb-2 uppercase tracking-wider">Run At Time</label>
                    <input type="time" name="run_at_time" required value="{{ old('run_at_time') }}"
                           class="glass-input w-full">
                </div>

                {{-- Hidden Payload Json field --}}
                <input type="hidden" name="payload" :value="JSON.stringify(payload)">
            </div>

            {{-- Dynamic Fields based on Task Type --}}
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-5" x-show="taskType !== ''" x-cloak>
                
                {{-- 1. Building Selector (for stop/start/buff tasks) --}}
                <div x-show="taskType === 'stop_production' || taskType === 'start_production' || taskType === 'apply_buff'">
                    <label class="block text-xs font-medium text-white/40 mb-2 uppercase tracking-wider">Target Building</label>
                    <div class="relative">
                        <select x-model="payload.grid" class="glass-select w-full">
                            <option value="" class="bg-dark-900">Select Building</option>
                            <template x-for="b in zone.buildings" :key="b.buildingGrid">
                                <option :value="b.buildingGrid" class="bg-dark-900" 
                                        x-text="formatBuildingName(b.buildingName_string) + ' (Grid #' + b.buildingGrid + ' Lvl ' + b.upgradeLevel + ')'">
                                </option>
                            </template>
                        </select>
                        <div class="absolute inset-y-0 right-3 flex items-center pointer-events-none">
                            <svg class="w-4 h-4 text-white/30" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                            </svg>
                        </div>
                    </div>
                </div>

                {{-- 2. Buff Selector (for apply_buff tasks) --}}
                <div x-show="taskType === 'apply_buff'">
                    <label class="block text-xs font-medium text-white/40 mb-2 uppercase tracking-wider">Buff Item</label>
                    <div class="relative">
                        <select @change="onBuffChange($event.target.value)" class="glass-select w-full">
                            <option value="" class="bg-dark-900">Select Buff</option>
                            <template x-for="bf in zone.buffs" :key="bf.uniqueId1 + '-' + bf.uniqueId2">
                                <option :value="bf.uniqueId1 + '|' + bf.uniqueID2 + '|' + bf.uniqueId2" class="bg-dark-900"
                                        x-text="formatBuffName(bf.name) + ' (' + bf.amount + ' left)'">
                                </option>
                            </template>
                        </select>
                        <div class="absolute inset-y-0 right-3 flex items-center pointer-events-none">
                            <svg class="w-4 h-4 text-white/30" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                            </svg>
                        </div>
                    </div>
                </div>

                {{-- 3. Specialist Selector (for geologist/explorer send tasks) --}}
                <div x-show="taskType === 'send_geologist' || taskType === 'send_explorer'">
                    <label class="block text-xs font-medium text-white/40 mb-2 uppercase tracking-wider">Select Specialist</label>
                    <div class="relative">
                        <select @change="onSpecialistChange($event.target.value)" class="glass-select w-full">
                            <option value="" class="bg-dark-900">Select Specialist</option>
                            <template x-for="sp in filteredSpecialists" :key="sp.uniqueId1 + '-' + sp.uniqueId2">
                                <option :value="sp.uniqueId1 + '|' + sp.uniqueID1 + '|' + sp.uniqueID2 + '|' + sp.uniqueId2" class="bg-dark-900"
                                        x-text="sp.name || (taskType === 'send_geologist' ? 'Geologist' : 'Explorer')">
                                </option>
                            </template>
                        </select>
                        <div class="absolute inset-y-0 right-3 flex items-center pointer-events-none">
                            <svg class="w-4 h-4 text-white/30" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                            </svg>
                        </div>
                    </div>
                </div>

                {{-- 4. Search Task Category (for geologist/explorer task types) --}}
                <div x-show="taskType === 'send_geologist' || taskType === 'send_explorer'">
                    <label class="block text-xs font-medium text-white/40 mb-2 uppercase tracking-wider">Search Type</label>
                    <div class="relative">
                        <select x-model.number="payload.task_type" @change="onSearchTypeChange()" class="glass-select w-full">
                            <template x-if="taskType === 'send_geologist'">
                                <option value="0" class="bg-dark-900">Deposit Search</option>
                            </template>
                            <template x-if="taskType === 'send_explorer'">
                                <optgroup label="Explorer Tasks" class="bg-dark-900">
                                    <option value="1" class="bg-dark-900">Treasure Hunt</option>
                                    <option value="2" class="bg-dark-900">Adventure Hunt</option>
                                </optgroup>
                            </template>
                        </select>
                        <div class="absolute inset-y-0 right-3 flex items-center pointer-events-none">
                            <svg class="w-4 h-4 text-white/30" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                            </svg>
                        </div>
                    </div>
                </div>

                {{-- 5. Sub-Task ID Selector (specific search task, e.g. Copper, Gold, Short search) --}}
                <div x-show="taskType === 'send_geologist' || taskType === 'send_explorer'">
                    <label class="block text-xs font-medium text-white/40 mb-2 uppercase tracking-wider">Search Target / Duration</label>
                    <div class="relative">
                        <select x-model.number="payload.sub_task_id" class="glass-select w-full">
                            <template x-for="st in availableSubTasks" :key="st.id">
                                <option :value="st.id" class="bg-dark-900" x-text="st.name"></option>
                            </template>
                        </select>
                        <div class="absolute inset-y-0 right-3 flex items-center pointer-events-none">
                            <svg class="w-4 h-4 text-white/30" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                            </svg>
                        </div>
                    </div>
                </div>

            </div>

            <button type="submit" class="btn-primary flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                Schedule Task
            </button>
        </form>
    </div>

    {{-- Tasks List --}}
    <div>
        <h2 class="text-lg font-semibold text-white flex items-center gap-2 mb-5">
            <svg class="w-5 h-5 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
            </svg>
            Scheduled Tasks
            @if(isset($tasks))
                <span class="badge badge-neutral text-[10px]">{{ $tasks->count() }}</span>
            @endif
        </h2>

        @if(isset($tasks) && $tasks->count() > 0)
            @php
                $groupedTasks = $tasks->groupBy(function($task) {
                    return $task->account->nickname ?? $task->account->username ?? 'Unknown';
                });
            @endphp

            <div class="space-y-6">
                @foreach($groupedTasks as $accountName => $accountTasks)
                    <div class="glass-card overflow-hidden">
                        {{-- Group header --}}
                        <div class="px-5 py-3 border-b border-white/5 bg-white/[0.02]">
                            <h3 class="font-medium text-white/60 flex items-center gap-2">
                                <svg class="w-4 h-4 text-emerald-400/60" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                                </svg>
                                {{ $accountName }}
                                <span class="badge badge-neutral text-[10px]">{{ $accountTasks->count() }} tasks</span>
                            </h3>
                        </div>

                        {{-- Task rows --}}
                        <div class="divide-y divide-white/5">
                            @foreach($accountTasks as $task)
                                @php
                                    $typeIcons = [
                                        'stop_production'   => '🛑',
                                        'start_production'  => '▶️',
                                        'apply_buff'        => '⚡',
                                        'send_geologist'    => '⛏️',
                                        'send_explorer'     => '🧭',
                                    ];
                                    $typeLabels = [
                                        'stop_production'   => 'Stop Production',
                                        'start_production'  => 'Start Production',
                                        'apply_buff'        => 'Apply Buff',
                                        'send_geologist'    => 'Send Geologist',
                                        'send_explorer'     => 'Send Explorer',
                                    ];
                                    $taskPayload = is_string($task->payload) ? json_decode($task->payload, true) : ($task->payload ?? []);
                                @endphp

                                <div class="flex items-center gap-4 px-5 py-4 hover:bg-white/[0.02] transition-all duration-200 group">
                                    {{-- Type icon --}}
                                    <div class="w-9 h-9 rounded-lg bg-white/5 flex items-center justify-center text-lg flex-shrink-0">
                                        {{ $typeIcons[$task->task_type] ?? '📋' }}
                                    </div>

                                    {{-- Task info --}}
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium text-white/80 group-hover:text-white transition-colors">
                                            {{ $typeLabels[$task->task_type] ?? ucwords(str_replace('_', ' ', $task->task_type)) }}
                                        </p>
                                        <div class="flex items-center gap-2 mt-0.5">
                                            @if(isset($taskPayload['grid']))
                                                <span class="text-[10px] text-white/30 font-mono">Grid #{{ $taskPayload['grid'] }}</span>
                                            @endif
                                            @if(isset($taskPayload['sub_task_id']))
                                                @php
                                                    $stNames = [
                                                        0 => [1 => 'Copper', 2 => 'Stone', 3 => 'Coal', 4 => 'Gold', 5 => 'Iron', 6 => 'Marble'],
                                                        1 => [1 => 'Short Treasure', 2 => 'Medium Treasure', 3 => 'Long Treasure', 4 => 'Very Long Treasure', 5 => 'Extended Treasure'],
                                                        2 => [1 => 'Short Adventure', 2 => 'Medium Adventure', 3 => 'Long Adventure', 4 => 'Very Long Adventure']
                                                    ];
                                                    $taskCat = $taskPayload['task_type'] ?? 0;
                                                    $subId = $taskPayload['sub_task_id'] ?? 0;
                                                    $stName = $stNames[$taskCat][$subId] ?? 'Task #' . $subId;
                                                @endphp
                                                <span class="text-[10px] text-white/30">{{ $stName }}</span>
                                            @endif
                                        </div>
                                    </div>

                                    {{-- Scheduled time --}}
                                    <div class="text-right flex-shrink-0">
                                        <p class="text-sm text-white/60 font-mono">{{ substr($task->run_at_time, 0, 5) ?? '—' }}</p>
                                        <p class="text-[10px] text-white/20">Scheduled</p>
                                    </div>

                                    {{-- Last run result --}}
                                    @if(isset($task->last_result))
                                        <span class="badge {{ str_contains($task->last_result, 'OK') ? 'badge-success' : 'badge-danger' }} text-[10px] flex-shrink-0 max-w-[150px] truncate" title="{{ $task->last_result }}">
                                            {{ str_replace('OK: AMF response received', 'Success', $task->last_result) }}
                                        </span>
                                    @endif

                                    {{-- Active toggle --}}
                                    <form action="{{ route('tasks.toggle', $task->id) }}" method="POST" class="inline flex-shrink-0">
                                        @csrf
                                        <button type="submit" class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors duration-300 {{ $task->is_active ? 'bg-emerald-500' : 'bg-white/10' }}" title="{{ $task->is_active ? 'Deactivate' : 'Activate' }}">
                                            <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow-lg transition-transform duration-300 {{ $task->is_active ? 'translate-x-6' : 'translate-x-1' }}"></span>
                                        </button>
                                    </form>

                                    {{-- Delete --}}
                                    <form action="{{ route('tasks.destroy', $task->id) }}" method="POST" class="inline flex-shrink-0"
                                          onsubmit="return confirm('Delete this task?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn-secondary btn-sm text-red-400/60 hover:text-red-400 hover:border-red-500/30">
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                            </svg>
                                        </button>
                                    </form>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="glass-card p-12 text-center">
                <div class="w-16 h-16 rounded-2xl bg-white/5 flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-white/20" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                </div>
                <h3 class="text-white/60 font-medium mb-1">No Tasks Scheduled</h3>
                <p class="text-white/30 text-sm">Create your first automated task using the form above.</p>
            </div>
        @endif
    </div>
@endsection

@push('scripts')
<script>
    window.accountsData = @json($accounts);
</script>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
<script>
    function taskPlanner() {
        return {
            selectedAccountId: '',
            taskType: '',
            accounts: [],
            zone: { buildings: [], specialists: [], buffs: [] },
            payload: {},

            init() {
                this.accounts = window.accountsData || [];
                this.$watch('taskType', (val) => {
                    this.resetPayload(val);
                });
            },

            onAccountChange() {
                const acc = this.accounts.find(a => a.id == this.selectedAccountId);
                if (acc && acc.zone_data) {
                    try {
                        this.zone = typeof acc.zone_data === 'string' 
                            ? JSON.parse(acc.zone_data) 
                            : acc.zone_data;
                    } catch (e) {
                        this.zone = { buildings: [], specialists: [], buffs: [] };
                    }
                } else {
                    this.zone = { buildings: [], specialists: [], buffs: [] };
                }
                this.resetPayload(this.taskType);
            },

            resetPayload(type) {
                this.payload = {};
                if (type === 'stop_production' || type === 'start_production') {
                    this.payload = { grid: '' };
                } else if (type === 'apply_buff') {
                    this.payload = { grid: '', unique_id1: '', unique_id2: '' };
                } else if (type === 'send_geologist' || type === 'send_explorer') {
                    this.payload = { 
                        unique_id1: '', 
                        unique_id2: '', 
                        task_type: type === 'send_geologist' ? 0 : 1, 
                        sub_task_id: 1 
                    };
                }
            },

            onBuffChange(val) {
                if (!val) return;
                const parts = val.split('|');
                // Format: uniqueId1 | uniqueID2 (if exists) | uniqueId2
                this.payload.unique_id1 = parseInt(parts[0]);
                this.payload.unique_id2 = parseInt(parts[2] || parts[1] || 0);
            },

            onSpecialistChange(val) {
                if (!val) return;
                const parts = val.split('|');
                // Format: uniqueId1 | uniqueID1 | uniqueID2 | uniqueId2
                this.payload.unique_id1 = parseInt(parts[0]);
                this.payload.unique_id2 = parseInt(parts[3] || parts[2] || 0);
            },

            onSearchTypeChange() {
                // Set default sub task
                this.payload.sub_task_id = 1;
            },

            get filteredSpecialists() {
                if (!this.zone.specialists) return [];
                return this.zone.specialists.filter(sp => {
                    const typeName = String(sp.type || '').toLowerCase();
                    if (this.taskType === 'send_geologist') {
                        return typeName.includes('geologist');
                    } else if (this.taskType === 'send_explorer') {
                        return typeName.includes('explorer') || typeName.includes('scout') || typeName.includes('specialist');
                    }
                    return false;
                });
            },

            get availableSubTasks() {
                if (this.taskType === 'send_geologist') {
                    return [
                        { id: 1, name: 'Copper Deposit' },
                        { id: 2, name: 'Stone Deposit' },
                        { id: 3, name: 'Coal Deposit' },
                        { id: 4, name: 'Gold Deposit' },
                        { id: 5, name: 'Iron Deposit' },
                        { id: 6, name: 'Marble Deposit' }
                    ];
                } else if (this.taskType === 'send_explorer') {
                    if (this.payload.task_type === 1) { // Treasure Hunt
                        return [
                            { id: 1, name: 'Short Treasure Hunt (6h)' },
                            { id: 2, name: 'Medium Treasure Hunt (12h)' },
                            { id: 3, name: 'Long Treasure Hunt (24h)' },
                            { id: 4, name: 'Very Long Treasure Hunt (36h)' },
                            { id: 5, name: 'Extended Treasure Hunt (48h)' }
                        ];
                    } else { // Adventure Hunt
                        return [
                            { id: 1, name: 'Short Adventure Hunt' },
                            { id: 2, name: 'Medium Adventure Hunt' },
                            { id: 3, name: 'Long Adventure Hunt' },
                            { id: 4, name: 'Very Long Adventure Hunt' }
                        ];
                    }
                }
                return [];
            },

            formatBuildingName(name) {
                if (!name) return 'Unknown Building';
                let formatted = name.replace(/(?<!^)(?=[A-Z])/g, ' ');
                formatted = formatted.replace(/_/g, ' ');
                return formatted.replace(/\w\S*/g, (w) => w.replace(/^\w/, (c) => c.toUpperCase()));
            },

            formatBuffName(name) {
                if (!name) return 'Buff';
                let formatted = name.replace(/(?<!^)(?=[A-Z])/g, ' ');
                formatted = formatted.replace(/_/g, ' ');
                return formatted.replace(/\w\S*/g, (w) => w.replace(/^\w/, (c) => c.toUpperCase()));
            }
        }
    }
</script>
@endpush
