<template>
    <div>
        <!-- Заголовок страницы -->
        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-3xl font-bold text-white">Планировщик задач</h1>
                <p class="text-white/40 mt-1">Автоматизируйте действия на ваших аккаунтах</p>
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
                            {{ editingTaskId ? `Редактирование задачи #${editingTaskId}` : 'Запланировать новую задачу' }}
                        </h2>
                        <p v-if="editingTaskId" class="text-xs text-amber-400 font-medium">Вы редактируете существующую задачу. Добавляйте/удаляйте действия и нажмите «Сохранить изменения».</p>
                    </div>
                </div>

                <button v-if="editingTaskId" type="button" @click="cancelEdit" class="btn-secondary btn-sm text-xs text-white/70 hover:text-white flex items-center gap-1 border-white/10 hover:border-white/20">
                    ✕ Отменить редактирование
                </button>
            </div>

            <form @submit.prevent="scheduleTask">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                    <!-- Название серии -->
                    <div>
                        <label class="block text-xs font-medium text-white/40 mb-2 uppercase tracking-wider">Название серии задач</label>
                        <input type="text" v-model="taskName" placeholder="Напр. Бафф ратуши друга" class="glass-input w-full text-xs py-2.5">
                    </div>

                    <!-- Выбор аккаунта -->
                    <div>
                        <label class="block text-xs font-medium text-white/40 mb-2 uppercase tracking-wider">Игровой аккаунт</label>
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
                        <label class="block text-xs font-medium text-white/40 mb-2 uppercase tracking-wider">Тип планирования</label>
                        <div class="grid grid-cols-3 gap-2">
                            <button type="button" @click="scheduleType = 'daily'"
                                    class="px-3 py-2 rounded-lg text-xs font-semibold border transition-all duration-300"
                                    :class="scheduleType === 'daily' ? 'bg-emerald-500/20 text-emerald-400 border-emerald-500/30' : 'bg-white/5 text-white/40 border-transparent hover:bg-white/10'">
                                Ежедневно
                            </button>
                            <button type="button" @click="scheduleType = 'once'"
                                    class="px-3 py-2 rounded-lg text-xs font-semibold border transition-all duration-300"
                                    :class="scheduleType === 'once' ? 'bg-emerald-500/20 text-emerald-400 border-emerald-500/30' : 'bg-white/5 text-white/40 border-transparent hover:bg-white/10'">
                                Одноразово
                            </button>
                            <button type="button" @click="scheduleType = 'interval'"
                                    class="px-3 py-2 rounded-lg text-xs font-semibold border transition-all duration-300"
                                    :class="scheduleType === 'interval' ? 'bg-emerald-500/20 text-emerald-400 border-emerald-500/30' : 'bg-white/5 text-white/40 border-transparent hover:bg-white/10'">
                                Интервал
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Блок времени планирования в зависимости от типа -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-5 border-t border-white/5 pt-4">
                    <!-- 1. Ежедневный запуск (Время) -->
                    <div v-if="scheduleType === 'daily'">
                        <label class="block text-xs font-medium text-white/40 mb-2 uppercase tracking-wider">Время запуска (каждый день)</label>
                        <input type="time" required v-model="runAtTime" class="glass-input w-full">
                    </div>

                    <!-- 2. Одноразовый запуск (Дата и время) -->
                    <div v-if="scheduleType === 'once'">
                        <label class="block text-xs font-medium text-white/40 mb-2 uppercase tracking-wider">Дата и время запуска</label>
                        <input type="datetime-local" required v-model="runAtDatetime" class="glass-input w-full">
                    </div>

                    <!-- 3. Интервальный запуск (Каждые X часов Y минут) -->
                    <div v-if="scheduleType === 'interval'" class="col-span-2">
                        <label class="block text-xs font-medium text-white/40 mb-2 uppercase tracking-wider">Запускать каждые</label>
                        <div class="flex gap-4">
                            <div class="flex items-center gap-2 flex-1">
                                <input type="number" min="0" required v-model.number="intervalHours" class="glass-input w-full">
                                <span class="text-xs text-white/40">час.</span>
                            </div>
                            <div class="flex items-center gap-2 flex-1">
                                <input type="number" min="0" max="59" required v-model.number="intervalMinutes" class="glass-input w-full">
                                <span class="text-xs text-white/40">мин.</span>
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
                            Конструктор серии действий
                        </h3>
                        <button v-if="sequenceActions.length > 0" type="button" @click="clearSequence" class="text-xs text-red-400/70 hover:text-red-400 transition-colors flex items-center gap-1 font-medium">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                            </svg>
                            Очистить серию
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
                                            Здание: {{ act.meta.building ? getBuildingName(act.meta.building) : `Сетка #${act.payload.grid}` }}
                                        </span>
                                        <span v-if="act.task_type === 'apply_buff'" class="inline-flex items-center gap-1.5 flex-wrap">
                                            <span class="text-emerald-400 font-semibold">
                                                🏭 Здание: {{ (act.meta && act.meta.building) ? getBuildingName(act.meta.building) : `Сетка #${act.payload.grid}` }}
                                            </span>
                                            <span v-if="(act.payload.target_scope || 'self') === 'friend'" class="text-amber-400">
                                                • 👤 Друг: <strong>{{ act.payload.target_player_name || 'Неизвестный друг' }}</strong>
                                            </span>
                                            <span v-else class="text-white/50">
                                                • 🏡 Моя зона
                                            </span>
                                            <span v-if="act.meta && act.meta.buff" class="text-amber-300">
                                                • ✨ Бафф: <strong>{{ getStarBuffName(act.meta.buff) }}</strong>
                                            </span>
                                            <span class="text-white/60">
                                                • Кол-во: <strong>{{ act.payload.amount || 1 }}</strong>
                                            </span>
                                        </span>
                                        <span v-if="['send_geologist', 'send_explorer'].includes(act.task_type)">
                                            Специалист: {{ act.meta.specialist ? (act.meta.specialist.name || getSpecialistTypeName(act.meta.specialist.type)) : `Тип #${act.payload.unique_id1}` }}
                                            <span v-if="act.meta.subTaskLabel">• {{ act.meta.subTaskLabel }}</span>
                                        </span>
                                    </p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2 flex-shrink-0">
                                <!-- Move Up -->
                                <button type="button" :disabled="idx === 0" @click="moveActionUp(idx)" class="text-white/30 hover:text-emerald-400 disabled:opacity-20 transition-colors p-1" title="Переместить вверх">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 15.75l7.5-7.5 7.5 7.5" />
                                    </svg>
                                </button>
                                <!-- Move Down -->
                                <button type="button" :disabled="idx === sequenceActions.length - 1" @click="moveActionDown(idx)" class="text-white/30 hover:text-emerald-400 disabled:opacity-20 transition-colors p-1" title="Переместить вниз">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                                    </svg>
                                </button>
                                <span class="text-[10px] text-amber-400 bg-amber-400/10 px-2 py-0.5 rounded-full border border-amber-400/20 font-mono ml-1">
                                    задержка: {{ act.delay_seconds }} сек.
                                </span>
                                <button type="button" @click="removeAction(idx)" class="text-white/30 hover:text-red-400 transition-colors p-1" title="Удалить шаг">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div v-else class="text-xs text-white/30 py-4 text-center border border-dashed border-white/10 rounded-lg mb-4">
                        Серия действий пуста. Добавьте шаги ниже.
                    </div>

                    <!-- Форма добавления нового шага -->
                    <div class="bg-white/[0.02] border border-white/5 rounded-xl p-4">
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
                            <!-- Выбор типа действия для нового шага -->
                            <div>
                                <label class="block text-[10px] font-medium text-white/40 mb-1.5 uppercase">Тип действия шага</label>
                                <div class="relative">
                                    <button type="button" @click.stop="activeDropdown = activeDropdown === 'stepActionType' ? null : 'stepActionType'" class="glass-select w-full flex items-center justify-between text-left text-xs py-2">
                                        <span>{{ stepActionTypeLabel }}</span>
                                        <svg class="w-3.5 h-3.5 text-white/30" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                        </svg>
                                    </button>
                                    <div v-if="activeDropdown === 'stepActionType'" class="absolute z-50 mt-1.5 w-full glass-card border border-white/10 shadow-2xl rounded-xl py-1 max-h-60 overflow-y-auto">
                                        <button type="button" @click="stepActionType = 'stop_production'; onStepActionTypeChange(); activeDropdown = null" class="w-full px-3 py-1.5 text-left text-xs text-white/80 hover:bg-white/5 hover:text-white transition-colors">🛑 Остановить производство</button>
                                        <button type="button" @click="stepActionType = 'start_production'; onStepActionTypeChange(); activeDropdown = null" class="w-full px-3 py-1.5 text-left text-xs text-white/80 hover:bg-white/5 hover:text-white transition-colors">▶️ Запустить производство</button>
                                        <button type="button" @click="stepActionType = 'apply_buff'; onStepActionTypeChange(); activeDropdown = null" class="w-full px-3 py-1.5 text-left text-xs text-white/80 hover:bg-white/5 hover:text-white transition-colors">⚡ Применить бафф</button>
                                        <button type="button" @click="stepActionType = 'send_geologist'; onStepActionTypeChange(); activeDropdown = null" class="w-full px-3 py-1.5 text-left text-xs text-white/80 hover:bg-white/5 hover:text-white transition-colors">⛏️ Отправить геолога</button>
                                        <button type="button" @click="stepActionType = 'send_explorer'; onStepActionTypeChange(); activeDropdown = null" class="w-full px-3 py-1.5 text-left text-xs text-white/80 hover:bg-white/5 hover:text-white transition-colors">🧭 Отправить разведчика</button>
                                    </div>
                                </div>
                            </div>

                            <!-- Кастомный выбор в зависимости от типа шага -->
                            <div class="md:col-span-2">
                                <label class="block text-[10px] font-medium text-white/40 mb-1.5 uppercase">Параметры шага</label>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                    <!-- Селекторы целевой зоны и зданий/друзей -->
                                    <div class="col-span-2">
                                        <!-- Переключатель: Моя зона / Зона друга (только для баффа) -->
                                        <div v-if="stepActionType === 'apply_buff'" class="mb-3">
                                            <label class="block text-[10px] font-medium text-white/40 mb-1.5 uppercase">Где применить</label>
                                            <div class="grid grid-cols-2 gap-2">
                                                <button type="button" @click="stepTargetScope = 'self'; onTargetScopeChange()"
                                                        class="px-3 py-1.5 rounded-lg text-xs font-semibold border transition-all duration-300"
                                                        :class="stepTargetScope === 'self' ? 'bg-emerald-500/20 text-emerald-400 border-emerald-500/30' : 'bg-white/5 text-white/40 border-transparent hover:bg-white/10'">
                                                    Моя зона
                                                </button>
                                                <button type="button" @click="stepTargetScope = 'friend'; onTargetScopeChange()"
                                                        class="px-3 py-1.5 rounded-lg text-xs font-semibold border transition-all duration-300"
                                                        :class="stepTargetScope === 'friend' ? 'bg-emerald-500/20 text-emerald-400 border-emerald-500/30' : 'bg-white/5 text-white/40 border-transparent hover:bg-white/10'">
                                                    Зона друга
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Друг (только если выбрана зона друга) -->
                                        <div v-if="stepActionType === 'apply_buff' && stepTargetScope === 'friend'" class="mb-3">
                                            <label class="block text-[10px] font-medium text-white/40 mb-1.5 uppercase">Друг</label>
                                            <div class="relative">
                                                <button type="button" @click.stop="activeDropdown = activeDropdown === 'friendList' ? null : 'friendList'"
                                                        class="glass-select w-full flex items-center justify-between text-left text-xs py-2 bg-dark-900/40 transition-all duration-300"
                                                        :class="{ 'border-amber-500/40 bg-amber-500/5': !selectedFriend }">
                                                    <span v-if="selectedFriend" class="flex items-center gap-2">
                                                        <span>👤 {{ selectedFriend.nickname || selectedFriend.username }} (уровень {{ selectedFriend.playerLevel }})</span>
                                                    </span>
                                                    <span v-else class="text-amber-400/80 font-medium">Выберите друга...</span>
                                                    <svg class="w-3.5 h-3.5 text-white/30" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                                    </svg>
                                                </button>
                                                <div v-if="activeDropdown === 'friendList'" class="absolute z-50 mt-1.5 w-full glass-card border border-white/10 shadow-2xl rounded-xl py-1 max-h-60 overflow-y-auto">
                                                    <button v-for="friend in friendsList" :key="friend.id" type="button" @click="selectFriend(friend); activeDropdown = null" class="w-full px-3 py-1.5 text-left text-xs text-white/80 hover:bg-white/5 hover:text-white transition-colors flex justify-between items-center">
                                                        <span>👤 {{ friend.nickname || friend.username }} (уровень {{ friend.playerLevel }})</span>
                                                        <span class="text-[9px]" :class="friend.onlineStatus ? 'text-green-400' : 'text-white/30'">
                                                            {{ friend.onlineStatus ? 'в сети' : 'не в сети' }}
                                                        </span>
                                                    </button>
                                                    <div v-if="friendsList.length === 0" class="px-3 py-1.5 text-xs text-white/40">
                                                        Список друзей пуст. Выполните синхронизацию аккаунта.
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Здание друга (только если выбрана зона друга) -->
                                        <div v-if="stepActionType === 'apply_buff' && stepTargetScope === 'friend'" class="mb-3">
                                            <label class="block text-[10px] font-medium text-white/40 mb-1.5 uppercase">Здание друга</label>
                                            <div v-if="loadingFriendZone" class="text-xs text-emerald-400/80 flex items-center gap-2 py-2">
                                                <svg class="animate-spin h-3.5 w-3.5 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                </svg>
                                                <span>Загружаем зону {{ selectedFriend?.nickname || selectedFriend?.username }}…</span>
                                            </div>
                                            <div v-else-if="friendZoneError" class="text-xs text-red-400 flex items-center justify-between py-1 bg-red-500/10 px-3 rounded-lg border border-red-500/20">
                                                <span>Не удалось загрузить зону друга</span>
                                                <button type="button" @click="fetchFriendZoneBuildings" class="text-[10px] uppercase font-bold text-white bg-white/10 hover:bg-white/20 px-2 py-0.5 rounded transition-all">Повторить</button>
                                            </div>
                                            <button v-else type="button" @click="openFriendBuildingModal" :disabled="!selectedFriend"
                                                    class="glass-select w-full flex items-center justify-between text-left text-xs py-2 bg-dark-900/40 disabled:opacity-50 transition-all duration-300"
                                                    :class="{ 'border-amber-500/40 bg-amber-500/5': !selectedFriendBuilding }">
                                                <span v-if="selectedFriendBuilding" class="flex items-center gap-2">
                                                    <span>🏭 {{ getBuildingName(selectedFriendBuilding) }} (Grid #{{ selectedFriendBuilding.buildingGrid }})</span>
                                                </span>
                                                <span v-else class="text-amber-400/80 font-medium">Выберите здание друга... ({{ filteredFriendBuildings.length }} дост.)</span>
                                                <svg class="w-3.5 h-3.5 text-white/30" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                                </svg>
                                            </button>
                                        </div>

                                        <!-- Собственное здание (для остановки/запуска или для баффа на себя) -->
                                        <div v-if="['stop_production', 'start_production'].includes(stepActionType) || (stepActionType === 'apply_buff' && stepTargetScope === 'self')">
                                            <button type="button" @click="openBuildingModal"
                                                    class="glass-select w-full flex items-center justify-between text-left text-xs py-2 bg-dark-900/40 transition-all duration-300"
                                                    :class="{ 'border-amber-500/40 bg-amber-500/5': !selectedBuilding }">
                                                <span v-if="selectedBuilding" class="flex items-center gap-2">
                                                    <img v-if="getBuildingIcon(selectedBuilding)" :src="getBuildingIcon(selectedBuilding)" class="w-5 h-5 object-contain" @error="handleBuildingIconError($event, selectedBuilding)" />
                                                    <span class="truncate">{{ getBuildingName(selectedBuilding) }} (Grid #{{ selectedBuilding.buildingGrid }})</span>
                                                </span>
                                                <span v-else class="text-amber-400/80 font-medium">Выберите здание... ({{ totalBuildingsCount }} дост.)</span>
                                                <svg class="w-3.5 h-3.5 text-white/30" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
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
                                            <span v-else class="text-amber-400/80 font-medium">Выберите бафф... ({{ totalBuffsCount }} дост.)</span>
                                            <svg class="w-3.5 h-3.5 text-white/30" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                            </svg>
                                        </button>

                                        <div>
                                            <label class="block text-[10px] font-medium text-white/40 mb-1 uppercase">Количество</label>
                                            <input type="number" min="1" required v-model.number="stepAmount" class="glass-input w-full text-xs py-1.5">
                                        </div>
                                    </div>

                                    <!-- Специалист -->
                                    <div v-if="['send_geologist', 'send_explorer'].includes(stepActionType)" class="col-span-2">
                                        <button type="button" @click="openSpecialistModal"
                                                class="glass-select w-full flex items-center justify-between text-left text-xs py-2 bg-dark-900/40 transition-all duration-300"
                                                :class="{ 'border-amber-500/40 bg-amber-500/5': !selectedSpecialist }">
                                            <span v-if="selectedSpecialist" class="flex items-center gap-2">
                                                <img v-if="getSpecialistIcon(selectedSpecialist.type)" :src="getSpecialistIcon(selectedSpecialist.type)" class="w-5 h-5 object-contain" @error="handleSpecialistIconError($event, selectedSpecialist.type)" />
                                                <span class="truncate">{{ selectedSpecialist.name || getSpecialistTypeName(selectedSpecialist.type) }}</span>
                                            </span>
                                            <span v-else class="text-amber-400/80 font-medium">Выберите специалиста... ({{ totalSpecialistsCount }} дост.)</span>
                                            <svg class="w-3.5 h-3.5 text-white/30" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                            </svg>
                                        </button>
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
                                                <button v-if="stepActionType === 'send_geologist'" type="button" @click="payload.task_type = 0; onSearchTypeChange(); activeDropdown = null" class="w-full px-3 py-1.5 text-left text-xs text-white/80 hover:bg-white/5 hover:text-white transition-colors">Поиск залежей</button>
                                                <template v-if="stepActionType === 'send_explorer'">
                                                    <button type="button" @click="payload.task_type = 1; onSearchTypeChange(); activeDropdown = null" class="w-full px-3 py-1.5 text-left text-xs text-white/80 hover:bg-white/5 hover:text-white transition-colors">Поиск сокровищ</button>
                                                    <button type="button" @click="payload.task_type = 2; onSearchTypeChange(); activeDropdown = null" class="w-full px-3 py-1.5 text-left text-xs text-white/80 hover:bg-white/5 hover:text-white transition-colors">Поиск приключений</button>
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
                                <label class="block text-[10px] font-medium text-white/40 mb-1.5 uppercase">Задержка после шага</label>
                                <div class="flex items-center gap-2">
                                    <input type="number" min="0" required v-model.number="stepDelay" class="glass-input w-full text-xs py-1.5">
                                    <span class="text-[10px] text-white/40 font-semibold uppercase">сек</span>
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-end">
                            <button type="button" @click="addStepToSequence" class="px-4 py-2 rounded-lg text-xs font-semibold bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 hover:bg-emerald-500/30 transition-all duration-300 flex items-center gap-1.5">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                </svg>
                                Добавить шаг в серию
                            </button>
                        </div>
                    </div>
                </div>

                <button type="submit" :disabled="scheduling" class="btn-primary flex items-center gap-2 disabled:opacity-50"
                        :class="{ 'bg-gradient-to-r from-amber-500 to-orange-600 border-amber-500/30': editingTaskId }">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    {{ scheduling ? 'Сохранение...' : (editingTaskId ? 'Сохранить изменения' : 'Запланировать серию задач') }}
                </button>
            </form>
        </div>

        <!-- Список запланированных задач -->
        <div>
            <h2 class="text-lg font-semibold text-white flex items-center gap-2 mb-5">
                <svg class="w-5 h-5 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
                Запланированные задачи
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
                            <span class="badge badge-neutral text-[10px]">{{ groupTasks.length }} задач</span>
                        </h3>
                    </div>

                    <!-- Список задач -->
                    <div class="divide-y divide-white/5">
                        <div v-for="t in groupTasks" :key="t.id" class="p-5 hover:bg-white/[0.01] transition-all duration-200 group">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                <div class="flex items-center gap-3">
                                    <!-- Иконка действия -->
                                    <div class="w-10 h-10 rounded-xl bg-white/5 flex items-center justify-center text-xl flex-shrink-0 border border-white/5">
                                        {{ t.task_type === 'sequence' ? '⛓️' : (typeIcons[t.task_type] || '📋') }}
                                    </div>

                                    <!-- Информация о задаче -->
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <p class="text-sm font-semibold text-white/90 group-hover:text-white transition-colors">
                                                <span v-if="t.name" class="text-emerald-400/90">{{ t.name }}</span>
                                                <span v-else-if="t.task_type === 'sequence'">Серия задач ({{ getTaskActionsList(t).length }})</span>
                                                <span v-else>{{ typeLabels[t.task_type] || t.task_type }}</span>
                                            </p>
                                            <span class="badge badge-neutral text-[9px] uppercase">
                                                {{ t.schedule_type === 'once' ? 'Одноразово' : t.schedule_type === 'interval' ? 'Интервал' : 'Ежедневно' }}
                                            </span>
                                        </div>

                                        <div class="flex flex-wrap items-center gap-2 mt-1.5 text-[11px]">
                                            <!-- Кнопка раскрывающегося списка всех действий -->
                                            <button type="button"
                                                    @click="toggleTaskExpanded(t.id)"
                                                    class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md text-[10px] font-medium bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-300 border border-emerald-500/20 transition-all duration-200">
                                                <span>{{ expandedTasks[t.id] ? '📖 Скрыть действия' : '📘 Действия задачи' }} ({{ getTaskActionsList(t).length }})</span>
                                                <svg class="w-3 h-3 transition-transform duration-300" :class="{ 'rotate-180': expandedTasks[t.id] }" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                                </svg>
                                            </button>

                                            <span v-if="t.task_type !== 'sequence' && t.payload && t.payload.grid" class="font-mono text-white/40">
                                                Сетка #{{ t.payload.grid }}
                                            </span>
                                            <span v-if="t.task_type !== 'sequence' && t.payload && t.payload.sub_task_id !== undefined" class="text-white/40">
                                                {{ getSubTaskLabel(t.task_type, t.payload.task_type, t.payload.sub_task_id) }}
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Правая секция: До следующего запуска, Расписание, Статус и Действия -->
                                <div class="flex items-center justify-end gap-4 ml-auto sm:ml-0">
                                    <!-- Время до следующего запуска задачи -->
                                    <div class="text-right px-3 py-1.5 rounded-xl bg-white/[0.02] border border-white/5 min-w-[130px]">
                                        <div v-if="!t.is_active" class="flex items-center justify-end gap-1.5 text-xs text-amber-400/80 font-medium">
                                            <span class="w-1.5 h-1.5 rounded-full bg-amber-400"></span>
                                            <span>Пауза</span>
                                        </div>
                                        <div v-else-if="t.schedule_type === 'once' && t.last_run_at" class="flex items-center justify-end gap-1.5 text-xs text-white/40">
                                            <span>Завершена</span>
                                        </div>
                                        <div v-else class="flex flex-col items-end">
                                            <span class="text-xs font-mono font-bold text-emerald-400 flex items-center gap-1.5">
                                                <svg class="w-3 h-3 text-emerald-400 animate-pulse" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                                </svg>
                                                {{ getTaskNextRunText(t).label }}
                                            </span>
                                            <span class="text-[9px] text-white/30 font-mono" v-if="getTaskNextRunText(t).nextRunTime">
                                                (в {{ getTaskNextRunText(t).nextRunTime }})
                                            </span>
                                        </div>
                                        <p class="text-[9px] text-white/30 uppercase tracking-wider mt-0.5 text-right font-medium">До запуска</p>
                                    </div>

                                    <!-- Расписание -->
                                    <div class="text-right hidden md:block">
                                        <p v-if="t.schedule_type === 'once'" class="text-xs text-white/60 font-mono">
                                            {{ formatDateTime(t.run_at_datetime) }}
                                        </p>
                                        <p v-else-if="t.schedule_type === 'interval'" class="text-xs text-white/60 font-mono">
                                            Каждые {{ formatInterval(t.interval_hours, t.interval_minutes) }}
                                        </p>
                                        <p v-else class="text-xs text-white/60 font-mono">
                                            Ежедневно в {{ t.run_at_time ? utcTimeToLocal(t.run_at_time.substring(0, 5)) : '—' }}
                                        </p>
                                        <p class="text-[9px] text-white/20 uppercase tracking-wider">Расписание</p>
                                    </div>

                                    <!-- Последний результат выполнения -->
                                    <span v-if="t.last_result"
                                          class="badge text-[10px] flex-shrink-0 max-w-[100px] truncate"
                                          :class="t.last_result.includes('OK') ? 'badge-success' : 'badge-danger'"
                                          :title="t.last_result">
                                        {{ t.last_result.includes('OK') ? 'Успешно' : 'Ошибка' }}
                                    </span>

                                    <!-- Кнопка ручного запуска -->
                                    <button @click="runTaskNow(t)"
                                            :disabled="executingTasks[t.id]"
                                            class="btn-secondary btn-sm flex items-center justify-center gap-1.5 flex-shrink-0"
                                            :class="executingTasks[t.id] ? 'opacity-50 cursor-not-allowed' : 'hover:border-emerald-500/30 text-emerald-400/80 hover:text-emerald-400'"
                                            title="Запустить сейчас">
                                        <svg v-if="executingTasks[t.id]" class="animate-spin h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        <svg v-else class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 0 1 0 1.972l-11.54 6.347a1.125 1.125 0 0 1-1.667-.986V5.653Z" />
                                        </svg>
                                        <span class="text-[9px] uppercase font-semibold">{{ executingTasks[t.id] ? 'Запуск...' : 'Пуск' }}</span>
                                    </button>

                                    <!-- Кнопка редактирования -->
                                    <button @click="editTask(t)"
                                            class="btn-secondary btn-sm flex items-center justify-center gap-1.5 flex-shrink-0"
                                            :class="editingTaskId === t.id ? 'border-amber-500/50 bg-amber-500/20 text-amber-300' : 'hover:border-amber-500/30 text-amber-400/80 hover:text-amber-400'"
                                            title="Редактировать задачу">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                                        </svg>
                                        <span class="text-[9px] uppercase font-semibold">Ред.</span>
                                    </button>

                                    <!-- Тумблер активации -->
                                    <button @click="toggleTask(t)"
                                            class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors duration-300 flex-shrink-0"
                                            :class="t.is_active ? 'bg-emerald-500' : 'bg-white/10'">
                                        <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow-lg transition-transform duration-300"
                                              :class="t.is_active ? 'translate-x-6' : 'translate-x-1'"></span>
                                    </button>

                                    <!-- Кнопка удаления -->
                                    <button @click="deleteTask(t.id)"
                                            class="btn-secondary btn-sm text-red-400/60 hover:text-red-400 hover:border-red-500/30 flex-shrink-0">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            <!-- Раскрывающийся список действий задачи -->
                            <div v-if="expandedTasks[t.id]" class="mt-4 pt-4 border-t border-white/5 space-y-2 animate-fade-in">
                                <div class="flex items-center justify-between mb-2">
                                    <p class="text-[10px] uppercase font-semibold text-white/40 tracking-wider flex items-center gap-1.5">
                                        <svg class="w-3.5 h-3.5 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75Zm0 5.25h.007v.008H3.75V12Zm0 5.25h.007v.008H3.75v-.008Z" />
                                        </svg>
                                        Действия в задаче (всего {{ getTaskActionsList(t).length }})
                                    </p>
                                </div>

                                <div class="grid grid-cols-1 gap-2">
                                    <div v-for="(act, aIdx) in getTaskActionsList(t)" :key="aIdx"
                                         class="glass-card p-3 flex flex-col sm:flex-row sm:items-center justify-between gap-3 border border-white/5 hover:border-white/10 transition-all bg-white/[0.01]">
                                        <div class="flex items-start sm:items-center gap-3">
                                            <span class="w-6 h-6 rounded-lg bg-emerald-500/10 text-emerald-400 flex items-center justify-center text-xs font-bold font-mono flex-shrink-0 mt-0.5 sm:mt-0">
                                                {{ aIdx + 1 }}
                                            </span>
                                            <span class="text-xl flex-shrink-0">{{ typeIcons[act.task_type] || '📋' }}</span>
                                            <div class="min-w-0 text-xs">
                                                <div class="flex items-center gap-2">
                                                    <span class="font-semibold text-white/90">{{ typeLabels[act.task_type] || act.task_type }}</span>
                                                    <span v-if="act.payload?.target_scope === 'friend'" class="badge badge-warning text-[9px]">Зона друга</span>
                                                    <span v-else-if="act.payload?.target_scope === 'self'" class="badge badge-neutral text-[9px]">Моя зона</span>
                                                </div>

                                                <!-- Подробные параметры действия -->
                                                <div class="text-white/60 text-[11px] mt-1 space-y-0.5">
                                                    <!-- Здание -->
                                                    <div v-if="['stop_production', 'start_production', 'apply_buff'].includes(act.task_type)" class="flex items-center gap-1.5 flex-wrap">
                                                        <span class="text-white/40">Здание:</span>
                                                        <span class="font-mono text-emerald-300 font-medium">
                                                            {{ getBuildingDisplayName(t, act) }}
                                                        </span>
                                                    </div>

                                                    <!-- Бафф -->
                                                    <div v-if="act.task_type === 'apply_buff'" class="flex items-center gap-1.5 flex-wrap">
                                                        <span class="text-white/40">Бафф:</span>
                                                        <span class="font-medium text-amber-300">{{ getBuffDisplayName(t, act) }}</span>
                                                        <span class="text-white/40">• Количество: <strong class="text-white font-mono">{{ act.payload?.amount || 1 }}</strong></span>
                                                        <span v-if="act.payload?.target_player_name" class="text-emerald-400">• Друг: <strong>{{ act.payload.target_player_name }}</strong></span>
                                                    </div>

                                                    <!-- Специалист -->
                                                    <div v-if="['send_geologist', 'send_explorer'].includes(act.task_type)" class="flex items-center gap-1.5 flex-wrap">
                                                        <span class="text-white/40">Тип/Цель поиска:</span>
                                                        <span class="font-medium text-teal-300">
                                                            {{ getSubTaskLabel(act.task_type, act.payload?.task_type, act.payload?.sub_task_id) }}
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Задержка после шага -->
                                        <div v-if="act.delay_seconds > 0" class="flex items-center gap-1 text-[10px] text-amber-400 bg-amber-400/10 px-2 py-1 rounded-lg border border-amber-400/20 font-mono self-end sm:self-center">
                                            <span>⏱️ Задержка после шага:</span>
                                            <span class="font-bold">{{ act.delay_seconds }} сек.</span>
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
                <h3 class="text-white/60 font-medium mb-1">Нет запланированных задач</h3>
                <p class="text-white/30 text-sm">Создайте свою первую автозадачу с помощью формы выше.</p>
            </div>
        </div>

        <!-- МОДАЛЬНОЕ ОКНО: ВЫБОР ЗДАНИЯ -->
        <div v-if="showBuildingModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm animate-fade-in">
            <div class="glass-card max-w-2xl w-full flex flex-col max-h-[80vh] shadow-2xl border border-white/10">
                <div class="px-6 py-4 border-b border-white/5 flex items-center justify-between">
                    <h3 class="text-base font-semibold text-white">Выбор здания на аккаунте</h3>
                    <button type="button" @click="closeBuildingModal" class="text-white/40 hover:text-white transition-colors">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <div class="p-4 border-b border-white/5 bg-white/[0.01] space-y-3">
                    <div class="flex gap-1.5 flex-wrap">
                        <button type="button" v-for="cat in buildingCategories" :key="cat" @click="buildingFilter = cat"
                                class="px-3 py-1.5 rounded-lg text-[11px] font-semibold transition-all duration-300"
                                :class="buildingFilter === cat
                                    ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30'
                                    : 'bg-white/5 text-white/40 border-transparent hover:bg-white/10'">
                            {{ cat }}
                        </button>
                    </div>
                    <div class="relative">
                        <input v-model="buildingSearch" type="text" placeholder="Поиск здания по названию или сетке..." class="glass-input w-full text-xs py-2 pl-4">
                    </div>
                </div>
                <div class="p-6 overflow-y-auto flex-1 bg-dark-950/20">
                    <div v-if="filteredBuildings.length > 0" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div v-for="b in filteredBuildings" :key="b.buildingGrid"
                             @click="selectBuilding(b)"
                             class="glass-card p-3 cursor-pointer hover:border-emerald-500/40 hover:scale-[1.01] transition-all duration-200 flex items-center gap-3"
                             :class="payload.grid === b.buildingGrid ? 'border-emerald-500/50 bg-emerald-500/10' : 'border-transparent'">
                            <div class="w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0 bg-dark-900/50 border border-white/5">
                                <img v-if="getBuildingIcon(b)" :src="getBuildingIcon(b)" :alt="getBuildingName(b)" class="w-7 h-7 object-contain" @error="handleBuildingIconError($event, b)">
                                <span v-else class="text-sm">🏰</span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-xs font-semibold text-white/90 truncate">{{ getBuildingName(b) }}</p>
                                <p class="text-[10px] text-white/40 mt-0.5">Сетка #{{ b.buildingGrid }} • Ур. {{ b.upgradeLevel || 1 }}</p>
                            </div>
                        </div>
                    </div>
                    <div v-else class="text-center py-8 text-white/30 text-xs">
                        <span v-if="!zone || !zone.buildings || zone.buildings.length === 0">
                            Данные острова не загружены. Пожалуйста, сначала синхронизируйте этот игровой аккаунт в разделе Accounts.
                        </span>
                        <span v-else>Здания не найдены.</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- МОДАЛЬНОЕ ОКНО: ВЫБОР ЗДАНИЯ ДРУГА -->
        <div v-if="showFriendBuildingModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm animate-fade-in">
            <div class="glass-card max-w-2xl w-full flex flex-col max-h-[80vh] shadow-2xl border border-white/10">
                <div class="px-6 py-4 border-b border-white/5 flex items-center justify-between">
                    <h3 class="text-base font-semibold text-white">Выбор здания друга</h3>
                    <button type="button" @click="closeFriendBuildingModal" class="text-white/40 hover:text-white transition-colors">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <div class="p-4 border-b border-white/5 bg-white/[0.01]">
                    <input v-model="friendBuildingSearch" type="text" placeholder="Поиск здания по названию или сетке..." class="glass-input w-full text-xs py-2 pl-4">
                </div>
                <div class="p-6 overflow-y-auto flex-1 bg-dark-950/20">
                    <div v-if="searchedFriendBuildings.length > 0" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div v-for="b in searchedFriendBuildings" :key="b.buildingGrid"
                             @click="selectFriendBuilding(b)"
                             class="glass-card p-3 cursor-pointer hover:border-emerald-500/40 hover:scale-[1.01] transition-all duration-200 flex items-center gap-3"
                             :class="payload.grid === b.buildingGrid ? 'border-emerald-500/50 bg-emerald-500/10' : 'border-transparent'">
                            <div class="w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0 bg-dark-900/50 border border-white/5">
                                <img v-if="getBuildingIcon(b)" :src="getBuildingIcon(b)" :alt="getBuildingName(b)" class="w-7 h-7 object-contain" @error="handleBuildingIconError($event, b)">
                                <span v-else class="text-sm">🏭</span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-xs font-semibold text-white/90 truncate">{{ getBuildingName(b) }}</p>
                                <p class="text-[10px] text-white/40 mt-0.5">Сетка #{{ b.buildingGrid }} • Ур. {{ b.upgradeLevel || 1 }}</p>
                            </div>
                        </div>
                    </div>
                    <div v-else class="text-center py-8 text-white/30 text-xs">
                        Здания не найдены.
                    </div>
                </div>
            </div>
        </div>

        <!-- МОДАЛЬНОЕ ОКНО: ВЫБОР СПЕЦИАЛИСТА -->
        <div v-if="showSpecialistModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm animate-fade-in">
            <div class="glass-card max-w-2xl w-full flex flex-col max-h-[80vh] shadow-2xl border border-white/10">
                <div class="px-6 py-4 border-b border-white/5 flex items-center justify-between">
                    <h3 class="text-base font-semibold text-white">Выбор специалиста</h3>
                    <button type="button" @click="closeSpecialistModal" class="text-white/40 hover:text-white transition-colors">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <div class="p-4 border-b border-white/5 bg-white/[0.01]">
                    <input v-model="specialistSearch" type="text" placeholder="Поиск по имени или типу..." class="glass-input w-full text-xs py-2 pl-4">
                </div>
                <div class="p-6 overflow-y-auto flex-1 bg-dark-950/20">
                    <div v-if="filteredSpecialistsModal.length > 0" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div v-for="s in filteredSpecialistsModal" :key="s.uniqueId1 + '-' + s.uniqueId2"
                             @click="selectSpecialist(s)"
                             class="glass-card p-3 cursor-pointer hover:border-emerald-500/40 hover:scale-[1.01] transition-all duration-200 flex items-center gap-3"
                             :class="payload.unique_id1 === s.uniqueId1 ? 'border-emerald-500/50 bg-emerald-500/10' : 'border-transparent'">
                            <div class="w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0 bg-dark-900/50 border border-white/5">
                                <img v-if="getSpecialistIcon(s.type)" :src="getSpecialistIcon(s.type)" class="w-8 h-8 object-contain" @error="handleSpecialistIconError($event, s.type)">
                                <span v-else class="text-sm">🎖️</span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-xs font-semibold text-white/90 truncate">{{ s.name || getSpecialistTypeName(s.type) }}</p>
                                <p class="text-[10px] text-white/40 mt-0.5">{{ getSpecialistTypeName(s.type) }}</p>
                            </div>
                        </div>
                    </div>
                    <div v-else class="text-center py-8 text-white/30 text-xs">
                        Специалисты не найдены.
                    </div>
                </div>
            </div>
        </div>

        <!-- МОДАЛЬНОЕ ОКНО: ВЫБОР БАФФА -->
        <div v-if="showBuffModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm animate-fade-in">
            <div class="glass-card max-w-2xl w-full flex flex-col max-h-[80vh] shadow-2xl border border-white/10">
                <div class="px-6 py-4 border-b border-white/5 flex items-center justify-between">
                    <h3 class="text-base font-semibold text-white">Выбор баффа из звездного меню</h3>
                    <button type="button" @click="closeBuffModal" class="text-white/40 hover:text-white transition-colors">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <div class="p-4 border-b border-white/5 bg-white/[0.01]">
                    <input v-model="buffSearch" type="text" placeholder="Поиск баффа по названию..." class="glass-input w-full text-xs py-2 pl-4">
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
                                <p class="text-[10px] text-white/40 mt-0.5">В наличии: {{ bf.amount }}</p>
                            </div>
                        </div>
                    </div>
                    <div v-else class="text-center py-8 text-white/30 text-xs">
                        Баффы не найдены.
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
            if (!acc) return 'Выберите аккаунт';
            return `${acc.nickname || acc.username} (${(acc.region || '').toUpperCase()})`;
        });

        const stepActionTypeLabel = computed(() => {
            const labels = {
                stop_production: '🛑 Остановить производство',
                start_production: '▶️ Запустить производство',
                apply_buff: '⚡ Применить бафф',
                send_geologist: '⛏️ Отправить геолога',
                send_explorer: '🧭 Отправить разведчика'
            };
            return labels[stepActionType.value] || 'Выберите действие';
        });

        const specialistSearchTypeLabel = computed(() => {
            if (stepActionType.value === 'send_geologist') {
                return 'Поиск залежей';
            }
            if (stepActionType.value === 'send_explorer') {
                return payload.value.task_type === 2 ? 'Поиск приключений' : 'Поиск сокровищ';
            }
            return 'Выберите тип поиска';
        });

        const specialistSubTaskLabel = computed(() => {
            const st = availableSubTasks.value.find(s => s.id === payload.value.sub_task_id);
            return st ? st.name : 'Выберите цель';
        });

        const zone = ref({ buildings: [], specialists: [], buffs: [] });
        const payload = ref({});

        // Состояние модальных окон
        const showBuildingModal = ref(false);
        const showSpecialistModal = ref(false);
        const showBuffModal = ref(false);

        const buildingSearch = ref('');
        const buildingFilter = ref('Все');

        const specialistSearch = ref('');
        const buffSearch = ref('');

        const selectedBuilding = ref(null);
        const selectedSpecialist = ref(null);
        const selectedBuff = ref(null);

        const stepTargetScope = ref('self');
        const selectedFriend = ref(null);
        const selectedFriendBuilding = ref(null);
        const friendBuildings = ref([]);
        const loadingFriendZone = ref(false);
        const friendZoneError = ref(false);
        const stepAmount = ref(1);
        const showFriendBuildingModal = ref(false);
        const friendBuildingSearch = ref('');

        const friendsList = computed(() => {
            return zone.value?.friends || [];
        });

        const isBuffable = (b) => {
            if (!b) return false;
            const name = (b.buildingName_string || b.buildingName || '').toLowerCase();
            const nonBuffable = [
                'decoration', 'mountain', 'mine_02', 'wall', 'gate',
                'ruin', 'rubble', 'wreckage', 'depleted', 'deposit',
                'collectible', 'bandit'
            ];
            return !nonBuffable.some(word => name.includes(word));
        };

        const filteredFriendBuildings = computed(() => {
            return friendBuildings.value.filter(b => isBuffable(b));
        });

        const searchedFriendBuildings = computed(() => {
            let list = filteredFriendBuildings.value;
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
            friendBuildings.value = [];
            if (friend) {
                if (!friend.id) {
                    showToast('У этого друга отсутствует ID. Пожалуйста, выполните синхронизацию (Sync) в разделе Accounts.', 'error');
                    return;
                }
                fetchFriendZoneBuildings();
            }
        };

        const fetchFriendZoneBuildings = async () => {
            if (!selectedAccountId.value || !selectedFriend.value) return;
            loadingFriendZone.value = true;
            friendZoneError.value = false;
            try {
                const res = await axios.get(`/api/accounts/${selectedAccountId.value}/friends/${selectedFriend.value.id}/zone`);
                if (res.data.success) {
                    friendBuildings.value = res.data.buildings || [];
                } else {
                    friendZoneError.value = true;
                }
            } catch (e) {
                friendZoneError.value = true;
            } finally {
                loadingFriendZone.value = false;
            }
        };

        const openFriendBuildingModal = () => {
            if (!selectedFriend.value) {
                showToast('Сначала выберите друга.', 'warning');
                return;
            }
            friendBuildingSearch.value = '';
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
            selectedBuilding.value = null;
            payload.value.grid = '';
            friendBuildings.value = [];
            friendZoneError.value = false;
        };

        const buildingCategories = ['Все', 'Дерево', 'Рудники', 'Металл', 'Еда', 'Другие'];

        const typeIcons = {
            stop_production: '🛑',
            start_production: '▶️',
            apply_buff: '⚡',
            send_geologist: '⛏️',
            send_explorer: '🧭'
        };

        const typeLabels = {
            stop_production: 'Остановка производства',
            start_production: 'Запуск производства',
            apply_buff: 'Применение баффа',
            send_geologist: 'Отправка геолога',
            send_explorer: 'Отправка разведчика'
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
                showToast('Не удалось загрузить данные планировщика.', 'error');
            }
        };

        const onAccountChange = () => {
            selectedBuilding.value = null;
            selectedSpecialist.value = null;
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
            selectedBuilding.value = null;
            selectedSpecialist.value = null;
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

        const addStepToSequence = () => {
            if (stepActionType.value === 'apply_buff' && stepTargetScope.value === 'friend') {
                if (loadingFriendZone.value) {
                    showToast('Подождите, пока загрузится зона друга.', 'warning');
                    return;
                }
                if (!selectedFriend.value) {
                    showToast('Сначала выберите друга.', 'warning');
                    return;
                }
                if (!payload.value.grid) {
                    showToast('Сначала выберите здание друга.', 'warning');
                    return;
                }
            } else if (['stop_production', 'start_production', 'apply_buff'].includes(stepActionType.value)) {
                if (!payload.value.grid) {
                    showToast('Сначала выберите целевое здание.', 'warning');
                    return;
                }
            }
            if (stepActionType.value === 'apply_buff') {
                if (!payload.value.unique_id1) {
                    showToast('Сначала выберите бафф.', 'warning');
                    return;
                }
            }
            if (['send_geologist', 'send_explorer'].includes(stepActionType.value)) {
                if (!selectedSpecialist.value) {
                    showToast('Сначала выберите специалиста.', 'warning');
                    return;
                }
            }

            // Create step payload
            const actionPayload = {
                ...payload.value,
                target_scope: stepTargetScope.value,
                target_player_id: stepTargetScope.value === 'friend' ? selectedFriend.value?.id : null,
                target_player_name: stepTargetScope.value === 'friend' ? (selectedFriend.value?.nickname || selectedFriend.value?.username) : null,
                amount: stepActionType.value === 'apply_buff' ? stepAmount.value : 1
            };

            // Save meta for frontend rendering
            const meta = {
                building: stepTargetScope.value === 'friend'
                    ? (selectedFriendBuilding.value ? { ...selectedFriendBuilding.value } : null)
                    : (selectedBuilding.value ? { ...selectedBuilding.value } : null),
                specialist: selectedSpecialist.value ? { ...selectedSpecialist.value } : null,
                buff: selectedBuff.value ? { ...selectedBuff.value } : null,
                subTaskLabel: selectedSpecialist.value
                    ? getSubTaskLabel(stepActionType.value, payload.value.task_type, payload.value.sub_task_id)
                    : ''
            };

            sequenceActions.value.push({
                task_type: stepActionType.value,
                payload: actionPayload,
                delay_seconds: Number(stepDelay.value || 0),
                meta: meta
            });

            // Reset temp step variables
            if (stepActionType.value === 'apply_buff') {
                // Keep selectedFriend, friendBuildings, selectedBuff, stepTargetScope, stepAmount!
                // Only reset the targeted building so the user can quickly apply the same buff to multiple buildings.
                selectedBuilding.value = null;
                selectedFriendBuilding.value = null;
                payload.value.grid = '';
            } else {
                selectedBuilding.value = null;
                selectedFriendBuilding.value = null;
                selectedFriend.value = null;
                selectedSpecialist.value = null;
                selectedBuff.value = null;
                stepTargetScope.value = 'self';
                stepAmount.value = 1;
                friendBuildings.value = [];
                resetPayload(stepActionType.value);
            }
            stepDelay.value = 5;

            showToast('Действие успешно добавлено в серию!');
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
            if (confirm('Вы уверены, что хотите очистить всю серию шагов?')) {
                sequenceActions.value = [];
                showToast('Серия шагов очищена.');
            }
        };

        const removeAction = (idx) => {
            sequenceActions.value.splice(idx, 1);
            showToast('Действие удалено из серии.');
        };

        resetPayload('stop_production');

        // Логика фильтрации и поиска зданий
        const isStoppable = (b) => {
            if (!b) return false;
            const mode = b.buildingMode;
            if (mode < 20 || mode > 28) return false;

            const name = (b.buildingName_string || b.buildingName || '').toLowerCase();
            const nonStoppable = [
                'mayorhouse', 'storehouse', 'residence', 'tavern', 'decoration',
                'mountain', 'mine_02', 'pioneercastle', 'lookouttower', 'waterstorehouse',
                'floatingstorehouse', 'spaciousstorehouse', 'improvedstorehouse', 'tower', 'wall', 'gate',
                'garrison', 'excelsior', 'ruin', 'rubble', 'wreckage', 'ship', 'depleted', 'deposit',
                'collectible', 'bandit'
            ];
            return !nonStoppable.some(word => name.includes(word));
        };

        const getBuildingName = (b) => {
            if (!b) return '';
            const name = b.buildingName_string || b.buildingName || 'Building';
            return name.replace(/(?<!^)(?=[A-Z])/g, ' ').replace(/_/g, ' ');
        };

        const getBuildingIcon = (b) => {
            if (!b) return null;
            const name = b.buildingName_string || b.buildingName || '';
            if (!name) return null;
            let clean = name.replace(/_lvl_\d+/i, '').replace(/decoration_/g, '').trim().toLowerCase();

            const nameMapping = {
                'realwoodsawmill': 'sawmill_real_planks',
                'exoticwoodsawmill': 'sawmill_exotic_planks',
                'mahoganysawmill': 'mahogany_sawmill',
                'exoticwoodtreeschool': 'exoticwood_treeschool',
                'stonecutter': 'stonemason',
                'marblecutter': 'marblemason',
                'granitecutter': 'granitemason'
            };

            if (nameMapping[clean]) clean = nameMapping[clean];
            return `/images/buildings/${clean}.webp`;
        };

        const handleBuildingIconError = (event, b) => {
            if (!b) return;
            const img = event.target;
            const name = b.buildingName_string || b.buildingName || '';
            let clean = name.replace(/_lvl_\d+/i, '').replace(/decoration_/g, '').trim().toLowerCase();

            const nameMapping = {
                'realwoodsawmill': 'sawmill_real_planks',
                'exoticwoodsawmill': 'sawmill_exotic_planks',
                'mahoganysawmill': 'mahogany_sawmill',
                'exoticwoodtreeschool': 'exoticwood_treeschool',
                'stonecutter': 'stonemason',
                'marblecutter': 'marblemason',
                'granitecutter': 'granitemason'
            };

            if (nameMapping[clean]) clean = nameMapping[clean];

            if (img.src.includes('/images/buildings/') && img.src.endsWith('.webp')) {
                img.src = `/images/buildings/${clean}.webp`;
            } else if (img.src.includes('/images/buildings/') && img.src.endsWith('.webp')) {
                img.src = `/images/resources/${clean}.webp`;
            } else {
                img.style.display = 'none';
            }
        };

        const totalBuildingsCount = computed(() => {
            if (!zone.value || !zone.value.buildings) return 0;
            return zone.value.buildings.filter(b => isStoppable(b)).length;
        });
        const totalSpecialistsCount = computed(() => zone.value?.specialists?.length || 0);
        const totalBuffsCount = computed(() => zone.value?.availableBuffs?.length || 0);

        const filteredBuildings = computed(() => {
            if (!zone.value || !zone.value.buildings) return [];
            let list = zone.value.buildings.filter(b => isStoppable(b));

            if (buildingSearch.value) {
                const query = buildingSearch.value.toLowerCase();
                list = list.filter(b => getBuildingName(b).toLowerCase().includes(query) || String(b.buildingGrid).includes(query));
            }

            if (buildingFilter.value !== 'Все') {
                list = list.filter(b => {
                    const name = (b.buildingName_string || b.buildingName || '').toLowerCase();
                    if (buildingFilter.value === 'Дерево') {
                        return name.includes('wood') || name.includes('sawmill') || name.includes('forester') || name.includes('cutter');
                    }
                    if (buildingFilter.value === 'Рудники') {
                        return name.includes('mine') || name.includes('quarry') || name.includes('cutter');
                    }
                    if (buildingFilter.value === 'Металл') {
                        return name.includes('iron') || name.includes('copper') || name.includes('gold') || name.includes('steel') || name.includes('smelter') || name.includes('weapon');
                    }
                    if (buildingFilter.value === 'Еда') {
                        return name.includes('farm') || name.includes('brewery') || name.includes('butcher') || name.includes('mill') || name.includes('bakery') || name.includes('hunter') || name.includes('fish');
                    }
                    return !name.includes('wood') && !name.includes('sawmill') && !name.includes('forester') && !name.includes('mine') && !name.includes('quarry') && !name.includes('iron') && !name.includes('copper') && !name.includes('gold') && !name.includes('steel') && !name.includes('smelter') && !name.includes('weapon') && !name.includes('farm') && !name.includes('brewery') && !name.includes('butcher') && !name.includes('mill') && !name.includes('bakery') && !name.includes('hunter') && !name.includes('fish');
                });
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
            event.target.style.display = 'none';
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

        const translations = ref({});

        const formatResourceName = (name) => {
            if (!name) return '';
            let formatted = name.replace(/(?<!^)(?=[A-Z])/g, ' ').replace(/_/g, ' ').trim();
            return formatted.replace(/\w\S*/g, (w) => w.replace(/^\w/, (c) => c.toUpperCase()));
        };

        const getStarBuffName = (b) => {
            if (!b || !b.buffName_string) return 'Неизвестный бафф';

            const name = b.buffName_string;

            if (translations.value[name]) {
                let tpl = translations.value[name];
                if (tpl.includes('{0}')) {
                    tpl = tpl.replace('{0}', formatResourceName(b.resourceName_string));
                }
                tpl = tpl.replace(/\{1,\w+\}/g, '').replace(/[:\s]+$/, '').replace(/\s+/g, ' ').trim();
                return tpl;
            }

            if (name === 'AddResource') {
                return `Добавить ресурс: ${formatResourceName(b.resourceName_string)}`;
            }
            if (name === 'BuildBuilding') {
                return `Лицензия: ${formatResourceName(b.resourceName_string)}`;
            }
            if (name === 'Adventure') {
                return `Приключение: ${formatResourceName(b.resourceName_string)}`;
            }

            return name.replace(/(?<!^)(?=[A-Z])/g, ' ').replace(/_/g, ' ');
        };

        const getBuffIcon = (b) => {
            if (!b) return null;
            const name = b.buffName_string || b.name || '';
            if (!name) return null;
            let clean = name.trim().toLowerCase().replace(/\s+/g, '_').replace(/['"]/g, '');
            const buffMap = {
                'aunt_irmas_basket': 'aunt_irma_basket',
                'aunt_irmas_feast_basket': 'aunt_irma_feast_basket',
                'plate_of_fish': 'plate_fish',
                'solid_sandwich': 'solid_sandwich',
                'chocolate_rabbit': 'chocolate_rabbit',
                'love_potion': 'love_potion',
                'fermentation_accelerator': 'fermentation_accelerator',
                'balloon_dog': 'balloon_dog',
                'secretsanta': 'buff_secretsanta',
                'buff_secretsanta': 'buff_secretsanta'
            };
            if (buffMap[clean]) clean = buffMap[clean];
            return `/images/other/${clean}.webp`;
        };

        const handleBuffIconError = (event, b) => {
            if (!b) return;
            const img = event.target;
            const name = b.buffName_string || b.name || '';
            let clean = name.trim().toLowerCase().replace(/\s+/g, '_').replace(/['"]/g, '');

            const buffMap = {
                'aunt_irmas_basket': 'aunt_irma_basket',
                'aunt_irmas_feast_basket': 'aunt_irma_feast_basket',
                'solid_sandwich': 'solid_sandwich',
                'grilled_steak': 'grilled_steak',
                'fish_platter': 'fish_platter',
                'chocolate_rabbit': 'chocolate_rabbit',
                'love_potion': 'love_potion',
                'fermentation_accelerator': 'fermentation_accelerator',
                'balloon_dog': 'balloon_dog',
                'secretsanta': 'buff_secretsanta',
                'buff_secretsanta': 'buff_secretsanta'
            };

            if (buffMap[clean]) clean = buffMap[clean];

            if (img.src.includes('/images/other/') && img.src.endsWith('.webp')) {
                img.src = `/images/buildings/${clean}.webp`;
            } else if (img.src.includes('/images/buildings/') && img.src.endsWith('.webp')) {
                img.src = `/images/buildings/${clean}.webp`;
            } else {
                img.style.display = 'none';
            }
        };

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
        const openBuildingModal = () => {
            if (!selectedAccountId.value) {
                showToast('Сначала выберите игровой аккаунт.', 'warning');
                return;
            }
            buildingSearch.value = '';
            buildingFilter.value = 'Все';
            showBuildingModal.value = true;
        };
        const closeBuildingModal = () => { showBuildingModal.value = false; };
        const selectBuilding = (b) => {
            selectedBuilding.value = b;
            payload.value.grid = b.buildingGrid;
            closeBuildingModal();
        };

        const openSpecialistModal = () => {
            if (!selectedAccountId.value) {
                showToast('Сначала выберите игровой аккаунт.', 'warning');
                return;
            }
            specialistSearch.value = '';
            showSpecialistModal.value = true;
        };
        const closeSpecialistModal = () => { showSpecialistModal.value = false; };
        const selectSpecialist = (s) => {
            selectedSpecialist.value = s;
            payload.value.unique_id1 = s.uniqueId1;
            payload.value.unique_id2 = s.uniqueID2 || s.uniqueId2 || 0;
            closeSpecialistModal();
        };

        const openBuffModal = () => {
            if (!selectedAccountId.value) {
                showToast('Сначала выберите игровой аккаунт.', 'warning');
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
                    { id: 0, name: 'Камень' },
                    { id: 1, name: 'Медная руда' },
                    { id: 2, name: 'Мрамор' },
                    { id: 3, name: 'Железная руда' },
                    { id: 4, name: 'Золотая руда' },
                    { id: 5, name: 'Каменный уголь' },
                    { id: 6, name: 'Гранит' },
                    { id: 7, name: 'Титановая руда' },
                    { id: 8, name: 'Селитра' }
                ];
            }

            if (stepActionType.value === 'send_explorer') {
                if (payload.value.task_type === 1) {
                    // Поиск сокровищ
                    return [
                        {
                            id: 0,
                            name: 'Короткий поиск сокровищ (6ч)'
                        },
                        {
                            id: 1,
                            name: 'Средний поиск сокровищ (12ч)'
                        },
                        {
                            id: 2,
                            name: 'Длинный поиск сокровищ (24ч)'
                        },
                        {
                            id: 3,
                            name: 'Очень длинный поиск сокровищ (36ч)'
                        },
                        {
                            id: 6,
                            name: 'Экстрадлинный поиск сокровищ (48ч)'
                        }
                    ];
                }

                if (payload.value.task_type === 2) {
                    // Поиск приключений
                    return [
                        {
                            id: 0,
                            name: 'Короткий поиск приключений'
                        },
                        {
                            id: 1,
                            name: 'Средний поиск приключений'
                        },
                        {
                            id: 2,
                            name: 'Длинный поиск приключений'
                        },
                        {
                            id: 3,
                            name: 'Очень длинный поиск приключений'
                        }
                    ];
                }
            }

            return [];
        });

        const groupedTasks = computed(() => {
            const groups = {};
            tasks.value.forEach(t => {
                const name = t.account ? (t.account.nickname || t.account.username) : 'Неизвестный аккаунт';
                if (!groups[name]) groups[name] = [];
                groups[name].push(t);
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
                    0: 'Камень',
                    1: 'Медь',
                    2: 'Мрамор',
                    3: 'Железо',
                    4: 'Золото',
                    5: 'Уголь',
                    6: 'Гранит',
                    7: 'Титановая руда',
                    8: 'Селитра'
                },

                // Разведчик: сокровища
                1: {
                    0: 'Кор. сокровища',
                    1: 'Ср. сокровища',
                    2: 'Дл. сокровища',
                    3: 'Очень дл. сокровища',
                    4: 'Особый поиск Erudite',
                    5: 'Особый поиск Bean A Collada',
                    6: 'Экстрадл. сокровища'
                },

                // Разведчик: приключения
                2: {
                    0: 'Кор. приключение',
                    1: 'Ср. приключение',
                    2: 'Дл. приключение',
                    3: 'Очень дл. приключение'
                }
            };

            const cat = category !== undefined
                ? Number(category)
                : coreTaskType === 'send_geologist'
                    ? 0
                    : 1;

            return stNames[cat]?.[Number(subId)]
                || `Задача #${subId}`;
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
            return d.toLocaleString('ru-RU', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
            });
        };

        const formatInterval = (hours, minutes) => {
            const parts = [];
            if (hours) parts.push(`${hours} ч.`);
            if (minutes) parts.push(`${minutes} мин.`);
            return parts.join(' ') || '0 мин.';
        };

        const editingTaskId = ref(null);

        const cancelEdit = () => {
            editingTaskId.value = null;
            taskName.value = '';
            sequenceActions.value = [];
            selectedBuilding.value = null;
            selectedSpecialist.value = null;
            selectedBuff.value = null;
            resetPayload(stepActionType.value);

            runAtTime.value = '';
            runAtDatetime.value = '';
            intervalHours.value = 0;
            intervalMinutes.value = 0;
            scheduleType.value = 'daily';
            zone.value = { buildings: [], specialists: [], buffs: [] };
        };

        const editTask = (t) => {
            editingTaskId.value = t.id;
            taskName.value = t.name || '';
            selectedAccountId.value = t.account_id;
            onAccountChange();

            scheduleType.value = t.schedule_type || 'daily';
            runAtTime.value = t.run_at_time ? utcTimeToLocal(t.run_at_time.substring(0, 5)) : '';
            runAtDatetime.value = utcToDatetimeLocalInput(t.run_at_datetime);
            intervalHours.value = t.interval_hours || 0;
            intervalMinutes.value = t.interval_minutes || 0;

            if (t.task_type === 'sequence' && t.payload && t.payload.actions) {
                const acc = accounts.value.find(a => a.id === t.account_id);
                let zoneData = { buildings: [], specialists: [], buffs: [] };
                if (acc && acc.zone_data) {
                    try {
                        zoneData = typeof acc.zone_data === 'string' ? JSON.parse(acc.zone_data) : acc.zone_data;
                    } catch (e) {}
                }

                sequenceActions.value = t.payload.actions.map(act => {
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
                showToast('Выберите игровой аккаунт.', 'warning');
                return;
            }

            if (sequenceActions.value.length === 0) {
                showToast('Добавьте хотя бы одно действие в серию.', 'warning');
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
                    showToast(editingTaskId.value ? 'Задача успешно обновлена.' : 'Серия задач успешно запланирована.');
                    cancelEdit();
                    loadPlanner();
                }
            } catch (e) {
                showToast(e.response?.data?.message || 'Не удалось сохранить серию задач.', 'error');
            } finally {
                scheduling.value = false;
            }
        };

        const toggleTask = async (task) => {
            try {
                const res = await axios.post(`/api/tasks/${task.id}/toggle`);
                if (res.data.success) {
                    task.is_active = res.data.task.is_active;
                    showToast(task.is_active ? 'Задача активирована' : 'Задача приостановлена');
                }
            } catch (e) {
                showToast('Не удалось переключить состояние задачи.', 'error');
            }
        };

        const deleteTask = async (id) => {
            if (!confirm('Удалить эту задачу?')) return;
            try {
                const res = await axios.delete(`/api/tasks/${id}`);
                if (res.data.success) {
                    showToast('Задача удалена.');
                    loadPlanner();
                }
            } catch (e) {
                showToast('Не удалось удалить задачу.', 'error');
            }
        };

        const runTaskNow = async (task) => {
            if (executingTasks.value[task.id]) return;
            executingTasks.value[task.id] = true;
            try {
                const res = await axios.post(`/api/tasks/${task.id}/execute`);
                if (res.data.success) {
                    showToast('Задача выполнена успешно: ' + (res.data.message || 'ОК'));
                } else {
                    showToast('Ошибка выполнения: ' + (res.data.message || 'Неизвестно'), 'error');
                }
                loadPlanner();
            } catch (e) {
                showToast(e.response?.data?.message || 'Не удалось выполнить задачу.', 'error');
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

        const getTaskActionsList = (t) => {
            if (!t) return [];
            if (t.task_type === 'sequence' && Array.isArray(t.payload?.actions)) {
                return t.payload.actions;
            }
            return [{
                task_type: t.task_type,
                payload: t.payload || {},
                delay_seconds: 0
            }];
        };

        const getNextRunDate = (t) => {
            if (!t) return null;

            if (t.schedule_type === 'once') {
                if (!t.run_at_datetime) return null;
                return parseServerDate(t.run_at_datetime);
            }

            if (t.schedule_type === 'daily') {
                if (!t.run_at_time) return null;
                const parts = t.run_at_time.split(':');
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

            if (t.schedule_type === 'interval') {
                const h = Number(t.interval_hours || 0);
                const m = Number(t.interval_minutes || 0);
                const intervalMs = (h * 3600 + m * 60) * 1000;
                if (intervalMs <= 0) return null;

                const baseStr = t.last_run_at || t.created_at;
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

        const getTaskNextRunText = (t) => {
            if (!t.is_active) {
                return { status: 'paused', label: 'Приостановлена', detail: '' };
            }

            if (t.schedule_type === 'once' && t.last_run_at) {
                return { status: 'completed', label: 'Завершена', detail: '' };
            }

            const nextDate = getNextRunDate(t);
            if (!nextDate) {
                return { status: 'none', label: '—', detail: '' };
            }

            const diffMs = nextDate.getTime() - currentTimeMs.value;

            if (diffMs <= 0) {
                return { status: 'due', label: 'Запуск...', detail: 'Выполняется или ожидает очереди' };
            }

            const totalSec = Math.floor(diffMs / 1000);
            const days = Math.floor(totalSec / 86400);
            const hours = Math.floor((totalSec % 86400) / 3600);
            const mins = Math.floor((totalSec % 3600) / 60);
            const secs = totalSec % 60;

            let parts = [];
            if (days > 0) parts.push(`${days}д`);
            if (hours > 0 || days > 0) parts.push(`${hours}ч`);
            if (mins > 0 || hours > 0 || days > 0) parts.push(`${mins}м`);
            parts.push(`${secs}с`);

            return {
                status: 'active',
                label: `через ${parts.join(' ')}`,
                nextRunTime: nextDate.toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' })
            };
        };

        const getBuildingDisplayName = (t, action) => {
            if (action.meta?.building) {
                return getBuildingName(action.meta.building);
            }
            const grid = action.payload?.grid;
            if (!grid) return 'Здание не указано';

            const acc = accounts.value.find(a => Number(a.id) === Number(t.account_id));
            if (acc && acc.zone_data) {
                try {
                    const zd = typeof acc.zone_data === 'string' ? JSON.parse(acc.zone_data) : acc.zone_data;
                    const b = zd.buildings?.find(b => Number(b.buildingGrid) === Number(grid));
                    if (b) return getBuildingName(b);
                } catch (e) {}
            }
            return `Сетка #${grid}`;
        };

        const getBuffDisplayName = (t, action) => {
            if (action.meta?.buff) {
                return getStarBuffName(action.meta.buff);
            }
            const u1 = action.payload?.unique_id1;
            if (!u1) return 'Бафф из меню';

            const acc = accounts.value.find(a => Number(a.id) === Number(t.account_id));
            if (acc && acc.zone_data) {
                try {
                    const zd = typeof acc.zone_data === 'string' ? JSON.parse(acc.zone_data) : acc.zone_data;
                    const buffs = zd.availableBuffs || zd.buffs || [];
                    const bf = buffs.find(b => (b.uniqueId1 || b.uniqueID1) == u1);
                    if (bf) return getStarBuffName(bf);
                } catch (e) {}
            }
            return `Бафф #${u1}`;
        };

        const closeAllDropdowns = (e) => {
            if (!e.target.closest('.relative')) {
                activeDropdown.value = null;
            }
        };

        onMounted(() => {
            loadPlanner();
            fetch('/api/lang/res')
                .then(r => r.json())
                .then(data => { translations.value = data; })
                .catch(() => {});
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
            translations,
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
            selectedBuilding,
            selectedSpecialist,
            selectedBuff,
            buildingCategories,
            openBuildingModal,
            closeBuildingModal,
            selectBuilding,
            openSpecialistModal,
            closeSpecialistModal,
            selectSpecialist,
            openBuffModal,
            closeBuffModal,
            selectBuff,

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
