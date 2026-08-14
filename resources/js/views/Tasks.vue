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
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-4">
                    <!-- Название серии -->
                    <div>
                        <label class="block text-xs font-medium text-white/40 mb-2 uppercase tracking-wider">{{ t('tasks.series_name') }}</label>
                        <input type="text" v-model="taskName" :placeholder="t('tasks.series_name_placeholder')" class="glass-input w-full text-left  py-2.5">
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
                        <div class="grid grid-cols-1 xs:grid-cols-3 gap-2">
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
                <SchedulePicker v-model:runAtTime="runAtTime"
                                v-model:runAtDatetime="runAtDatetime"
                                v-model:intervalHours="intervalHours"
                                v-model:intervalMinutes="intervalMinutes"
                                :scheduleType="scheduleType" />

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

                    <div v-if="sequenceActions.length > 0" class="space-y-2 mb-4 max-h-72 sm:max-h-60 overflow-y-auto pr-1 sm:pr-2">
                        <div v-for="(act, idx) in sequenceActions" :key="idx" class="glass-card p-3 flex flex-col sm:flex-row sm:items-center justify-between gap-2 sm:gap-4 border border-white/5 hover:border-white/20 hover:bg-white/[0.04] transition-all duration-200">
                            <div class="flex items-start gap-3 min-w-0 w-full">
                                <span class="w-5 h-5 rounded-full bg-emerald-500/10 text-emerald-400 flex items-center justify-center text-[10px] font-bold font-mono flex-shrink-0">{{ idx + 1 }}</span>
                                <span class="text-lg flex-shrink-0">{{ typeIcons[act.task_type] }}</span>
                                <div class="text-xs min-w-0 flex-1">
                                    <p class="font-semibold text-white/90 wrap-anywhere leading-snug">{{ typeLabels[act.task_type] }}</p>
                                    <p class="text-white/40 text-[10px] mt-1 wrap-anywhere leading-relaxed">
                                        <span v-if="['stop_production', 'start_production'].includes(act.task_type)" class="inline-flex items-center gap-1.5 flex-wrap">
                                            <span class="inline-flex items-center gap-1 text-emerald-400 font-semibold">
                                                <img v-if="getBuildingInfo(null, act).icon"
                                                     :src="getBuildingInfo(null, act).icon"
                                                     class="w-4 h-4 object-contain rounded flex-shrink-0"
                                                     @error="handleBuildingIconError($event, getBuildingInfo(null, act).raw || act.meta?.building)" />
                                                <span v-else class="text-xs flex-shrink-0">🏭</span>
                                                <span>{{ t('tasks.building') }}: {{ getBuildingInfo(null, act).name || t('tasks.building') }}</span>
                                            </span>
                                            <span class="font-mono text-white/50 bg-white/5 px-1.5 py-0.5 rounded text-[9px]">
                                                {{ t('tasks.grid_number', { id: act.payload.grid }) }}
                                            </span>
                                        </span>
                                        <span v-if="act.task_type === 'apply_buff'" class="inline-flex items-center gap-1.5 flex-wrap">
                                            <span class="inline-flex items-center gap-1 text-amber-300 font-medium">
                                                <img v-if="getBuffInfo(null, act).icon"
                                                     :src="getBuffInfo(null, act).icon"
                                                     class="w-4 h-4 object-contain rounded flex-shrink-0"
                                                     @error="handleBuffIconError($event, getBuffInfo(null, act).raw || act.meta?.buff)" />
                                                <span v-else class="text-xs flex-shrink-0">✨</span>
                                                <span>{{ t('tasks.buff') }}: <strong>{{ getBuffInfo(null, act).name }}</strong></span>
                                            </span>
                                            <span class="text-white/60">
                                                • {{ t('tasks.qty_short') }}: <strong>{{ act.payload.amount || 1 }}</strong>
                                            </span>
                                            <span v-if="(act.payload.target_scope || 'self') === 'friend'" class="text-amber-400 font-medium">
                                                • 👤 {{ t('tasks.friend') }}: <strong>{{ act.payload.target_player_name || t('tasks.unknown_friend') }}</strong>
                                            </span>
                                            <span v-else class="text-white/50">
                                                • 🏡 {{ t('tasks.my_zone') }}
                                            </span>
                                            <span class="inline-flex items-center gap-1 text-emerald-400 font-semibold">
                                                • <img v-if="getBuildingInfo(null, act).icon"
                                                     :src="getBuildingInfo(null, act).icon"
                                                     class="w-4 h-4 object-contain rounded flex-shrink-0"
                                                     @error="handleBuildingIconError($event, getBuildingInfo(null, act).raw || act.meta?.building)" />
                                                <span v-else class="text-xs flex-shrink-0">🏭</span>
                                                <span>{{ getBuildingInfo(null, act).name || t('tasks.building') }}</span>
                                            </span>
                                            <span class="font-mono text-white/50 bg-white/5 px-1.5 py-0.5 rounded text-[9px]">
                                                {{ t('tasks.grid_number', { id: act.payload.grid }) }}
                                            </span>
                                        </span>
                                        <span v-if="['send_geologist', 'send_explorer'].includes(act.task_type)" class="inline-flex items-center gap-1.5 flex-wrap">
                                            <span class="inline-flex items-center gap-1 text-emerald-300 font-medium">
                                                <img v-if="getSpecialistInfo(null, act).icon"
                                                     :src="getSpecialistInfo(null, act).icon"
                                                     class="w-4 h-4 object-contain rounded flex-shrink-0"
                                                     @error="handleSpecialistIconError($event)" />
                                                <span v-else class="text-xs flex-shrink-0">🎖️</span>
                                                <span>{{ getSpecialistInfo(null, act).name }}</span>
                                            </span>
                                            <span v-if="getSpecialistInfo(null, act).subTaskLabel" class="badge badge-neutral text-[9px]">
                                                🧭 {{ getSpecialistInfo(null, act).subTaskLabel }}
                                            </span>
                                        </span>
                                        <span v-if="act.task_type === 'collect_pickups'" class="inline-flex items-center gap-1.5 flex-wrap">
                                            <span class="badge badge-neutral text-[9px]">
                                                🧺 {{ act.payload?.pickup_type === 'event' ? t('tasks.event_pickups') : t('tasks.all_pickups') }}
                                            </span>
                                        </span>
                                    </p>
                                </div>
                            </div>
                            <div class="flex items-center flex-wrap justify-end gap-1.5 sm:gap-2 flex-shrink-0 w-full sm:w-auto border-t sm:border-t-0 border-white/5 pt-2 sm:pt-0">
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
                                <span class="text-[10px] text-amber-400 bg-amber-400/10 px-2 py-0.5 rounded-full border border-amber-400/20 font-mono ml-1 whitespace-nowrap order-first sm:order-none mr-auto sm:mr-0">
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
                                        <button type="button" @click="stepActionType = 'collect_pickups'; onStepActionTypeChange(); activeDropdown = null" class="w-full px-3 py-1.5 text-left text-xs text-white/80 hover:bg-white/5 hover:text-white transition-colors">🧺 {{ t('tasks.action.collect_pickups') }}</button>
                                    </div>
                                </div>
                            </div>

                            <!-- Кастомный выбор в зависимости от типа шага -->
                            <div class="md:col-span-2">
                                <label class="block text-[10px] font-medium text-white/40 mb-1.5 uppercase">{{ t('tasks.step_params') }}</label>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                    <!-- Селекторы целевой зоны и зданий/друзей -->
                                    <div class="col-span-2">
                                        <!-- 1. ЗОНА (для apply_buff выбирается ПЕРВОЙ) -->
                                        <div v-if="stepActionType === 'apply_buff'" class="mb-3">
                                            <label class="block text-[10px] font-medium text-white/40 mb-1.5 uppercase">{{ t('tasks.where_apply') }}</label>
                                            <div class="grid grid-cols-1 xs:grid-cols-2 gap-2">
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

                                        <!-- 2. ВЫБОР ДРУГА (если выбрана Зона друга) -->
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
                                                    </button>
                                                    <div v-if="friendsList.length === 0" class="px-3 py-1.5 text-xs text-white/40">
                                                        {{ t('tasks.friends_empty') }}
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- 3. БАФФ (для домашней зоны — сразу; для друга — после выбора друга) -->
                                        <div v-if="stepActionType === 'apply_buff' && (stepTargetScope === 'self' || (stepTargetScope === 'friend' && selectedFriend))" class="mb-3">
                                            <label class="block text-[10px] font-medium text-white/40 mb-1.5 uppercase">{{ t('tasks.buff') }}</label>
                                            <button type="button" @click="openBuffModal"
                                                    class="glass-select w-full flex items-center justify-between text-left text-xs py-2 bg-dark-900/40 transition-all duration-300"
                                                    :class="{ 'border-amber-500/40 bg-amber-500/5': !selectedBuff }">
                                                <span v-if="selectedBuff" class="flex items-center gap-2 min-w-0">
                                                    <img alt="" v-if="getBuffIcon(selectedBuff)" :src="getBuffIcon(selectedBuff)" class="w-5 h-5 object-contain flex-shrink-0" @error="handleBuffIconError($event, selectedBuff)" />
                                                    <span class="truncate">{{ getStarBuffName(selectedBuff) }} ({{ selectedBuff.amount }})</span>
                                                    <span v-if="buffDurationLabel(selectedBuff)" class="badge badge-emerald text-[9px] flex-shrink-0">⏱ {{ buffDurationLabel(selectedBuff) }}</span>
                                                </span>
                                                <span v-else class="text-amber-400/80 font-medium">{{ t('tasks.select_buff') }} ({{ totalBuffsCount }} {{ t('tasks.avail_short') }})</span>
                                                <svg class="w-3.5 h-3.5 text-white/30 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                                </svg>
                                            </button>
                                            <p v-if="!selectedBuff" class="text-[10px] text-white/30 mt-1.5">{{ t('tasks.buff_first_hint') }}</p>
                                        </div>

                                        <!-- 4. ЗДАНИЯ (для остановки/запуска — сразу; для баффа — после выбора баффа) -->
                                        <div v-if="['stop_production', 'start_production'].includes(stepActionType) || (stepActionType === 'apply_buff' && selectedBuff)" class="mb-3">
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
                                                    <img alt="" v-if="getBuildingIcon(bTarget.building)" :src="getBuildingIcon(bTarget.building)" class="w-4 h-4 object-contain flex-shrink-0" @error="handleBuildingIconError($event, bTarget.building)" />
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
                                                    <img alt="" v-if="getSpecialistIcon(spec.type)" :src="getSpecialistIcon(spec.type)" class="w-5 h-5 object-contain flex-shrink-0" @error="handleSpecialistIconError($event, spec.type)" />
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
                    <spinner v-if="scheduling" size="sm" />
                    <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
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

            <!-- Skeleton while tasks are loading -->
            <div v-if="loadingPlanner && tasks.length === 0" class="space-y-6">
                <div class="glass-card overflow-hidden">
                    <div class="px-5 py-3 border-b border-white/5 bg-white/[0.02]">
                        <div class="w-40 h-4 rounded skeleton"></div>
                    </div>
                    <div class="divide-y divide-white/5">
                        <div v-for="i in 3" :key="'task-skeleton-' + i" class="p-5 flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl skeleton flex-shrink-0"></div>
                            <div class="flex-1 min-w-0">
                                <div class="w-48 h-4 rounded skeleton mb-2"></div>
                                <div class="w-32 h-3 rounded skeleton"></div>
                            </div>
                            <div class="w-24 h-8 rounded-lg skeleton hidden sm:block"></div>
                            <div class="w-16 h-8 rounded-lg skeleton"></div>
                        </div>
                    </div>
                </div>
            </div>
            <TaskList v-else
                      :tasks="tasks"
                      :filteredTasks="filteredTasks"
                      :accounts="accounts"
                      v-model:searchQuery="taskSearchQuery"
                      v-model:accountFilter="taskAccountFilter"
                      v-model:statusFilter="taskStatusFilter">
                <div v-for="(groupTasks, accountName) in groupedTasks" :key="accountName" class="glass-card overflow-hidden">
                    <div class="px-5 py-3 border-b border-white/5 bg-white/[0.02]">
                        <h3 class="font-medium text-white/60 flex items-center gap-2">
                            <svg class="w-4 h-4 text-emerald-400/60" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                            </svg>
                            {{ accountName }}
                            <span class="badge badge-neutral text-[10px]">{{ groupTasks.length }} {{ t('tasks.tasks_word') }}</span>
                        </h3>
                    </div>

                    <div class="p-4 sm:p-5 space-y-4">
                        <TaskCard v-for="task in groupTasks" :key="task.id"
                                  :task="task"
                                  :typeIcons="typeIcons"
                                  :typeLabels="typeLabels"
                                  :isExpanded="expandedTasks[task.id]"
                                  :isExecuting="executingTasks[task.id]"
                                  :getTaskActionsList="getTaskActionsList"
                                  :getSubTaskLabel="getSubTaskLabel"
                                  :getTaskNextRunText="getTaskNextRunText"
                                  :formatDateTime="formatDateTime"
                                  :formatInterval="formatInterval"
                                  :utcTimeToLocal="utcTimeToLocal"
                                  :getTaskLastResultBadge="getTaskLastResultBadge"
                                  :getActionStepStatus="getActionStepStatus"
                                  :getActionStepError="getActionStepError"
                                  :getBuildingInfo="getBuildingInfo"
                                  :getBuffInfo="getBuffInfo"
                                  :getSpecialistInfo="getSpecialistInfo"
                                  :handleBuildingIconError="handleBuildingIconError"
                                  :handleBuffIconError="handleBuffIconError"
                                  :handleSpecialistIconError="handleSpecialistIconError"
                                  @toggle-expand="toggleTaskExpanded"
                                  @toggle-active="toggleTask"
                                  @execute="runTaskNow"
                                  @edit="editTask"
                                  @delete="deleteTask" />
                    </div>
                </div>
            </TaskList>
        </div>

        <!-- МОДАЛЬНЫЕ ОКНА ВЫБОРА -->
        <BuildingPicker :showModal="showBuildingModal"
                        v-model:tab="buildingModalTab"
                        v-model:buildingSearch="buildingSearch"
                        v-model:friendBuildingSearch="friendBuildingSearch"
                        :selectedBuildings="selectedBuildings"
                        :filteredBuildings="filteredBuildings"
                        :searchedFriendBuildings="searchedFriendBuildings"
                        :selectedFriend="selectedFriend"
                        :loadingFriendZone="loadingFriendZone"
                        :zone="zone"
                        :isSelectedBuilding="isSelectedBuilding"
                        :getBuildingIcon="getBuildingIcon"
                        :getBuildingName="getBuildingName"
                        :handleBuildingIconError="handleBuildingIconError"
                        @close="closeBuildingModal"
                        @select-all="selectAllFilteredBuildings"
                        @clear="clearSelectedBuildings"
                        @toggle-building="toggleBuildingSelection" />

        <SpecialistPicker :showModal="showSpecialistModal"
                          v-model:specialistSearch="specialistSearch"
                          :selectedSpecialists="selectedSpecialists"
                          :filteredSpecialistsModal="filteredSpecialistsModal"
                          :getSpecialistId="getSpecialistId"
                          :isSelectedSpecialist="isSelectedSpecialist"
                          :getSpecialistIcon="getSpecialistIcon"
                          :getSpecialistTypeName="getSpecialistTypeName"
                          :handleSpecialistIconError="handleSpecialistIconError"
                          @close="closeSpecialistModal"
                          @select-all="selectAllFilteredSpecialists"
                          @clear="clearSelectedSpecialists"
                          @toggle-specialist="toggleSpecialistSelection" />

        <BuffPicker :showModal="showBuffModal"
                    v-model:buffSearch="buffSearch"
                    :selectedBuffId="payload.unique_id1"
                    :filteredBuffsModal="filteredBuffsModal"
                    :getStarBuffName="getStarBuffName"
                    :buffDurationLabel="buffDurationLabel"
                    :getBuffIcon="getBuffIcon"
                    :handleBuffIconError="handleBuffIconError"
                    @close="closeBuffModal"
                    @select-buff="selectBuff" />
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { tasksApi } from '../services/api/tasks';
import { accountsApi } from '../services/api/accounts';
import { showToast } from '../toast';
import { t, gameAny, gameAnyLookup, intlLocale } from '../lang';
import { humanizeGameId, resourceName, buildingName, isBuffableBuilding, getBuildingCategory } from '../lang/gameNames';
import { canBuffTarget, isBuildingBuff, isFriendZoneBuff, getBuffDurations, formatBuffDuration } from '../lang/buffTargets';
import { getGameImageUrl, handleGameImageError } from '../services/gameImageService';

