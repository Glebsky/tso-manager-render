<template>
    <div class="glass-card p-3 sm:p-4.5 transition-all duration-200 hover:border-white/20 relative"
         :class="{
             'border-emerald-500/30 bg-emerald-500/[0.02]': task.is_active && !isDragging,
             'opacity-40 border-dashed border-emerald-500/60 bg-emerald-500/5': isDragging,
             'border-t-2 !border-t-emerald-400': isDragOver && dropPosition === 'top',
             'border-b-2 !border-b-emerald-400': isDragOver && dropPosition === 'bottom'
         }"
         :draggable="canDrag"
         @dragstart="onDragStart"
         @dragenter="$emit('dragenter', $event)"
         @dragover="$emit('dragover', $event)"
         @dragleave="$emit('dragleave', $event)"
         @dragend="onDragEnd">
        <div class="flex flex-col xl:flex-row xl:items-center justify-between gap-3 sm:gap-4">
            <!-- Тип и иконка -->
            <div class="flex items-start gap-2 sm:gap-2.5 min-w-0 flex-1">
                <!-- Drag Handle -->
                <div class="drag-handle cursor-grab active:cursor-grabbing p-1 text-white/30 hover:text-emerald-400 hover:bg-white/5 rounded-lg transition-colors flex items-center justify-center flex-shrink-0 self-center select-none"
                     :title="t('tasks.drag_to_reorder')"
                     :aria-label="t('tasks.drag_to_reorder')"
                     @mouseenter="canDrag = true"
                     @mouseleave="if (!isDragging) canDrag = false;"
                     @touchstart.passive="canDrag = true">
                    <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                        <path d="M7 2a2 2 0 1 0 .001 4.001A2 2 0 0 0 7 2zm0 6a2 2 0 1 0 .001 4.001A2 2 0 0 0 7 8zm0 6a2 2 0 1 0 .001 4.001A2 2 0 0 0 7 14zm6-12a2 2 0 1 0 .001 4.001A2 2 0 0 0 13 2zm0 6a2 2 0 1 0 .001 4.001A2 2 0 0 0 13 8zm0 6a2 2 0 1 0 .001 4.001A2 2 0 0 0 13 14z"/>
                    </svg>
                </div>

                <div class="w-8 h-8 sm:w-10 sm:h-10 rounded-xl border flex items-center justify-center text-base sm:text-lg flex-shrink-0"
                     :class="task.is_active ? 'bg-emerald-500/10 border-emerald-500/20 text-emerald-400' : 'bg-white/5 border-white/10 text-white/40'">
                    {{ taskPrimaryIcon }}
                </div>

                <!-- Название и теги -->
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-1.5 sm:gap-2 flex-wrap mb-1">
                        <p class="text-xs sm:text-sm font-bold text-white tracking-wide">
                            <span v-if="task.name" class="text-emerald-400">{{ task.name }}</span>
                            <span v-else-if="task.task_type === 'sequence'">{{ t('tasks.task_series') }} ({{ getTaskActionsList(task).length }})</span>
                            <span v-else>{{ typeLabels[task.task_type] || task.task_type }}</span>
                        </p>

                        <!-- Статус активности -->
                        <span class="badge text-[9px] sm:text-[10px] font-semibold uppercase px-1.5 sm:px-2 py-0.5"
                              :class="task.is_active ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-amber-500/10 text-amber-400 border border-amber-500/20'">
                            {{ task.is_active ? '● ' + t('tasks.status.active') : '○ ' + t('tasks.status.paused') }}
                        </span>

                        <!-- Тип расписания -->
                        <span class="badge badge-neutral text-[8px] sm:text-[9px] uppercase tracking-wider">
                            {{ task.schedule_type === 'once' ? t('tasks.once') : task.schedule_type === 'interval' ? t('tasks.interval') : t('tasks.daily') }}
                        </span>

                        <!-- Последний результат -->
                        <span v-if="getTaskLastResultBadge(task)"
                              class="badge text-[9px] sm:text-[10px] uppercase font-semibold px-1.5 sm:px-2 py-0.5 truncate max-w-[140px]"
                              :class="getTaskLastResultBadge(task).class"
                              :title="task.last_result">
                            {{ getTaskLastResultBadge(task).label }}
                        </span>
                    </div>

                    <div class="flex flex-wrap items-center gap-1.5 sm:gap-2 text-[10px] sm:text-[11px] text-white/50">
                        <!-- Раскрытие списка действий -->
                        <button type="button"
                                @click="$emit('toggle-expand', task.id)"
                                class="inline-flex items-center gap-1 sm:gap-1.5 px-2 sm:px-2.5 py-0.5 sm:py-1 rounded-lg text-[9px] sm:text-[10px] font-semibold bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-300 border border-emerald-500/20 transition-all duration-200">
                            <span>{{ isExpanded ? '📖 ' + t('tasks.hide_actions') : '📘 ' + t('tasks.show_actions') }} ({{ getTaskActionsList(task).length }})</span>
                            <svg class="w-3 h-3 transition-transform duration-300" :class="{ 'rotate-180': isExpanded }" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                            </svg>
                        </button>

                        <template v-if="task.task_type !== 'sequence'">
                            <!-- Single building stop/start task -->
                            <span v-if="['stop_production', 'start_production'].includes(task.task_type)" class="inline-flex items-center gap-1.5 flex-wrap">
                                <span class="inline-flex items-center gap-1 text-emerald-300 font-medium">
                                    <img v-if="getBuildingInfo(task, { task_type: task.task_type, payload: task.payload }).icon"
                                         :src="getBuildingInfo(task, { task_type: task.task_type, payload: task.payload }).icon"
                                         loading="lazy" decoding="async"
                                         class="w-4 h-4 object-contain rounded flex-shrink-0"
                                         @error="handleBuildingIconError($event, getBuildingInfo(task, { task_type: task.task_type, payload: task.payload }).raw)" />
                                    <span v-else class="text-xs">🏭</span>
                                    <span>{{ getBuildingInfo(task, { task_type: task.task_type, payload: task.payload }).name || t('tasks.building') }}</span>
                                </span>
                                <span v-if="task.payload?.grid" class="font-mono bg-white/5 px-1.5 sm:px-2 py-0.5 rounded text-white/60 text-[10px]">
                                    {{ t('tasks.grid_number', { id: task.payload.grid }) }}
                                </span>
                            </span>

                            <!-- Single buff task -->
                            <span v-else-if="task.task_type === 'apply_buff'" class="inline-flex items-center gap-1.5 flex-wrap">
                                <span class="inline-flex items-center gap-1 text-amber-300 font-medium">
                                    <img v-if="getBuffInfo(task, { task_type: task.task_type, payload: task.payload }).icon"
                                         :src="getBuffInfo(task, { task_type: task.task_type, payload: task.payload }).icon"
                                         loading="lazy" decoding="async"
                                         class="w-4 h-4 object-contain rounded flex-shrink-0"
                                         @error="handleBuffIconError($event, getBuffInfo(task, { task_type: task.task_type, payload: task.payload }).raw)" />
                                    <span v-else class="text-xs">✨</span>
                                    <span>{{ getBuffInfo(task, { task_type: task.task_type, payload: task.payload }).name }}</span>
                                    <span v-if="(task.payload?.amount || 1) > 1" class="font-bold text-amber-200">(x{{ task.payload.amount }})</span>
                                </span>
                                <span v-if="(task.payload?.target_scope || 'self') === 'friend'" class="text-amber-400 text-[10px]">
                                    • 👤 {{ task.payload?.target_player_name || t('tasks.unknown_friend') }}
                                </span>
                                <span class="inline-flex items-center gap-1 text-emerald-300 font-medium">
                                    • <img v-if="getBuildingInfo(task, { task_type: task.task_type, payload: task.payload }).icon"
                                         :src="getBuildingInfo(task, { task_type: task.task_type, payload: task.payload }).icon"
                                         loading="lazy" decoding="async"
                                         class="w-4 h-4 object-contain rounded flex-shrink-0"
                                         @error="handleBuildingIconError($event, getBuildingInfo(task, { task_type: task.task_type, payload: task.payload }).raw)" />
                                    <span v-else class="text-xs">🏭</span>
                                    <span>{{ getBuildingInfo(task, { task_type: task.task_type, payload: task.payload }).name || t('tasks.building') }}</span>
                                </span>
                                <span v-if="task.payload?.grid" class="font-mono bg-white/5 px-1.5 sm:px-2 py-0.5 rounded text-white/60 text-[10px]">
                                    {{ t('tasks.grid_number', { id: task.payload.grid }) }}
                                </span>
                            </span>

                            <!-- Single specialist task -->
                            <span v-else-if="['send_geologist', 'send_explorer'].includes(task.task_type)" class="inline-flex items-center gap-1.5 flex-wrap">
                                <span class="inline-flex items-center gap-1 text-emerald-300 font-medium">
                                    <img v-if="getSpecialistInfo(task, { task_type: task.task_type, payload: task.payload }).icon"
                                         :src="getSpecialistInfo(task, { task_type: task.task_type, payload: task.payload }).icon"
                                         loading="lazy" decoding="async"
                                         class="w-4 h-4 object-contain rounded flex-shrink-0"
                                         @error="handleSpecialistIconError($event)" />
                                    <span v-else class="text-xs">🎖️</span>
                                    <span>{{ getSpecialistInfo(task, { task_type: task.task_type, payload: task.payload }).name }}</span>
                                </span>
                                <span v-if="getSpecialistInfo(task, { task_type: task.task_type, payload: task.payload }).subTaskLabel" class="badge badge-neutral text-[10px]">
                                    🧭 {{ getSpecialistInfo(task, { task_type: task.task_type, payload: task.payload }).subTaskLabel }}
                                </span>
                            </span>

                            <!-- Single buff production task -->
                            <span v-else-if="task.task_type === 'produce_buff'" class="inline-flex items-center gap-1.5 flex-wrap">
                                <span class="inline-flex items-center gap-1 text-emerald-300 font-medium">
                                    <img v-if="getBuffInfo(task, { task_type: task.task_type, payload: task.payload }).icon"
                                         :src="getBuffInfo(task, { task_type: task.task_type, payload: task.payload }).icon"
                                         loading="lazy" decoding="async"
                                         class="w-4 h-4 object-contain rounded flex-shrink-0"
                                         @error="handleBuffIconError($event, task.payload?.recipe_name)" />
                                    <span v-else class="text-xs">🧪</span>
                                    <span>{{ getBuffInfo(task, { task_type: task.task_type, payload: task.payload }).name || task.payload?.recipe_name }} (x{{ task.payload?.amount || 1 }})</span>
                                </span>
                                <span v-if="task.payload?.stacks && task.payload.stacks > 1" class="text-white/60 text-[10px]">
                                    • {{ task.payload.stacks }} {{ t('tasks.produce_stacks').toLowerCase() }}
                                </span>
                                <span v-if="task.payload?.grid" class="font-mono bg-white/5 px-1.5 sm:px-2 py-0.5 rounded text-white/60 text-[10px]">
                                    {{ t('tasks.grid_number', { id: task.payload.grid }) }}
                                </span>
                            </span>

                            <span v-else-if="task.payload && task.payload.grid" class="font-mono bg-white/5 px-2 py-0.5 rounded text-white/60">
                                {{ t('tasks.grid_number', { id: task.payload.grid }) }}
                            </span>
                        </template>
                    </div>
                </div>
            </div>

            <!-- Время запуска, Расписание и Кнопки управления -->
            <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between xl:justify-end gap-2 sm:gap-3 w-full xl:w-auto pt-2.5 sm:pt-3 xl:pt-0 border-t xl:border-t-0 border-white/5">
                <!-- Блок времени до запуска и расписания -->
                <div class="flex items-center justify-between gap-3 bg-black/20 border border-white/5 px-3 py-1.5 sm:py-2 rounded-xl flex-1 xl:flex-initial">
                    <!-- Время до запуска -->
                    <div class="text-left sm:text-right min-w-0">
                        <div v-if="!task.is_active" class="flex items-center sm:justify-end gap-1.5 text-xs text-amber-400 font-semibold">
                            <span class="w-1.5 h-1.5 rounded-full bg-amber-400"></span>
                            <span>{{ t('tasks.status.paused') }}</span>
                        </div>
                        <div v-else-if="task.schedule_type === 'once' && task.last_run_at" class="flex items-center sm:justify-end gap-1.5 text-xs text-white/40 font-medium">
                            <span>{{ t('tasks.status.completed') }}</span>
                        </div>
                        <div v-else class="flex flex-col sm:items-end">
                            <span class="text-xs font-mono font-bold text-emerald-400 flex items-center gap-1.5">
                                <svg class="w-3.5 h-3.5 text-emerald-400 animate-pulse shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
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

                <!-- Кнопки действий -->
                <div class="flex items-center gap-1.5 w-full sm:w-auto flex-wrap xs:flex-nowrap">
                    <button type="button" @click="$emit('toggle-active', task)"
                            class="btn-secondary btn-sm text-xs py-1.5 px-2.5 sm:px-3 flex items-center justify-center gap-1 flex-1 sm:flex-initial whitespace-nowrap"
                            :class="task.is_active ? 'border-amber-500/30 text-amber-300 hover:bg-amber-500/10' : 'border-emerald-500/30 text-emerald-300 hover:bg-emerald-500/10'">
                        {{ task.is_active ? '⏸ ' + t('tasks.status.pause_short') : '▶ ' + t('tasks.status.resume_short') }}
                    </button>

                    <button type="button" @click="$emit('execute', task)" :disabled="isExecuting"
                            class="btn-primary btn-sm text-xs py-1.5 px-2.5 sm:px-3 flex items-center justify-center gap-1.5 flex-1 sm:flex-initial whitespace-nowrap">
                        <svg v-if="isExecuting" class="animate-spin h-3.5 w-3.5 text-dark-950" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span class="truncate">{{ isExecuting ? t('tasks.status.running') : '🚀 ' + t('tasks.run_now') }}</span>
                    </button>

                    <div class="flex items-center gap-1.5 shrink-0">
                        <button type="button" @click="$emit('duplicate', task)"
                                class="btn-secondary btn-sm text-xs py-1.5 px-2 text-white/60 hover:text-white border-white/10 hover:border-white/20"
                                :aria-label="t('tasks.duplicate_task') || 'Duplicate task'"
                                :title="t('tasks.duplicate_task')">
                            📋
                        </button>

                        <button type="button" @click="$emit('edit', task)"
                                class="btn-secondary btn-sm text-xs py-1.5 px-2 text-white/60 hover:text-white border-white/10 hover:border-white/20"
                                :aria-label="t('tasks.edit_task') || 'Edit task'"
                                :title="t('tasks.edit_task')">
                            ✏️
                        </button>

                        <button type="button" @click="$emit('delete', task.id)"
                                class="btn-secondary btn-sm text-xs py-1.5 px-2 text-red-400 hover:text-red-300 border-red-500/20 hover:border-red-500/40"
                                :aria-label="t('tasks.delete_task') || 'Delete task'"
                                :title="t('tasks.delete_task')">
                            🗑
                        </button>
                    </div>
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
                 class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 sm:gap-3 text-xs p-2.5 rounded-lg border transition-all"
                 :class="getActionStepStatus(task, aIdx) === 'running' ? 'bg-amber-500/10 border-amber-500/30 text-amber-200' :
                         getActionStepStatus(task, aIdx) === 'completed' ? 'bg-emerald-500/10 border-emerald-500/20 text-emerald-300' :
                         getActionStepStatus(task, aIdx) === 'failed' ? 'bg-red-500/10 border-red-500/30 text-red-300' : 'bg-white/5 border-white/5 text-white/70'">
                <div class="flex items-center gap-2.5 min-w-0 flex-1 flex-wrap">
                    <span class="w-5 h-5 rounded-full bg-white/10 flex items-center justify-center text-[10px] font-mono font-bold text-white/80 flex-shrink-0">
                        {{ aIdx + 1 }}
                    </span>
                    <span class="font-medium truncate flex-shrink-0">{{ typeLabels[act.task_type] || act.task_type }}</span>

                    <!-- Buff Action Details -->
                    <template v-if="act.task_type === 'apply_buff'">
                        <span class="inline-flex items-center gap-1.5 text-amber-300 font-medium max-w-full truncate bg-amber-400/10 px-2 py-0.5 rounded-md border border-amber-400/20 text-[10px]">
                            <img v-if="getBuffInfo(task, act).icon"
                                 :src="getBuffInfo(task, act).icon"
                                 loading="lazy" decoding="async"
                                 class="w-4 h-4 object-contain rounded flex-shrink-0"
                                 @error="handleBuffIconError($event, getBuffInfo(task, act).raw || act.meta?.buff)" />
                            <span v-else class="text-[10px]">✨</span>
                            <span class="truncate">{{ getBuffInfo(task, act).name }}</span>
                            <span v-if="(act.payload?.amount || 1) > 1" class="font-bold text-amber-200">x{{ act.payload.amount }}</span>
                        </span>

                        <span v-if="(act.payload?.target_scope || 'self') === 'friend'" class="text-amber-400 text-[10px] whitespace-nowrap">
                            👤 {{ act.payload?.target_player_name || t('tasks.unknown_friend') }}
                        </span>

                        <span class="inline-flex items-center gap-1 text-emerald-300 font-medium max-w-full truncate text-[10px]">
                            <img v-if="getBuildingInfo(task, act).icon"
                                 :src="getBuildingInfo(task, act).icon"
                                 loading="lazy" decoding="async"
                                 class="w-4 h-4 object-contain rounded flex-shrink-0"
                                 @error="handleBuildingIconError($event, getBuildingInfo(task, act).raw || act.meta?.building)" />
                            <span v-else class="text-[10px]">🏭</span>
                            <span class="truncate">{{ getBuildingInfo(task, act).name || t('tasks.building') }}</span>
                        </span>

                        <span v-if="act.payload?.grid" class="font-mono text-[10px] text-white/50 bg-white/5 px-1.5 py-0.5 rounded whitespace-nowrap">
                            Grid #{{ act.payload.grid }}
                        </span>
                    </template>

                    <!-- Building Stop/Start Production Details -->
                    <template v-else-if="['stop_production', 'start_production'].includes(act.task_type)">
                        <span class="inline-flex items-center gap-1 text-emerald-300 font-medium max-w-full truncate text-[10px]">
                            <img v-if="getBuildingInfo(task, act).icon"
                                 :src="getBuildingInfo(task, act).icon"
                                 loading="lazy" decoding="async"
                                 class="w-4 h-4 object-contain rounded flex-shrink-0"
                                 @error="handleBuildingIconError($event, getBuildingInfo(task, act).raw || act.meta?.building)" />
                            <span v-else class="text-[10px]">🏭</span>
                            <span class="truncate">{{ getBuildingInfo(task, act).name || t('tasks.building') }}</span>
                        </span>

                        <span v-if="act.payload?.grid" class="font-mono text-[10px] text-white/50 bg-white/5 px-1.5 py-0.5 rounded whitespace-nowrap">
                            Grid #{{ act.payload.grid }}
                        </span>
                    </template>

                    <!-- Specialist Dispatch Details -->
                    <template v-else-if="['send_geologist', 'send_explorer'].includes(act.task_type)">
                        <span class="inline-flex items-center gap-1.5 text-emerald-300 font-medium max-w-full truncate bg-emerald-400/10 px-2 py-0.5 rounded-md border border-emerald-400/20 text-[10px]">
                            <img v-if="getSpecialistInfo(task, act).icon"
                                 :src="getSpecialistInfo(task, act).icon"
                                 loading="lazy" decoding="async"
                                 class="w-4 h-4 object-contain rounded flex-shrink-0"
                                 @error="handleSpecialistIconError($event)" />
                            <span v-else class="text-[10px]">🎖️</span>
                            <span class="truncate">{{ getSpecialistInfo(task, act).name }}</span>
                        </span>

                        <span v-if="getSpecialistInfo(task, act).subTaskLabel" class="badge badge-neutral text-[9px] whitespace-nowrap">
                            🧭 {{ getSpecialistInfo(task, act).subTaskLabel }}
                        </span>
                    </template>

                    <!-- Pickups Details -->
                    <template v-else-if="act.task_type === 'collect_pickups'">
                        <span class="badge badge-neutral text-[9px] whitespace-nowrap">
                            🧺 {{ act.payload?.pickup_type === 'event' ? t('tasks.event_pickups') : t('tasks.all_pickups') }}
                        </span>
                    </template>

                    <!-- Build Mine Details -->
                    <template v-else-if="act.task_type === 'build_mine'">
                        <span class="inline-flex items-center gap-1 text-emerald-300 font-medium max-w-full truncate text-[10px]">
                            <img v-if="getBuildingInfo(task, act).icon"
                                 :src="getBuildingInfo(task, act).icon"
                                 loading="lazy" decoding="async"
                                 class="w-4 h-4 object-contain rounded flex-shrink-0"
                                 @error="handleBuildingIconError($event, getBuildingInfo(task, act).raw || act.payload?.mine_name)" />
                            <span v-else class="text-[10px]">⛏️</span>
                            <span class="truncate">{{ getBuildingInfo(task, act).name || act.payload?.mine_name || act.payload?.deposit_name }}</span>
                        </span>
                        <span v-if="act.payload?.grid" class="font-mono text-[10px] text-white/50 bg-white/5 px-1.5 py-0.5 rounded whitespace-nowrap">
                            Grid #{{ act.payload.grid }}
                        </span>
                    </template>

                    <!-- Upgrade Mine Details -->
                    <template v-else-if="act.task_type === 'upgrade_mine'">
                        <span class="inline-flex items-center gap-1 text-emerald-300 font-medium max-w-full truncate text-[10px]">
                            <img v-if="getBuildingInfo(task, act).icon"
                                 :src="getBuildingInfo(task, act).icon"
                                 loading="lazy" decoding="async"
                                 class="w-4 h-4 object-contain rounded flex-shrink-0"
                                 @error="handleBuildingIconError($event, getBuildingInfo(task, act).raw || act.payload?.building_name)" />
                            <span v-else class="text-[10px]">🏭</span>
                            <span class="truncate">{{ getBuildingInfo(task, act).name || act.payload?.building_name }}</span>
                        </span>
                        <span v-if="act.payload?.grid" class="font-mono text-[10px] text-white/50 bg-white/5 px-1.5 py-0.5 rounded whitespace-nowrap">
                            Grid #{{ act.payload.grid }}
                        </span>
                        <span v-if="act.payload?.max_level" class="badge badge-emerald text-[9px] whitespace-nowrap">
                            Lvl ≤ {{ act.payload.max_level }}
                        </span>
                    </template>

                    <!-- Produce Buff Details -->
                    <template v-else-if="act.task_type === 'produce_buff'">
                        <span class="inline-flex items-center gap-1 text-emerald-300 font-medium max-w-full truncate text-[10px]">
                            <img v-if="getBuffInfo(task, act).icon"
                                 :src="getBuffInfo(task, act).icon"
                                 loading="lazy" decoding="async"
                                 class="w-4 h-4 object-contain rounded flex-shrink-0"
                                 @error="handleBuffIconError($event, act.payload?.recipe_name)" />
                            <span v-else class="text-[10px]">🧪</span>
                            <span class="truncate">{{ getBuffInfo(task, act).name || act.payload?.recipe_name }} (x{{ act.payload?.amount || 1 }})</span>
                        </span>
                        <span v-if="act.payload?.stacks && act.payload.stacks > 1" class="text-white/60 text-[10px]">
                            {{ act.payload.stacks }} {{ t('tasks.produce_stacks').toLowerCase() }}
                        </span>
                        <span v-if="act.payload?.grid" class="font-mono text-[10px] text-white/50 bg-white/5 px-1.5 py-0.5 rounded whitespace-nowrap">
                            Grid #{{ act.payload.grid }}
                        </span>
                    </template>
                </div>

                <div class="flex items-center gap-2 flex-shrink-0 font-mono text-[10px] self-end sm:self-center">
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
import { ref, computed } from 'vue';
import { t } from '../../lang';

