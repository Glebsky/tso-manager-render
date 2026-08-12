<template>
    <div class="glass-card p-5 transition-all duration-300 hover:border-white/20"
         :class="{ 'border-emerald-500/30 bg-emerald-500/[0.02]': task.is_active }">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
            <!-- Тип и иконка -->
            <div class="flex items-start gap-3 min-w-0 flex-1">
                <div class="w-10 h-10 rounded-xl border flex items-center justify-center text-lg flex-shrink-0"
                     :class="task.is_active ? 'bg-emerald-500/10 border-emerald-500/20 text-emerald-400' : 'bg-white/5 border-white/10 text-white/40'">
                    {{ task.task_type === 'sequence' ? '⛓️' : (typeIcons[task.task_type] || '📋') }}
                </div>

                <!-- Название и теги -->
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2 flex-wrap mb-1">
                        <p class="text-sm font-bold text-white tracking-wide">
                            <span v-if="task.name" class="text-emerald-400">{{ task.name }}</span>
                            <span v-else-if="task.task_type === 'sequence'">{{ t('tasks.task_series') }} ({{ getTaskActionsList(task).length }})</span>
                            <span v-else>{{ typeLabels[task.task_type] || task.task_type }}</span>
                        </p>

                        <!-- Статус активности -->
                        <span class="badge text-[10px] font-semibold uppercase px-2 py-0.5"
                              :class="task.is_active ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-amber-500/10 text-amber-400 border border-amber-500/20'">
                            {{ task.is_active ? '● ' + t('tasks.status.active') : '○ ' + t('tasks.status.pause_short') }}
                        </span>

                        <!-- Тип расписания -->
                        <span class="badge badge-neutral text-[9px] uppercase tracking-wider">
                            {{ task.schedule_type === 'once' ? t('tasks.once') : task.schedule_type === 'interval' ? t('tasks.interval') : t('tasks.daily') }}
                        </span>
                    </div>

                    <div class="flex flex-wrap items-center gap-2 text-[11px] text-white/50">
                        <!-- Раскрытие списка действий -->
                        <button type="button"
                                @click="$emit('toggle-expand', task.id)"
                                class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[10px] font-semibold bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-300 border border-emerald-500/20 transition-all duration-200">
                            <span>{{ isExpanded ? '📖 ' + t('tasks.hide_actions') : '📘 ' + t('tasks.show_actions') }} ({{ getTaskActionsList(task).length }})</span>
                            <svg class="w-3 h-3 transition-transform duration-300" :class="{ 'rotate-180': isExpanded }" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                            </svg>
                        </button>

                        <span v-if="task.task_type !== 'sequence' && task.payload && task.payload.grid" class="font-mono bg-white/5 px-2 py-0.5 rounded text-white/60">
                            {{ t('tasks.grid_number', { id: task.payload.grid }) }}
                        </span>
                        <span v-if="task.task_type !== 'sequence' && task.payload && task.payload.sub_task_id !== undefined" class="text-white/60 font-medium">
                            {{ getSubTaskLabel(task.task_type, task.payload.task_type, task.payload.sub_task_id) }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Время запуска, Расписание и Кнопки управления -->
            <div class="flex flex-wrap items-center justify-between lg:justify-end gap-3 w-full lg:w-auto pt-3 lg:pt-0 border-t lg:border-t-0 border-white/5">
                <!-- Блок времени до запуска и расписания -->
                <div class="flex items-center gap-3 bg-black/20 border border-white/5 px-3.5 py-2 rounded-xl">
                    <!-- Время до запуска -->
                    <div class="text-right">
                        <div v-if="!task.is_active" class="flex items-center justify-end gap-1.5 text-xs text-amber-400 font-semibold">
                            <span class="w-1.5 h-1.5 rounded-full bg-amber-400"></span>
                            <span>{{ t('tasks.status.pause_short') }}</span>
                        </div>
                        <div v-else-if="task.schedule_type === 'once' && task.last_run_at" class="flex items-center justify-end gap-1.5 text-xs text-white/40 font-medium">
                            <span>{{ t('tasks.status.completed') }}</span>
                        </div>
                        <div v-else class="flex flex-col items-end">
                            <span class="text-xs font-mono font-bold text-emerald-400 flex items-center gap-1.5">
                                <svg class="w-3.5 h-3.5 text-emerald-400 animate-pulse" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                </svg>
                                {{ getTaskNextRunText(task).label }}
                            </span>
                            <span class="text-[9px] text-white/40 font-mono" v-if="getTaskNextRunText(task).nextRunTime">
                                ({{ t('tasks.at_time') }} {{ getTaskNextRunText(task).nextRunTime }})
                            </span>
                        </div>
                        <p class="text-[9px] text-white/30 uppercase tracking-wider mt-0.5 font-semibold">{{ t('tasks.until_launch') }}</p>
                    </div>

                    <!-- Разделитель -->
                    <div class="h-6 w-px bg-white/10 hidden sm:block"></div>

                    <!-- Расписание -->
                    <div class="text-right hidden sm:block">
                        <p v-if="task.schedule_type === 'once'" class="text-xs text-white/70 font-mono font-medium">
                            {{ formatDateTime(task.run_at_datetime) }}
                        </p>
                        <p v-else-if="task.schedule_type === 'interval'" class="text-xs text-white/70 font-mono font-medium">
                            {{ t('tasks.every') }} {{ formatInterval(task.interval_hours, task.interval_minutes) }}
                        </p>
                        <p v-else class="text-xs text-white/70 font-mono font-medium">
                            {{ t('tasks.daily_at') }} {{ task.run_at_time ? utcTimeToLocal(task.run_at_time.substring(0, 5)) : '—' }}
                        </p>
                        <p class="text-[9px] text-white/30 uppercase tracking-wider mt-0.5 font-semibold">{{ t('tasks.schedule') }}</p>
                    </div>
                </div>

                <!-- Последний результат -->
                <span v-if="getTaskLastResultBadge(task)"
                      class="badge text-[10px] flex-shrink-0 max-w-[120px] truncate py-1"
                      :class="getTaskLastResultBadge(task).class"
                      :title="task.last_result">
                    {{ getTaskLastResultBadge(task).label }}
                </span>

                <!-- Кнопки действий -->
                <div class="flex items-center gap-1.5 flex-shrink-0">
                    <button type="button" @click="$emit('toggle-active', task)"
                            class="btn-secondary btn-sm text-xs py-1.5 px-3 flex items-center gap-1"
                            :class="task.is_active ? 'border-amber-500/30 text-amber-300 hover:bg-amber-500/10' : 'border-emerald-500/30 text-emerald-300 hover:bg-emerald-500/10'">
                        {{ task.is_active ? '⏸ ' + t('tasks.status.pause_short') : '▶ ' + t('tasks.status.resume_short') }}
                    </button>

                    <button type="button" @click="$emit('execute', task.id)" :disabled="isExecuting"
                            class="btn-primary btn-sm text-xs py-1.5 px-3 flex items-center gap-1.5">
                        <svg v-if="isExecuting" class="animate-spin h-3.5 w-3.5 text-dark-950" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span>{{ isExecuting ? t('tasks.status.running') : '🚀 ' + t('tasks.run_now') }}</span>
                    </button>

                    <button type="button" @click="$emit('edit', task)"
                            class="btn-secondary btn-sm text-xs py-1.5 px-2 text-white/60 hover:text-white border-white/10 hover:border-white/20"
                            :title="t('tasks.edit_task')">
                        ✏️
                    </button>

                    <button type="button" @click="$emit('delete', task.id)"
                            class="btn-secondary btn-sm text-xs py-1.5 px-2 text-red-400 hover:text-red-300 border-red-500/20 hover:border-red-500/40"
                            :title="t('tasks.delete_task')">
                        🗑
                    </button>
                </div>
            </div>
        </div>

        <!-- Раскрывающийся список шагов серии -->
        <div v-if="isExpanded" class="mt-4 pt-4 border-t border-white/5 space-y-2 animate-fade-in bg-black/20 p-4 rounded-xl">
            <h4 class="text-xs font-semibold text-white/70 uppercase tracking-wider mb-2 flex items-center gap-2">
                <span>📋 {{ t('tasks.actions_list') }}</span>
                <span class="badge badge-neutral text-[10px]">{{ getTaskActionsList(task).length }}</span>
            </h4>

            <div v-for="(act, aIdx) in getTaskActionsList(task)" :key="aIdx"
                 class="flex items-center justify-between gap-3 text-xs p-2.5 rounded-lg border transition-all"
                 :class="getActionStepStatus(task, aIdx) === 'running' ? 'bg-amber-500/10 border-amber-500/30 text-amber-200' :
                         getActionStepStatus(task, aIdx) === 'completed' ? 'bg-emerald-500/10 border-emerald-500/20 text-emerald-300' :
                         getActionStepStatus(task, aIdx) === 'failed' ? 'bg-red-500/10 border-red-500/30 text-red-300' : 'bg-white/5 border-white/5 text-white/70'">
                <div class="flex items-center gap-2 min-w-0 flex-1">
                    <span class="w-5 h-5 rounded-full bg-white/10 flex items-center justify-center text-[10px] font-mono font-bold text-white/80 flex-shrink-0">
                        {{ aIdx + 1 }}
                    </span>
                    <span class="font-medium truncate">{{ typeLabels[act.task_type] || act.task_type }}</span>

                    <span v-if="act.task_type === 'apply_buff' && act.meta && act.meta.buff" class="badge badge-emerald text-[10px] truncate max-w-[150px]">
                        ✨ {{ getBuffDisplayName(act.meta.buff) }}
                    </span>
                    <span v-if="act.payload && act.payload.grid" class="font-mono text-[10px] text-white/50">
                        (Grid #{{ act.payload.grid }})
                    </span>
                </div>

                <div class="flex items-center gap-2 flex-shrink-0 font-mono text-[10px]">
                    <span v-if="act.delay_seconds > 0" class="text-white/40">⏱ {{ act.delay_seconds }}s {{ t('tasks.delay_short') }}</span>
                    <span v-if="getActionStepStatus(task, aIdx) === 'completed'" class="text-emerald-400 font-bold">✓ OK</span>
                    <span v-else-if="getActionStepStatus(task, aIdx) === 'failed'" class="text-red-400 font-bold" :title="getActionStepError(task, aIdx)">
                        ✕ ERROR
                    </span>
                    <span v-else-if="getActionStepStatus(task, aIdx) === 'running'" class="text-amber-400 font-bold animate-pulse">
                        ⏳ RUNNING
                    </span>
                    <span v-else class="text-white/30">PENDING</span>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { t } from '../../lang';

defineProps({
    task: { type: Object, required: true },
    typeIcons: { type: Object, required: true },
    typeLabels: { type: Object, required: true },
    isExpanded: { type: Boolean, default: false },
    isExecuting: { type: Boolean, default: false },
    getTaskActionsList: { type: Function, required: true },
    getSubTaskLabel: { type: Function, required: true },
    getTaskNextRunText: { type: Function, required: true },
    formatDateTime: { type: Function, required: true },
    formatInterval: { type: Function, required: true },
    utcTimeToLocal: { type: Function, required: true },
    getTaskLastResultBadge: { type: Function, required: true },
    getActionStepStatus: { type: Function, required: true },
    getActionStepError: { type: Function, required: true },
    getBuffDisplayName: { type: Function, required: true }
});

defineEmits(['toggle-expand', 'toggle-active', 'execute', 'edit', 'delete']);
</script>