import Spinner from '../components/Spinner.vue';
import SchedulePicker from '../components/tasks/SchedulePicker.vue';
import BuildingPicker from '../components/tasks/BuildingPicker.vue';
import SpecialistPicker from '../components/tasks/SpecialistPicker.vue';
import BuffPicker from '../components/tasks/BuffPicker.vue';
import TaskCard from '../components/tasks/TaskCard.vue';
import TaskList from '../components/tasks/TaskList.vue';
        const tasks = ref([]);
        const accounts = ref([]);
        const loadingPlanner = ref(true);
        const scheduling = ref(false);
        const executingTasks = ref({});

        const taskSearchQuery = ref('');
        const taskAccountFilter = ref('');
        const taskStatusFilter = ref('');

        const selectedAccountId = ref('');
        const taskName = ref('');
        const activeDropdown = ref(null);
        const taskType = ref('sequence');
        const runAtTime = ref('');

        // New scheduling fields
        const scheduleType = ref('daily');
        const runAtDatetime = ref('');
        const intervalHours = ref(0);
        const intervalMinutes = ref(0);

        // Action series
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
                send_explorer: '🧭 ' + t('tasks.action.send_explorer'),
                collect_pickups: '🧺 ' + t('tasks.action.collect_pickups')
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

        // Modal state
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

        // Multi-select building helpers
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

        // Multi-select specialist helpers
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
            let list = friendBuildings.value.filter(b => isBuffableBuilding(b));
            // Buff-first flow: filter buildings suitable for the selected buff.
            if (stepActionType.value === 'apply_buff' && selectedBuff.value?.buffName_string) {
                const buffKey = selectedBuff.value.buffName_string;
                list = list.filter(b => canBuffTarget(buffKey, b.buildingName_string || b.buildingName || ''));
            }
            return list;
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
            // Buff-first flow: available buffs depend on zone scope (self/friend) - reset buff selection.
            if (stepActionType.value === 'apply_buff') {
                selectedBuff.value = null;
                delete payload.value.unique_id1;
                delete payload.value.unique_id2;
            }
        };

        const typeIcons = {
            stop_production: '🛑',
            start_production: '▶️',
            apply_buff: '⚡',
            send_geologist: '⛏️',
            send_explorer: '🧭',
            collect_pickups: '🧺'
        };

        const typeLabels = {
            stop_production: t('tasks.type_label.stop_production'),
            start_production: t('tasks.type_label.start_production'),
            apply_buff: t('tasks.type_label.apply_buff'),
            send_geologist: t('tasks.type_label.send_geologist'),
            send_explorer: t('tasks.type_label.send_explorer'),
            collect_pickups: t('tasks.type_label.collect_pickups')
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
            if (tasks.value.length === 0 && accounts.value.length === 0) loadingPlanner.value = true;
            try {
                const res = await axios.get('/api/tasks');
                tasks.value = res.data.data || res.data.tasks || [];
                accounts.value = res.data.accounts || [];

                const distinctAccountIds = [...new Set(tasks.value.map(t => Number(t.account_id)).filter(Boolean))];
                distinctAccountIds.forEach(id => {
                    fetchAccountZone(id);
                });
            } catch (e) {
                showToast(t('tasks.toast.load_failed'), 'error');
            } finally {
                loadingPlanner.value = false;
            }
        };

        const accountZonesCache = ref({});

        const fetchAccountZone = async (accountId) => {
            if (!accountId) return { buildings: [], specialists: [], buffs: [] };
            const numId = Number(accountId);
            if (accountZonesCache.value[numId]) {
                return accountZonesCache.value[numId];
            }
            try {
                const res = await axios.get(`/api/accounts/${numId}/zone`);
                const zd = res.data.zone_data;
                const parsed = typeof zd === 'string' ? JSON.parse(zd) : (zd || { buildings: [], specialists: [], buffs: [] });
                accountZonesCache.value[numId] = parsed;
                return parsed;
            } catch (e) {
                console.error('Failed to fetch account zone data:', e);
                return { buildings: [], specialists: [], buffs: [] };
            }
        };

        const onAccountChange = async () => {
            selectedBuildings.value = [];
            selectedSpecialists.value = [];
            selectedBuff.value = null;
            selectedFriend.value = null;
            selectedFriendBuilding.value = null;
            stepTargetScope.value = 'self';
            stepAmount.value = 1;
            friendBuildings.value = [];
            friendZoneError.value = false;

            if (selectedAccountId.value) {
                zone.value = await fetchAccountZone(selectedAccountId.value);
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
            } else if (type === 'collect_pickups') {
                // Не привязано к зданию: только фильтр и пауза между кликами
                payload.value = { pickup_type: 'all', delay_ms: 250 };
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
                    const bName = bTarget.buildingName || bTarget.name || (bTarget.building ? getBuildingName(bTarget.building) : null);
                    const bRaw = bTarget.building?.buildingName_string || bTarget.building?.buildingName || '';
                    const actionPayload = {
                        grid: bTarget.buildingGrid,
                        building_name: bName,
                        building_raw_name: bRaw,
                        name: bName,
                        target_scope: bTarget.scope,
                        target_player_id: bTarget.scope === 'friend' ? bTarget.friend?.id : null,
                        target_player_name: bTarget.scope === 'friend' ? (bTarget.friend?.nickname || bTarget.friend?.username) : null,
                        amount: stepActionType.value === 'apply_buff' ? stepAmount.value : 1
                    };

                    if (stepActionType.value === 'apply_buff') {
                        const sb = selectedBuff.value || {};
                        const u1 = sb.uniqueId1 ?? sb.uniqueID1 ?? sb.uniqueID?.uniqueID1 ?? sb.uniqueID?.uniqueId1 ?? sb.uniqueId?.uniqueId1 ?? 0;
                        const u2 = sb.uniqueId2 ?? sb.uniqueID2 ?? sb.uniqueID?.uniqueID2 ?? sb.uniqueID?.uniqueId2 ?? sb.uniqueId?.uniqueId2 ?? 0;
                        actionPayload.unique_id1 = u1;
                        actionPayload.unique_id2 = u2;
                        actionPayload.buff_name = getStarBuffName(sb);
                        actionPayload.buff_raw_name = sb.buffName_string || '';
                        actionPayload.buff_resource_name = sb.resourceName_string || '';
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
                    const specTypeName = getSpecialistTypeName(spec.type);
                    const specName = spec.name || specTypeName;
                    const subTaskLabel = getSubTaskLabel(stepActionType.value, payload.value.task_type, payload.value.sub_task_id);
                    const actionPayload = {
                        unique_id1: spec.uniqueId1,
                        unique_id2: spec.uniqueID2 || spec.uniqueId2 || 0,
                        type: spec.type,
                        specialist_name: specName,
                        specialist_type_name: specTypeName,
                        sub_task_label: subTaskLabel,
                        task_type: stepActionType.value === 'send_geologist' ? 0 : payload.value.task_type,
                        sub_task_id: payload.value.sub_task_id || 0
                    };

                    const meta = {
                        specialist: { ...spec },
                        subTaskLabel: subTaskLabel
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

            if (stepActionType.value === 'collect_pickups') {
                sequenceActions.value.push({
                    task_type: 'collect_pickups',
                    payload: {
                        pickup_type: payload.value.pickup_type || 'all',
                        delay_ms: Number(payload.value.delay_ms ?? 250)
                    },
                    delay_seconds: Number(stepDelay.value || 0),
                    meta: {}
                });
                showToast(t('tasks.toast.action_added'));
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

        const getBuildingName = (b) => {
            if (!b) return '';
            if (typeof b === 'string') return buildingName(b);
            return buildingName(b.buildingName_string || b.buildingName || b.name || b.building_raw_name || b.building_name || 'Building');
        };

        const getBuildingImageName = (building) => {
            const name = typeof building === 'string'
                ? building
                : (building?.buildingName_string || building?.buildingName || building?.name || building?.building_raw_name || building?.building_name || '');
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

            // Buff-first flow: when a buff is selected, only show buildings where it can be applied.
            if (stepActionType.value === 'apply_buff' && selectedBuff.value?.buffName_string) {
                const buffKey = selectedBuff.value.buffName_string;
                list = list.filter(b => canBuffTarget(buffKey, b.buildingName_string || b.buildingName || ''));
            }

            if (buildingFilter.value && buildingFilter.value !== 'All') {
                list = list.filter(b => getBuildingCategory(b) === buildingFilter.value);
            }

            if (buildingSearch.value) {
                const query = buildingSearch.value.toLowerCase();
                list = list.filter(b => getBuildingName(b).toLowerCase().includes(query) || String(b.buildingGrid).includes(query));
            }

            return list;
        });

        // Specialist logic
        const getSpecialistCategory = (type) => {
            const rawName = SPECIALIST_TYPES[type];
            if (!rawName) return 'Other';
            const lower = rawName.toLowerCase();
            if (lower.includes('explorer') || lower.includes('scout')) return 'Explorer';
            if (lower.includes('geologist')) return 'Geologist';
            return 'General';
        };

        const getSpecialistTypeName = (type) => {
            const raw = SPECIALIST_TYPES[type];
            if (!raw) return `Specialist #${type}`;
            return gameAnyLookup(raw) || humanizeGameId(raw);
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
            if (!b) return t('tasks.unknown_buff');
            const name = typeof b === 'string'
                ? b
                : (b.buffName_string || b.buff_raw_name || b.name || b.buff_name);
            if (!name) return t('tasks.unknown_buff');

            const template = gameAnyLookup(name);
            if (template) {
                let tpl = template;
                if (tpl.includes('{0}')) {
                    const resName = typeof b === 'object' ? (b.resourceName_string || b.buff_resource_name || '') : '';
                    tpl = tpl.replace('{0}', resourceDisplayName(resName));
                }
                tpl = tpl.replace(/\{1,\w+\}/g, '').replace(/[:\s]+$/, '').replace(/\s+/g, ' ').trim();
                return tpl;
            }

            if (name === 'AddResource') {
                return `${t('tasks.buff_add_resource')}: ${resourceDisplayName(typeof b === 'object' ? (b.resourceName_string || b.buff_resource_name) : '')}`;
            }
            if (name === 'BuildBuilding') {
                return `${t('tasks.buff_build_license')}: ${resourceDisplayName(typeof b === 'object' ? (b.resourceName_string || b.buff_resource_name) : '')}`;
            }
            if (name === 'Adventure') {
                return `${t('tasks.buff_adventure')}: ${resourceDisplayName(typeof b === 'object' ? (b.resourceName_string || b.buff_resource_name) : '')}`;
            }

            return humanizeGameId(name);
        };

        const getBuffImageName = (buff) => {
            const name = typeof buff === 'string'
                ? buff
                : (buff?.buffName_string || buff?.buff_raw_name || buff?.name || buff?.buff_name || '');
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

            // Only genuine building buffs (exclude AddResource, FillDeposit, Adventure, etc.).
            list = list.filter(bf => isBuildingBuff(bf.buffName_string));

            // On friend zone, only friend-compatible buffs are available.
            if (stepTargetScope.value === 'friend') {
                list = list.filter(bf => isFriendZoneBuff(bf.buffName_string));
            }

            if (buffSearch.value) {
                const query = buffSearch.value.toLowerCase();
                list = list.filter(bf => getStarBuffName(bf).toLowerCase().includes(query));
            }
            return list;
        });

        // Modal window management
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
            if (stepTargetScope.value === 'friend' && !selectedFriend.value) {
                showToast(t('tasks.toast.select_friend_first'), 'warning');
                return;
            }
            buffSearch.value = '';
            showBuffModal.value = true;
        };
        const closeBuffModal = () => { showBuffModal.value = false; };
        const selectBuff = (bf) => {
            selectedBuff.value = bf;
            const u1 = bf.uniqueId1 ?? bf.uniqueID1 ?? bf.uniqueID?.uniqueID1 ?? bf.uniqueID?.uniqueId1 ?? bf.uniqueId?.uniqueId1 ?? 0;
            const u2 = bf.uniqueId2 ?? bf.uniqueID2 ?? bf.uniqueID?.uniqueID2 ?? bf.uniqueID?.uniqueId2 ?? bf.uniqueId?.uniqueId2 ?? 0;
            payload.value.unique_id1 = u1;
            payload.value.unique_id2 = u2;
            // Buff-first flow: remove selected buildings that cannot accept this buff.
            const buffKey = bf.buffName_string;
            if (buffKey) {
                selectedBuildings.value = selectedBuildings.value.filter(bt =>
                    canBuffTarget(buffKey, bt.building?.buildingName_string || bt.building?.buildingName || ''));
            }
            closeBuffModal();
        };

        // Buff duration label e.g. "2h 30m" (friend duration for friend zone).
        const buffDurationLabel = (bf) => {
            const d = getBuffDurations(bf?.buffName_string);
            if (!d) return '';
            const seconds = stepTargetScope.value === 'friend' ? (d.Friend ?? d.Player) : (d.Player ?? d.Friend);
            return formatBuffDuration(seconds);
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
                    // Treasure search
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
                    // Adventure search
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

        const filteredTasks = computed(() => {
            return tasks.value.filter(task => {
                // Account filter
                if (taskAccountFilter.value !== '' && taskAccountFilter.value !== null && taskAccountFilter.value !== undefined) {
                    const accId = Number(taskAccountFilter.value);
                    const taskAccId = task.account_id ? Number(task.account_id) : (task.account?.id ? Number(task.account.id) : null);
                    if (taskAccId !== accId) {
                        return false;
                    }
                }

                // Status filter ('active' or 'paused')
                if (taskStatusFilter.value === 'active' && !task.is_active) {
                    return false;
                }
                if (taskStatusFilter.value === 'paused' && task.is_active) {
                    return false;
                }

                // Search query filter
                if (taskSearchQuery.value && taskSearchQuery.value.trim() !== '') {
                    const q = taskSearchQuery.value.toLowerCase().trim();

                    // Match task name
                    if (task.name && task.name.toLowerCase().includes(q)) return true;

                    // Match account nickname / username
                    const nickname = task.account?.nickname || '';
                    const username = task.account?.username || '';
                    if (nickname.toLowerCase().includes(q) || username.toLowerCase().includes(q)) return true;

                    // Match task type label
                    const typeLabel = typeLabels.value ? typeLabels.value[task.task_type] : (task.task_type || '');
                    if (typeLabel && String(typeLabel).toLowerCase().includes(q)) return true;

                    // Match grid number
                    if (task.payload?.grid && String(task.payload.grid).includes(q)) return true;

                    // Match actions inside sequence or single payload
                    const actionsList = getTaskActionsList(task);
                    const stepMatch = actionsList.some(act => {
                        const actTypeLabel = typeLabels.value ? typeLabels.value[act.task_type] : (act.task_type || '');
                        if (actTypeLabel && String(actTypeLabel).toLowerCase().includes(q)) return true;

                        if (act.meta?.building) {
                            const bName = act.meta.building.buildingName_string || act.meta.building.buildingName || '';
                            if (bName.toLowerCase().includes(q)) return true;
                        }

                        if (act.meta?.buff) {
                            const buffName = getStarBuffName(act.meta.buff);
                            if (buffName && buffName.toLowerCase().includes(q)) return true;
                        }

                        if (act.payload?.grid && String(act.payload.grid).includes(q)) return true;

                        return false;
                    });

                    if (stepMatch) return true;

                    return false;
                }

                return true;
            });
        });

        const groupedTasks = computed(() => {
            const groups = {};
            const sortedTasks = [...filteredTasks.value].sort((a, b) => (Number(b.id) || 0) - (Number(a.id) || 0));
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
                // Geologist
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

                // Explorer: treasure
                1: {
                    0: t('tasks.treasure_short.short'),
                    1: t('tasks.treasure_short.medium'),
                    2: t('tasks.treasure_short.long'),
                    3: t('tasks.treasure_short.very_long'),
                    4: t('tasks.treasure_short.erudite'),
                    5: t('tasks.treasure_short.bean_collada'),
                    6: t('tasks.treasure_short.extra_long')
                },

                // Explorer: adventure
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

        // ===== Timezone Handling =====
        // Server stores and returns timestamps in UTC.
        // Browser converts UTC -> local timezone when displaying
        // and local timezone -> UTC when sending to server.

        const pad2 = (n) => String(n).padStart(2, '0');

        // Parse server date string. Timestamps without explicit timezone are treated as UTC.
        const parseServerDate = (dtStr) => {
            if (!dtStr) return null;
            let s = String(dtStr).trim();
            if (!s.includes('T')) s = s.replace(' ', 'T');
            if (!/(Z|[+-]\d{2}:?\d{2})$/.test(s)) s += 'Z';
            const d = new Date(s);
            return isNaN(d.getTime()) ? null : d;
        };

        // 'HH:mm' (UTC, from server) -> 'HH:mm' in user local timezone
        const utcTimeToLocal = (hhmm) => {
            if (!hhmm) return hhmm;
            const [h, m] = hhmm.split(':').map(Number);
            const d = new Date();
            d.setUTCHours(h, m, 0, 0);
            return `${pad2(d.getHours())}:${pad2(d.getMinutes())}`;
        };

        // 'HH:mm' (local, from input[type=time]) -> 'HH:mm' in UTC for server
        const localTimeToUtc = (hhmm) => {
            if (!hhmm) return hhmm;
            const [h, m] = hhmm.split(':').map(Number);
            const d = new Date();
            d.setHours(h, m, 0, 0);
            return `${pad2(d.getUTCHours())}:${pad2(d.getUTCMinutes())}`;
        };

        // Value of input[type=datetime-local] (local) -> ISO string in UTC for server
        const localDatetimeToUtcIso = (val) => {
            if (!val) return val;
            const d = new Date(val); // datetime-local parsed by browser as local time
            return isNaN(d.getTime()) ? val : d.toISOString();
        };

        // UTC date from server -> value for input[type=datetime-local] (local)
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

        const editTask = async (task) => {
            editingTaskId.value = task.id;
            taskName.value = task.name || '';
            selectedAccountId.value = task.account_id;
            await onAccountChange();

            scheduleType.value = task.schedule_type || 'daily';
            runAtTime.value = task.run_at_time ? utcTimeToLocal(task.run_at_time.substring(0, 5)) : '';
            runAtDatetime.value = utcToDatetimeLocalInput(task.run_at_datetime);
            intervalHours.value = task.interval_hours || 0;
            intervalMinutes.value = task.interval_minutes || 0;

            if (task.task_type === 'sequence' && task.payload && task.payload.actions) {
                const zoneData = await fetchAccountZone(task.account_id);

                sequenceActions.value = task.payload.actions.map(act => {
                    const meta = act.meta && typeof act.meta === 'object' ? { ...act.meta } : { building: null, buff: null, specialist: null };

                    if (['stop_production', 'start_production', 'apply_buff'].includes(act.task_type) && !meta.building) {
                        if (zoneData.buildings) {
                            meta.building = zoneData.buildings.find(b => b.buildingGrid == act.payload?.grid) || null;
                        }
                    }
                    if (act.task_type === 'apply_buff' && !meta.buff) {
                        const buffs = zoneData.availableBuffs || zoneData.buffs || [];
                        meta.buff = buffs.find(b => (b.uniqueId1 || b.uniqueID1) == act.payload?.unique_id1 && (b.uniqueId2 || b.uniqueID2) == act.payload?.unique_id2) || null;
                    }
                    if (['send_geologist', 'send_explorer'].includes(act.task_type) && !meta.specialist) {
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
                            delay_seconds: a.delay_seconds,
                            meta: a.meta || {}
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

                if (res.status === 201 || res.status === 200 || res.data.data || res.data.success) {
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

        const toggleTask = async (taskOrId) => {
            const taskId = typeof taskOrId === 'object' && taskOrId !== null ? taskOrId.id : taskOrId;
            if (!taskId || taskId === 'undefined') return;
            const task = typeof taskOrId === 'object' && taskOrId !== null ? taskOrId : tasks.value.find(t => t.id === taskId);
            try {
                const res = await axios.post(`/api/tasks/${taskId}/toggle`);
                const updated = res.data.data || res.data.task || res.data;
                if (updated && updated.is_active !== undefined) {
                    if (task) task.is_active = updated.is_active;
                    showToast(updated.is_active ? t('tasks.toast.task_activated') : t('tasks.toast.task_paused'));
                }
            } catch (e) {
                showToast(t('tasks.toast.toggle_failed'), 'error');
            }
        };

        const deleteTask = async (idOrTask) => {
            const id = typeof idOrTask === 'object' && idOrTask !== null ? idOrTask.id : idOrTask;
            if (!id || id === 'undefined') return;
            if (!confirm(t('tasks.confirm.delete_task'))) return;
            try {
                const res = await axios.delete(`/api/tasks/${id}`);
                if (res.status === 204 || res.data.success) {
                    showToast(t('tasks.toast.task_deleted'));
                    loadPlanner();
                }
            } catch (e) {
                showToast(t('tasks.toast.delete_failed'), 'error');
            }
        };

        const activePollTimers = {};

        const stopPollingTask = (taskId) => {
            if (activePollTimers[taskId]) {
                clearTimeout(activePollTimers[taskId]);
                delete activePollTimers[taskId];
            }
            executingTasks.value[taskId] = false;
        };

        const pollTaskExecution = (taskId) => {
            if (!taskId || taskId === 'undefined') return;
            stopPollingTask(taskId);

            let attempts = 0;
            const maxAttempts = 150; // polling up to 5 minutes max

            const doPoll = async () => {
                attempts++;
                try {
                    // Lightweight endpoint: only this task's state, not the full planner payload
                    const res = await axios.get(`/api/tasks/${taskId}/status`);
                    const updatedTask = res.data?.task;

                    // Refresh the affected row in place without reloading the whole list
                    const idx = tasks.value.findIndex(t => t.id === taskId);
                    if (idx !== -1 && updatedTask) {
                        const oldAccount = tasks.value[idx].account;
                        tasks.value[idx] = {
                            ...tasks.value[idx],
                            ...updatedTask,
                            account: updatedTask.account || oldAccount
                        };
                    }

                    const isFinished = !updatedTask || (updatedTask.status !== 'queued' && updatedTask.status !== 'running');

                    if (isFinished || attempts >= maxAttempts) {
                        stopPollingTask(taskId);

                        if (updatedTask && isFinished) {
                            const isErr = updatedTask.status === 'failed' || (updatedTask.last_result && updatedTask.last_result.startsWith('ERROR:'));
                            if (isErr) {
                                const err = getActionStepError(updatedTask, 0) || updatedTask.last_result || t('tasks.toast.unknown');
                                showToast(t('tasks.toast.run_error') + ': ' + err, 'error');
                            } else if (updatedTask.last_result) {
                                showToast(t('tasks.toast.run_success') + ': ' + updatedTask.last_result);
                            }
                        }

                        // One full refresh at the end to sync the planner state
                        loadPlanner();
                        return;
                    }
                } catch (e) {
                    // Task was deleted while polling: stop and resync
                    if (e.response?.status === 404 || attempts >= maxAttempts) {
                        stopPollingTask(taskId);
                        loadPlanner();
                        return;
                    }
                }

                if (executingTasks.value[taskId]) {
                    activePollTimers[taskId] = setTimeout(doPoll, 5000);
                }
            };

            executingTasks.value[taskId] = true;
            activePollTimers[taskId] = setTimeout(doPoll, 5000);
        };

        const runTaskNow = async (target) => {
            const taskId = typeof target === 'object' && target !== null ? target.id : target;
            const taskObj = typeof target === 'object' && target !== null ? target : tasks.value.find(t => t.id === taskId);
            if (!taskId || taskId === 'undefined') return;

            if (executingTasks.value[taskId]) return;
            executingTasks.value[taskId] = true;

            // Immediately reset local task state so UI doesn't show stale step_results/completed_steps
            const idx = tasks.value.findIndex(t => t.id === taskId);
            if (idx !== -1) {
                const oldAccount = tasks.value[idx].account;
                const cleanPayload = { ...tasks.value[idx].payload };
                delete cleanPayload.step_results;
                tasks.value[idx] = {
                    ...tasks.value[idx],
                    status: 'queued',
                    completed_steps: 0,
                    last_result: null,
                    payload: cleanPayload,
                    account: oldAccount
                };
            }

            try {
                const res = await axios.post(`/api/tasks/${taskId}/execute`);
                const currentTask = res.data.task || taskObj;

                // Update tasks list in state
                if (idx !== -1 && res.data.task) {
                    const oldAccount = tasks.value[idx].account;
                    tasks.value[idx] = {
                        ...tasks.value[idx],
                        ...res.data.task,
                        account: res.data.task.account || oldAccount
                    };
                }

                if (currentTask && currentTask.status !== 'queued' && currentTask.status !== 'running') {
                    if (res.data.success && !currentTask.last_result?.startsWith('ERROR:')) {
                        showToast(t('tasks.toast.run_success') + ': ' + (currentTask.last_result || 'OK'));
                    } else {
                        const err = getActionStepError(currentTask, 0) || currentTask.last_result || res.data.message || t('tasks.toast.unknown');
                        showToast(t('tasks.toast.run_error') + ': ' + err, 'error');
                    }
                    executingTasks.value[taskId] = false;
                    loadPlanner();
                    return;
                }

                // Poll status periodically until execution finishes
                pollTaskExecution(taskId);
            } catch (e) {
                showToast(e.response?.data?.message || t('tasks.toast.run_failed'), 'error');
                executingTasks.value[taskId] = false;
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

            if (task.status === 'queued') {
                return 'pending';
            }

            const stepResults = task.payload?.step_results;
            if (Array.isArray(stepResults) && stepResults[aIdx] && stepResults[aIdx].status) {
                return stepResults[aIdx].status;
            }

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
            if (!task) return null;

            let rawMsg = '';
            const stepResults = task.payload?.step_results;
            if (Array.isArray(stepResults) && stepResults[aIdx] && stepResults[aIdx].status === 'failed') {
                rawMsg = stepResults[aIdx].error || '';
            } else if (getActionStepStatus(task, aIdx) === 'failed') {
                rawMsg = task.last_result || '';
            } else {
                return null;
            }

            let msg = rawMsg;
            if (msg.startsWith('ERROR: ')) {
                msg = msg.substring(7);
            } else if (msg.startsWith('ERROR:')) {
                msg = msg.substring(6);
            }

            if (msg.startsWith('{') && msg.endsWith('}')) {
                try {
                    const parsed = JSON.parse(msg);
                    if (parsed && parsed.key) {
                        return t(parsed.key, parsed.params || {});
                    }
                } catch (e) {
                    // Not valid JSON, fallback to standard parsing
                }
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
        };

        const getTaskLastResultBadge = (task) => {
            if (!task || !task.last_result) return null;
            const res = task.last_result;

            if (res.startsWith('WARNING:') || res.includes('timed out') || res.includes('stuck in')) {
                return {
                    label: t('tasks.warning'),
                    class: 'badge-warning'
                };
            }

            if (res.startsWith('PARTIAL:') || res.includes('PARTIAL')) {
                return {
                    label: t('tasks.partial'),
                    class: 'badge-warning'
                };
            }

            const isErr = res.startsWith('ERROR:') || res.startsWith('FAILED:') || (task.status === 'failed' && !res.includes('OK'));
            if (isErr) {
                return {
                    label: t('tasks.error'),
                    class: 'badge-danger'
                };
            }

            if (res.includes('ERROR') && res.includes('OK')) {
                return {
                    label: t('tasks.partial'),
                    class: 'badge-warning'
                };
            }

            return {
                label: t('tasks.success'),
                class: 'badge-success'
            };
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

                const now = new Date(currentTimeMs.value);
                const targetToday = new Date(Date.UTC(now.getUTCFullYear(), now.getUTCMonth(), now.getUTCDate(), hours, minutes, 0, 0));

                const lastRun = parseServerDate(task.last_run_at);
                if (lastRun && lastRun.getTime() >= targetToday.getTime()) {
                    targetToday.setUTCDate(targetToday.getUTCDate() + 1);
                }
                return targetToday;
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

        const getBuildingInfo = (taskObj, action) => {
            const grid = action?.payload?.grid || action?.meta?.building?.buildingGrid;
            let buildingObj = action?.meta?.building;

            if (!buildingObj && grid) {
                const accId = Number(taskObj?.account_id || selectedAccountId.value);
                const zd = accountZonesCache.value[accId];
                if (zd && zd.buildings) {
                    buildingObj = zd.buildings.find(b => Number(b.buildingGrid) === Number(grid));
                }
            }

            let name = '';
            if (buildingObj) {
                name = getBuildingName(buildingObj);
            } else if (action?.payload?.building_name || action?.payload?.name) {
                name = action.payload.building_name || action.payload.name;
            } else if (action?.payload?.building_raw_name) {
                name = buildingName(action.payload.building_raw_name);
            }

            let icon = null;
            if (buildingObj) {
                icon = getBuildingIcon(buildingObj);
            } else if (action?.payload?.building_raw_name) {
                icon = getBuildingIcon(action.payload.building_raw_name);
            } else if (action?.payload?.building_name) {
                icon = getBuildingIcon(action.payload.building_name);
            }

            return {
                name: name || '',
                grid: grid || null,
                icon: icon,
                level: buildingObj?.upgradeLevel || null,
                raw: buildingObj || action?.meta?.building || null
            };
        };

        const getBuffInfo = (taskObj, action) => {
            let buffObj = action?.meta?.buff;

            if (!buffObj && action?.payload?.unique_id1) {
                const accId = Number(taskObj?.account_id || selectedAccountId.value);
                const zd = accountZonesCache.value[accId];
                if (zd) {
                    const buffs = zd.availableBuffs || zd.buffs || [];
                    buffObj = buffs.find(b => (b.uniqueId1 || b.uniqueID1 || b.uniqueId) == action.payload.unique_id1);
                }
            }

            let name = '';
            if (buffObj) {
                name = getStarBuffName(buffObj);
            } else if (action?.payload?.buff_name) {
                name = action.payload.buff_name;
            } else if (action?.payload?.buff_raw_name) {
                name = getStarBuffName({ buffName_string: action.payload.buff_raw_name, resourceName_string: action.payload.buff_resource_name });
            } else if (action?.payload?.unique_id1) {
                name = t('tasks.buff_number', { id: action.payload.unique_id1 });
            } else {
                name = t('tasks.buff_from_menu');
            }

            let icon = null;
            if (buffObj) {
                icon = getBuffIcon(buffObj);
            } else if (action?.payload?.buff_raw_name) {
                icon = getBuffIcon({ buffName_string: action.payload.buff_raw_name });
            }

            return {
                name: name || t('tasks.buff'),
                icon: icon,
                amount: action?.payload?.amount || 1,
                raw: buffObj || action?.meta?.buff || null
            };
        };

        const getSpecialistInfo = (taskObj, action) => {
            const u1 = action?.payload?.unique_id1;
            let specObj = action?.meta?.specialist;

            if (!specObj && u1) {
                const accId = Number(taskObj?.account_id || selectedAccountId.value);
                const zd = accountZonesCache.value[accId];
                if (zd && zd.specialists) {
                    specObj = zd.specialists.find(s => (s.uniqueId1 || s.uniqueID1 || s.uniqueId) == u1);
                }
            }

            const type = specObj?.type ?? action?.payload?.type ?? (action?.task_type === 'send_geologist' ? 2 : 1);
            const typeName = getSpecialistTypeName(type);
            const name = specObj?.name || action?.payload?.specialist_name || (specObj ? typeName : (action?.payload?.specialist_type_name || typeName));
            const icon = type !== undefined ? getSpecialistIcon(type) : null;
            const subTaskLabel = action?.meta?.subTaskLabel || action?.payload?.sub_task_label || getSubTaskLabel(action?.task_type, action?.payload?.task_type, action?.payload?.sub_task_id);

            return {
                name: name || typeName,
                typeName: typeName,
                icon: icon,
                subTaskLabel: subTaskLabel,
                raw: specObj || action?.meta?.specialist || null
            };
        };

        const getBuildingDisplayName = (taskObj, action) => {
            const info = getBuildingInfo(taskObj, action);
            if (info.name && info.grid) {
                return `${info.name} (${t('tasks.grid_number', { id: info.grid })})`;
            }
            if (info.name) return info.name;
            if (info.grid) return t('tasks.grid_number', { id: info.grid });
            return t('tasks.building_not_set');
        };

        const getBuffDisplayName = (taskObj, action) => {
            return getBuffInfo(taskObj, action).name;
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
            Object.keys(activePollTimers).forEach(id => {
                stopPollingTask(id);
            });
        });
</script>