const props = defineProps({
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
    getBuildingInfo: { type: Function, required: true },
    getBuffInfo: { type: Function, required: true },
    getSpecialistInfo: { type: Function, required: true },
    handleBuildingIconError: { type: Function, default: () => {} },
    handleBuffIconError: { type: Function, default: () => {} },
    handleSpecialistIconError: { type: Function, default: () => {} },
    isDragging: { type: Boolean, default: false },
    isDragOver: { type: Boolean, default: false },
    dropPosition: { type: String, default: null }
});

const emit = defineEmits([
    'toggle-expand',
    'toggle-active',
    'execute',
    'edit',
    'delete',
    'duplicate',
    'dragstart',
    'dragenter',
    'dragover',
    'dragleave',
    'dragend'
]);

const taskPrimaryIcon = computed(() => {
    if (props.task.task_type === 'sequence') {
        const actions = props.getTaskActionsList(props.task);
        if (actions.length > 0) {
            return props.typeIcons[actions[0].task_type] || '⛓️';
        }
        return '⛓️';
    }
    return props.typeIcons[props.task.task_type] || '📋';
});

const canDrag = ref(false);

const onDragStart = (e) => {
    emit('dragstart', e);
};

const onDragEnd = (e) => {
    canDrag.value = false;
    emit('dragend', e);
};
</script>
