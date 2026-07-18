<template>
    <div>
        <!-- Заголовок страницы -->
        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-3xl font-bold text-white">Планировщик задач</h1>
                <p class="text-white/40 mt-1">Автоматизируйте действия на ваших аккаунтах</p>
            </div>
        </div>

        <!-- Форма добавления задачи -->
        <div class="glass-card p-6 mb-8">
            <div class="flex items-center gap-3 mb-5">
                <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-emerald-500 to-teal-600 flex items-center justify-center">
                    <svg class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                </div>
                <h2 class="text-lg font-semibold text-white">Запланировать новую задачу</h2>
            </div>

            <form @submit.prevent="scheduleTask">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
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
                    <h3 class="text-sm font-semibold text-white mb-4 flex items-center gap-2">
                        <svg class="w-4 h-4 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244" />
                        </svg>
                        Конструктор серии действий
                    </h3>

                    <!-- Текущие шаги серии -->
                    <div v-if="sequenceActions.length > 0" class="space-y-2 mb-4 max-h-60 overflow-y-auto pr-2">
                        <div v-for="(act, idx) in sequenceActions" :key="idx" class="glass-card p-3 flex items-center justify-between gap-4 border border-white/5 hover:border-white/10 transition-all duration-200">
                            <div class="flex items-center gap-3">
                                <span class="w-5 h-5 rounded-full bg-emerald-500/10 text-emerald-400 flex items-center justify-center text-[10px] font-bold font-mono">{{ idx + 1 }}</span>
                                <span class="text-lg">{{ typeIcons[act.task_type] }}</span>
                                <div class="text-xs">
                                    <p class="font-semibold text-white/90">{{ typeLabels[act.task_type] }}</p>
                                    <p class="text-white/40 text-[10px] mt-0.5">
                                        <span v-if="['stop_production', 'start_production', 'apply_buff'].includes(act.task_type)">
                                            Здание: {{ act.meta.building ? getBuildingName(act.meta.building) : `Сетка #${act.payload.grid}` }}
                                        </span>
                                        <span v-if="act.task_type === 'apply_buff' && act.meta.buff">
                                            • Бафф: {{ getStarBuffName(act.meta.buff) }}
                                        </span>
                                        <span v-if="['send_geologist', 'send_explorer'].includes(act.task_type)">
                                            Специалист: {{ act.meta.specialist ? (act.meta.specialist.name || getSpecialistTypeName(act.meta.specialist.type)) : `Тип #${act.payload.unique_id1}` }}
                                            <span v-if="act.meta.subTaskLabel">• {{ act.meta.subTaskLabel }}</span>
                                        </span>
                                    </p>
                                </div>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="text-[10px] text-amber-400 bg-amber-400/10 px-2 py-0.5 rounded-full border border-amber-400/20 font-mono">
                                    задержка: {{ act.delay_seconds }} сек.
                                </span>
                                <button type="button" @click="removeAction(idx)" class="text-white/30 hover:text-red-400 transition-colors">
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
                                    <!-- Здание -->
                                    <div v-if="['stop_production', 'start_production', 'apply_buff'].includes(stepActionType)" class="col-span-2">
                                        <button type="button" @click="openBuildingModal" class="glass-select w-full flex items-center justify-between text-left text-xs py-2 bg-dark-900/40">
                                            <span v-if="selectedBuilding" class="flex items-center gap-2">
                                                <img v-if="getBuildingIcon(selectedBuilding)" :src="getBuildingIcon(selectedBuilding)" class="w-5 h-5 object-contain" @error="handleBuildingIconError($event, selectedBuilding)" />
                                                <span class="truncate">{{ getBuildingName(selectedBuilding) }} (Grid #{{ selectedBuilding.buildingGrid }})</span>
                                            </span>
                                            <span v-else class="text-white/30">Выберите здание... ({{ totalBuildingsCount }} дост.)</span>
                                            <svg class="w-3.5 h-3.5 text-white/30" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                            </svg>
                                        </button>
                                    </div>

                                    <!-- Бафф -->
                                    <div v-if="stepActionType === 'apply_buff'" class="col-span-2 mt-1">
                                        <button type="button" @click="openBuffModal" class="glass-select w-full flex items-center justify-between text-left text-xs py-2 bg-dark-900/40">
                                            <span v-if="selectedBuff" class="flex items-center gap-2">
                                                <img v-if="getBuffIcon(selectedBuff)" :src="getBuffIcon(selectedBuff)" class="w-5 h-5 object-contain" @error="handleBuffIconError($event, selectedBuff)" />
                                                <span class="truncate">{{ getStarBuffName(selectedBuff) }} ({{ selectedBuff.amount }})</span>
                                            </span>
                                            <span v-else class="text-white/30">Выберите бафф... ({{ totalBuffsCount }} дост.)</span>
                                            <svg class="w-3.5 h-3.5 text-white/30" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                            </svg>
                                        </button>
                                    </div>

                                    <!-- Специалист -->
                                    <div v-if="['send_geologist', 'send_explorer'].includes(stepActionType)" class="col-span-2">
                                        <button type="button" @click="openSpecialistModal" class="glass-select w-full flex items-center justify-between text-left text-xs py-2 bg-dark-900/40">
                                            <span v-if="selectedSpecialist" class="flex items-center gap-2">
                                                <img v-if="getSpecialistIcon(selectedSpecialist.type)" :src="getSpecialistIcon(selectedSpecialist.type)" class="w-5 h-5 object-contain" @error="handleSpecialistIconError($event, selectedSpecialist.type)" />
                                                <span class="truncate">{{ selectedSpecialist.name || getSpecialistTypeName(selectedSpecialist.type) }}</span>
                                            </span>
                                            <span v-else class="text-white/30">Выберите специалиста... ({{ totalSpecialistsCount }} дост.)</span>
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

                <button type="submit" :disabled="scheduling" class="btn-primary flex items-center gap-2 disabled:opacity-50">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    {{ scheduling ? 'Сохранение...' : 'Запланировать серию задач' }}
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
                        <div v-for="t in groupTasks" :key="t.id" class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 px-5 py-4 hover:bg-white/[0.02] transition-all duration-200 group">
                            <div class="flex items-center gap-3">
                                <!-- Иконка действия -->
                                <div class="w-9 h-9 rounded-lg bg-white/5 flex items-center justify-center text-lg flex-shrink-0">
                                    {{ t.task_type === 'sequence' ? '⛓️' : (typeIcons[t.task_type] || '📋') }}
                                </div>

                                <!-- Информация о задаче -->
                                <div class="min-w-0">
                                    <p class="text-sm font-medium text-white/80 group-hover:text-white transition-colors">
                                        <span v-if="t.task_type === 'sequence'">Серия шагов ({{ t.payload?.actions?.length || 0 }})</span>
                                        <span v-else>{{ typeLabels[t.task_type] || t.task_type }}</span>
                                    </p>
                                    <div class="flex flex-wrap items-center gap-2 mt-0.5 text-[10px] text-white/40">
                                        <span v-if="t.task_type !== 'sequence' && t.payload && t.payload.grid" class="font-mono">
                                            Сетка #{{ t.payload.grid }}
                                        </span>
                                        <span v-if="t.task_type !== 'sequence' && t.payload && t.payload.sub_task_id !== undefined">
                                            {{ getSubTaskLabel(t.task_type, t.payload.task_type, t.payload.sub_task_id) }}
                                        </span>
                                        <span class="badge badge-neutral text-[9px] uppercase">
                                            {{ t.schedule_type === 'once' ? 'Одноразово' : t.schedule_type === 'interval' ? 'Интервал' : 'Ежедневно' }}
                                        </span>
                                    </div>
                                    <div v-if="t.task_type === 'sequence' && t.payload && t.payload.actions" class="flex flex-wrap items-center gap-1.5 mt-1.5">
                                        <span v-for="(act, aIdx) in t.payload.actions" :key="aIdx" class="text-[9px] bg-white/5 border border-white/5 text-white/70 px-1.5 py-0.5 rounded flex items-center gap-1">
                                            <span class="text-[8px] text-white/30 font-bold font-mono">#{{ aIdx + 1 }}</span>
                                            <span>{{ typeIcons[act.task_type] }}</span>
                                            <span class="font-mono text-white/50" v-if="act.payload.grid">#{{ act.payload.grid }}</span>
                                            <span v-if="act.delay_seconds > 0" class="text-[8px] text-amber-400 font-mono">⏳{{ act.delay_seconds }}с</span>
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Правая секция: Расписание, Статус и Действия -->
                            <div class="flex items-center justify-end gap-4 ml-auto sm:ml-0">
                                <!-- Время/Интервал запуска -->
                                <div class="text-right">
                                    <p v-if="t.schedule_type === 'once'" class="text-xs text-white/60 font-mono">
                                        {{ formatDateTime(t.run_at_datetime) }}
                                    </p>
                                    <p v-else-if="t.schedule_type === 'interval'" class="text-xs text-white/60 font-mono">
                                        Каждые {{ formatInterval(t.interval_hours, t.interval_minutes) }}
                                    </p>
                                    <p v-else class="text-xs text-white/60 font-mono">
                                        Ежедневно в {{ t.run_at_time ? t.run_at_time.substring(0, 5) : '—' }}
                                    </p>
                                    <p class="text-[9px] text-white/20 uppercase tracking-wider">Расписание</p>
                                </div>

                                <!-- Последний результат выполнения -->
                                <span v-if="t.last_result" 
                                      class="badge text-[10px] flex-shrink-0 max-w-[120px] truncate"
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
            resetPayload(taskType.value);
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
                    sub_task_id: 1
                };
            }
        };

        const onStepActionTypeChange = () => {
            resetPayload(stepActionType.value);
        };

        const onSearchTypeChange = () => {
            payload.value.sub_task_id = 1;
        };

        const addStepToSequence = () => {
            if (['stop_production', 'start_production', 'apply_buff'].includes(stepActionType.value)) {
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
            const actionPayload = { ...payload.value };

            // Save meta for frontend rendering
            const meta = {
                building: selectedBuilding.value ? { ...selectedBuilding.value } : null,
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
            selectedBuilding.value = null;
            selectedSpecialist.value = null;
            selectedBuff.value = null;
            resetPayload(stepActionType.value);
            stepDelay.value = 5;

            showToast('Действие успешно добавлено в серию!');
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
                img.src = `/images/buildings/${clean}.png`;
            } else if (img.src.includes('/images/buildings/') && img.src.endsWith('.png')) {
                img.src = `/images/resources/${clean}.png`;
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
            if (!zone.value || !zone.value.specialists) return [];
            let list = zone.value.specialists.filter(sp => {
                const category = getSpecialistCategory(sp.type);
                if (taskType.value === 'send_geologist') return category === 'Geologist';
                if (taskType.value === 'send_explorer') return category === 'Explorer';
                return false;
            });

            if (specialistSearch.value) {
                const query = specialistSearch.value.toLowerCase();
                list = list.filter(sp => {
                    const name = (sp.name || '').toLowerCase();
                    const typeName = getSpecialistTypeName(sp.type).toLowerCase();
                    return name.includes(query) || typeName.includes(query);
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
            return `/images/resources/${clean}.png`;
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

            if (img.src.includes('/images/resources/') && img.src.endsWith('.png')) {
                img.src = `/images/buildings/${clean}.webp`;
            } else if (img.src.includes('/images/buildings/') && img.src.endsWith('.webp')) {
                img.src = `/images/buildings/${clean}.png`;
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
            if (taskType.value === 'send_geologist') {
                return [
                    { id: 1, name: 'Медная руда' },
                    { id: 2, name: 'Камень' },
                    { id: 3, name: 'Каменный уголь' },
                    { id: 4, name: 'Золотая руда' },
                    { id: 5, name: 'Железная руда' },
                    { id: 6, name: 'Мрамор' }
                ];
            } else if (taskType.value === 'send_explorer') {
                if (payload.value.task_type === 1) { // Treasure
                    return [
                        { id: 1, name: 'Короткий поиск сокровищ (6ч)' },
                        { id: 2, name: 'Средний поиск сокровищ (12ч)' },
                        { id: 3, name: 'Длинный поиск сокровищ (24ч)' },
                        { id: 4, name: 'Очень длинный поиск сокровищ (36ч)' },
                        { id: 5, name: 'Экстрадлинный поиск сокровищ (48ч)' }
                    ];
                } else { // Adventure
                    return [
                        { id: 1, name: 'Короткий поиск приключений' },
                        { id: 2, name: 'Средний поиск приключений' },
                        { id: 3, name: 'Длинный поиск приключений' },
                        { id: 4, name: 'Очень длинный поиск приключений' }
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

        const getSubTaskLabel = (coreTaskType, category, subId) => {
            const stNames = {
                0: { 1: 'Медь', 2: 'Камень', 3: 'Уголь', 4: 'Золото', 5: 'Железо', 6: 'Мрамор' },
                1: { 1: 'Кор. сокровища', 2: 'Ср. сокровища', 3: 'Дл. сокровища', 4: 'Очень дл. сокровища', 5: 'Доп. сокровища' },
                2: { 1: 'Кор. приключение', 2: 'Ср. приключение', 3: 'Дл. приключение', 4: 'Очень дл. приключение' }
            };
            const cat = category !== undefined ? category : (coreTaskType === 'send_geologist' ? 0 : 1);
            return stNames[cat]?.[subId] || 'Задача #' + subId;
        };

        const formatDateTime = (dtStr) => {
            if (!dtStr) return '—';
            try {
                const d = new Date(dtStr);
                return d.toLocaleString('ru-RU', {
                    day: '2-digit',
                    month: '2-digit',
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit',
                });
            } catch (e) {
                return dtStr;
            }
        };

        const formatInterval = (hours, minutes) => {
            const parts = [];
            if (hours) parts.push(`${hours} ч.`);
            if (minutes) parts.push(`${minutes} мин.`);
            return parts.join(' ') || '0 мин.';
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
                    postData.run_at_time = runAtTime.value;
                } else if (scheduleType.value === 'once') {
                    postData.run_at_datetime = runAtDatetime.value;
                } else if (scheduleType.value === 'interval') {
                    postData.interval_hours = intervalHours.value;
                    postData.interval_minutes = intervalMinutes.value;
                }

                const res = await axios.post('/api/tasks', postData);

                if (res.data.success) {
                    showToast('Серия задач успешно запланирована.');
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
                    loadPlanner();
                }
            } catch (e) {
                showToast(e.response?.data?.message || 'Не удалось запланировать серию задач.', 'error');
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
        });

        onUnmounted(() => {
            document.removeEventListener('click', closeAllDropdowns);
        });

        return {
            translations,
            tasks,
            accounts,
            scheduling,
            selectedAccountId,
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
            formatDateTime,
            formatInterval,

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
