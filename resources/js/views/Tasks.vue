<template>
    <div>
        <!-- Заголовок страницы -->
        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-3xl font-bold text-white">{{ t('tasks.title') }}</h1>
                <p class="text-white/40 mt-1">{{ t('tasks.subtitle') }}</p>
            </div>
        </div>

        <!-- Форма добавления/редактирования задачи -->
        <div id="task-form-card" class="glass-card p-6 mb-8 transition-all duration-300" :class="{ 'border-amber-500/30 bg-amber-500/[0.02]': editingTaskId }">
            <div class="flex items-center justify-between mb-5 flex-wrap gap-2">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-gradient-to-br flex items-center justify-center transition-all duration-300"
                         :class="editingTaskId ? 'from-amber-500 to-orange-600 shadow-lg shadow-amber-500/20' : 'from-emerald-500 to-teal-600 shadow-lg shadow-emerald-500/20'">
                        <svg class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path v-if="editingTaskId" stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                            <path v-else stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-lg font-semibold text-white">
                            {{ editingTaskId ? t('tasks.editing_task', { id: editingTaskId }) : t('tasks.schedule_new') }}
                        </h2>
                        <p v-if="editingTaskId" class="text-xs text-amber-400 font-medium">{{ t('tasks.editing_hint') }}</p>
                    </div>
                </div>

                <button v-if="editingTaskId" type="button" @click="cancelEdit" class="btn-secondary btn-sm text-xs text-white/70 hover:text-white flex items-center gap-1 border-white/10 hover:border-white/20">
                    ✕ {{ t('tasks.cancel_editing') }}
                </button>
            </div>

            <form @submit.prevent="scheduleTask">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                    <!-- Название серии -->
                    <div>
                        <label class="block text-xs font-medium text-white/40 mb-2 uppercase tracking-wider">{{ t('tasks.series_name') }}</label>
                        <input type="text" v-model="taskName" :placeholder="t('tasks.series_name_placeholder')" class="glass-input w-full text-xs py-2.5">
                    </div>

                    <!-- Выбор аккаунта -->
                    <div>
                        <label class="block text-xs font-medium text-white/40 mb-2 uppercase tracking-wider">{{ t('tasks.game_account') }}</label>
                        <div class="relative">
                            <button type="button" @click.stop="activeDropdown = activeDropdown === 'account' ? null : 'account'" class="glass-select w-full flex items-center justify-between text-left">
                                <span>{{ selectedAccountLabel }}</span>
                                <svg class="w-4 h-4 text-white/30" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                </svg>
                            </button>
                            <div v-if="activeDropdown === 'account'" class="absolute z-50 mt-1.5 w-full glass-card border border-white/10 shadow-2xl rounded-xl py-1 max-h-60 overflow-y-auto">
                                <button v-for="acc in accounts" :key="acc.id" type="button" @click="selectedAccountId = acc.id; onAccountChange(); activeDropdown = null" class="w-full px-4 py-2 text-left text-xs text-white/80 hover:bg-white/5 hover:text-white transition-colors">
                                    {{ acc.nickname || acc.username }} ({{ (acc.region || '').toUpperCase() }})
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Режим запуска (Тип планирования) -->
                    <div>
                        <label class="block text-xs font-medium text-white/40 mb-2 uppercase tracking-wider">{{ t('tasks.planning_type') }}</label>
                        <div class="grid grid-cols-3 gap-2">
                            <button type="button" @click="scheduleType = 'daily'"
                                    class="px-3 py-2 rounded-lg text-xs font-semibold border transition-all duration-300"
                                    :class="scheduleType === 'daily' ? 'bg-emerald-500/20 text-emerald-400 border-emerald-500/30' : 'bg-white/5 text-white/40 border-transparent hover:bg-white/10'">
                                {{ t('tasks.daily') }}
                            </button>
                            <button type="button" @click="scheduleType = 'once'"
                                    class="px-3 py-2 rounded-lg text-xs font-semibold border transition-all duration-300"
                                    :class="scheduleType === 'once' ? 'bg-emerald-500/20 text-emerald-400 border-emerald-500/30' : 'bg-white/5 text-white/40 border-transparent hover:bg-white/10'">
                                {{ t('tasks.once') }}
                            </button>
                            <button type="button" @click="scheduleType = 'interval'"
                                    class="px-3 py-2 rounded-lg text-xs font-semibold border transition-all duration-300"
                                    :class="scheduleType === 'interval' ? 'bg-emerald-500/20 text-emerald-400 border-emerald-500/30' : 'bg-white/5 text-white/40 border-transparent hover:bg-white/10'">
                                {{ t('tasks.interval') }}
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Блок времени планирования в зависимости от типа -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-5 border-t border-white/5 pt-4">
                    <!-- 1. Ежедневный запуск (Время) -->
                    <div v-if="scheduleType === 'daily'">
                        <label class="block text-xs font-medium text-white/40 mb-2 uppercase tracking-wider">{{ t('tasks.run_time_daily') }}</label>
                        <input type="time" required v-model="runAtTime" class="glass-input w-full">
                    </div>

                    <!-- 2. Одноразовый запуск (Дата и время) -->
                    <div v-if="scheduleType === 'once'">
                        <label class="block text-xs font-medium text-white/40 mb-2 uppercase tracking-wider">{{ t('tasks.run_datetime') }}</label>
                        <input type="datetime-local" required v-model="runAtDatetime" class="glass-input w-full">
                    </div>

                    <!-- 3. Интервальный запуск (Каждые X часов Y минут) -->
                    <div v-if="scheduleType === 'interval'" class="col-span-2">
                        <label class="block text-xs font-medium text-white/40 mb-2 uppercase tracking-wider">{{ t('tasks.run_every') }}</label>
                        <div class="flex gap-4">
                            <div class="flex items-center gap-2 flex-1">
                                <input type="number" min="0" required v-model.number="intervalHours" class="glass-input w-full">
                                <span class="text-xs text-white/40">{{ t('tasks.hours_unit') }}</span>
                            </div>
                            <div class="flex items-center gap-2 flex-1">
                                <input type="number" min="0" max="59" required v-model.number="intervalMinutes" class="glass-input w-full">
                                <span class="text-xs text-white/40">{{ t('tasks.minutes_unit') }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Панель конструктора серии действий -->
                <div class="border-t border-white/5 pt-5 mb-5">
                    <!-- Текущие шаги серии -->
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="text-sm font-semibold text-white flex items-center gap-2">
                            <svg class="w-4 h-4 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244" />
                            </svg>
                            {{ t('tasks.sequence_builder') }}
                        </h3>
                        <button v-if="sequenceActions.length > 0" type="button" @click="clearSequence" class="text-xs text-red-400/70 hover:text-red-400 transition-colors flex items-center gap-1 font-medium">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                            </svg>
                            {{ t('tasks.clear_series') }}
                        </button>
                    </div>

                    <div v-if="sequenceActions.length > 0" class="space-y-2 mb-4 max-h-60 overflow-y-auto pr-2">
                        <div v-for="(act, idx) in sequenceActions" :key="idx" class="glass-card p-3 flex items-center justify-between gap-4 border border-white/5 hover:border-white/10 transition-all duration-200">
                            <div class="flex items-center gap-3 min-w-0">
                                <span class="w-5 h-5 rounded-full bg-emerald-500/10 text-emerald-400 flex items-center justify-center text-[10px] font-bold font-mono flex-shrink-0">{{ idx + 1 }}</span>
                                <span class="text-lg flex-shrink-0">{{ typeIcons[act.task_type] }}</span>
                                <div class="text-xs min-w-0">
                                    <p class="font-semibold text-white/90 truncate">{{ typeLabels[act.task_type] }}</p>
                                    <p class="text-white/40 text-[10px] mt-0.5 truncate">
                                        <span v-if="['stop_production', 'start_production'].includes(act.task_type)">
                                            {{ t('tasks.building') }}: {{ act.meta.building ? getBuildingName(act.meta.building) : t('tasks.grid_number', { id: act.payload.grid }) }}
                                        </span>
                                        <span v-if="act.task_type === 'apply_buff'" class="inline-flex items-center gap-1.5 flex-wrap">
                                            <span class="text-emerald-400 font-semibold">
                                                🏭 {{ t('tasks.building') }}: {{ (act.meta && act.meta.building) ? getBuildingName(act.meta.building) : t('tasks.grid_number', { id: act.payload.grid }) }}
                                            </span>
                                            <span v-if="(act.payload.target_scope || 'self') === 'friend'" class="text-amber-400">
                                                • 👤 {{ t('tasks.friend') }}: <strong>{{ act.payload.target_player_name || t('tasks.unknown_friend') }}</strong>
                                            </span>
                                            <span v-else class="text-white/50">
                                                • 🏡 {{ t('tasks.my_zone') }}
                                            </span>
                                            <span v-if="act.meta && act.meta.buff" class="text-amber-300">
                                                • ✨ {{ t('tasks.buff') }}: <strong>{{ getStarBuffName(act.meta.buff) }}</strong>
                                            </span>
                                            <span class="text-white/60">
                                                • {{ t('tasks.qty_short') }}: <strong>{{ act.payload.amount || 1 }}</strong>
                                            </span>
                                        </span>
                                        <span v-if="['send_geologist', 'send_explorer'].includes(act.task_type)">
                                            {{ t('tasks.specialist') }}: {{ act.meta.specialist ? (act.meta.specialist.name || getSpecialistTypeName(act.meta.specialist.type)) : t('tasks.type_number', { id: act.payload.unique_id1 }) }}
                                            <span v-if="act.meta.subTaskLabel">• {{ act.meta.subTaskLabel }}</span>
                                        </span>
                                    </p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2 flex-shrink-0">
                                <!-- Move Up -->
                                <button type="button" :disabled="idx === 0" @click="moveActionUp(idx)" class="text-white/30 hover:text-emerald-400 disabled:opacity-20 transition-colors p-1" :title="t('tasks.move_up')">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 15.75l7.5-7.5 7.5 7.5" />
                                    </svg>
                                </button>
                                <!-- Move Down -->
                                <button type="button" :disabled="idx === sequenceActions.length - 1" @click="moveActionDown(idx)" class="text-white/30 hover:text-emerald-400 disabled:opacity-20 transition-colors p-1" :title="t('tasks.move_down')">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                                    </svg>
                                </button>
                                <span class="text-[10px] text-amber-400 bg-amber-400/10 px-2 py-0.5 rounded-full border border-amber-400/20 font-mono ml-1">
                                    {{ t('tasks.delay') }}: {{ act.delay_seconds }} {{ t('tasks.seconds_unit') }}.
                                </span>
                                <button type="button" @click="removeAction(idx)" class="text-white/30 hover:text-red-400 transition-colors p-1" :title="t('tasks.remove_step')">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div v-else class="text-xs text-white/30 py-4 text-center border border-dashed border-white/10 rounded-lg mb-4">
                        {{ t('tasks.empty_series') }}
                    </div>

                    <!-- Форма добавления нового шага -->
                    <div class="bg-white/[0.02] border border-white/5 rounded-xl p-4">
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
                            <!-- Выбор типа действия для нового шага -->
                            <div>
                                <label class="block text-[10px] font-medium text-white/40 mb-1.5 uppercase">{{ t('tasks.step_action_type') }}</label>
                                <div class="relative">
                                    <button type="button" :disabled="!selectedAccountId"
                                            @click.stop="toggleStepActionDropdown"
                                            class="glass-select w-full flex items-center justify-between text-left text-xs py-2 disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-300"
                                            :class="{ 'border-amber-500/40 bg-amber-500/5 text-amber-400/80': !selectedAccountId }">
                                        <span>{{ selectedAccountId ? stepActionTypeLabel : t('tasks.toast.select_account_first') }}</span>
                                        <svg class="w-3.5 h-3.5 text-white/30" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                        </svg>
                                    </button>
                                    <div v-if="selectedAccountId && activeDropdown === 'stepActionType'" class="absolute z-50 mt-1.5 w-full glass-card border border-white/10 shadow-2xl rounded-xl py-1 max-h-60 overflow-y-auto">
                                        <button type="button" @click="stepActionType = 'stop_production'; onStepActionTypeChange(); activeDropdown = null" class="w-full px-3 py-1.5 text-left text-xs text-white/80 hover:bg-white/5 hover:text-white transition-colors">🛑 {{ t('tasks.action.stop_production') }}</button>
                                        <button type="button" @click="stepActionType = 'start_production'; onStepActionTypeChange(); activeDropdown = null" class="w-full px-3 py-1.5 text-left text-xs text-white/80 hover:bg-white/5 hover:text-white transition-colors">▶️ {{ t('tasks.action.start_production') }}</button>
                                        <button type="button" @click="stepActionType = 'apply_buff'; onStepActionTypeChange(); activeDropdown = null" class="w-full px-3 py-1.5 text-left text-xs text-white/80 hover:bg-white/5 hover:text-white transition-colors">⚡ {{ t('tasks.action.apply_buff') }}</button>
                                        <button type="button" @click="stepActionType = 'send_geologist'; onStepActionTypeChange(); activeDropdown = null" class="w-full px-3 py-1.5 text-left text-xs text-white/80 hover:bg-white/5 hover:text-white transition-colors">⛏️ {{ t('tasks.action.send_geologist') }}</button>
                                        <button type="button" @click="stepActionType = 'send_explorer'; onStepActionTypeChange(); activeDropdown = null" class="w-full px-3 py-1.5 text-left text-xs text-white/80 hover:bg-white/5 hover:text-white transition-colors">🧭 {{ t('tasks.action.send_explorer') }}</button>
                                    </div>
                                </div>
                            </div>

                            <!-- Кастомный выбор в зависимости от типа шага -->
                            <div class="md:col-span-2">
                                <label class="block text-[10px] font-medium text-white/40 mb-1.5 uppercase">{{ t('tasks.step_params') }}</label>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                    <!-- Селекторы целевой зоны и зданий/друзей -->
                                    <div class="col-span-2">
                                        <!-- ЗДАНИЯ (для остановки, запуска или баффа) -->
                                        <div v-if="['stop_production', 'start_production', 'apply_buff'].includes(stepActionType)" class="mb-3">
                                            <!-- Переключатель: Моя зона / Зона друга (только для баффа) -->
                                            <div v-if="stepActionType === 'apply_buff'" class="mb-3">
                                                <label class="block text-[10px] font-medium text-white/40 mb-1.5 uppercase">{{ t('tasks.where_apply') }}</label>
                                                <div class="grid grid-cols-2 gap-2">
                                                    <button type="button" @click="stepTargetScope = 'self'; onTargetScopeChange()"
                                                            class="px-3 py-1.5 rounded-lg text-xs font-semibold border transition-all duration-300"
                                                            :class="stepTargetScope === 'self' ? 'bg-emerald-500/20 text-emerald-400 border-emerald-500/30' : 'bg-white/5 text-white/40 border-transparent hover:bg-white/10'">
                                                        🏡 {{ t('tasks.my_zone') }}
                                                    </button>
                                                    <button type="button" @click="stepTargetScope = 'friend'; onTargetScopeChange()"
                                                            class="px-3 py-1.5 rounded-lg text-xs font-semibold border transition-all duration-300"
                                                            :class="stepTargetScope === 'friend' ? 'bg-amber-500/20 text-amber-400 border-amber-500/30' : 'bg-white/5 text-white/40 border-transparent hover:bg-white/10'">
                                                        👤 {{ t('tasks.friend_zone') }}
                                                    </button>
                                                </div>
                                            </div>

                                            <!-- Выбор друга (отображается ТОЛЬКО если выбрана Зона друга) -->
                                            <div v-if="stepActionType === 'apply_buff' && stepTargetScope === 'friend'" class="mb-3">
                                                <label class="block text-[10px] font-medium text-white/40 mb-1.5 uppercase">{{ t('tasks.friend') }}</label>
                                                <div class="relative">
                                                    <button type="button" @click.stop="activeDropdown = activeDropdown === 'friendList' ? null : 'friendList'"
                                                            class="glass-select w-full flex items-center justify-between text-left text-xs py-2 bg-dark-900/40 transition-all duration-300"
                                                            :class="{ 'border-amber-500/40 bg-amber-500/5': !selectedFriend }">
                                                        <span v-if="selectedFriend" class="flex items-center gap-2">
                                                            <span>👤 {{ selectedFriend.nickname || selectedFriend.username }} ({{ t('tasks.level') }} {{ selectedFriend.playerLevel }})</span>
                                                        </span>
                                                        <span v-else class="text-amber-400/80 font-medium">{{ t('tasks.select_friend') }}</span>
                                                        <svg class="w-3.5 h-3.5 text-white/30" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                                        </svg>
                                                    </button>
                                                    <div v-if="activeDropdown === 'friendList'" class="absolute z-50 mt-1.5 w-full glass-card border border-white/10 shadow-2xl rounded-xl py-1 max-h-60 overflow-y-auto">
                                                        <button v-for="friend in friendsList" :key="friend.id" type="button" @click="selectFriend(friend); activeDropdown = null" class="w-full px-3 py-1.5 text-left text-xs text-white/80 hover:bg-white/5 hover:text-white transition-colors flex justify-between items-center">
                                                            <span>👤 {{ friend.nickname || friend.username }} ({{ t('tasks.level') }} {{ friend.playerLevel }})</span>
                                                            <span class="text-[9px]" :class="friend.onlineStatus ? 'text-green-400' : 'text-white/30'">
                                                                {{ friend.onlineStatus ? t('tasks.online') : t('tasks.offline') }}
                                                            </span>
                                                        </button>
                                                        <div v-if="friendsList.length === 0" class="px-3 py-1.5 text-xs text-white/40">
                                                            {{ t('tasks.friends_empty') }}
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="flex items-center justify-between mb-1.5">
                                                <label class="block text-[10px] font-medium text-white/40 uppercase">
                                                    {{ t('tasks.selected_targets', { count: selectedBuildings.length }) }}
                                                </label>
                                                <button v-if="selectedBuildings.length > 0" type="button" @click="clearSelectedBuildings" class="text-[10px] text-red-400/80 hover:text-red-400 transition-colors">
                                                    {{ t('tasks.modal.clear_selection') }}
                                                </button>
                                            </div>

                                            <!-- Список выбранных чипов зданий -->
                                            <div v-if="selectedBuildings.length > 0" class="flex flex-wrap gap-2 mb-2 max-h-40 overflow-y-auto p-2 bg-dark-900/40 rounded-xl border border-white/5">
                                                <div v-for="(bTarget, bIdx) in selectedBuildings" :key="bTarget.id"
                                                     class="glass-card px-2.5 py-1.5 flex items-center gap-2 text-xs border border-emerald-500/30 bg-emerald-500/10 rounded-lg max-w-full">
                                                    <span class="text-[9px] px-1.5 py-0.5 rounded font-medium flex-shrink-0"
                                                          :class="bTarget.scope === 'friend' ? 'bg-amber-500/20 text-amber-300' : 'bg-emerald-500/20 text-emerald-300'">
                                                        {{ bTarget.scope === 'friend' ? ('👤 ' + (bTarget.friend?.nickname || bTarget.friend?.username || t('tasks.friend'))) : '🏡 ' + t('tasks.my_city') }}
                                                    </span>
                                                    <img v-if="getBuildingIcon(bTarget.building)" :src="getBuildingIcon(bTarget.building)" class="w-4 h-4 object-contain flex-shrink-0" @error="handleBuildingIconError($event, bTarget.building)" />
                                                    <span class="text-white/90 font-medium truncate text-xs">{{ getBuildingName(bTarget.building) }} (Grid #{{ bTarget.buildingGrid }})</span>
                                                    <button type="button" @click="removeSelectedBuilding(bIdx)" class="text-white/40 hover:text-red-400 transition-colors ml-1 font-bold flex-shrink-0">✕</button>
                                                </div>
                                            </div>

                                            <!-- Кнопка вызова модального окна выбора зданий -->
                                            <div>
                                                <button v-if="stepTargetScope === 'self'" type="button" @click="openBuildingModal('self')"
                                                        class="glass-select w-full flex items-center justify-between text-left text-xs py-2 bg-dark-900/40 transition-all duration-300"
                                                        :class="{ 'border-amber-500/40 bg-amber-500/5': selectedBuildings.length === 0 }">
                                                    <span class="flex items-center gap-2 truncate">
                                                        <span>🏭 {{ t('tasks.select_buildings') }}</span>
                                                        <span v-if="selectedBuildings.length > 0" class="badge badge-emerald text-[10px]">{{ selectedBuildings.length }}</span>
                                                    </span>
                                                    <svg class="w-3.5 h-3.5 text-white/30 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                                    </svg>
                                                </button>
                                                <button v-else type="button" @click="openBuildingModal('friend')" :disabled="!selectedFriend"
                                                        class="glass-select w-full flex items-center justify-between text-left text-xs py-2 bg-dark-900/40 disabled:opacity-50 transition-all duration-300"
                                                        :class="{ 'border-amber-500/40 bg-amber-500/5': !selectedFriend }">
                                                    <span class="flex items-center gap-2 truncate">
                                                        <span>👤 {{ t('tasks.modal.select_friend_building') }}</span>
                                                        <span v-if="selectedBuildings.filter(b => b.scope === 'friend').length > 0" class="badge badge-emerald text-[10px]">{{ selectedBuildings.filter(b => b.scope === 'friend').length }}</span>
                                                    </span>
                                                    <svg class="w-3.5 h-3.5 text-white/30 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                                    </svg>
                                                </button>
                                            </div>
                                        </div>

                                        <!-- СПЕЦИАЛИСТЫ (для отправки геологов / разведчиков) -->
                                        <div v-if="['send_geologist', 'send_explorer'].includes(stepActionType)" class="mb-3">
                                            <div class="flex items-center justify-between mb-1.5">
                                                <label class="block text-[10px] font-medium text-white/40 uppercase">
                                                    {{ t('tasks.selected_specialists', { count: selectedSpecialists.length }) }}
                                                </label>
                                                <button v-if="selectedSpecialists.length > 0" type="button" @click="clearSelectedSpecialists" class="text-[10px] text-red-400/80 hover:text-red-400 transition-colors">
                                                    {{ t('tasks.modal.clear_selection') }}
                                                </button>
                                            </div>

                                            <!-- Список выбранных чипов специалистов -->
                                            <div v-if="selectedSpecialists.length > 0" class="flex flex-wrap gap-2 mb-2 max-h-40 overflow-y-auto p-2 bg-dark-900/40 rounded-xl border border-white/5">
                                                <div v-for="(spec, sIdx) in selectedSpecialists" :key="getSpecialistId(spec)"
                                                     class="glass-card px-2.5 py-1.5 flex items-center gap-2 text-xs border border-emerald-500/30 bg-emerald-500/10 rounded-lg max-w-full">
                                                    <img v-if="getSpecialistIcon(spec.type)" :src="getSpecialistIcon(spec.type)" class="w-5 h-5 object-contain flex-shrink-0" @error="handleSpecialistIconError($event, spec.type)" />
                                                    <span class="text-white/90 font-medium truncate text-xs">{{ spec.name || getSpecialistTypeName(spec.type) }}</span>
                                                    <button type="button" @click="removeSelectedSpecialist(sIdx)" class="text-white/40 hover:text-red-400 transition-colors ml-1 font-bold flex-shrink-0">✕</button>
                                                </div>
                                            </div>

                                            <button type="button" @click="openSpecialistModal"
                                                    class="glass-select w-full flex items-center justify-between text-left text-xs py-2 bg-dark-900/40 transition-all duration-300"
                                                    :class="{ 'border-amber-500/40 bg-amber-500/5': selectedSpecialists.length === 0 }">
                                                <span class="flex items-center gap-2 truncate">
                                                    <span>🎖️ {{ t('tasks.select_specialists') }}</span>
                                                    <span v-if="selectedSpecialists.length > 0" class="badge badge-emerald text-[10px]">{{ selectedSpecialists.length }}</span>
                                                </span>
                                                <svg class="w-3.5 h-3.5 text-white/30 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                                </svg>
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Бафф и количество (только для apply_buff) -->
                                    <div v-if="stepActionType === 'apply_buff'" class="col-span-2 mt-1 space-y-2">
                                        <button type="button" @click="openBuffModal"
                                                class="glass-select w-full flex items-center justify-between text-left text-xs py-2 bg-dark-900/40 transition-all duration-300"
                                                :class="{ 'border-amber-500/40 bg-amber-500/5': !selectedBuff }">
                                            <span v-if="selectedBuff" class="flex items-center gap-2">
                                                <img v-if="getBuffIcon(selectedBuff)" :src="getBuffIcon(selectedBuff)" class="w-5 h-5 object-contain" @error="handleBuffIconError($event, selectedBuff)" />
                                                <span class="truncate">{{ getStarBuffName(selectedBuff) }} ({{ selectedBuff.amount }})</span>
                                            </span>
                                            <span v-else class="text-amber-400/80 font-medium">{{ t('tasks.select_buff') }} ({{ totalBuffsCount }} {{ t('tasks.avail_short') }})</span>
                                            <svg class="w-3.5 h-3.5 text-white/30" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                            </svg>
                                        </button>

                                        <div>
                                            <label class="block text-[10px] font-medium text-white/40 mb-1 uppercase">{{ t('tasks.quantity') }}</label>
                                        </div>
                                    </div>

                                    <!-- Тип поиска (для специалистов) -->
                                    <div v-if="['send_geologist', 'send_explorer'].includes(stepActionType)">
                                        <div class="relative">
                                            <button type="button" @click.stop="activeDropdown = activeDropdown === 'searchType' ? null : 'searchType'" class="glass-select w-full flex items-center justify-between text-left text-xs py-2 bg-dark-900/40">
                                                <span>{{ specialistSearchTypeLabel }}</span>
                                                <svg class="w-3.5 h-3.5 text-white/30" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                                </svg>
                                            </button>
                                            <div v-if="activeDropdown === 'searchType'" class="absolute z-50 mt-1.5 w-full glass-card border border-white/10 shadow-2xl rounded-xl py-1 max-h-60 overflow-y-auto">
                                                <button v-if="stepActionType === 'send_geologist'" type="button" @click="payload.task_type = 0; onSearchTypeChange(); activeDropdown = null" class="w-full px-3 py-1.5 text-left text-xs text-white/80 hover:bg-white/5 hover:text-white transition-colors">{{ t('tasks.search_deposits') }}</button>
                                                <template v-if="stepActionType === 'send_explorer'">
                                                    <button type="button" @click="payload.task_type = 1; onSearchTypeChange(); activeDropdown = null" class="w-full px-3 py-1.5 text-left text-xs text-white/80 hover:bg-white/5 hover:text-white transition-colors">{{ t('tasks.search_treasure') }}</button>
                                                    <button type="button" @click="payload.task_type = 2; onSearchTypeChange(); activeDropdown = null" class="w-full px-3 py-1.5 text-left text-xs text-white/80 hover:bg-white/5 hover:text-white transition-colors">{{ t('tasks.search_adventure') }}</button>
                                                </template>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Цель поиска / Длительность (для специалистов) -->
                                    <div v-if="['send_geologist', 'send_explorer'].includes(stepActionType)">
                                        <div class="relative">
                                            <button type="button" @click.stop="activeDropdown = activeDropdown === 'subTask' ? null : 'subTask'" class="glass-select w-full flex items-center justify-between text-left text-xs py-2 bg-dark-900/40">
                                                <span>{{ specialistSubTaskLabel }}</span>
                                                <svg class="w-3.5 h-3.5 text-white/30" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                                </svg>
                                            </button>
                                            <div v-if="activeDropdown === 'subTask'" class="absolute z-50 mt-1.5 w-full glass-card border border-white/10 shadow-2xl rounded-xl py-1 max-h-60 overflow-y-auto">
                                                <button v-for="st in availableSubTasks" :key="st.id" type="button" @click="payload.sub_task_id = st.id; activeDropdown = null" class="w-full px-3 py-1.5 text-left text-xs text-white/80 hover:bg-white/5 hover:text-white transition-colors">
                                                    {{ st.name }}
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Задержка после шага -->
                            <div>
                                <label class="block text-[10px] font-medium text-white/40 mb-1.5 uppercase">{{ t('tasks.step_delay') }}</label>
                                <div class="flex items-center gap-2">
                                    <input type="number" min="0" required v-model.number="stepDelay" class="glass-input w-full text-xs py-1.5">
                                    <span class="text-[10px] text-white/40 font-semibold uppercase">{{ t('tasks.seconds_unit') }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-end">
                            <button type="button" @click="addStepToSequence" class="px-4 py-2 rounded-lg text-xs font-semibold bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 hover:bg-emerald-500/30 transition-all duration-300 flex items-center gap-1.5">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                </svg>
                                {{ t('tasks.add_step') }}
                            </button>
                        </div>
                    </div>
                </div>

                <button type="submit" :disabled="scheduling" class="btn-primary flex items-center gap-2 disabled:opacity-50"
                        :class="{ 'bg-gradient-to-r from-amber-500 to-orange-600 border-amber-500/30': editingTaskId }">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    {{ scheduling ? t('tasks.saving') : (editingTaskId ? t('tasks.save_changes') : t('tasks.schedule_series')) }}
                </button>
            </form>
        </div>

        <!-- Список запланированных задач -->
        <div>
            <h2 class="text-lg font-semibold text-white flex items-center gap-2 mb-5">
                <svg class="w-5 h-5 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
                {{ t('tasks.scheduled_tasks') }}
                <span class="badge badge-neutral text-[10px]">{{ tasks.length }}</span>
            </h2>

            <div v-if="tasks.length > 0" class="space-y-6">
                <div v-for="(groupTasks, accountName) in groupedTasks" :key="accountName" class="glass-card overflow-hidden">
                    <!-- Заголовок группы (Аккаунт) -->
                    <div class="px-5 py-3 border-b border-white/5 bg-white/[0.02]">
                        <h3 class="font-medium text-white/60 flex items-center gap-2">
                            <svg class="w-4 h-4 text-emerald-400/60" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                            </svg>
                            {{ accountName }}
                            <span class="badge badge-neutral text-[10px]">{{ groupTasks.length }} {{ t('tasks.tasks_word') }}</span>
                        </h3>
                    </div>

                    <!-- Список задач -->
                    <div class="divide-y divide-white/5">
                        <div v-for="task in groupTasks" :key="task.id" class="p-5 hover:bg-white/[0.01] transition-all duration-200 group">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                <div class="flex items-center gap-3">
                                    <!-- Иконка действия -->
                                    <div class="w-10 h-10 rounded-xl bg-white/5 flex items-center justify-center text-xl flex-shrink-0 border border-white/5">
                                        {{ task.task_type === 'sequence' ? '⛓️' : (typeIcons[task.task_type] || '📋') }}
                                    </div>

                                    <!-- Информация о задаче -->
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <p class="text-sm font-semibold text-white/90 group-hover:text-white transition-colors">
                                                <span v-if="task.name" class="text-emerald-400/90">{{ task.name }}</span>
                                                <span v-else-if="task.task_type === 'sequence'">{{ $lang.t('tasks.task_series') }} ({{ getTaskActionsList(task).length }})</span>
                                                <span v-else>{{ typeLabels[task.task_type] || task.task_type }}</span>
                                            </p>
                                            <span class="badge badge-neutral text-[9px] uppercase">
                                                {{ task.schedule_type === 'once' ? $lang.t('tasks.once') : task.schedule_type === 'interval' ? $lang.t('tasks.interval') : $lang.t('tasks.daily') }}
                                            </span>
                                        </div>

                                        <div class="flex flex-wrap items-center gap-2 mt-1.5 text-[11px]">
                                            <!-- Кнопка раскрывающегося списка всех действий -->
                                            <button type="button"
                                                    @click="toggleTaskExpanded(task.id)"
                                                    class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md text-[10px] font-medium bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-300 border border-emerald-500/20 transition-all duration-200">
                                                <span>{{ expandedTasks[task.id] ? '📖 ' + $lang.t('tasks.hide_actions') : '📘 ' + $lang.t('tasks.show_actions') }} ({{ getTaskActionsList(task).length }})</span>
                                                <svg class="w-3 h-3 transition-transform duration-300" :class="{ 'rotate-180': expandedTasks[task.id] }" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                                </svg>
                                            </button>

                                            <span v-if="task.task_type !== 'sequence' && task.payload && task.payload.grid" class="font-mono text-white/40">
                                                {{ $lang.t('tasks.grid_number', { id: task.payload.grid }) }}
                                            </span>
                                            <span v-if="task.task_type !== 'sequence' && task.payload && task.payload.sub_task_id !== undefined" class="text-white/40">
                                                {{ getSubTaskLabel(task.task_type, task.payload.task_type, task.payload.sub_task_id) }}
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Правая секция: До следующего запуска, Расписание, Статус и Действия -->
                                <div class="flex items-center justify-end gap-4 ml-auto sm:ml-0">
                                    <!-- Время до следующего запуска задачи -->
                                    <div class="text-right px-3 py-1.5 rounded-xl bg-white/[0.02] border border-white/5 min-w-[130px]">
                                        <div v-if="!task.is_active" class="flex items-center justify-end gap-1.5 text-xs text-amber-400/80 font-medium">
                                            <span class="w-1.5 h-1.5 rounded-full bg-amber-400"></span>
                                            <span>{{ $lang.t('tasks.status.pause_short') }}</span>
                                        </div>
                                        <div v-else-if="task.schedule_type === 'once' && task.last_run_at" class="flex items-center justify-end gap-1.5 text-xs text-white/40">
                                            <span>{{ $lang.t('tasks.status.completed') }}</span>
                                        </div>
                                        <div v-else class="flex flex-col items-end">
                                            <span class="text-xs font-mono font-bold text-emerald-400 flex items-center gap-1.5">
                                                <svg class="w-3 h-3 text-emerald-400 animate-pulse" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                                </svg>
                                                {{ getTaskNextRunText(task).label }}
                                            </span>
                                            <span class="text-[9px] text-white/30 font-mono" v-if="getTaskNextRunText(task).nextRunTime">
                                                ({{ $lang.t('tasks.at_time') }} {{ getTaskNextRunText(task).nextRunTime }})
                                            </span>
                                        </div>
                                        <p class="text-[9px] text-white/30 uppercase tracking-wider mt-0.5 text-right font-medium">{{ $lang.t('tasks.until_launch') }}</p>
                                    </div>

                                    <!-- Расписание -->
                                    <div class="text-right hidden md:block">
                                        <p v-if="task.schedule_type === 'once'" class="text-xs text-white/60 font-mono">
                                            {{ formatDateTime(task.run_at_datetime) }}
                                        </p>
                                        <p v-else-if="task.schedule_type === 'interval'" class="text-xs text-white/60 font-mono">
                                            {{ $lang.t('tasks.every') }} {{ formatInterval(task.interval_hours, task.interval_minutes) }}
                                        </p>
                                        <p v-else class="text-xs text-white/60 font-mono">
                                            {{ $lang.t('tasks.daily_at') }} {{ task.run_at_time ? utcTimeToLocal(task.run_at_time.substring(0, 5)) : '—' }}
                                        </p>
                                        <p class="text-[9px] text-white/20 uppercase tracking-wider">{{ $lang.t('tasks.schedule') }}</p>
                                    </div>

                                    <!-- Последний результат выполнения -->
                                    <span v-if="task.last_result"
                                          class="badge text-[10px] flex-shrink-0 max-w-[100px] truncate"
                                          :class="task.last_result.includes('OK') ? 'badge-success' : 'badge-danger'"
                                          :title="task.last_result">
                                        {{ task.last_result.includes('OK') ? $lang.t('tasks.success') : $lang.t('tasks.error') }}
                                    </span>

                                    <!-- Кнопка ручного запуска -->
                                    <button @click="runTaskNow(task)"
                                            :disabled="executingTasks[task.id]"
                                            class="btn-secondary btn-sm flex items-center justify-center gap-1.5 flex-shrink-0"
                                            :class="executingTasks[task.id] ? 'opacity-50 cursor-not-allowed' : 'hover:border-emerald-500/30 text-emerald-400/80 hover:text-emerald-400'"
                                            :title="$lang.t('tasks.run_now')">
                                        <svg v-if="executingTasks[task.id]" class="animate-spin h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        <svg v-else class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 0 1 0 1.972l-11.54 6.347a1.125 1.125 0 0 1-1.667-.986V5.653Z" />
                                        </svg>
                                        <span class="text-[9px] uppercase font-semibold">{{ executingTasks[task.id] ? $lang.t('tasks.status.launching') : $lang.t('tasks.run_short') }}</span>
                                    </button>

                                    <!-- Кнопка редактирования -->
                                    <button @click="editTask(task)"
                                            class="btn-secondary btn-sm flex items-center justify-center gap-1.5 flex-shrink-0"
                                            :class="editingTaskId === task.id ? 'border-amber-500/50 bg-amber-500/20 text-amber-300' : 'hover:border-amber-500/30 text-amber-400/80 hover:text-amber-400'"
                                            :title="$lang.t('tasks.edit_task')">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                                        </svg>
                                        <span class="text-[9px] uppercase font-semibold">{{ $lang.t('tasks.edit_short') }}</span>
                                    </button>

                                    <!-- Тумблер активации -->
                                    <button @click="toggleTask(task)"
                                            class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors duration-300 flex-shrink-0"
                                            :class="task.is_active ? 'bg-emerald-500' : 'bg-white/10'">
                                        <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow-lg transition-transform duration-300"
                                              :class="task.is_active ? 'translate-x-6' : 'translate-x-1'"></span>
                                    </button>

                                    <!-- Кнопка удаления -->
                                    <button @click="deleteTask(task.id)"
                                            class="btn-secondary btn-sm text-red-400/60 hover:text-red-400 hover:border-red-500/30 flex-shrink-0">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            <!-- Раскрывающийся список действий задачи -->
                            <div v-if="expandedTasks[task.id]" class="mt-4 pt-4 border-t border-white/5 space-y-2 animate-fade-in">
                                <div class="flex items-center justify-between mb-2">
                                    <p class="text-[10px] uppercase font-semibold text-white/40 tracking-wider flex items-center gap-1.5">
                                        <svg class="w-3.5 h-3.5 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75Zm0 5.25h.007v.008H3.75V12Zm0 5.25h.007v.008H3.75v-.008Z" />
                                        </svg>
                                        {{ $lang.t('tasks.actions_in_task', { count: getTaskActionsList(task).length }) }}
                                    </p>
                                </div>

                                <div class="grid grid-cols-1 gap-2">
                                    <div v-for="(act, aIdx) in getTaskActionsList(task)" :key="aIdx"
                                         class="glass-card p-3 flex flex-col sm:flex-row sm:items-center justify-between gap-3 border transition-all"
                                         :class="[
                                             getActionStepStatus(task, aIdx) === 'failed'
                                                 ? 'border-red-500/40 bg-red-500/10 shadow-lg shadow-red-500/5'
                                                 : getActionStepStatus(task, aIdx) === 'completed'
                                                     ? 'border-emerald-500/20 bg-emerald-500/[0.02]'
                                                     : 'border-white/5 bg-white/[0.01]'
                                         ]">
                                        <div class="flex items-start sm:items-center gap-3">
                                            <span class="w-6 h-6 rounded-lg flex items-center justify-center text-xs font-bold font-mono flex-shrink-0 mt-0.5 sm:mt-0"
                                                  :class="[
                                                      getActionStepStatus(task, aIdx) === 'failed'
                                                          ? 'bg-red-500/20 text-red-400'
                                                          : getActionStepStatus(task, aIdx) === 'completed'
                                                              ? 'bg-emerald-500/20 text-emerald-400'
                                                              : 'bg-white/10 text-white/50'
                                                  ]">
                                                {{ aIdx + 1 }}
                                            </span>
                                            <span class="text-xl flex-shrink-0">{{ typeIcons[act.task_type] || '📋' }}</span>
                                            <div class="min-w-0 text-xs">
                                                <div class="flex items-center gap-2 flex-wrap">
                                                    <span class="font-semibold text-white/90">{{ typeLabels[act.task_type] || act.task_type }}</span>
                                                    <span v-if="act.payload?.target_scope === 'friend'" class="badge badge-warning text-[9px]">{{ $lang.t('tasks.friend_zone') }}</span>
                                                    <span v-else-if="act.payload?.target_scope === 'self'" class="badge badge-neutral text-[9px]">{{ $lang.t('tasks.my_zone') }}</span>

                                                    <!-- Статус шага -->
                                                    <span v-if="getActionStepStatus(task, aIdx) === 'completed'" class="badge badge-emerald text-[9px] flex items-center gap-1">
                                                        ✓ {{ $lang.t('tasks.step_status.completed') }}
                                                    </span>
                                                    <span v-else-if="getActionStepStatus(task, aIdx) === 'failed'" class="badge badge-rose text-[9px] flex items-center gap-1">
                                                        ❌ {{ $lang.t('tasks.step_status.failed') }}
                                                    </span>
                                                    <span v-else-if="getActionStepStatus(task, aIdx) === 'skipped'" class="badge badge-neutral text-[9px] opacity-60">
                                                        ⏸️ {{ $lang.t('tasks.step_status.skipped') }}
                                                    </span>
                                                    <span v-else-if="getActionStepStatus(task, aIdx) === 'running'" class="badge badge-warning text-[9px] animate-pulse">
                                                        ⏳ {{ $lang.t('tasks.step_status.running') }}
                                                    </span>
                                                </div>

                                                <!-- Подробные параметры действия -->
                                                <div class="text-white/60 text-[11px] mt-1 space-y-0.5">
                                                    <!-- Здание -->
                                                    <div v-if="['stop_production', 'start_production', 'apply_buff'].includes(act.task_type)" class="flex items-center gap-1.5 flex-wrap">
                                                        <span class="text-white/40">{{ $lang.t('tasks.building') }}:</span>
                                                        <span class="font-mono text-emerald-300 font-medium">
                                                            {{ getBuildingDisplayName(task, act) }}
                                                        </span>
                                                    </div>

                                                    <!-- Бафф -->
                                                    <div v-if="act.task_type === 'apply_buff'" class="flex items-center gap-1.5 flex-wrap">
                                                        <span class="text-white/40">{{ $lang.t('tasks.buff') }}:</span>
                                                        <span class="font-medium text-amber-300">{{ getBuffDisplayName(task, act) }}</span>
                                                        <span class="text-white/40">• {{ $lang.t('tasks.quantity') }}: <strong class="text-white font-mono">{{ act.payload?.amount || 1 }}</strong></span>
                                                        <span v-if="act.payload?.target_player_name" class="text-emerald-400">• {{ $lang.t('tasks.friend') }}: <strong>{{ act.payload.target_player_name }}</strong></span>
                                                    </div>

                                                    <!-- Специалист -->
                                                    <div v-if="['send_geologist', 'send_explorer'].includes(act.task_type)" class="flex items-center gap-1.5 flex-wrap">
                                                        <span class="text-white/40">{{ $lang.t('tasks.search_type_target') }}:</span>
                                                        <span class="font-medium text-teal-300">
                                                            {{ getSubTaskLabel(act.task_type, act.payload?.task_type, act.payload?.sub_task_id) }}
                                                        </span>
                                                    </div>

                                                    <!-- Блок ошибки конкретного действия -->
                                                    <div v-if="getActionStepStatus(task, aIdx) === 'failed'" class="mt-2 p-2 bg-red-950/80 border border-red-500/40 rounded-lg text-red-300 text-xs flex items-start gap-1.5">
                                                        <span class="flex-shrink-0">⚠️</span>
                                                        <span><strong>{{ $lang.t('tasks.error') }}:</strong> {{ getActionStepError(task, aIdx) }}</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Задержка после шага -->
                                        <div v-if="act.delay_seconds > 0" class="flex items-center gap-1 text-[10px] text-amber-400 bg-amber-400/10 px-2 py-1 rounded-lg border border-amber-400/20 font-mono self-end sm:self-center">
                                            <span>⏱️ {{ $lang.t('tasks.step_delay') }}:</span>
                                            <span class="font-bold">{{ act.delay_seconds }} {{ $lang.t('tasks.seconds_unit') }}.</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Нет задач -->
            <div v-else class="glass-card p-12 text-center">
                <div class="w-16 h-16 rounded-2xl bg-white/5 flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-white/20" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                </div>
                <h3 class="text-white/60 font-medium mb-1">{{ t('tasks.no_tasks') }}</h3>
                <p class="text-white/30 text-sm">{{ t('tasks.no_tasks_hint') }}</p>
            </div>
        </div>

        <!-- МОДАЛЬНОЕ ОКНО: ВЫБОР ЗДАНИЙ -->
        <div v-if="showBuildingModal" @click.self="closeBuildingModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm animate-fade-in">
            <div class="glass-card max-w-3xl w-full flex flex-col max-h-[85vh] shadow-2xl border border-white/10">
                <div class="px-6 py-4 border-b border-white/5 flex items-center justify-between flex-wrap gap-2">
                    <div class="flex items-center gap-3">
                        <h3 class="text-base font-semibold text-white">
                            {{ buildingModalTab === 'friend' ? (t('tasks.modal.select_friend_building') + (selectedFriend ? ' (' + (selectedFriend.nickname || selectedFriend.username) + ')' : '')) : t('tasks.modal.select_building') }}
                        </h3>
                        <span class="badge badge-emerald text-xs font-mono">{{ t('tasks.modal.selected_count', { count: selectedBuildings.length }) }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" @click="selectAllFilteredBuildings" class="btn-secondary btn-sm text-[11px] py-1 px-2.5">
                            {{ t('tasks.modal.select_all') }}
                        </button>
                        <button type="button" @click="clearSelectedBuildings" class="btn-secondary btn-sm text-[11px] py-1 px-2.5 text-red-400 hover:text-red-300 border-red-500/20">
                            {{ t('tasks.modal.clear_selection') }}
                        </button>
                        <button type="button" @click="closeBuildingModal" class="btn-primary btn-sm text-xs py-1 px-3">
                            {{ t('tasks.modal.done') }}
                        </button>
                        <button type="button" @click="closeBuildingModal" class="text-white/40 hover:text-white transition-colors p-1 rounded-lg hover:bg-white/5 ml-1">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Контент фильтров -->
                <div class="p-4 border-b border-white/5 bg-white/[0.01] space-y-3">
                    <div class="flex gap-1.5 flex-wrap">
                        <button type="button" v-for="cat in buildingCategories" :key="cat"
                                @click="buildingModalTab === 'self' ? buildingFilter = cat : friendBuildingFilter = cat"
                                class="px-3 py-1.5 rounded-lg text-[11px] font-semibold transition-all duration-300"
                                :class="(buildingModalTab === 'self' ? buildingFilter === cat : friendBuildingFilter === cat)
                                    ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30'
                                    : 'bg-white/5 text-white/40 border-transparent hover:bg-white/10'">
                            {{ t('account.building_filter.' + cat.toLowerCase()) }}
                        </button>
                    </div>
                    <div class="relative">
                        <input v-if="buildingModalTab === 'self'" v-model="buildingSearch" type="text" :placeholder="t('tasks.modal.search_building')" class="glass-input w-full text-xs py-2 pl-4">
                        <input v-else v-model="friendBuildingSearch" type="text" :placeholder="t('tasks.modal.search_building')" class="glass-input w-full text-xs py-2 pl-4">
                    </div>
                </div>

                <!-- Список карточек зданий для 'self' -->
                <div v-if="buildingModalTab === 'self'" class="p-6 overflow-y-auto flex-1 bg-dark-950/20">
                    <div v-if="filteredBuildings.length > 0" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div v-for="b in filteredBuildings" :key="b.buildingGrid"
                             @click="toggleBuildingSelection(b, 'self')"
                             class="glass-card p-3 cursor-pointer hover:border-emerald-500/40 transition-all duration-200 flex items-center justify-between gap-3"
                             :class="isSelectedBuilding(b.buildingGrid, 'self') ? 'border-emerald-500/70 bg-emerald-500/15 shadow-lg shadow-emerald-500/5' : 'border-transparent hover:bg-white/[0.02]'">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0 bg-dark-900/50 border border-white/5">
                                    <img v-if="getBuildingIcon(b)" :src="getBuildingIcon(b)" :alt="getBuildingName(b)" class="w-7 h-7 object-contain" @error="handleBuildingIconError($event, b)">
                                    <span v-else class="text-sm">🏰</span>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-xs font-semibold text-white/90 truncate">{{ getBuildingName(b) }}</p>
                                    <p class="text-[10px] text-white/40 mt-0.5">{{ t('tasks.grid_number', { id: b.buildingGrid }) }} • {{ t('tasks.level_short') }} {{ b.upgradeLevel || 1 }}</p>
                                </div>
                            </div>
                            <div class="w-5 h-5 rounded-md flex items-center justify-center border transition-all flex-shrink-0"
                                 :class="isSelectedBuilding(b.buildingGrid, 'self') ? 'bg-emerald-500 border-emerald-400 text-dark-950 font-bold' : 'border-white/20 bg-white/5'">
                                <span v-if="isSelectedBuilding(b.buildingGrid, 'self')" class="text-xs">✓</span>
                            </div>
                        </div>
                    </div>
                    <div v-else class="text-center py-8 text-white/30 text-xs">
                        <span v-if="!zone || !zone.buildings || zone.buildings.length === 0">
                            {{ t('tasks.modal.island_not_loaded') }}
                        </span>
                        <span v-else>{{ t('tasks.modal.no_buildings') }}</span>
                    </div>
                </div>

                <!-- Список карточек зданий для 'friend' -->
                <div v-else class="p-6 overflow-y-auto flex-1 bg-dark-950/20">
                    <div v-if="loadingFriendZone" class="text-center py-8 text-emerald-400 text-xs flex items-center justify-center gap-2">
                        <svg class="animate-spin h-4 w-4 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span>{{ t('tasks.loading_zone') }} {{ selectedFriend?.nickname || selectedFriend?.username }}…</span>
                    </div>
                    <div v-else-if="!selectedFriend" class="text-center py-8 text-amber-400/80 text-xs">
                        {{ t('tasks.select_friend') }}
                    </div>
                    <div v-else-if="searchedFriendBuildings.length > 0" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div v-for="b in searchedFriendBuildings" :key="b.buildingGrid"
                             @click="toggleBuildingSelection(b, 'friend', selectedFriend)"
                             class="glass-card p-3 cursor-pointer hover:border-emerald-500/40 transition-all duration-200 flex items-center justify-between gap-3"
                             :class="isSelectedBuilding(b.buildingGrid, 'friend', selectedFriend?.id) ? 'border-emerald-500/70 bg-emerald-500/15 shadow-lg shadow-emerald-500/5' : 'border-transparent hover:bg-white/[0.02]'">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0 bg-dark-900/50 border border-white/5">
                                    <img v-if="getBuildingIcon(b)" :src="getBuildingIcon(b)" :alt="getBuildingName(b)" class="w-7 h-7 object-contain" @error="handleBuildingIconError($event, b)">
                                    <span v-else class="text-sm">🏭</span>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-xs font-semibold text-white/90 truncate">{{ getBuildingName(b) }}</p>
                                    <p class="text-[10px] text-white/40 mt-0.5">{{ t('tasks.grid_number', { id: b.buildingGrid }) }} • {{ t('tasks.level_short') }} {{ b.upgradeLevel || 1 }}</p>
                                </div>
                            </div>
                            <div class="w-5 h-5 rounded-md flex items-center justify-center border transition-all flex-shrink-0"
                                 :class="isSelectedBuilding(b.buildingGrid, 'friend', selectedFriend?.id) ? 'bg-emerald-500 border-emerald-400 text-dark-950 font-bold' : 'border-white/20 bg-white/5'">
                                <span v-if="isSelectedBuilding(b.buildingGrid, 'friend', selectedFriend?.id)" class="text-xs">✓</span>
                            </div>
                        </div>
                    </div>
                    <div v-else class="text-center py-8 text-white/30 text-xs">
                        {{ t('tasks.modal.no_buildings') }}
                    </div>
                </div>
            </div>
        </div>

        <!-- МОДАЛЬНОЕ ОКНО: ВЫБОР СПЕЦИАЛИСТА -->
        <div v-if="showSpecialistModal" @click.self="closeSpecialistModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm animate-fade-in">
            <div class="glass-card max-w-2xl w-full flex flex-col max-h-[85vh] shadow-2xl border border-white/10">
                <div class="px-6 py-4 border-b border-white/5 flex items-center justify-between flex-wrap gap-2">
                    <div class="flex items-center gap-3">
                        <h3 class="text-base font-semibold text-white">{{ t('tasks.modal.select_specialist') }}</h3>
                        <span class="badge badge-emerald text-xs font-mono">{{ t('tasks.modal.selected_count', { count: selectedSpecialists.length }) }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" @click="selectAllFilteredSpecialists" class="btn-secondary btn-sm text-[11px] py-1 px-2.5">
                            {{ t('tasks.modal.select_all') }}
                        </button>
                        <button type="button" @click="clearSelectedSpecialists" class="btn-secondary btn-sm text-[11px] py-1 px-2.5 text-red-400 hover:text-red-300 border-red-500/20">
                            {{ t('tasks.modal.clear_selection') }}
                        </button>
                        <button type="button" @click="closeSpecialistModal" class="btn-primary btn-sm text-xs py-1 px-3">
                            {{ t('tasks.modal.done') }}
                        </button>
                        <button type="button" @click="closeSpecialistModal" class="text-white/40 hover:text-white transition-colors p-1 rounded-lg hover:bg-white/5 ml-1">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>
                <div class="p-4 border-b border-white/5 bg-white/[0.01]">
                    <input v-model="specialistSearch" type="text" :placeholder="t('tasks.modal.search_specialist')" class="glass-input w-full text-xs py-2 pl-4">
                </div>
                <div class="p-6 overflow-y-auto flex-1 bg-dark-950/20">
                    <div v-if="filteredSpecialistsModal.length > 0" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div v-for="s in filteredSpecialistsModal" :key="getSpecialistId(s)"
                             @click="toggleSpecialistSelection(s)"
                             class="glass-card p-3 cursor-pointer hover:border-emerald-500/40 transition-all duration-200 flex items-center justify-between gap-3"
                             :class="isSelectedSpecialist(s) ? 'border-emerald-500/70 bg-emerald-500/15 shadow-lg shadow-emerald-500/5' : 'border-transparent hover:bg-white/[0.02]'">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0 bg-dark-900/50 border border-white/5">
                                    <img v-if="getSpecialistIcon(s.type)" :src="getSpecialistIcon(s.type)" class="w-8 h-8 object-contain" @error="handleSpecialistIconError($event, s.type)">
                                    <span v-else class="text-sm">🎖️</span>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-xs font-semibold text-white/90 truncate">{{ s.name || getSpecialistTypeName(s.type) }}</p>
                                    <p class="text-[10px] text-white/40 mt-0.5">{{ getSpecialistTypeName(s.type) }}</p>
                                </div>
                            </div>
                            <div class="w-5 h-5 rounded-md flex items-center justify-center border transition-all flex-shrink-0"
                                 :class="isSelectedSpecialist(s) ? 'bg-emerald-500 border-emerald-400 text-dark-950 font-bold' : 'border-white/20 bg-white/5'">
                                <span v-if="isSelectedSpecialist(s)" class="text-xs">✓</span>
                            </div>
                        </div>
                    </div>
                    <div v-else class="text-center py-8 text-white/30 text-xs">
                        {{ t('tasks.modal.no_specialists') }}
                    </div>
                </div>
            </div>
        </div>

        <!-- МОДАЛЬНОЕ ОКНО: ВЫБОР БАФФА -->
        <div v-if="showBuffModal" @click.self="closeBuffModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm animate-fade-in">
            <div class="glass-card max-w-2xl w-full flex flex-col max-h-[80vh] shadow-2xl border border-white/10">
                <div class="px-6 py-4 border-b border-white/5 flex items-center justify-between">
                    <h3 class="text-base font-semibold text-white">{{ t('tasks.modal.select_buff') }}</h3>
                    <button type="button" @click="closeBuffModal" class="text-white/40 hover:text-white transition-colors">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <div class="p-4 border-b border-white/5 bg-white/[0.01]">
                    <input v-model="buffSearch" type="text" :placeholder="t('tasks.modal.search_buff')" class="glass-input w-full text-xs py-2 pl-4">
                </div>
                <div class="p-6 overflow-y-auto flex-1 bg-dark-950/20">
                    <div v-if="filteredBuffsModal.length > 0" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div v-for="bf in filteredBuffsModal" :key="bf.uniqueId1 + '-' + bf.uniqueId2"
                             @click="selectBuff(bf)"
                             class="glass-card p-3 cursor-pointer hover:border-emerald-500/40 hover:scale-[1.01] transition-all duration-200 flex items-center gap-3"
                             :class="payload.unique_id1 === bf.uniqueId1 ? 'border-emerald-500/50 bg-emerald-500/10' : 'border-transparent'">
                            <div class="w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0 bg-dark-900/50 border border-white/5">
                                <img v-if="getBuffIcon(bf)" :src="getBuffIcon(bf)" :alt="getStarBuffName(bf)" class="w-8 h-8 object-contain" @error="handleBuffIconError($event, bf)">
                                <span v-else class="text-sm">✨</span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-xs font-semibold text-white/90 truncate">{{ getStarBuffName(bf) }}</p>
                                <p class="text-[10px] text-white/40 mt-0.5">{{ t('tasks.modal.in_stock') }}: {{ bf.amount }}</p>
                            </div>
                        </div>
                    </div>
                    <div v-else class="text-center py-8 text-white/30 text-xs">
                        {{ t('tasks.modal.no_buffs') }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import axios from 'axios';
import { showToast } from '../toast';
import { t, gameAny, gameAnyLookup, intlLocale } from '../lang';
import { humanizeGameId, resourceName, buildingName, isBuffableBuilding, getBuildingCategory } from '../lang/gameNames';
import { getGameImageUrl, handleGameImageError } from '../services/gameImageService';

export default {
    name: 'Tasks',
    setup() {
        const tasks = ref([]);
        const accounts = ref([]);
        const scheduling = ref(false);
        const executingTasks = ref({});

        const selectedAccountId = ref('');
        const taskName = ref('');
        const activeDropdown = ref(null);
        const taskType = ref('sequence');
        const runAtTime = ref('');

        // Новые поля планирования
        const scheduleType = ref('daily');
        const runAtDatetime = ref('');
        const intervalHours = ref(0);
        const intervalMinutes = ref(0);

        // Серии действий
        const sequenceActions = ref([]);
        const stepActionType = ref('stop_production');
        const stepDelay = ref(5);

        const selectedAccount = computed(() => {
            return accounts.value.find(a => Number(a.id) === Number(selectedAccountId.value));
        });

        const selectedAccountLabel = computed(() => {
            const acc = selectedAccount.value;
            if (!acc) return t('tasks.select_account');
            return `${acc.nickname || acc.username} (${(acc.region || '').toUpperCase()})`;
        });

        const stepActionTypeLabel = computed(() => {
            const labels = {
                stop_production: '🛑 ' + t('tasks.action.stop_production'),
                start_production: '▶️ ' + t('tasks.action.start_production'),
                apply_buff: '⚡ ' + t('tasks.action.apply_buff'),
                send_geologist: '⛏️ ' + t('tasks.action.send_geologist'),
                send_explorer: '🧭 ' + t('tasks.action.send_explorer')
            };
            return labels[stepActionType.value] || t('tasks.select_action');
        });

        const specialistSearchTypeLabel = computed(() => {
            if (stepActionType.value === 'send_geologist') {
                return t('tasks.search_deposits');
            }
            if (stepActionType.value === 'send_explorer') {
                return payload.value.task_type === 2 ? t('tasks.search_adventure') : t('tasks.search_treasure');
            }
            return t('tasks.select_search_type');
        });

        const specialistSubTaskLabel = computed(() => {
            const st = availableSubTasks.value.find(s => s.id === payload.value.sub_task_id);
            return st ? st.name : t('tasks.select_target');
        });

        const zone = ref({ buildings: [], specialists: [], buffs: [] });
        const payload = ref({});

        // Состояние модальных окон
        const showBuildingModal = ref(false);
        const showSpecialistModal = ref(false);
        const showBuffModal = ref(false);

        const buildingCategories = ['All', 'Basic', 'Improved', 'Advanced', 'Elite'];
        const buildingSearch = ref('');
        const buildingFilter = ref('All');
        const friendBuildingFilter = ref('All');

        const specialistSearch = ref('');
        const buffSearch = ref('');

        const selectedBuildings = ref([]);
        const selectedSpecialists = ref([]);
        const selectedBuff = ref(null);
        const buildingModalTab = ref('self');

        const stepTargetScope = ref('self');
        const selectedFriend = ref(null);
        const selectedFriendBuilding = ref(null);
        const friendBuildings = ref([]);
        const friendZonesCache = ref({});
        const loadingFriendZone = ref(false);
        const friendZoneError = ref(false);
        const stepAmount = ref(1);
        const showFriendBuildingModal = ref(false);
        const friendBuildingSearch = ref('');

        // Хелперы выбора зданий (мультиселект)
        const getBuildingTargetId = (buildingGrid, scope = 'self', friendId = null) => {
            return scope === 'friend' ? `friend:${friendId}:${buildingGrid}` : `self:${buildingGrid}`;
        };

        const isSelectedBuilding = (buildingGrid, scope = 'self', friendId = null) => {
            const id = getBuildingTargetId(buildingGrid, scope, friendId);
            return selectedBuildings.value.some(b => b.id === id);
        };

        const toggleBuildingSelection = (building, scope = 'self', friend = null) => {
            const friendId = scope === 'friend' ? friend?.id : null;
            const id = getBuildingTargetId(building.buildingGrid, scope, friendId);
            const index = selectedBuildings.value.findIndex(b => b.id === id);
            if (index >= 0) {
                selectedBuildings.value.splice(index, 1);
            } else {
                selectedBuildings.value.push({
                    id,
                    scope,
                    buildingGrid: building.buildingGrid,
                    building: { ...building },
                    friend: friend ? { ...friend } : null
                });
            }
        };

        const removeSelectedBuilding = (index) => {
            selectedBuildings.value.splice(index, 1);
        };

        const clearSelectedBuildings = () => {
            selectedBuildings.value = [];
        };

        const selectAllFilteredBuildings = () => {
            if (buildingModalTab.value === 'self') {
                filteredBuildings.value.forEach(b => {
                    if (!isSelectedBuilding(b.buildingGrid, 'self')) {
                        toggleBuildingSelection(b, 'self');
                    }
                });
            } else if (buildingModalTab.value === 'friend' && selectedFriend.value) {
                searchedFriendBuildings.value.forEach(b => {
                    if (!isSelectedBuilding(b.buildingGrid, 'friend', selectedFriend.value.id)) {
                        toggleBuildingSelection(b, 'friend', selectedFriend.value);
                    }
                });
            }
        };

        // Хелперы выбора специалистов (мультиселект)
        const getSpecialistId = (s) => {
            const u2 = s.uniqueID2 || s.uniqueId2 || 0;
            return `${s.uniqueId1}_${u2}`;
        };

        const isSelectedSpecialist = (s) => {
            const id = getSpecialistId(s);
            return selectedSpecialists.value.some(sp => getSpecialistId(sp) === id);
        };

        const toggleSpecialistSelection = (s) => {
            const id = getSpecialistId(s);
            const index = selectedSpecialists.value.findIndex(sp => getSpecialistId(sp) === id);
            if (index >= 0) {
                selectedSpecialists.value.splice(index, 1);
            } else {
                selectedSpecialists.value.push({ ...s });
            }
        };

        const removeSelectedSpecialist = (index) => {
            selectedSpecialists.value.splice(index, 1);
        };

        const clearSelectedSpecialists = () => {
            selectedSpecialists.value = [];
        };

        const selectAllFilteredSpecialists = () => {
            filteredSpecialistsModal.value.forEach(s => {
                if (!isSelectedSpecialist(s)) {
                    toggleSpecialistSelection(s);
                }
            });
        };

        const friendsList = computed(() => {
            return zone.value?.friends || [];
        });

        const filteredFriendBuildings = computed(() => {
            return friendBuildings.value.filter(b => isBuffableBuilding(b));
        });

        const searchedFriendBuildings = computed(() => {
            let list = filteredFriendBuildings.value;

            if (friendBuildingFilter.value && friendBuildingFilter.value !== 'All') {
                list = list.filter(b => getBuildingCategory(b) === friendBuildingFilter.value);
            }

            if (friendBuildingSearch.value) {
                const q = friendBuildingSearch.value.toLowerCase();
                list = list.filter(b => getBuildingName(b).toLowerCase().includes(q) || String(b.buildingGrid).includes(q));
            }
            return list;
        });

        const selectFriend = (friend) => {
            selectedFriend.value = friend;
            selectedFriendBuilding.value = null;
            payload.value.grid = '';
            
            if (friend) {
                if (!friend.id) {
                    showToast(t('tasks.toast.friend_no_id'), 'error');
                    return;
                }
                const cacheKey = `${selectedAccountId.value}:${friend.id}`;
                if (friendZonesCache.value[cacheKey]) {
                    friendBuildings.value = friendZonesCache.value[cacheKey];
                    friendZoneError.value = false;
                }
                fetchFriendZoneBuildings();
            } else {
                friendBuildings.value = [];
            }
        };

        const fetchFriendZoneBuildings = async () => {
            if (!selectedAccountId.value || !selectedFriend.value) return;
            const friendId = selectedFriend.value.id;
            const cacheKey = `${selectedAccountId.value}:${friendId}`;

            if (!friendZonesCache.value[cacheKey] || friendZonesCache.value[cacheKey].length === 0) {
                loadingFriendZone.value = true;
            }
            friendZoneError.value = false;
            try {
                const res = await axios.get(`/api/accounts/${selectedAccountId.value}/friends/${friendId}/zone`);
                if (res.data.success && Array.isArray(res.data.buildings)) {
                    friendBuildings.value = res.data.buildings;
                    friendZonesCache.value[cacheKey] = res.data.buildings;
                } else if (!friendZonesCache.value[cacheKey]) {
                    friendZoneError.value = true;
                }
            } catch (e) {
                if (!friendZonesCache.value[cacheKey]) {
                    friendZoneError.value = true;
                }
            } finally {
                loadingFriendZone.value = false;
            }
        };

        const openFriendBuildingModal = () => {
            if (!selectedFriend.value) {
                showToast(t('tasks.toast.select_friend_first'), 'warning');
                return;
            }
            friendBuildingSearch.value = '';
            friendBuildingFilter.value = 'All';
            showFriendBuildingModal.value = true;
        };
        const closeFriendBuildingModal = () => { showFriendBuildingModal.value = false; };
        const selectFriendBuilding = (b) => {
            selectedFriendBuilding.value = b;
            payload.value.grid = b.buildingGrid;
            closeFriendBuildingModal();
        };

        const onTargetScopeChange = () => {
            selectedFriend.value = null;
            selectedFriendBuilding.value = null;
            selectedBuildings.value = [];
            payload.value.grid = '';
            friendBuildings.value = [];
            friendZoneError.value = false;
        };

        const typeIcons = {
            stop_production: '🛑',
            start_production: '▶️',
            apply_buff: '⚡',
            send_geologist: '⛏️',
            send_explorer: '🧭'
        };

        const typeLabels = {
            stop_production: t('tasks.type_label.stop_production'),
            start_production: t('tasks.type_label.start_production'),
            apply_buff: t('tasks.type_label.apply_buff'),
            send_geologist: t('tasks.type_label.send_geologist'),
            send_explorer: t('tasks.type_label.send_explorer')
        };

        const SPECIALIST_TYPES = {
            0: 'General', 1: 'Explorer', 2: 'Geologist', 3: 'MasterGeneral',
            4: 'MasterExplorer', 5: 'MasterGeologist', 6: 'TmpArmyTransporter',
            7: 'HalloweenGeneral', 8: 'RetailBoxGeneral', 9: 'EasterGeneral',
            10: 'EasterExplorer', 11: 'RetailBox2General', 12: 'TransporterGeneral',
            13: 'MajorGeneral', 14: 'StarGeneral1', 15: 'StarGeneral2', 16: 'StarGeneral3',
            17: 'FastLuckyExplorer', 18: 'Admiral', 19: 'TransporterAdmiral', 20: 'ExpertAdmiral',
            21: 'ExpertTransporterAdmiral', 22: 'AdditionalAdmiralShop', 23: 'Easter2015TransporterAdmiral',
            24: 'BlackMarshal', 25: 'HalloweenGeneralDracul', 26: 'ConscientiousGeologist',
            27: 'SantaGeneral', 28: 'IntrepidExplorer', 29: 'GeneralVargus', 30: 'GeneralAnslem',
            31: 'GeneralNusala', 32: 'CorageousExplorer', 33: 'GeneralMary', 34: 'IronWilledGeologist',
            35: 'StoneColdGeologist', 36: 'MedicGeneral', 37: 'MadScientistGeneral', 38: 'VersedGeologist',
            39: 'CandidExplorer', 40: 'LovelyGeologist', 41: 'LovelyExplorer', 42: 'GoldheartedGeologist',
            43: 'BorisGeneral', 44: 'PrincessZoeExplorer', 45: 'ArcheologistGeologist', 46: 'Soccer2019Explorer',
            47: 'Anniversary2019General', 48: 'EmphaticExplorer', 49: 'ThoroughGeologist',
            50: 'Halloween2019General', 51: 'BewitchingExplorer', 52: 'Xmas2019General', 53: 'HumbleExplorer',
            54: 'ValentinesTransporterGeneral', 55: 'KeenerExplorer', 56: 'AssassinGeneral', 57: 'SylvanaGeneral',
            58: 'BoldExplorer', 59: 'DiligentGeologist', 60: 'GeneralTrembleBeard', 61: 'ScaredExplorer',
            62: 'ChummyGeologist', 63: 'GhostGeneral', 64: 'FrostyGeneral', 65: 'SnowyExplorer',
            66: 'RomanticExplorer', 67: 'LonerGeneral', 68: 'MotherlyExplorer', 69: 'BenevolentExplorer',
            70: 'RoyalExplorer', 71: 'SophisticatedGeologist', 72: 'GeneralLoudmouth', 73: 'MummifiedGeologist',
            74: 'PirateExplorer', 75: 'NutcrackerGeneral', 76: 'GingerbreadGeologist', 77: 'MiraculousGeneral',
            78: 'FluffyButteExplorer', 79: 'ResoluteGeneral', 80: 'GeologistOnVacation', 81: 'RinaTheExplorer',
            82: 'TransporterGeneralBjoern', 83: 'SootyGeologist', 84: 'LoveStruckExplorer', 85: 'GeneralJuan',
            86: 'BalancedGeologist', 87: 'BlacktreeExplorer', 88: 'Brohmann', 89: 'MarathonGeologist',
            90: 'ChummyExplorer', 91: 'VesyGeologist', 92: 'MercenaryExplorer', 93: 'TheSmuggler',
            94: 'GhostExplorer', 95: 'StargazingGeologist', 96: 'NarcissisticGeneral', 97: 'GloryExploriExplorer',
            98: 'TitanicGeologist'
        };

        const loadPlanner = async () => {
            try {
                const res = await axios.get('/api/tasks');
                tasks.value = res.data.tasks || [];
                accounts.value = res.data.accounts || [];
            } catch (e) {
                showToast(t('tasks.toast.load_failed'), 'error');
            }
        };

        const onAccountChange = () => {
            selectedBuildings.value = [];
            selectedSpecialists.value = [];
            selectedBuff.value = null;
            selectedFriend.value = null;
            selectedFriendBuilding.value = null;
            stepTargetScope.value = 'self';
            stepAmount.value = 1;
            friendBuildings.value = [];
            friendZoneError.value = false;

            const acc = accounts.value.find(a => Number(a.id) === Number(selectedAccountId.value));
            if (acc && acc.zone_data) {
                try {
                    zone.value = typeof acc.zone_data === 'string'
                        ? JSON.parse(acc.zone_data)
                        : acc.zone_data;
                } catch (e) {
                    console.error('Failed to parse zone data:', e);
                    zone.value = { buildings: [], specialists: [], buffs: [] };
                }
            } else {
                zone.value = { buildings: [], specialists: [], buffs: [] };
            }
            resetPayload(stepActionType.value);
        };

        const resetPayload = (type) => {
            payload.value = {};
            selectedBuildings.value = [];
            selectedSpecialists.value = [];
            selectedBuff.value = null;

            if (['stop_production', 'start_production'].includes(type)) {
                payload.value = { grid: '' };
            } else if (type === 'apply_buff') {
                payload.value = { grid: '', unique_id1: '', unique_id2: '' };
            } else if (['send_geologist', 'send_explorer'].includes(type)) {
                payload.value = {
                    unique_id1: '',
                    unique_id2: '',
                    task_type: type === 'send_geologist' ? 0 : 1,
                    sub_task_id: 0
                };
            }
        };

        const onStepActionTypeChange = () => {
            resetPayload(stepActionType.value);
            stepTargetScope.value = 'self';
            selectedFriend.value = null;
            selectedFriendBuilding.value = null;
            friendBuildings.value = [];
            stepAmount.value = 1;
            friendZoneError.value = false;
        };

        const onSearchTypeChange = () => {
            payload.value.sub_task_id = 0;
        };

        const toggleStepActionDropdown = () => {
            if (selectedAccountId.value) {
                activeDropdown.value = activeDropdown.value === 'stepActionType' ? null : 'stepActionType';
            }
        };

        const addStepToSequence = () => {
            if (['stop_production', 'start_production', 'apply_buff'].includes(stepActionType.value)) {
                if (selectedBuildings.value.length === 0) {
                    showToast(t('tasks.toast.select_building_first'), 'warning');
                    return;
                }
                if (stepActionType.value === 'apply_buff' && !selectedBuff.value) {
                    showToast(t('tasks.toast.select_buff_first'), 'warning');
                    return;
                }

                let addedCount = 0;
                for (const bTarget of selectedBuildings.value) {
                    const actionPayload = {
                        grid: bTarget.buildingGrid,
                        target_scope: bTarget.scope,
                        target_player_id: bTarget.scope === 'friend' ? bTarget.friend?.id : null,
                        target_player_name: bTarget.scope === 'friend' ? (bTarget.friend?.nickname || bTarget.friend?.username) : null,
                        amount: stepActionType.value === 'apply_buff' ? stepAmount.value : 1
                    };

                    if (stepActionType.value === 'apply_buff') {
                        actionPayload.unique_id1 = selectedBuff.value.uniqueId1;
                        actionPayload.unique_id2 = selectedBuff.value.uniqueID2 || selectedBuff.value.uniqueId2 || 0;
                    }

                    const meta = {
                        building: { ...bTarget.building },
                        buff: selectedBuff.value ? { ...selectedBuff.value } : null,
                        friend: bTarget.friend ? { ...bTarget.friend } : null,
                    };

                    sequenceActions.value.push({
                        task_type: stepActionType.value,
                        payload: actionPayload,
                        delay_seconds: Number(stepDelay.value || 0),
                        meta: meta
                    });
                    addedCount++;
                }

                selectedBuildings.value = [];
                showToast(`${t('tasks.toast.action_added')} (${addedCount})`);
                return;
            }

            if (['send_geologist', 'send_explorer'].includes(stepActionType.value)) {
                if (selectedSpecialists.value.length === 0) {
                    showToast(t('tasks.toast.select_specialist_first'), 'warning');
                    return;
                }

                let addedCount = 0;
                for (const spec of selectedSpecialists.value) {
                    const actionPayload = {
                        unique_id1: spec.uniqueId1,
                        unique_id2: spec.uniqueID2 || spec.uniqueId2 || 0,
                        task_type: stepActionType.value === 'send_geologist' ? 0 : payload.value.task_type,
                        sub_task_id: payload.value.sub_task_id || 0
                    };

                    const meta = {
                        specialist: { ...spec },
                        subTaskLabel: getSubTaskLabel(stepActionType.value, payload.value.task_type, payload.value.sub_task_id)
                    };

                    sequenceActions.value.push({
                        task_type: stepActionType.value,
                        payload: actionPayload,
                        delay_seconds: Number(stepDelay.value || 0),
                        meta: meta
                    });
                    addedCount++;
                }

                selectedSpecialists.value = [];
                showToast(`${t('tasks.toast.action_added')} (${addedCount})`);
                return;
            }
        };

        const moveActionUp = (idx) => {
            if (idx <= 0) return;
            const temp = sequenceActions.value[idx];
            sequenceActions.value[idx] = sequenceActions.value[idx - 1];
            sequenceActions.value[idx - 1] = temp;
        };

        const moveActionDown = (idx) => {
            if (idx >= sequenceActions.value.length - 1) return;
            const temp = sequenceActions.value[idx];
            sequenceActions.value[idx] = sequenceActions.value[idx + 1];
            sequenceActions.value[idx + 1] = temp;
        };

        const clearSequence = () => {
            if (sequenceActions.value.length === 0) return;
            if (confirm(t('tasks.confirm.clear_series'))) {
                sequenceActions.value = [];
                showToast(t('tasks.toast.series_cleared'));
            }
        };

        const removeAction = (idx) => {
            sequenceActions.value.splice(idx, 1);
            showToast(t('tasks.toast.action_removed'));
        };

        resetPayload('stop_production');

        const isStoppable = (b) => isBuffableBuilding(b);

        const getBuildingName = (b) => (b ? buildingName(b.buildingName_string || b.buildingName || 'Building') : '');

        const getBuildingImageName = (building) => {
            const name = building?.buildingName_string || building?.buildingName || '';
            let imageName = name.replace(/_lvl_\d+/i, '').replace(/decoration_/g, '').trim().toLowerCase();
            const aliases = {
                realwoodsawmill: 'sawmill_real_planks', exoticwoodsawmill: 'sawmill_exotic_planks',
                mahoganysawmill: 'mahogany_sawmill', exoticwoodtreeschool: 'exoticwood_treeschool',
                stonecutter: 'stonemason', marblecutter: 'marblemason', granitecutter: 'granitemason',
            };
            return aliases[imageName] || imageName;
        };

        const getBuildingIcon = (building) => getGameImageUrl('building', getBuildingImageName(building));
        const handleBuildingIconError = (event, building) =>
            handleGameImageError(event, 'building', getBuildingImageName(building));

        const totalBuildingsCount = computed(() => {
            if (!zone.value || !zone.value.buildings) return 0;
            return zone.value.buildings.filter(b => isBuffableBuilding(b)).length;
        });
        const totalSpecialistsCount = computed(() => zone.value?.specialists?.length || 0);
        const totalBuffsCount = computed(() => zone.value?.availableBuffs?.length || 0);

        const filteredBuildings = computed(() => {
            if (!zone.value || !zone.value.buildings) return [];
            let list = zone.value.buildings.filter(b => isBuffableBuilding(b));

            if (buildingFilter.value && buildingFilter.value !== 'All') {
                list = list.filter(b => getBuildingCategory(b) === buildingFilter.value);
            }

            if (buildingSearch.value) {
                const query = buildingSearch.value.toLowerCase();
                list = list.filter(b => getBuildingName(b).toLowerCase().includes(query) || String(b.buildingGrid).includes(query));
            }

            return list;
        });

        // Логика специалистов
        const getSpecialistCategory = (type) => {
            const rawName = SPECIALIST_TYPES[type];
            if (!rawName) return 'Other';
            const lower = rawName.toLowerCase();
            if (lower.includes('explorer') || lower.includes('scout')) return 'Explorer';
            if (lower.includes('geologist')) return 'Geologist';
            return 'General';
        };

        const getSpecialistTypeName = (type) => {
            return SPECIALIST_TYPES[type] || `Specialist #${type}`;
        };

        const getSpecialistIcon = (type) => {
            return `/images/specialists/${type}.webp`;
        };

        const handleSpecialistIconError = (event) => {
            const img = event.target;
            if (img) {
                img.dataset.failed = 'true';
                img.style.display = 'none';
            }
        };

        const filteredSpecialistsModal = computed(() => {
            const specialists = zone.value?.specialists;

            if (!Array.isArray(specialists)) {
                return [];
            }

            let list = specialists.filter(sp => {
                const category = getSpecialistCategory(
                    Number(sp.type)
                );

                if (stepActionType.value === 'send_geologist') {
                    return category === 'Geologist';
                }

                if (stepActionType.value === 'send_explorer') {
                    return category === 'Explorer';
                }

                return false;
            });

            const query = specialistSearch.value
                .trim()
                .toLowerCase();

            if (query) {
                list = list.filter(sp => {
                    const name = String(sp.name || '')
                        .toLowerCase();

                    const typeName = getSpecialistTypeName(
                        Number(sp.type)
                    ).toLowerCase();

                    return name.includes(query)
                        || typeName.includes(query);
                });
            }

            return list;
        });

        const formatResourceName = humanizeGameId;

        // Central game-catalog lookup with legacy prettifier fallback.
        const resourceDisplayName = resourceName;

        const getStarBuffName = (b) => {
            if (!b || !b.buffName_string) return t('tasks.unknown_buff');

            const name = b.buffName_string;
            const template = gameAnyLookup(name);

            if (template) {
                let tpl = template;
                if (tpl.includes('{0}')) {
                    tpl = tpl.replace('{0}', resourceDisplayName(b.resourceName_string));
                }
                tpl = tpl.replace(/\{1,\w+\}/g, '').replace(/[:\s]+$/, '').replace(/\s+/g, ' ').trim();
                return tpl;
            }

            if (name === 'AddResource') {
                return `${t('tasks.buff_add_resource')}: ${resourceDisplayName(b.resourceName_string)}`;
            }
            if (name === 'BuildBuilding') {
                return `${t('tasks.buff_build_license')}: ${resourceDisplayName(b.resourceName_string)}`;
            }
            if (name === 'Adventure') {
                return `${t('tasks.buff_adventure')}: ${resourceDisplayName(b.resourceName_string)}`;
            }

            return name.replace(/(?<!^)(?=[A-Z])/g, ' ').replace(/_/g, ' ');
        };

        const getBuffImageName = (buff) => {
            const name = buff?.buffName_string || buff?.name || '';
            let imageName = name.trim().toLowerCase().replace(/\s+/g, '_').replace(/[\'"]/g, '');
            const aliases = {
                aunt_irmas_basket: 'aunt_irma_basket', aunt_irmas_feast_basket: 'aunt_irma_feast_basket',
                secretsanta: 'buff_secretsanta', buff_secretsanta: 'buff_secretsanta',
            };
            return aliases[imageName] || imageName;
        };

        const getBuffIcon = (buff) => getGameImageUrl('buff', getBuffImageName(buff));
        const handleBuffIconError = (event, buff) =>
            handleGameImageError(event, 'buff', getBuffImageName(buff));

        const filteredBuffsModal = computed(() => {
            if (!zone.value || !zone.value.availableBuffs) return [];
            let list = zone.value.availableBuffs;

            if (buffSearch.value) {
                const query = buffSearch.value.toLowerCase();
                list = list.filter(bf => getStarBuffName(bf).toLowerCase().includes(query));
            }
            return list;
        });

        // Управление модальными окнами
        const openBuildingModal = (tab = 'self') => {
            if (!selectedAccountId.value) {
                showToast(t('tasks.toast.select_account_first'), 'warning');
                return;
            }
            buildingSearch.value = '';
            buildingFilter.value = 'All';
            friendBuildingFilter.value = 'All';
            friendBuildingSearch.value = '';
            buildingModalTab.value = tab;
            showBuildingModal.value = true;
        };
        const closeBuildingModal = () => { showBuildingModal.value = false; };

        const openSpecialistModal = () => {
            if (!selectedAccountId.value) {
                showToast(t('tasks.toast.select_account_first'), 'warning');
                return;
            }
            specialistSearch.value = '';
            showSpecialistModal.value = true;
        };
        const closeSpecialistModal = () => { showSpecialistModal.value = false; };

        const openBuffModal = () => {
            if (!selectedAccountId.value) {
                showToast(t('tasks.toast.select_account_first'), 'warning');
                return;
            }
            buffSearch.value = '';
            showBuffModal.value = true;
        };
        const closeBuffModal = () => { showBuffModal.value = false; };
        const selectBuff = (bf) => {
            selectedBuff.value = bf;
            payload.value.unique_id1 = bf.uniqueId1;
            payload.value.unique_id2 = bf.uniqueID2 || bf.uniqueId2 || 0;
            closeBuffModal();
        };

        const availableSubTasks = computed(() => {
            if (stepActionType.value === 'send_geologist') {
                return [
                    { id: 0, name: t('tasks.deposit.stone') },
                    { id: 1, name: t('tasks.deposit.copper_ore') },
                    { id: 2, name: t('tasks.deposit.marble') },
                    { id: 3, name: t('tasks.deposit.iron_ore') },
                    { id: 4, name: t('tasks.deposit.gold_ore') },
                    { id: 5, name: t('tasks.deposit.coal') },
                    { id: 6, name: t('tasks.deposit.granite') },
                    { id: 7, name: t('tasks.deposit.titanium_ore') },
                    { id: 8, name: t('tasks.deposit.saltpeter') }
                ];
            }

            if (stepActionType.value === 'send_explorer') {
                if (payload.value.task_type === 1) {
                    // Поиск сокровищ
                    return [
                        {
                            id: 0,
                            name: t('tasks.treasure.short')
                        },
                        {
                            id: 1,
                            name: t('tasks.treasure.medium')
                        },
                        {
                            id: 2,
                            name: t('tasks.treasure.long')
                        },
                        {
                            id: 3,
                            name: t('tasks.treasure.very_long')
                        },
                        {
                            id: 6,
                            name: t('tasks.treasure.extra_long')
                        }
                    ];
                }

                if (payload.value.task_type === 2) {
                    // Поиск приключений
                    return [
                        {
                            id: 0,
                            name: t('tasks.adventure.short')
                        },
                        {
                            id: 1,
                            name: t('tasks.adventure.medium')
                        },
                        {
                            id: 2,
                            name: t('tasks.adventure.long')
                        },
                        {
                            id: 3,
                            name: t('tasks.adventure.very_long')
                        }
                    ];
                }
            }

            return [];
        });

        const groupedTasks = computed(() => {
            const groups = {};
            const sortedTasks = [...tasks.value].sort((a, b) => (Number(b.id) || 0) - (Number(a.id) || 0));
            sortedTasks.forEach(taskItem => {
                const name = taskItem.account ? (taskItem.account.nickname || taskItem.account.username) : t('tasks.unknown_account');
                if (!groups[name]) groups[name] = [];
                groups[name].push(taskItem);
            });
            return groups;
        });

        const getSubTaskLabel = (
            coreTaskType,
            category,
            subId
        ) => {
            const stNames = {
                // Геолог
                0: {
                    0: t('tasks.deposit_short.stone'),
                    1: t('tasks.deposit_short.copper'),
                    2: t('tasks.deposit_short.marble'),
                    3: t('tasks.deposit_short.iron'),
                    4: t('tasks.deposit_short.gold'),
                    5: t('tasks.deposit_short.coal'),
                    6: t('tasks.deposit_short.granite'),
                    7: t('tasks.deposit_short.titanium_ore'),
                    8: t('tasks.deposit_short.saltpeter')
                },

                // Разведчик: сокровища
                1: {
                    0: t('tasks.treasure_short.short'),
                    1: t('tasks.treasure_short.medium'),
                    2: t('tasks.treasure_short.long'),
                    3: t('tasks.treasure_short.very_long'),
                    4: t('tasks.treasure_short.erudite'),
                    5: t('tasks.treasure_short.bean_collada'),
                    6: t('tasks.treasure_short.extra_long')
                },

                // Разведчик: приключения
                2: {
                    0: t('tasks.adventure_short.short'),
                    1: t('tasks.adventure_short.medium'),
                    2: t('tasks.adventure_short.long'),
                    3: t('tasks.adventure_short.very_long')
                }
            };

            const cat = category !== undefined
                ? Number(category)
                : coreTaskType === 'send_geologist'
                    ? 0
                    : 1;

            return stNames[cat]?.[Number(subId)]
                || t('tasks.task_number', { id: subId });
        };

        // ===== Работа с часовыми поясами =====
        // Сервер хранит и отдаёт время в UTC.
        // Браузер конвертирует UTC -> локальный пояс при отображении
        // и локальный пояс -> UTC при отправке на сервер.

        const pad2 = (n) => String(n).padStart(2, '0');

        // Парсит дату с сервера. Строки без явного часового пояса считаем UTC.
        const parseServerDate = (dtStr) => {
            if (!dtStr) return null;
            let s = String(dtStr).trim();
            if (!s.includes('T')) s = s.replace(' ', 'T');
            if (!/(Z|[+-]\d{2}:?\d{2})$/.test(s)) s += 'Z';
            const d = new Date(s);
            return isNaN(d.getTime()) ? null : d;
        };

        // 'HH:mm' (UTC, с сервера) -> 'HH:mm' в локальном поясе пользователя
        const utcTimeToLocal = (hhmm) => {
            if (!hhmm) return hhmm;
            const [h, m] = hhmm.split(':').map(Number);
            const d = new Date();
            d.setUTCHours(h, m, 0, 0);
            return `${pad2(d.getHours())}:${pad2(d.getMinutes())}`;
        };

        // 'HH:mm' (локальное, из input[type=time]) -> 'HH:mm' в UTC для сервера
        const localTimeToUtc = (hhmm) => {
            if (!hhmm) return hhmm;
            const [h, m] = hhmm.split(':').map(Number);
            const d = new Date();
            d.setHours(h, m, 0, 0);
            return `${pad2(d.getUTCHours())}:${pad2(d.getUTCMinutes())}`;
        };

        // Значение input[type=datetime-local] (локальное) -> ISO-строка UTC для сервера
        const localDatetimeToUtcIso = (val) => {
            if (!val) return val;
            const d = new Date(val); // datetime-local парсится браузером как локальное время
            return isNaN(d.getTime()) ? val : d.toISOString();
        };

        // UTC-дата с сервера -> значение для input[type=datetime-local] (локальное)
        const utcToDatetimeLocalInput = (dtStr) => {
            const d = parseServerDate(dtStr);
            if (!d) return '';
            return `${d.getFullYear()}-${pad2(d.getMonth() + 1)}-${pad2(d.getDate())}T${pad2(d.getHours())}:${pad2(d.getMinutes())}`;
        };

        const formatDateTime = (dtStr) => {
            if (!dtStr) return '—';
            const d = parseServerDate(dtStr);
            if (!d) return dtStr;
            return d.toLocaleString(intlLocale, {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
            });
        };

        const formatInterval = (hours, minutes) => {
            const parts = [];
            if (hours) parts.push(`${hours} ${t('tasks.hours_short')}`);
            if (minutes) parts.push(`${minutes} ${t('tasks.minutes_short')}`);
            return parts.join(' ') || `0 ${t('tasks.minutes_short')}`;
        };

        const editingTaskId = ref(null);

        const cancelEdit = () => {
            editingTaskId.value = null;
            taskName.value = '';
            sequenceActions.value = [];
            selectedBuildings.value = [];
            selectedSpecialists.value = [];
            selectedBuff.value = null;
            resetPayload(stepActionType.value);

            runAtTime.value = '';
            runAtDatetime.value = '';
            intervalHours.value = 0;
            intervalMinutes.value = 0;
            scheduleType.value = 'daily';
            zone.value = { buildings: [], specialists: [], buffs: [] };
        };

        const editTask = (task) => {
            editingTaskId.value = task.id;
            taskName.value = task.name || '';
            selectedAccountId.value = task.account_id;
            onAccountChange();

            scheduleType.value = task.schedule_type || 'daily';
            runAtTime.value = task.run_at_time ? utcTimeToLocal(task.run_at_time.substring(0, 5)) : '';
            runAtDatetime.value = utcToDatetimeLocalInput(task.run_at_datetime);
            intervalHours.value = task.interval_hours || 0;
            intervalMinutes.value = task.interval_minutes || 0;

            if (task.task_type === 'sequence' && task.payload && task.payload.actions) {
                const acc = accounts.value.find(a => a.id === task.account_id);
                let zoneData = { buildings: [], specialists: [], buffs: [] };
                if (acc && acc.zone_data) {
                    try {
                        zoneData = typeof acc.zone_data === 'string' ? JSON.parse(acc.zone_data) : acc.zone_data;
                    } catch (e) {}
                }

                sequenceActions.value = task.payload.actions.map(act => {
                    const meta = { building: null, buff: null, specialist: null };

                    if (['stop_production', 'start_production', 'apply_buff'].includes(act.task_type)) {
                        if (zoneData.buildings) {
                            meta.building = zoneData.buildings.find(b => b.buildingGrid == act.payload?.grid) || null;
                        }
                    }
                    if (act.task_type === 'apply_buff') {
                        const buffs = zoneData.availableBuffs || zoneData.buffs || [];
                        meta.buff = buffs.find(b => (b.uniqueId1 || b.uniqueID1) == act.payload?.unique_id1 && (b.uniqueId2 || b.uniqueID2) == act.payload?.unique_id2) || null;
                    }
                    if (['send_geologist', 'send_explorer'].includes(act.task_type)) {
                        if (zoneData.specialists) {
                            meta.specialist = zoneData.specialists.find(s => (s.uniqueId1 || s.uniqueId) == act.payload?.unique_id1) || null;
                        }
                    }

                    return {
                        task_type: act.task_type,
                        payload: { ...act.payload },
                        delay_seconds: act.delay_seconds || 0,
                        meta
                    };
                });
            }

            const formCard = document.getElementById('task-form-card');
            if (formCard) {
                formCard.scrollIntoView({ behavior: 'smooth' });
            }
        };

        const scheduleTask = async () => {
            if (!selectedAccountId.value) {
                showToast(t('tasks.toast.select_game_account'), 'warning');
                return;
            }

            if (sequenceActions.value.length === 0) {
                showToast(t('tasks.toast.add_action_first'), 'warning');
                return;
            }

            scheduling.value = true;
            try {
                const postData = {
                    name: taskName.value || null,
                    account_id: selectedAccountId.value,
                    task_type: 'sequence',
                    payload: {
                        actions: sequenceActions.value.map(a => ({
                            task_type: a.task_type,
                            payload: a.payload,
                            delay_seconds: a.delay_seconds
                        }))
                    },
                    schedule_type: scheduleType.value
                };

                if (scheduleType.value === 'daily') {
                    postData.run_at_time = localTimeToUtc(runAtTime.value);
                } else if (scheduleType.value === 'once') {
                    postData.run_at_datetime = localDatetimeToUtcIso(runAtDatetime.value);
                } else if (scheduleType.value === 'interval') {
                    postData.interval_hours = intervalHours.value;
                    postData.interval_minutes = intervalMinutes.value;
                }

                let res;
                if (editingTaskId.value) {
                    res = await axios.put(`/api/tasks/${editingTaskId.value}`, postData);
                } else {
                    res = await axios.post('/api/tasks', postData);
                }

                if (res.data.success) {
                    showToast(editingTaskId.value ? t('tasks.toast.task_updated') : t('tasks.toast.series_scheduled'));
                    cancelEdit();
                    loadPlanner();
                }
            } catch (e) {
                const errorMsg = e.response?.data?.errors
                    ? Object.values(e.response.data.errors).flat().join(', ')
                    : (e.response?.data?.message || e.message || t('tasks.toast.save_failed'));
                showToast(errorMsg, 'error');
            } finally {
                scheduling.value = false;
            }
        };

        const toggleTask = async (task) => {
            try {
                const res = await axios.post(`/api/tasks/${task.id}/toggle`);
                if (res.data.success) {
                    task.is_active = res.data.task.is_active;
                    showToast(task.is_active ? t('tasks.toast.task_activated') : t('tasks.toast.task_paused'));
                }
            } catch (e) {
                showToast(t('tasks.toast.toggle_failed'), 'error');
            }
        };

        const deleteTask = async (id) => {
            if (!confirm(t('tasks.confirm.delete_task'))) return;
            try {
                const res = await axios.delete(`/api/tasks/${id}`);
                if (res.data.success) {
                    showToast(t('tasks.toast.task_deleted'));
                    loadPlanner();
                }
            } catch (e) {
                showToast(t('tasks.toast.delete_failed'), 'error');
            }
        };

        const runTaskNow = async (task) => {
            if (executingTasks.value[task.id]) return;
            executingTasks.value[task.id] = true;
            try {
                const res = await axios.post(`/api/tasks/${task.id}/execute`);
                if (res.data.success) {
                    showToast(t('tasks.toast.run_success') + ': ' + (res.data.message || 'OK'));
                } else {
                    showToast(t('tasks.toast.run_error') + ': ' + (res.data.message || t('tasks.toast.unknown')), 'error');
                }
                loadPlanner();
            } catch (e) {
                showToast(e.response?.data?.message || t('tasks.toast.run_failed'), 'error');
            } finally {
                executingTasks.value[task.id] = false;
            }
        };

        const currentTimeMs = ref(Date.now());
        const expandedTasks = ref({});
        let countdownTimer = null;

        const toggleTaskExpanded = (taskId) => {
            expandedTasks.value[taskId] = !expandedTasks.value[taskId];
        };

        const getTaskActionsList = (task) => {
            if (!task) return [];
            if (task.task_type === 'sequence' && Array.isArray(task.payload?.actions)) {
                return task.payload.actions;
            }
            return [{
                task_type: task.task_type,
                payload: task.payload || {},
                delay_seconds: 0
            }];
        };

        const getActionStepStatus = (task, aIdx) => {
            if (!task) return 'pending';
            const isFailed = task.status === 'failed' || (task.last_result && task.last_result.startsWith('ERROR:'));
            const isCompleted = task.status === 'completed';
            const isRunning = task.status === 'running';
            const completedSteps = task.completed_steps ?? 0;

            if (isCompleted) {
                return 'completed';
            }
            if (isFailed) {
                if (aIdx < completedSteps) {
                    return 'completed';
                } else if (aIdx === completedSteps) {
                    return 'failed';
                } else {
                    return 'skipped';
                }
            }
            if (isRunning) {
                if (aIdx < completedSteps) {
                    return 'completed';
                } else if (aIdx === completedSteps) {
                    return 'running';
                } else {
                    return 'pending';
                }
            }
            return 'pending';
        };

        const getActionStepError = (task, aIdx) => {
            if (getActionStepStatus(task, aIdx) === 'failed') {
                let msg = task.last_result || '';
                if (msg.startsWith('ERROR: ')) {
                    msg = msg.substring(7);
                } else if (msg.startsWith('ERROR:')) {
                    msg = msg.substring(6);
                }

                const match = msg.match(/(?:ошибки|error|код|code)\D*(\d+)/i);
                if (match) {
                    const code = parseInt(match[1], 10);
                    const translationKey = `game_error.${code}`;
                    const translatedMsg = t(translationKey);
                    if (translatedMsg && translatedMsg !== translationKey) {
                        msg = msg.replace(/Неизвестная ошибка (?:сервера )?\(код \d+\)/gi, translatedMsg);
                        msg = msg.replace(/Unknown game error \(code \d+\)/gi, translatedMsg);
                        msg = msg.replace(/Неизвестная ошибка (?:сервера|игры)/gi, translatedMsg);
                        msg = msg.replace(/Unknown game error/gi, translatedMsg);
                    }
                }
                return msg;
            }
            return null;
        };

        const getNextRunDate = (task) => {
            if (!task) return null;

            if (task.schedule_type === 'once') {
                if (!task.run_at_datetime) return null;
                return parseServerDate(task.run_at_datetime);
            }

            if (task.schedule_type === 'daily') {
                if (!task.run_at_time) return null;
                const parts = task.run_at_time.split(':');
                if (parts.length < 2) return null;
                const hours = parseInt(parts[0], 10);
                const minutes = parseInt(parts[1], 10);

                // run_at_time хранится в UTC, поэтому следующий запуск считаем в UTC
                const now = new Date(currentTimeMs.value);
                const next = new Date(Date.UTC(now.getUTCFullYear(), now.getUTCMonth(), now.getUTCDate(), hours, minutes, 0, 0));

                if (next.getTime() <= now.getTime()) {
                    next.setUTCDate(next.getUTCDate() + 1);
                }
                return next;
            }

            if (task.schedule_type === 'interval') {
                const h = Number(task.interval_hours || 0);
                const m = Number(task.interval_minutes || 0);
                const intervalMs = (h * 3600 + m * 60) * 1000;
                if (intervalMs <= 0) return null;

                const baseStr = task.last_run_at || task.created_at;
                if (!baseStr) return null;
                const base = parseServerDate(baseStr);
                if (!base) return null;

                let nextMs = base.getTime() + intervalMs;
                const nowMs = currentTimeMs.value;
                if (nextMs < nowMs) {
                    const passed = Math.ceil((nowMs - base.getTime()) / intervalMs);
                    nextMs = base.getTime() + (passed * intervalMs);
                }
                return new Date(nextMs);
            }

            return null;
        };

        const getTaskNextRunText = (task) => {
            if (!task.is_active) {
                return { status: 'paused', label: t('tasks.status.paused'), detail: '' };
            }

            if (task.schedule_type === 'once' && task.last_run_at) {
                return { status: 'completed', label: t('tasks.status.completed'), detail: '' };
            }

            const nextDate = getNextRunDate(task);
            if (!nextDate) {
                return { status: 'none', label: '—', detail: '' };
            }

            const diffMs = nextDate.getTime() - currentTimeMs.value;

            if (diffMs <= 0) {
                return { status: 'due', label: t('tasks.status.launching'), detail: t('tasks.status.running_detail') };
            }

            const totalSec = Math.floor(diffMs / 1000);
            const days = Math.floor(totalSec / 86400);
            const hours = Math.floor((totalSec % 86400) / 3600);
            const mins = Math.floor((totalSec % 3600) / 60);
            const secs = totalSec % 60;

            let parts = [];
            if (days > 0) parts.push(`${days}${t('tasks.unit.d')}`);
            if (hours > 0 || days > 0) parts.push(`${hours}${t('tasks.unit.h')}`);
            if (mins > 0 || hours > 0 || days > 0) parts.push(`${mins}${t('tasks.unit.m')}`);
            parts.push(`${secs}${t('tasks.unit.s')}`);

            return {
                status: 'active',
                label: t('tasks.in_time', { time: parts.join(' ') }),
                nextRunTime: nextDate.toLocaleTimeString(intlLocale, { hour: '2-digit', minute: '2-digit' })
            };
        };

        const getBuildingDisplayName = (taskObj, action) => {
            if (action.meta?.building) {
                return getBuildingName(action.meta.building);
            }
            const grid = action.payload?.grid;
            if (!grid) return t('tasks.building_not_set');

            const acc = accounts.value.find(a => Number(a.id) === Number(taskObj?.account_id));
            if (acc && acc.zone_data) {
                try {
                    const zd = typeof acc.zone_data === 'string' ? JSON.parse(acc.zone_data) : acc.zone_data;
                    const b = zd.buildings?.find(b => Number(b.buildingGrid) === Number(grid));
                    if (b) return getBuildingName(b);
                } catch (e) {}
            }
            return t('tasks.grid_number', { id: grid });
        };

        const getBuffDisplayName = (taskObj, action) => {
            if (action.meta?.buff) {
                return getStarBuffName(action.meta.buff);
            }
            const u1 = action.payload?.unique_id1;
            if (!u1) return t('tasks.buff_from_menu');

            const acc = accounts.value.find(a => Number(a.id) === Number(taskObj?.account_id));
            if (acc && acc.zone_data) {
                try {
                    const zd = typeof acc.zone_data === 'string' ? JSON.parse(acc.zone_data) : acc.zone_data;
                    const buffs = zd.availableBuffs || zd.buffs || [];
                    const bf = buffs.find(b => (b.uniqueId1 || b.uniqueID1) == u1);
                    if (bf) return getStarBuffName(bf);
                } catch (e) {}
            }
            return t('tasks.buff_number', { id: u1 });
        };

        const closeAllDropdowns = (e) => {
            if (!e.target.closest('.relative')) {
                activeDropdown.value = null;
            }
        };

        onMounted(() => {
            loadPlanner();
            document.addEventListener('click', closeAllDropdowns);
            countdownTimer = setInterval(() => {
                currentTimeMs.value = Date.now();
            }, 1000);
        });

        onUnmounted(() => {
            document.removeEventListener('click', closeAllDropdowns);
            if (countdownTimer) clearInterval(countdownTimer);
        });

        return {
            tasks,
            accounts,
            scheduling,
            selectedAccountId,
            taskName,
            taskType,
            runAtTime,
            scheduleType,
            runAtDatetime,
            intervalHours,
            intervalMinutes,
            zone,
            payload,
            typeIcons,
            typeLabels,
            availableSubTasks,
            groupedTasks,
            getSubTaskLabel,
            activeDropdown,
            selectedAccountLabel,
            stepActionTypeLabel,
            toggleStepActionDropdown,
            specialistSearchTypeLabel,
            specialistSubTaskLabel,
            onAccountChange,
            onStepActionTypeChange,
            onSearchTypeChange,
            sequenceActions,
            stepActionType,
            stepDelay,
            addStepToSequence,
            moveActionUp,
            moveActionDown,
            clearSequence,
            removeAction,
            totalBuildingsCount,
            totalSpecialistsCount,
            totalBuffsCount,
            filteredBuildings,
            filteredSpecialistsModal,
            filteredBuffsModal,
            scheduleTask,
            toggleTask,
            deleteTask,
            runTaskNow,
            executingTasks,
            editingTaskId,
            editTask,
            cancelEdit,
            formatDateTime,
            formatInterval,
            utcTimeToLocal,

            // Next run time and expandable actions
            currentTimeMs,
            expandedTasks,
            toggleTaskExpanded,
            getTaskActionsList,
            getActionStepStatus,
            getActionStepError,
            getNextRunDate,
            getTaskNextRunText,
            getBuildingDisplayName,
            getBuffDisplayName,

            // target scope and friends state
            stepTargetScope,
            selectedFriend,
            selectedFriendBuilding,
            friendBuildings,
            loadingFriendZone,
            friendZoneError,
            stepAmount,
            showFriendBuildingModal,
            friendBuildingSearch,
            friendBuildingFilter,
            friendsList,
            searchedFriendBuildings,
            filteredFriendBuildings,
            selectFriend,
            fetchFriendZoneBuildings,
            openFriendBuildingModal,
            closeFriendBuildingModal,
            selectFriendBuilding,
            onTargetScopeChange,

            // Состояние модальных окон
            showBuildingModal,
            showSpecialistModal,
            showBuffModal,
            buildingSearch,
            buildingFilter,
            specialistSearch,
            buffSearch,
            selectedBuildings,
            selectedSpecialists,
            selectedBuff,
            buildingModalTab,
            buildingCategories,
            openBuildingModal,
            closeBuildingModal,
            openSpecialistModal,
            closeSpecialistModal,
            openBuffModal,
            closeBuffModal,
            selectBuff,

            // Мультиселект хелперы
            getBuildingTargetId,
            isSelectedBuilding,
            toggleBuildingSelection,
            removeSelectedBuilding,
            clearSelectedBuildings,
            selectAllFilteredBuildings,
            getSpecialistId,
            isSelectedSpecialist,
            toggleSpecialistSelection,
            removeSelectedSpecialist,
            clearSelectedSpecialists,
            selectAllFilteredSpecialists,

            // Иконки и методы отображения
            getBuildingName,
            getBuildingIcon,
            handleBuildingIconError,
            getSpecialistTypeName,
            getSpecialistIcon,
            handleSpecialistIconError,
            getStarBuffName,
            getBuffIcon,
            handleBuffIconError
        };
    }
};
</script>
