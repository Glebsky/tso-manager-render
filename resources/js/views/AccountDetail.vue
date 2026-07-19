<template>
    <div v-if="loading" class="flex items-center justify-center min-h-[60vh]">
        <div class="text-center">
            <svg class="w-12 h-12 text-emerald-400 animate-spin mx-auto mb-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182" />
            </svg>
            <p class="text-white/40">Loading account data...</p>
        </div>
    </div>

    <div v-else-if="account">
        <!-- Back Button -->
        <router-link to="/accounts" class="inline-flex items-center gap-2 text-white/40 hover:text-white mb-6 transition-colors">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
            </svg>
            Back to Accounts
        </router-link>

        <!-- Profile Header -->
        <div class="glass-card overflow-hidden mb-8">
            <div class="h-2 bg-gradient-to-r relative overflow-hidden animate-glow"
                 :class="statusClass.gradient"
                 :style="{ '--glow': statusClass.glow }">
                <div class="absolute inset-0 animate-shimmer opacity-60"></div>
            </div>
            <div class="p-6">
                <!-- Main Info Section -->
                <div class="flex items-start gap-6">
                    <!-- Avatar -->
                    <div class="w-20 h-20 rounded-2xl overflow-hidden bg-gradient-to-br flex items-center justify-center shadow-lg flex-shrink-0"
                         :class="statusClass.gradient">
                        <img v-if="avatarUrl" :src="avatarUrl" :alt="playerNickname" class="w-full h-full object-cover" @error="avatarError = true">
                        <span v-else class="text-2xl font-bold text-white">{{ avatarLetters }}</span>
                    </div>

                    <!-- Details -->
                    <div class="flex-1 min-w-0">
                        <h1 class="text-2xl font-bold text-white mb-1">{{ playerNickname || account.username }}</h1>
                        <div class="flex items-center flex-wrap gap-4 text-sm text-white/40">
                            <span class="flex items-center gap-1 text-white/80">
                                <svg class="w-4 h-4 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.455 2.456L21.75 6l-1.036.259a3.375 3.375 0 0 0-2.455 2.456ZM16.894 20.567 16.5 21.75l-.394-1.183a2.25 2.25 0 0 0-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 0 0 1.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 0 0 1.423 1.423l1.183.394-1.183.394a2.25 2.25 0 0 0-1.423 1.423Z" />
                                </svg>
                                Уровень {{ level || '?' }}
                            </span>
                            <span v-if="pvpLevel" class="flex items-center gap-1 text-rose-400">
                                ⚔️ PvP {{ pvpLevel }}
                            </span>
                            <span v-if="account.region" class="badge badge-info uppercase text-[10px]">{{ account.region }}</span>
                            <span v-if="serverName" class="badge badge-success bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 text-[10px]">{{ serverName }}</span>
                            <span v-if="currentMaximumBuildingsCountAll" class="flex items-center gap-1 text-white/50 text-xs">
                                🏰 Макс. зданий: {{ currentMaximumBuildingsCountAll }}
                            </span>
                            <span v-if="account.last_sync_at" class="flex items-center gap-1">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                </svg>
                                Синхронизировано {{ formatSyncTime(account.last_sync_at) }}
                            </span>
                        </div>

                        <!-- Specialists overview -->
                        <div class="flex gap-4 mt-3 text-xs text-white/50 border-t border-white/5 pt-3">
                            <span title="Генералы">🎖️ Генералы: <strong class="text-white">{{ generalsAmount || 0 }}</strong></span>
                            <span title="Исследователи">🧭 Разведчики: <strong class="text-white">{{ explorersAmount || 0 }}</strong></span>
                            <span title="Геологи">🔨 Геологи: <strong class="text-white">{{ geologistsAmount || 0 }}</strong></span>
                        </div>

                        <!-- XP Progress Bar -->
                        <div v-if="xp !== null" class="mt-4">
                            <div class="flex items-center justify-between text-xs text-white/30 mb-1">
                                <span>Опыт: <strong class="text-white/70">{{ formatNumber(xp) }} XP</strong></span>
                                <span v-if="level && level >= 80">Максимальный уровень</span>
                                <span v-else-if="xpNextTarget">До {{ level + 1 }} уровня: {{ formatNumber(xpNextTarget - xp) }} XP (Всего {{ formatNumber(xpNextTarget) }})</span>
                            </div>
                            <div class="h-2 bg-white/5 rounded-full overflow-hidden">
                                <div class="h-full bg-gradient-to-r from-emerald-500 to-teal-500 rounded-full transition-all duration-500"
                                     :style="{ width: xpProgress + '%' }"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Side: Visitors and Sync Button -->
                <div class="flex items-center justify-end gap-4 mt-6 pt-4 border-t border-white/5 flex-wrap">
                    <!-- Visitors list -->
                    <div v-if="visitors.length > 0" class="flex items-center gap-3 bg-white/5 border border-white/10 px-4 py-2.5 rounded-2xl">
                        <span class="text-xs text-white/40 font-medium">Гости:</span>
                        <div class="flex -space-x-2">
                            <div v-for="visitor in visitors" :key="visitor.nickname" class="relative group">
                                <div class="w-8 h-8 rounded-full border-2 border-dark-900 overflow-hidden bg-gradient-to-br from-indigo-500/80 to-purple-600/80 flex items-center justify-center cursor-help">
                                    <img v-if="getAvatarById(visitor.avatarId)" :src="getAvatarById(visitor.avatarId)" :alt="visitor.nickname" class="w-full h-full object-cover">
                                    <span v-else class="text-[10px] font-bold text-white">{{ visitor.nickname.substring(0, 2).toUpperCase() }}</span>
                                </div>
                                <!-- Tooltip -->
                                <div class="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 hidden group-hover:block bg-dark-950 border border-white/10 px-3 py-1.5 rounded-xl text-xs text-white whitespace-nowrap shadow-2xl z-50">
                                    <div class="font-bold text-white">{{ visitor.nickname }}</div>
                                    <div class="text-[10px] text-white/50">Уровень {{ visitor.level }}</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sync button -->
                    <button @click="syncAccount" :disabled="syncing"
                            class="btn-primary flex items-center gap-2 px-5 py-2.5 rounded-xl text-sm font-semibold disabled:opacity-50">
                        <svg class="w-4 h-4" :class="{ 'animate-spin': syncing }" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                        </svg>
                        {{ syncing ? 'Синхронизация...' : 'Синхронизировать' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Tabs Navigation -->
        <div class="flex items-center gap-2.5 mb-6 overflow-x-auto pb-2">
            <button v-for="tab in tabs" :key="tab.id" @click="activeTab = tab.id"
                    class="flex items-center gap-2 px-5 py-2.5 rounded-xl text-sm font-medium transition-all duration-300 whitespace-nowrap"
                    :class="activeTab === tab.id
                        ? 'bg-gradient-to-r from-emerald-500/20 to-teal-500/10 text-emerald-400 border border-emerald-500/30'
                        : 'text-white/40 hover:text-white hover:bg-white/5 border border-transparent'">
                <component :is="tab.icon" class="w-4 h-4" />
                {{ tab.label }}
                <span v-if="tab.count !== null" class="text-[10px] px-1.5 py-0.5 rounded-full bg-white/10">{{ tab.count }}</span>
            </button>
        </div>

        <!-- Tab Content -->
        <div class="glass-card p-6">
            <!-- Buildings Tab -->
            <div v-show="activeTab === 'buildings'">
                <!-- Search & Filters -->
                <div class="flex flex-col sm:flex-row gap-4 mb-6">
                    <div class="flex gap-2 flex-wrap">
                        <button v-for="cat in buildingCategories" :key="cat" @click="buildingFilter = cat"
                                class="px-5 py-2.5 rounded-lg text-xs font-medium transition-all duration-300"
                                :class="buildingFilter === cat
                                    ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30'
                                    : 'bg-white/5 text-white/40 border border-transparent hover:bg-white/10'">
                            {{ cat }}
                        </button>
                    </div>
                    <div class="flex-1 relative">
                        <div class="absolute inset-y-0 left-0 flex items-center pl-4 pointer-events-none">
                            <svg class="w-4 h-4 text-white/30" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                            </svg>
                        </div>
                        <input v-model="buildingSearch" type="text" placeholder="Search buildings..." class="glass-input w-full pl-11">
                    </div>
                </div>

                <!-- Buildings Grid -->
                <div v-if="filteredBuildings.length > 0" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
                    <div v-for="b in filteredBuildings" :key="b.buildingGrid" class="glass-card p-4 hover:border-white/20 hover:scale-[1.02] transition-all duration-300">
                        <div class="flex items-start gap-3">
                            <div class="w-12 h-12 rounded-xl flex items-center justify-center flex-shrink-0 bg-dark-900/50 border border-white/5">
                                <img v-if="getBuildingIcon(b)" :src="getBuildingIcon(b)" :alt="getBuildingName(b)" class="w-8 h-8 object-contain" @error="handleBuildingIconError($event, b)">
                                <svg v-else class="w-6 h-6 text-white/30" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Z" />
                                </svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-white/80 truncate" :title="getBuildingName(b)">{{ getBuildingName(b) }}</p>
                                <div class="flex items-center gap-2 mt-1">
                                    <span class="text-[10px] text-white/30 font-mono">Grid #{{ b.buildingGrid }}</span>
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-emerald-500/10 text-emerald-400">Lvl {{ b.upgradeLevel || 1 }}</span>
                                </div>
                                <div v-if="b.buffs && b.buffs.length > 0" class="mt-2 flex flex-wrap gap-1">
                                    <span v-for="(bf, idx) in b.buffs" :key="idx"
                                          class="inline-flex items-center gap-1 bg-amber-500/10 text-amber-400 text-[10px] px-1.5 py-0.5 rounded border border-amber-500/20"
                                          :title="'ID: ' + bf.buffID">
                                        ✨ {{ getBuffName(bf.buffID) }}
                                    </span>
                                </div>
                            </div>
                            <div class="flex flex-col items-end gap-2 flex-shrink-0">
                                <span v-if="b.upgradeIsInProgress" class="badge bg-amber-500/20 text-amber-400 border border-amber-500/30 text-[10px]">
                                    🔨 Улучшается
                                </span>
                                <span v-else-if="isStoppable(b)" class="badge text-[10px]" :class="isBuildingActive(b) ? 'badge-success' : 'badge-neutral'">
                                    {{ isBuildingActive(b) ? 'Producing' : 'Stopped' }}
                                </span>
                                <span v-else class="badge badge-neutral text-[10px]">
                                    Built
                                </span>

                                <button v-if="isStoppable(b) && !b.upgradeIsInProgress" @click="toggleBuilding(b)" :disabled="actionLoading"
                                        class="btn-secondary btn-sm text-[10px] disabled:opacity-50"
                                        :class="isBuildingActive(b)
                                            ? 'text-amber-400/60 hover:text-amber-400 hover:border-amber-500/30'
                                            : 'text-emerald-400/60 hover:text-emerald-400 hover:border-emerald-500/30'">
                                    {{ actionLoading ? '...' : (isBuildingActive(b) ? 'Stop' : 'Start') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div v-else class="text-center py-12">
                    <p class="text-white/30 text-sm">No buildings found.</p>
                </div>
            </div>

            <!-- Specialists Tab -->
            <div v-show="activeTab === 'specialists'">
                <!-- Search & Filters -->
                <div class="flex flex-col sm:flex-row gap-4 mb-6">
                    <div class="flex gap-2 flex-wrap">
                        <button v-for="cat in ['All', 'General', 'Explorer', 'Geologist']" :key="cat" @click="specialistFilter = cat"
                                class="px-5 py-2.5 rounded-xl text-xs font-semibold transition-all duration-300 border"
                                :class="specialistFilter === cat
                                    ? 'bg-gradient-to-r from-emerald-500/20 to-teal-500/10 text-emerald-400 border-emerald-500/30'
                                    : 'bg-white/5 text-white/40 border-transparent hover:bg-white/10'">
                            {{ cat === 'All' ? 'Все' : cat === 'General' ? 'Генералы' : cat === 'Explorer' ? 'Разведчики' : 'Геологи' }}
                            <span class="ml-1.5 px-1.5 py-0.5 rounded-full text-[9px]" :class="specialistFilter === cat ? 'bg-emerald-500/20' : 'bg-white/5'">
                                {{ getSpecialistCategoryCount(cat) }}
                            </span>
                        </button>
                    </div>
                    <div class="flex-1 relative">
                        <div class="absolute inset-y-0 left-0 flex items-center pl-4 pointer-events-none">
                            <svg class="w-4 h-4 text-white/30" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                            </svg>
                        </div>
                        <input v-model="specialistSearch" type="text" placeholder="Поиск специалистов..." class="glass-input w-full pl-11">
                    </div>
                </div>

                <!-- Grid -->
                <div v-if="filteredSpecialists.length > 0" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
                    <div v-for="s in filteredSpecialists" :key="s.uniqueId || s.uniqueId1"
                         class="glass-card p-4 hover:border-white/20 hover:scale-[1.02] transition-all duration-300 relative overflow-hidden group">
                        <!-- Background Glow on Hover -->
                        <div class="absolute inset-0 bg-gradient-to-br transition-all duration-500 opacity-0 group-hover:opacity-10 pointer-events-none"
                             :class="getSpecialistCategory(s.type) === 'General' ? 'from-rose-500 to-red-500' : getSpecialistCategory(s.type) === 'Explorer' ? 'from-teal-500 to-emerald-500' : 'from-amber-500 to-orange-500'">
                        </div>

                        <div class="flex items-center gap-3 relative z-10">
                            <!-- Icon -->
                            <div class="w-12 h-12 rounded-2xl flex items-center justify-center flex-shrink-0 shadow-lg overflow-hidden bg-white/5"
                                 :class="getSpecialistCategory(s.type) === 'General' ? 'text-rose-400 shadow-rose-500/5' : getSpecialistCategory(s.type) === 'Explorer' ? 'text-teal-400 shadow-teal-500/5' : 'text-amber-400 shadow-amber-500/5'">
                                <img :src="getSpecialistIcon(s.type)" @error="handleSpecialistIconError($event, s.type)" class="w-full h-full object-contain p-1" v-if="!hasSpecialistIconError(s.type) && getSpecialistIcon(s.type)" />
                                <span v-else-if="getSpecialistCategory(s.type) === 'General'" class="text-xl">🎖️</span>
                                <span v-else-if="getSpecialistCategory(s.type) === 'Explorer'" class="text-xl">🧭</span>
                                <span v-else class="text-xl">🔨</span>
                            </div>

                            <!-- Names -->
                            <div class="flex-1 min-w-0">
                                <h4 class="text-sm font-bold text-white group-hover:text-emerald-400 transition-colors duration-300 truncate" :title="s.name || getSpecialistTypeName(s.type)">
                                    {{ s.name || getSpecialistTypeName(s.type) }}
                                </h4>
                                <div class="flex items-center flex-wrap gap-2 mt-1">
                                    <span v-if="s.name" class="text-[10px] text-white/40 truncate block max-w-[140px]" :title="getSpecialistTypeName(s.type)">
                                        {{ getSpecialistTypeName(s.type) }}
                                    </span>
                                    <span class="text-[9px] text-white/30 font-mono">
                                        ID: {{ s.uniqueId1 }}
                                    </span>
                                    <span class="badge text-[8px] px-1 py-0.5"
                                          :class="getSpecialistCategory(s.type) === 'General' ? 'bg-rose-500/10 text-rose-400' : getSpecialistCategory(s.type) === 'Explorer' ? 'bg-teal-500/10 text-teal-400' : 'bg-amber-500/10 text-amber-400'">
                                        {{ getSpecialistCategory(s.type) === 'General' ? 'Генерал' : getSpecialistCategory(s.type) === 'Explorer' ? 'Разведчик' : 'Геолог' }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Current Task details -->
                        <div class="mt-4 pt-3 border-t border-white/5 relative z-10">
                            <div v-if="s.taskType || s.taskSubType" class="flex flex-col gap-1.5">
                                <div class="flex items-center justify-between text-[10px]">
                                    <span class="text-white/40">Текущая задача:</span>
                                    <span class="badge badge-warning text-[9px] font-semibold bg-amber-500/10 text-amber-400 border border-amber-500/20">В пути</span>
                                </div>
                                <p class="text-xs font-medium text-white/80 flex items-center gap-1.5">
                                    <span>🚶‍♂️</span>
                                    {{ getTaskName(s.taskType, s.taskSubType, getSpecialistCategory(s.type)) }}
                                </p>
                            </div>
                            <div v-else class="flex items-center justify-between text-xs">
                                <span class="text-white/30 text-[10px]">Статус:</span>
                                <span class="badge badge-success text-[9px] font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 flex items-center gap-1">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                    Свободен
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-else class="text-center py-12">
                    <p class="text-white/30 text-sm">Специалисты не найдены.</p>
                </div>
            </div>

            <!-- Buffs Tab -->
            <div v-show="activeTab === 'buffs'">
                <!-- Star Menu Buffs -->
                <div class="mb-8">
                    <h3 class="text-sm font-semibold text-white/50 uppercase tracking-wider mb-4 flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-amber-400 animate-pulse"></span>
                        Звездное меню (Buffs in Star Menu)
                    </h3>
                    <div v-if="availableBuffs.length > 0" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4">
                        <div v-for="(b, idx) in availableBuffs" :key="idx" class="glass-card p-4 hover:border-white/20 hover:scale-[1.02] transition-all duration-300">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl bg-amber-500/10 flex items-center justify-center flex-shrink-0 overflow-hidden">
                                    <img :src="getBuffIcon(b)" :alt="getStarBuffName(b)" class="w-7 h-7 object-contain" @error="handleBuffIconError($event, b)">
                                    <span class="text-xl" style="display: none;">
                                        {{ b.buffName_string === 'AddResource' ? '📥' : b.buffName_string === 'Adventure' ? '🗺️' : b.buffName_string === 'BuildBuilding' ? '🏗️' : '✨' }}
                                    </span>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-white/80 truncate" :title="getStarBuffName(b)">
                                        {{ getStarBuffName(b) }}
                                    </p>
                                    <p class="text-[10px] text-white/30">ID: {{ b.uniqueId1 || 'N/A' }}</p>
                                </div>
                                <div class="text-right">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold"
                                          :class="b.buffName_string === 'AddResource' ? 'bg-emerald-500/10 text-emerald-400' : 'bg-amber-500/10 text-amber-400'">
                                        {{ b.buffName_string === 'AddResource' ? '+' + formatNumber(b.amount) : 'x' + b.amount }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-else class="text-center py-6 glass-card border-dashed">
                        <p class="text-white/30 text-sm">В звездном меню нет баффов.</p>
                    </div>
                </div>


            </div>

            <!-- Resources Tab -->
            <div v-show="activeTab === 'resources'">
                <!-- Storage limit info -->
                <div v-if="resourceLimit" class="glass-card p-4 mb-6 flex justify-between items-center">
                    <div>
                        <h3 class="text-sm font-semibold text-white/80">Вместимость склада</h3>
                        <p class="text-xs text-white/40 mt-0.5">Вместимость склада распространяется на каждый ресурс отдельно.</p>
                    </div>
                    <div class="px-4 py-2 bg-emerald-500/10 border border-emerald-500/20 rounded-xl">
                        <span class="text-lg font-bold text-emerald-400">{{ formatNumber(resourceLimit) }}</span>
                    </div>
                </div>

                <!-- Resources Grid -->
                <div v-if="parsedResources.length > 0" class="space-y-6">
                    <!-- Basic Resources (WarehouseTab1) -->
                    <div v-if="basicResources.length > 0">
                        <h4 class="text-xs font-semibold text-white/40 uppercase tracking-wider mb-3 flex items-center gap-2">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                            {{ translations['WarehouseTab1'] || 'Basic Resources (Базовые)' }}
                        </h4>
                        <div class="grid gap-2" style="grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));">
                            <div v-for="r in basicResources" :key="r.name" class="glass-card p-2 flex items-center gap-2 hover:border-white/20 hover:scale-[1.02] transition-all duration-300 w-full">
                                <div class="w-8 h-8 rounded-lg bg-white/5 flex items-center justify-center flex-shrink-0">
                                    <img v-if="getResourceIcon(r.name)" :src="getResourceIcon(r.name)" :alt="r.name" class="w-6 h-6 object-contain" @error="handleIconError($event, r.name)">
                                    <span v-else class="text-sm">{{ getResourceEmoji(r.name) }}</span>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-[10px] font-medium text-white/50 truncate" :title="r.name">{{ formatResourceName(r.name) }}</p>
                                    <p class="text-xs font-bold text-white leading-none mt-0.5">{{ formatNumber(r.amount) }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Improved Resources (WarehouseTab2) -->
                    <div v-if="improvedResources.length > 0">
                        <h4 class="text-xs font-semibold text-white/40 uppercase tracking-wider mb-3 flex items-center gap-2">
                            <span class="w-1.5 h-1.5 rounded-full bg-blue-400"></span>
                            {{ translations['WarehouseTab2'] || 'Improved Resources (Улучшенные)' }}
                        </h4>
                        <div class="grid gap-2" style="grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));">
                            <div v-for="r in improvedResources" :key="r.name" class="glass-card p-2 flex items-center gap-2 hover:border-white/20 hover:scale-[1.02] transition-all duration-300 w-full">
                                <div class="w-8 h-8 rounded-lg bg-white/5 flex items-center justify-center flex-shrink-0">
                                    <img v-if="getResourceIcon(r.name)" :src="getResourceIcon(r.name)" :alt="r.name" class="w-6 h-6 object-contain" @error="handleIconError($event, r.name)">
                                    <span v-else class="text-sm">{{ getResourceEmoji(r.name) }}</span>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-[10px] font-medium text-white/50 truncate" :title="r.name">{{ formatResourceName(r.name) }}</p>
                                    <p class="text-xs font-bold text-white leading-none mt-0.5">{{ formatNumber(r.amount) }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Advanced Resources (WarehouseTab3) -->
                    <div v-if="advancedResources.length > 0">
                        <h4 class="text-xs font-semibold text-white/40 uppercase tracking-wider mb-3 flex items-center gap-2">
                            <span class="w-1.5 h-1.5 rounded-full bg-amber-400"></span>
                            {{ translations['WarehouseTab3'] || 'Advanced Resources (Усовершенствованные)' }}
                        </h4>
                        <div class="grid gap-2" style="grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));">
                            <div v-for="r in advancedResources" :key="r.name" class="glass-card p-2 flex items-center gap-2 hover:border-white/20 hover:scale-[1.02] transition-all duration-300 w-full">
                                <div class="w-8 h-8 rounded-lg bg-white/5 flex items-center justify-center flex-shrink-0">
                                    <img v-if="getResourceIcon(r.name)" :src="getResourceIcon(r.name)" :alt="r.name" class="w-6 h-6 object-contain" @error="handleIconError($event, r.name)">
                                    <span v-else class="text-sm">{{ getResourceEmoji(r.name) }}</span>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-[10px] font-medium text-white/50 truncate" :title="r.name">{{ formatResourceName(r.name) }}</p>
                                    <p class="text-xs font-bold text-white leading-none mt-0.5">{{ formatNumber(r.amount) }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Master Resources (WarehouseTab4) -->
                    <div v-if="masterResources.length > 0">
                        <h4 class="text-xs font-semibold text-white/40 uppercase tracking-wider mb-3 flex items-center gap-2">
                            <span class="w-1.5 h-1.5 rounded-full bg-orange-400"></span>
                            {{ translations['WarehouseTab4'] || 'Master Resources (Искусные)' }}
                        </h4>
                        <div class="grid gap-2" style="grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));">
                            <div v-for="r in masterResources" :key="r.name" class="glass-card p-2 flex items-center gap-2 hover:border-white/20 hover:scale-[1.02] transition-all duration-300 w-full">
                                <div class="w-8 h-8 rounded-lg bg-white/5 flex items-center justify-center flex-shrink-0">
                                    <img v-if="getResourceIcon(r.name)" :src="getResourceIcon(r.name)" :alt="r.name" class="w-6 h-6 object-contain" @error="handleIconError($event, r.name)">
                                    <span v-else class="text-sm">{{ getResourceEmoji(r.name) }}</span>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-[10px] font-medium text-white/50 truncate" :title="r.name">{{ formatResourceName(r.name) }}</p>
                                    <p class="text-xs font-bold text-white leading-none mt-0.5">{{ formatNumber(r.amount) }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Elite Resources (WarehouseTab8) -->
                    <div v-if="eliteResources.length > 0">
                        <h4 class="text-xs font-semibold text-white/40 uppercase tracking-wider mb-3 flex items-center gap-2">
                            <span class="w-1.5 h-1.5 rounded-full bg-purple-400"></span>
                            {{ translations['WarehouseTab8'] || 'Elite Resources (Элита)' }}
                        </h4>
                        <div class="grid gap-2" style="grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));">
                            <div v-for="r in eliteResources" :key="r.name" class="glass-card p-2 flex items-center gap-2 hover:border-white/20 hover:scale-[1.02] transition-all duration-300 w-full">
                                <div class="w-8 h-8 rounded-lg bg-white/5 flex items-center justify-center flex-shrink-0">
                                    <img v-if="getResourceIcon(r.name)" :src="getResourceIcon(r.name)" :alt="r.name" class="w-6 h-6 object-contain" @error="handleIconError($event, r.name)">
                                    <span v-else class="text-sm">{{ getResourceEmoji(r.name) }}</span>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-[10px] font-medium text-white/50 truncate" :title="r.name">{{ formatResourceName(r.name) }}</p>
                                    <p class="text-xs font-bold text-white leading-none mt-0.5">{{ formatNumber(r.amount) }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Event Resources (WarehouseTab6) -->
                    <div v-if="eventResources.length > 0">
                        <h4 class="text-xs font-semibold text-white/40 uppercase tracking-wider mb-3 flex items-center gap-2">
                            <span class="w-1.5 h-1.5 rounded-full bg-rose-400"></span>
                            {{ translations['WarehouseTab6'] || 'Event Resources (Событие)' }}
                        </h4>
                        <div class="grid gap-2" style="grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));">
                            <div v-for="r in eventResources" :key="r.name" class="glass-card p-2 flex items-center gap-2 hover:border-white/20 hover:scale-[1.02] transition-all duration-300 w-full">
                                <div class="w-8 h-8 rounded-lg bg-white/5 flex items-center justify-center flex-shrink-0">
                                    <img v-if="getResourceIcon(r.name)" :src="getResourceIcon(r.name)" :alt="r.name" class="w-6 h-6 object-contain" @error="handleIconError($event, r.name)">
                                    <span v-else class="text-sm">{{ getResourceEmoji(r.name) }}</span>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-[10px] font-medium text-white/50 truncate" :title="r.name">{{ formatResourceName(r.name) }}</p>
                                    <p class="text-xs font-bold text-white leading-none mt-0.5">{{ formatNumber(r.amount) }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Collections (WarehouseTab7) -->
                    <div v-if="collectibleResources.length > 0">
                        <h4 class="text-xs font-semibold text-white/40 uppercase tracking-wider mb-3 flex items-center gap-2">
                            <span class="w-1.5 h-1.5 rounded-full bg-indigo-400"></span>
                            {{ translations['WarehouseTab7'] || 'Collections (Коллекции)' }}
                        </h4>
                        <div class="grid gap-2" style="grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));">
                            <div v-for="r in collectibleResources" :key="r.name" class="glass-card p-2 flex items-center gap-2 hover:border-white/20 hover:scale-[1.02] transition-all duration-300 w-full">
                                <div class="w-8 h-8 rounded-lg bg-white/5 flex items-center justify-center flex-shrink-0">
                                    <img v-if="getResourceIcon(r.name)" :src="getResourceIcon(r.name)" :alt="r.name" class="w-6 h-6 object-contain" @error="handleIconError($event, r.name)">
                                    <span v-else class="text-sm">{{ getResourceEmoji(r.name) }}</span>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-[10px] font-medium text-white/50 truncate" :title="r.name">{{ formatResourceName(r.name) }}</p>
                                    <p class="text-xs font-bold text-white leading-none mt-0.5">{{ formatNumber(r.amount) }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Military (WarehouseTab5) -->
                    <div v-if="militaryResources.length > 0">
                        <h4 class="text-xs font-semibold text-white/40 uppercase tracking-wider mb-3 flex items-center gap-2">
                            <span class="w-1.5 h-1.5 rounded-full bg-red-500"></span>
                            {{ translations['WarehouseTab5'] || 'Military (Войска)' }}
                        </h4>
                        <div class="grid gap-2" style="grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));">
                            <div v-for="r in militaryResources" :key="r.name" class="glass-card p-2 flex items-center gap-2 hover:border-white/20 hover:scale-[1.02] transition-all duration-300 w-full">
                                <div class="w-8 h-8 rounded-lg bg-white/5 flex items-center justify-center flex-shrink-0">
                                    <img v-if="getResourceIcon(r.name)" :src="getResourceIcon(r.name)" :alt="r.name" class="w-6 h-6 object-contain" @error="handleIconError($event, r.name)">
                                    <span v-else class="text-sm">{{ getResourceEmoji(r.name) }}</span>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-[10px] font-medium text-white/50 truncate" :title="r.name">{{ formatResourceName(r.name) }}</p>
                                    <p class="text-xs font-bold text-white leading-none mt-0.5">{{ formatNumber(r.amount) }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Other Resources -->
                    <div v-if="otherResources.length > 0">
                        <h4 class="text-xs font-semibold text-white/40 uppercase tracking-wider mb-3 flex items-center gap-2">
                            <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span>
                            Other (Другие)
                        </h4>
                        <div class="grid gap-2" style="grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));">
                            <div v-for="r in otherResources" :key="r.name" class="glass-card p-2 flex items-center gap-2 hover:border-white/20 hover:scale-[1.02] transition-all duration-300 w-full">
                                <div class="w-8 h-8 rounded-lg bg-white/5 flex items-center justify-center flex-shrink-0">
                                    <img v-if="getResourceIcon(r.name)" :src="getResourceIcon(r.name)" :alt="r.name" class="w-6 h-6 object-contain" @error="handleIconError($event, r.name)">
                                    <span v-else class="text-sm">{{ getResourceEmoji(r.name) }}</span>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-[10px] font-medium text-white/50 truncate" :title="r.name">{{ formatResourceName(r.name) }}</p>
                                    <p class="text-xs font-bold text-white leading-none mt-0.5">{{ formatNumber(r.amount) }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div v-else class="text-center py-12">
                    <p class="text-white/30 text-sm">No resources found.</p>
                </div>
            </div>

            <!-- Friends Tab -->
            <div v-show="activeTab === 'friends'">
                <div v-if="parsedFriends.length > 0" class="space-y-2">
                    <div v-for="f in parsedFriends" :key="f.username" class="flex items-center gap-3 p-3 rounded-xl bg-white/[0.02] hover:bg-white/5 border border-transparent hover:border-white/5 transition-all duration-300">
                        <div class="w-10 h-10 rounded-xl overflow-hidden bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center flex-shrink-0">
                            <img v-if="getFriendAvatar(f)" :src="getFriendAvatar(f)" :alt="f.username" class="w-full h-full object-cover" @error="$event.target.style.display='none'">
                            <span v-else class="text-sm font-bold text-white">{{ (f.username || '?').substring(0, 2).toUpperCase() }}</span>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-white/80 truncate">{{ f.username || f.nickname || 'Unknown' }}</p>
                            <p class="text-[10px] text-white/30">Level {{ f.level || f.playerLevel || '?' }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="w-2.5 h-2.5 rounded-full" :class="f.onlineStatus ? 'bg-emerald-500 shadow-lg shadow-emerald-500/50' : 'bg-gray-500'"></div>
                            <span class="text-[10px] text-white/30">{{ f.onlineStatus ? 'Online' : 'Offline' }}</span>
                        </div>
                    </div>
                </div>
                <div v-else class="text-center py-12">
                    <p class="text-white/30 text-sm">No friends found.</p>
                </div>
            </div>

            <!-- Session Tab -->
            <div v-show="activeTab === 'session'">
                <div class="max-w-2xl mx-auto">
                    <div class="flex items-center gap-3 mb-5">
                        <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center">
                            <svg class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 0 1 3 3m3 0a6 6 0 0 1-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1 1 21.75 8.25Z" />
                            </svg>
                        </div>
                        <h3 class="text-lg font-semibold text-white">Ручное управление сессией</h3>
                    </div>

                    <div class="glass-card p-5 mb-6 border-amber-500/20 bg-amber-500/[0.02]">
                        <div class="flex gap-3">
                            <span class="text-xl">⚠️</span>
                            <div>
                                <h4 class="text-xs font-bold text-amber-400 uppercase tracking-wider mb-1">Решение проблемы с капчей</h4>
                                <p class="text-xs text-white/60 leading-relaxed">
                                    Если Ubisoft требует ввести капчу при синхронизации, вы можете войти в игру в обычном браузере, скопировать токены сессии и вставить их вручную. Менеджер будет использовать эти данные напрямую без выполнения автоматического входа.
                                </p>
                            </div>
                        </div>
                    </div>

                    <form @submit.prevent="saveSession" class="space-y-4">
                        <div>
                            <label class="block text-xs font-medium text-white/40 mb-2 uppercase tracking-wider">DSO Auth Token</label>
                            <input type="text" required v-model="sessionForm.dso_auth_token" placeholder="Вставьте токен (например, IaMl0gKY9uxg...)" class="glass-input w-full font-mono text-sm">
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-white/40 mb-2 uppercase tracking-wider">DSO Auth User ID</label>
                                <input type="text" required v-model="sessionForm.dso_auth_user" placeholder="Числовой ID (например, 2425303)" class="glass-input w-full font-mono text-sm">
                            </div>

                            <div>
                                <label class="block text-xs font-medium text-white/40 mb-2 uppercase tracking-wider">BB URL (Сервер авторизации)</label>
                                <select required v-model="sessionForm.bb_url" class="glass-select w-full text-sm">
                                    <option value="https://r02-ls.thesettlersonline.ru/">RU (https://r02-ls.thesettlersonline.ru/)</option>
                                    <option value="https://r01-ls.thesettlersonline.com/">EN/US (https://r01-ls.thesettlersonline.com/)</option>
                                    <option value="https://r01-ls.diesiedleronline.de/">DE (https://r01-ls.diesiedleronline.de/)</option>
                                    <option value="https://r01-ls.thesettlersonline.fr/">FR (https://r01-ls.thesettlersonline.fr/)</option>
                                    <option value="https://r01-ls.thesettlersonline.pl/">PL (https://r01-ls.thesettlersonline.pl/)</option>
                                </select>
                            </div>
                        </div>

                        <div class="pt-2">
                            <button type="submit" :disabled="sessionSubmitting" class="btn-primary px-6 py-2.5 flex items-center gap-2 text-sm font-semibold">
                                <svg v-if="sessionSubmitting" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182" />
                                </svg>
                                Сохранить и обновить сессию
                            </button>
                        </div>
                    </form>

                    <!-- Instruction section -->
                    <div class="mt-8 border-t border-white/5 pt-6">
                        <h4 class="text-sm font-semibold text-white mb-3">Как скопировать эти данные в браузере?</h4>
                        <ol class="list-decimal pl-5 text-xs text-white/50 space-y-2 leading-relaxed">
                            <li>Перейдите на страницу игры, где идет загрузка самой карты (например, <code class="bg-white/5 px-1 py-0.5 rounded text-white/70">thesettlersonline.ru/ru/play</code>).</li>
                            <li>Откройте консоль разработчика (нажмите клавишу <code class="bg-white/5 px-1 py-0.5 rounded text-white/70">F12</code> и перейдите на вкладку <strong>Console</strong>).</li>
                            <li>Скопируйте и вставьте следующий JS-код, затем нажмите <code class="bg-white/5 px-1 py-0.5 rounded text-white/70">Enter</code>:
                                <pre class="bg-dark-950 border border-white/10 p-3 rounded-lg mt-2 text-white/90 font-mono overflow-x-auto text-[10px] select-all">const match = document.body.innerHTML.match(/(dsoAuthToken=[^&quot;]+)/);
if (match) {
    const params = new URLSearchParams(match[1]);
    console.log(&quot;DSO Auth Token:&quot;, params.get(&quot;dsoAuthToken&quot;));
    console.log(&quot;DSO Auth User ID:&quot;, params.get(&quot;dsoAuthUser&quot;));
    console.log(&quot;BB URL:&quot;, params.get(&quot;bb&quot;));
} else {
    console.log(&quot;Не удалось найти параметры. Убедитесь, что вы на странице загрузки игры (play)!&quot;);
}</pre>
                            </li>
                            <li>Скопируйте полученные значения в поля выше и нажмите Сохранить.</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div v-else class="text-center py-12">
        <p class="text-white/30 text-sm">Account not found.</p>
    </div>
</template>

<script>
import { ref, computed, onMounted, h, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import axios from 'axios';
import { showToast } from '../toast';

// Icon components
const BuildingIcon = { render() { return h('svg', { class: 'w-4 h-4', fill: 'none', viewBox: '0 0 24 24', 'stroke-width': '1.5', stroke: 'currentColor' }, [h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', d: 'M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Z' })]); } };
const SpecialistIcon = { render() { return h('svg', { class: 'w-4 h-4', fill: 'none', viewBox: '0 0 24 24', 'stroke-width': '1.5', stroke: 'currentColor' }, [h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', d: 'M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z' })]); } };
const BuffIcon = { render() { return h('svg', { class: 'w-4 h-4', fill: 'none', viewBox: '0 0 24 24', 'stroke-width': '1.5', stroke: 'currentColor' }, [h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', d: 'M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.455 2.456L21.75 6l-1.036.259a3.375 3.375 0 0 0-2.455 2.456ZM16.894 20.567 16.5 21.75l-.394-1.183a2.25 2.25 0 0 0-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 0 0 1.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 0 0 1.423 1.423l1.183.394-1.183.394a2.25 2.25 0 0 0-1.423 1.423Z' })]); } };
const ResourceIcon = { render() { return h('svg', { class: 'w-4 h-4', fill: 'none', viewBox: '0 0 24 24', 'stroke-width': '1.5', stroke: 'currentColor' }, [h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', d: 'M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75m16.5 0c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125' })]); } };
const FriendIcon = { render() { return h('svg', { class: 'w-4 h-4', fill: 'none', viewBox: '0 0 24 24', 'stroke-width': '1.5', stroke: 'currentColor' }, [h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', d: 'M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z' })]); } };
const SettingsIcon = { render() { return h('svg', { class: 'w-4 h-4', fill: 'none', viewBox: '0 0 24 24', 'stroke-width': '1.5', stroke: 'currentColor' }, [h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', d: 'M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.43l-1.003.828c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.43l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 0 1 0-.255c.007-.378-.138-.75-.43-.991l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128c.332-.183.582-.495.645-.869l.214-1.28Z' }), h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', d: 'M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z' })]); } };

// Building name to icon URL mapping (TSO CDN)
const buildingIconMap = {
    'Sawmill': 'sawmill.png',
    'Woodcutter': 'woodcutter.png',
    'Lumberjack': 'lumberjack.png',
    'CoalMine': 'coalmine.png',
    'IronMine': 'ironmine.png',
    'GoldMine': 'goldmine.png',
    'Quarry': 'quarry.png',
    'Farm': 'farm.png',
    'Windmill': 'windmill.png',
    'Bakery': 'bakery.png',
    'Brewery': 'brewery.png',
    'Tavern': 'tavern.png',
    'Market': 'market.png',
    'Storehouse': 'storehouse.png',
    'Barracks': 'barracks.png',
    'Smithy': 'smithy.png',
    'Foundry': 'foundry.png',
    'Arsenal': ' arsenal.png',
    'GuildHouse': 'guildhouse.png',
    ' residence': 'residence.png',
    'Residence': 'residence.png',
};

// Resource name to icon URL mapping
const resourceIconMap = {
    'Wood': 'wood.png',
    'Coal': 'charcoal.png',
    'Iron': 'iron.png',
    'Gold': 'gold.png',
    'Stone': 'stone.png',
    'Granite': 'granite.png',
    'Wheat': 'wheat.png',
    'Flour': 'flour.png',
    'Bread': 'bread.png',
    'Beer': 'beer.png',
    'Coke': 'coke.png',
    'Steel': 'steel.png',
    'Plank': 'plank.png',
    'Beam': 'beam.png',
    'MetalFrame': 'metals.png',
    'CutStone': 'cutstone.png',
    'Sword': 'sword.png',
    'Cannon': 'cannon.png',
    'Musket': 'musket.png',
};

// Resource categories
const basicResourceNames = ['Wood', 'Coal', 'Iron', 'Gold', 'Stone', 'Granite', 'Wheat', 'Flour', 'Bread', 'Beer'];
const improvedResourceNames = ['Coke', 'Steel', 'Plank', 'Beam', 'MetalFrame', 'CutStone'];
const advancedResourceNames = ['Sword', 'Cannon', 'Musket'];
const categorizedNames = [...basicResourceNames, ...improvedResourceNames, ...advancedResourceNames];

// Building stop-words (non-stoppable buildings to hide)
const stopWords = ['Bandit', 'DestroyableMountain', 'Mountain', 'Ruins', 'Camp', 'Loot'];

const LEVEL_XP_TABLE = {
    1: 0, 2: 100, 3: 200, 4: 300, 5: 400, 6: 500, 7: 600, 8: 700, 9: 800, 10: 900,
    11: 1000, 12: 1100, 13: 1200, 14: 1300, 15: 1400, 16: 1500, 17: 1700, 18: 2100, 19: 3000, 20: 4000,
    21: 5100, 22: 6300, 23: 7400, 24: 8600, 25: 9900, 26: 11300, 27: 12800, 28: 14400, 29: 16100, 30: 17900,
    31: 19900, 32: 28000, 33: 39000, 34: 51000, 35: 66000, 36: 88000, 37: 118000, 38: 158000, 39: 236000, 40: 314000,
    41: 412000, 42: 535000, 43: 690000, 44: 885000, 45: 1131000, 46: 1441000, 47: 1831000, 48: 2321000, 49: 2941000, 50: 3746000,
    51: 4697000, 52: 5889000, 53: 7383000, 54: 9257000, 55: 11610000, 56: 14550000, 57: 18240000, 58: 22870000, 59: 28680000, 60: 35960000,
    61: 45080000, 62: 56520000, 63: 70870000, 64: 88850000, 65: 111400000, 66: 139700000, 67: 175100000, 68: 219600000, 69: 275300000, 70: 345100000,
    71: 432700000, 72: 542500000, 73: 680200000, 74: 852800000, 75: 1069000000, 76: 1200000000, 77: 1360000000, 78: 1550000000, 79: 1780000000, 80: 2050000000
};

export default {
    name: 'AccountDetail',
    components: { BuildingIcon, SpecialistIcon, BuffIcon, ResourceIcon, FriendIcon, SettingsIcon },
    setup() {
        const route = useRoute();
        const router = useRouter();
        const loading = ref(true);
        const account = ref(null);

        const statusClass = computed(() => {
            const colors = {
                online:  { gradient: 'from-emerald-500 to-teal-500', glow: '#10b981' },
                syncing: { gradient: 'from-amber-500 to-orange-500', glow: '#f59e0b' },
                error:   { gradient: 'from-red-500 to-rose-500', glow: '#ef4444' },
                offline: { gradient: 'from-gray-500 to-gray-600', glow: '#6b7280' }
            };
            return colors[account.value?.status] || colors.offline;
        });

        const actionLoading = ref(false);
        const avatarError = ref(false);
        const activeTab = ref('buildings');
        const buildingSearch = ref('');
        const buildingFilter = ref('All');
        const buildingModeFilter = ref('all');
        const specialistSearch = ref('');
        const specialistFilter = ref('All');
        const translations = ref({});

        const tabs = computed(() => [
            { id: 'buildings', label: 'Buildings', icon: BuildingIcon, count: parsedBuildings.value.length },
            { id: 'specialists', label: 'Specialists', icon: SpecialistIcon, count: parsedSpecialists.value.length },
            { id: 'buffs', label: 'Buffs', icon: BuffIcon, count: availableBuffs.value.length },
            { id: 'resources', label: 'Resources', icon: ResourceIcon, count: parsedResources.value.length },
            { id: 'friends', label: 'Friends', icon: FriendIcon, count: parsedFriends.value.length },
            { id: 'session', label: 'Сессия', icon: SettingsIcon, count: null },
        ]);

        const buildingCategories = ['All', 'Basic', 'Improved', 'Advanced', 'Elite', 'Decorations'];

        const zoneData = computed(() => {
            if (!account.value?.zone_data) return null;
            try {
                return typeof account.value.zone_data === 'string'
                    ? JSON.parse(account.value.zone_data)
                    : account.value.zone_data;
            } catch (e) {
                return null;
            }
        });

        const parsedBuildings = computed(() => zoneData.value?.buildings || []);
        const parsedSpecialists = computed(() => zoneData.value?.specialists || []);
        const RESOURCE_CATEGORIES = {
            'WarehouseTab1': [
                'Tree', 'Wood', 'Plank', 'Stone', 'Fish', 'Population', 'Token',
                'CrystalShard', 'Crystal', 'MapPart', 'GuildCoins', 'StarfallStarDust', 'StarfallStarShards'
            ],
            'WarehouseTab2': [
                'Coal', 'BronzeOre', 'Bronze', 'Tool', 'Tools', 'Water', 'Corn', 'Beer',
                'Flour', 'Bread', 'BronzeSword', 'Bow', 'SimplePaper', 'Nib', 'Manuscript',
                'AdventureTale', 'Copper', 'CopperOre', 'Grain', 'Brew'
            ],
            'WarehouseTab3': [
                'RealWood', 'RealPlank', 'IronOre', 'Iron', 'Steel', 'GoldOre', 'Gold',
                'Coin', 'Coins', 'Coinage', 'Marble', 'Meat', 'Sausage', 'IronSword', 'SteelSword',
                'Longbow', 'Pike', 'CompositeBow', 'ExpeditionCrossbow', 'BattleLance', 'Saber',
                'SpikedMace', 'Horse', 'Horses', 'IntermediatePaper', 'Letter', 'Tome'
            ],
            'WarehouseTab4': [
                'ExoticWood', 'ExoticPlank', 'TitaniumOre', 'Titanium', 'Salpeter',
                'Gunpowder', 'Granite', 'Grout', 'Wheel', 'Carriage', 'TitaniumSword',
                'Crossbow', 'Cannon', 'StarCoin', 'MagicBean', 'MagicBeanstalk',
                'AdvancedPaper', 'BookFitting', 'Codex', 'ValorPoint', 'Oil',
                'AdvancedTools', 'Oilseed', 'Seed', 'DamasceneSword'
            ],
            'WarehouseTab8': [
                'MahoganyWood', 'MahoganyPlank', 'PlatinumOre', 'Platinum', 'ObsidianOre',
                'BattleHorse', 'Wagon', 'Wool', 'Cloth', 'Saddlecloth', 'PlatinumSword',
                'Archebuse', 'Mortar'
            ],
            'WarehouseTab6': [
                'RedNose', 'EventResource', 'EMEventResource', 'HalloweenResource',
                'ChristmasResource', 'Balloons', 'StripedEggs', 'Candles', 'CakeDough',
                'ValentinesFlower', 'GuildFestToken', 'GuildFestCommendation', 'AdventureRelics'
            ],
            'WarehouseTab7': [
                'Collectibles', 'CollectibleBanner', 'CollectibleBronzeCauldron', 'CollectibleChristmasBells',
                'CollectibleChristmasCandy', 'CollectibleChristmasGingerbread', 'CollectibleClue', 'CollectibleEggpaint',
                'CollectibleFoodCart', 'CollectibleFurs', 'CollectibleFurs2', 'CollectibleFursTMC', 'CollectibleGrainSacks',
                'CollectibleHerbs', 'CollectibleKettle', 'CollectibleMagicStone', 'CollectibleObsidianShard',
                'CollectiblePlainEgg', 'CollectibleRobustTools', 'CollectibleSacredStone', 'CollectibleScarecrow',
                'CollectibleWickerBasket', 'CollectibleWineBarrel', 'AdventureRelics', 'AdventureTale'
            ],
            'WarehouseTab5': [
                'DefensePoint'
            ]
        };
        const CATEGORY_ORDER = ['WarehouseTab1', 'WarehouseTab2', 'WarehouseTab3', 'WarehouseTab4', 'WarehouseTab8', 'WarehouseTab6', 'WarehouseTab7', 'WarehouseTab5'];
        const getCategory = (name) => {
            if (!name) return 'Other';
            const normName = name.trim().toLowerCase();

            // Check explicit patterns
            if (normName.includes('balloon') || normName.includes('egg') || normName.includes('gift') || normName.includes('pumpkin') || normName.includes('present')) {
                return 'WarehouseTab6';
            }

            if (normName.startsWith('collectible') || normName.includes('collectible') || normName === 'adventuretales') {
                return 'WarehouseTab7';
            }

            for (const cat of CATEGORY_ORDER) {
                if (RESOURCE_CATEGORIES[cat].some(r => r.toLowerCase() === normName)) return cat;
            }
            return 'Other';
        };

        const parsedResources = computed(() => {
            const raw = zoneData.value?.resources || [];
            return raw.map(r => {
                const category = r.category && r.category.startsWith('WarehouseTab')
                    ? r.category
                    : getCategory(r.name || r.name_string || '');
                return {
                    ...r,
                    name: r.name || r.name_string || 'Unknown',
                    category,
                };
            });
        });
        const parsedFriends = computed(() => zoneData.value?.friends || []);
        const xp = computed(() => zoneData.value?.xp);
        const level = computed(() => {
            const currentXp = xp.value;
            if (currentXp === null || currentXp === undefined) {
                return zoneData.value?.level || 1;
            }
            let calculatedLevel = 1;
            for (let lvl = 1; lvl <= 80; lvl++) {
                if (LEVEL_XP_TABLE[lvl] !== undefined && currentXp >= LEVEL_XP_TABLE[lvl]) {
                    calculatedLevel = lvl;
                } else {
                    break;
                }
            }
            return calculatedLevel;
        });
        const pvpLevel = computed(() => zoneData.value?.pvpLevel);
        const generalsAmount = computed(() => parsedSpecialists.value.filter(s => getSpecialistCategory(s.type) === 'General').length);
        const explorersAmount = computed(() => parsedSpecialists.value.filter(s => getSpecialistCategory(s.type) === 'Explorer').length);
        const geologistsAmount = computed(() => parsedSpecialists.value.filter(s => getSpecialistCategory(s.type) === 'Geologist').length);
        const currentMaximumBuildingsCountAll = computed(() => zoneData.value?.currentMaximumBuildingsCountAll);
        const availableBuffs = computed(() => zoneData.value?.availableBuffs || []);
        const resourceLimit = computed(() => zoneData.value?.resourceLimit);
        const playerNickname = computed(() => zoneData.value?.playerNickname || account.value?.nickname || account.value?.username);

        const getAvatarById = (avatarId) => {
            if (!avatarId) return null;
            const idNum = parseInt(avatarId);
            if (idNum >= 1 && idNum <= 60) {
                return `/images/avatars/${idNum}.png`;
            }
            return `https://settlersonlinewiki.eu/images/avatars/avatar_${avatarId}.png`;
        };

        const avatarUrl = computed(() => {
            if (avatarError.value) return null;
            return getAvatarById(zoneData.value?.avatarId);
        });

        const visitors = computed(() => zoneData.value?.visitors || []);
        const syncing = ref(false);

        const syncAccount = async () => {
            if (syncing.value) return;
            syncing.value = true;
            try {
                const res = await axios.post(`/api/accounts/${account.value.id}/sync`);
                if (res.data.success) {
                    showToast('Account synced successfully!');
                    if (res.data.account) {
                        account.value = res.data.account;
                    }
                } else {
                    showToast(res.data.message || 'Sync failed.', 'error');
                }
            } catch (e) {
                if (e.response?.data?.account) {
                    account.value = e.response.data.account;
                }
                showToast(e.response?.data?.message || 'Sync request failed.', 'error');
            } finally {
                syncing.value = false;
            }
        };

        const avatarLetters = computed(() => {
            const name = playerNickname.value || '?';
            return name.substring(0, 2).toUpperCase();
        });

        const xpNextTarget = computed(() => {
            if (!level.value) return 0;
            const lvl = level.value;
            if (lvl >= 80) return LEVEL_XP_TABLE[80];
            return LEVEL_XP_TABLE[lvl + 1] !== undefined ? LEVEL_XP_TABLE[lvl + 1] : LEVEL_XP_TABLE[lvl];
        });

        const xpProgress = computed(() => {
            if (xp.value === null || xp.value === undefined || !level.value) return 0;
            const lvl = level.value;
            if (lvl >= 80) return 100;
            const currentXp = xp.value;
            let startXp = LEVEL_XP_TABLE[lvl] !== undefined ? LEVEL_XP_TABLE[lvl] : 0;
            let endXp = xpNextTarget.value;
            if (currentXp < startXp) startXp = Math.max(0, currentXp - 5000);
            if (currentXp > endXp) endXp = currentXp + 10000;
            const range = endXp - startXp;
            if (range <= 0) return 100;
            return Math.min(Math.max(((currentXp - startXp) / range) * 100, 0), 100);
        });

        const totalResources = computed(() => {
            return parsedResources.value.reduce((sum, r) => sum + (r.amount || 0), 0);
        });

        const storagePercentage = computed(() => {
            if (!resourceLimit.value) return 0;
            return (totalResources.value / resourceLimit.value) * 100;
        });

        const basicResources = computed(() => parsedResources.value.filter(r => r.category === 'WarehouseTab1'));
        const improvedResources = computed(() => parsedResources.value.filter(r => r.category === 'WarehouseTab2'));
        const advancedResources = computed(() => parsedResources.value.filter(r => r.category === 'WarehouseTab3'));
        const masterResources = computed(() => parsedResources.value.filter(r => r.category === 'WarehouseTab4'));
        const eventResources = computed(() => parsedResources.value.filter(r => r.category === 'WarehouseTab6'));
        const collectibleResources = computed(() => parsedResources.value.filter(r => r.category === 'WarehouseTab7'));
        const eliteResources = computed(() => parsedResources.value.filter(r => r.category === 'WarehouseTab8'));
        const militaryResources = computed(() => parsedResources.value.filter(r => r.category === 'WarehouseTab5'));
        const otherResources = computed(() => parsedResources.value.filter(r => !r.category || r.category === 'Other'));

        const serverName = computed(() => zoneData.value?.gameWorldName || null);

        const filteredBuildings = computed(() => {
            let buildings = parsedBuildings.value;

            // Filter by buildingMode
            if (buildingModeFilter.value === '27') {
                buildings = buildings.filter(b => b.buildingMode === 27 || b.isBought === true);
                const stopWordsLower = stopWords.map(w => w.toLowerCase());
                buildings = buildings.filter(b => {
                    const name = (b.buildingName_string || b.buildingName || '').toLowerCase();
                    return !stopWordsLower.some(w => name.includes(w));
                });
            } else if (buildingModeFilter.value === '28') {
                buildings = buildings.filter(b => b.buildingMode === 28 && b.isBought !== true);
            }

            if (buildingSearch.value) {
                const search = buildingSearch.value.toLowerCase();
                buildings = buildings.filter(b => {
                    const name = (b.buildingName_string || b.buildingName || '').toLowerCase();
                    return name.includes(search);
                });
            }

            // Map building name to categories (CL1 to CL5)
            const BUILDING_MAP = {
                // Basic
                'mayorhouse': 'Basic', 'storehouse': 'Basic', 'woodcutter': 'Basic', 'forester': 'Basic',
                'sawmill': 'Basic', 'stonecutter': 'Basic', 'stonemason': 'Basic', 'fishfarm': 'Basic',
                'fisher': 'Basic', 'farm': 'Basic', 'well': 'Basic', 'provisionhouse': 'Basic',
                'tavern': 'Basic', 'barracks': 'Basic',

                // Improved
                'cokingplant': 'Improved', 'copperore': 'Improved', 'coppermine': 'Improved',
                'bronzesmelter': 'Improved', 'bronzeweaponsmith': 'Improved', 'toolmaker': 'Improved',
                'improvedstorehouse': 'Improved', 'improvedfarm': 'Improved', 'improvedwell': 'Improved',
                'silo': 'Improved', 'improvedsilo': 'Improved', 'mill': 'Improved', 'bakery': 'Improved',
                'brewery': 'Improved',

                // Advanced
                'ironore': 'Advanced', 'ironmine': 'Advanced', 'ironsmelter': 'Advanced',
                'steelsmelter': 'Advanced', 'ironweaponsmith': 'Advanced', 'steelweaponsmith': 'Advanced',
                'stable': 'Advanced', 'bowmaker': 'Advanced', 'longbowmaker': 'Advanced',
                'hunter': 'Advanced', 'deerstalkerhut': 'Advanced', 'butcher': 'Advanced',
                'marblecutter': 'Advanced', 'marblemason': 'Advanced',

                // Elite
                'coalmine': 'Elite', 'goldore': 'Elite', 'goldmine': 'Elite', 'goldsmelter': 'Elite',
                'coinage': 'Elite', 'titaniummine': 'Elite', 'titaniumsmelter': 'Elite',
                'titaniumweaponsmith': 'Elite', 'crossbowmaker': 'Elite', 'gunpowderforge': 'Elite',
                'cannonforge': 'Elite', 'eliteresidence': 'Elite', 'spaciousstorehouse': 'Elite',
                'floatingstorehouse': 'Elite', 'granite_pit': 'Elite', 'grout_factory': 'Elite',

                // Decorations / Specials (CL5)
                'residence': 'Decorations', 'nobleresidence': 'Decorations', 'floatingresidence': 'Decorations',
                'magnificentresidence': 'Decorations', 'witchtower': 'Decorations', 'darkcastle': 'Decorations',
                'bonechurch': 'Decorations', 'frozenmanor': 'Decorations', 'watercastle': 'Decorations',
                'goldtower': 'Decorations', 'recyclingmanufactory': 'Decorations', 'university2020': 'Decorations',
                'village_school01': 'Decorations'
            };

            const getBuildingCategory = (b) => {
                const name = (b.buildingName_string || b.buildingName || '').toLowerCase();

                // If it is explicitly in map
                for (const key in BUILDING_MAP) {
                    if (name.includes(key)) return BUILDING_MAP[key];
                }

                // Fallback rules
                if (name.includes('residence') || name.includes('manor') || name.includes('castle') || name.includes('palace') || name.includes('deco') || name.includes('monument') || name.includes('statue') || name.includes('flowerbed') || name.includes('bench') || name.includes('tree') || name.includes('lantern') || name.includes('signpost') || name.includes('gate') || name.includes('tower') || name.includes('tent') || name.includes('camp') || name.includes('trophy') || name.includes('garden') || name.includes('lake') || name.includes('well_0') || name.includes('excelsior') || name.includes('garrison') || name.includes('mountain') || name.includes('deposit') || name.includes('rubble') || name.includes('ruin') || name.includes('rock') || name.includes('peak')) {
                    return 'Decorations';
                }
                if (name.includes('titanium') || name.includes('platinum') || name.includes('gold') || name.includes('gunpowder') || name.includes('cannon') || name.includes('crossbow') || name.includes('salpeter') || name.includes('granite') || name.includes('elite')) {
                    return 'Elite';
                }
                if (name.includes('iron') || name.includes('steel') || name.includes('stable') || name.includes('bow') || name.includes('hunter') || name.includes('deerstalker') || name.includes('butcher') || name.includes('marble')) {
                    return 'Advanced';
                }
                if (name.includes('coke') || name.includes('copper') || name.includes('bronze') || name.includes('tool') || name.includes('improved') || name.includes('silo') || name.includes('mill') || name.includes('bakery') || name.includes('brewery')) {
                    return 'Improved';
                }
                return 'Basic';
            };

            if (buildingFilter.value !== 'All') {
                buildings = buildings.filter(b => getBuildingCategory(b) === buildingFilter.value);
            }

            return buildings;
        });

        const getBuffName = (buffId) => {
            if (!buffId) return 'Неизвестный бафф';
            const buffIdMap = {
                1: 'ProductivityBuffLvl1',
                2: 'ProductivityBuffLvl3',
                3: 'ProductivityBuffLvl2',
                4: 'GoldMineBuff',
                6: 'ProductivityBuffLvl3',
                7: 'ProductivityBuffLvl4',
                8: 'ProductivityBuffSpecialist2',
                9: 'ProductivityBuffSpecialist1',
                12: 'ProductivityBuffLvl5',
            };
            const mapped = buffIdMap[buffId];
            if (mapped && translations.value[mapped]) {
                return translations.value[mapped];
            }
            if (mapped) return mapped;
            return `Бафф #${buffId}`;
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
            const name = b.buffName_string || b.name || '';
            if (!name) return null;

            // Clean name: lowercase and strip spaces/special chars
            let clean = name.trim().toLowerCase().replace(/\s+/g, '_').replace(/['"]/g, '');

            // Map common buff names to their file names on disk
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

            if (buffMap[clean]) {
                clean = buffMap[clean];
            }

            return `/images/resources/${clean}.png`;
        };

        const handleBuffIconError = (event, b) => {
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

            if (buffMap[clean]) {
                clean = buffMap[clean];
            }

            if (img.src.includes('/images/resources/') && img.src.endsWith('.png')) {
                // Step 1: PNG in resources failed, try WebP in buildings (from TSO Wiki)
                img.src = `/images/buildings/${clean}.webp`;
            } else if (img.src.includes('/images/buildings/') && img.src.endsWith('.webp')) {
                // Step 2: WebP failed too, try PNG in buildings
                img.src = `/images/buildings/${clean}.png`;
            } else {
                // Step 3: Hide image and show sibling emoji/SVG
                img.style.display = 'none';
                const sibling = img.nextElementSibling;
                if (sibling) sibling.style.display = 'block';
            }
        };

        const getBuildingName = (b) => {
            const name = b.buildingName_string || b.buildingName || 'Building';
            return name.replace(/(?<!^)(?=[A-Z])/g, ' ').replace(/_/g, ' ');
        };

        const getBuildingIcon = (b) => {
            const name = b.buildingName_string || b.buildingName || '';
            if (!name) return null;

            // Clean name to lowercase and strip level info
            let clean = name.replace(/_lvl_\d+/i, '').replace(/decoration_/g, '').trim().toLowerCase();

            // Map game engine names to their actual image names from tsowiki
            const nameMapping = {
                'realwoodsawmill': 'sawmill_real_planks',
                'exoticwoodsawmill': 'sawmill_exotic_planks',
                'mahoganysawmill': 'mahogany_sawmill',
                'exoticwoodtreeschool': 'exoticwood_treeschool',
                'stonecutter': 'stonemason',
                'marblecutter': 'marblemason',
                'granitecutter': 'granitemason'
            };

            if (nameMapping[clean]) {
                clean = nameMapping[clean];
            }

            return `/images/buildings/${clean}.webp`;
        };

        const handleBuildingIconError = (event, b) => {
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

            if (nameMapping[clean]) {
                clean = nameMapping[clean];
            }

            if (img.src.includes('/images/buildings/') && img.src.endsWith('.webp')) {
                // Step 1: WebP failed in buildings, try PNG in buildings
                img.src = `/images/buildings/${clean}.png`;
            } else if (img.src.includes('/images/buildings/') && img.src.endsWith('.png')) {
                // Step 2: PNG failed in buildings, try PNG in resources
                img.src = `/images/resources/${clean}.png`;
            } else {
                // Step 3: All failed, hide
                img.style.display = 'none';
            }
        };

        const isStoppable = (b) => {
            const mode = b.buildingMode;
            // A building can be stopped/started if its mode is active (20-27) or produces no resources / stopped (28)
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

        const isBuildingActive = (b) => {
            return b.isProductionActive === true || (b.buildingMode >= 20 && b.buildingMode <= 27);
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

        const getSpecialistCategory = (type) => {
            const rawName = SPECIALIST_TYPES[type];
            if (!rawName) return 'Other';
            const lower = rawName.toLowerCase();
            if (lower.includes('explorer') || lower.includes('scout')) return 'Explorer';
            if (lower.includes('geologist')) return 'Geologist';
            return 'General';
        };

        const getSpecialistTypeName = (type) => {
            const rawName = SPECIALIST_TYPES[type] || `Specialist #${type}`;

            // Check translation in lang.txt
            if (translations.value[rawName]) {
                return translations.value[rawName];
            }

            return rawName
                .replace(/([A-Z0-9])/g, ' $1')
                .replace(/^./, str => str.toUpperCase())
                .trim();
        };

        const getSpecialistType = (type) => {
            return getSpecialistTypeName(type);
        };

        const specialistIconErrors = ref(new Set());
        const handleSpecialistIconError = (event, type) => {
            specialistIconErrors.value.add(type);
        };
        const hasSpecialistIconError = (type) => {
            return specialistIconErrors.value.has(type);
        };
        const getSpecialistIcon = (type) => {
            return `/images/specialists/${type}.webp`;
        };

        const filteredSpecialists = computed(() => {
            let specs = parsedSpecialists.value;

            if (specialistFilter.value !== 'All') {
                specs = specs.filter(s => getSpecialistCategory(s.type) === specialistFilter.value);
            }

            if (specialistSearch.value) {
                const search = specialistSearch.value.toLowerCase();
                specs = specs.filter(s => {
                    const name = (s.name || '').toLowerCase();
                    const typeName = getSpecialistTypeName(s.type).toLowerCase();
                    return name.includes(search) || typeName.includes(search);
                });
            }

            return specs;
        });

        const getSpecialistCategoryCount = (category) => {
            if (category === 'All') return parsedSpecialists.value.length;
            return parsedSpecialists.value.filter(s => getSpecialistCategory(s.type) === category).length;
        };

        const getTaskName = (taskType, taskSubType, category) => {
            if (category === 'Explorer') {
                const subTypeKeys = {
                    1: 'FindAdventureShort',
                    2: 'FindAdventureMedium',
                    3: 'FindAdventureLong',
                    4: 'FindTreasureShort',
                    5: 'FindTreasureMedium',
                    6: 'FindTreasureLong',
                    7: 'FindTreasureEvenLonger',
                    8: 'FindTreasureTravellingErudite',
                    9: 'FindAdventureLootMapFragmentMailSubject',
                };
                const key = subTypeKeys[taskSubType];
                if (key && translations.value[key]) {
                    return translations.value[key];
                }
                const fallbackSubTypes = {
                    1: 'Поиск приключений (Короткий)',
                    2: 'Поиск приключений (Средний)',
                    3: 'Поиск приключений (Длинный)',
                    4: 'Поиск сокровищ (Короткий)',
                    5: 'Поиск сокровищ (Средний)',
                    6: 'Поиск сокровищ (Длинный)',
                    7: 'Поиск сокровищ (Очень длинный)',
                    8: 'Поиск сокровищ (Путешествие)',
                    9: 'Поиск приключений (Очень длинный)',
                };
                return fallbackSubTypes[taskSubType] || `Разведка #${taskSubType}`;
            }
            if (category === 'Geologist') {
                const subTypeKeys = {
                    1: 'FindDepositStone',
                    2: 'FindDepositBronze',
                    3: 'FindDepositMarble',
                    4: 'FindDepositIron',
                    5: 'FindDepositCoal',
                    6: 'FindDepositGold',
                    7: 'FindDepositGranite',
                    8: 'FindDepositSalpeter',
                    9: 'FindDepositAlloy',
                };
                const key = subTypeKeys[taskSubType];
                if (key && translations.value[key]) {
                    return translations.value[key];
                }
                const fallbackSubTypes = {
                    1: 'Поиск каменных залежей',
                    2: 'Поиск медных залежей',
                    3: 'Поиск мраморных залежей',
                    4: 'Поиск железных залежей',
                    5: 'Поиск угольных залежей',
                    6: 'Поиск золотых залежей',
                    7: 'Поиск гранитных залежей',
                    8: 'Поиск селитры',
                    9: 'Поиск титановой руды',
                };
                return fallbackSubTypes[taskSubType] || `Поиск залежей #${taskSubType}`;
            }
            return `Задача #${taskSubType}`;
        };

        const getResourceIcon = (name) => {
            if (!name) return null;
            const clean = name.trim().toLowerCase().replace(/\s+/g, '');
            // We map some clean resource names to their actual image names in the directory
            const map = {
                'wheat': 'grain',
                'corn': 'grain',
                'coal': 'charcoal',
                'coin': 'coin',
                'coins': 'coin'
            };
            const target = map[clean] || clean;
            return `/images/resources/${target}.png`;
        };

        const handleIconError = (event, name) => {
            const img = event.target;
            const clean = name.trim().toLowerCase().replace(/\s+/g, '');
            if (img.src.endsWith('.png')) {
                // If PNG fails, try WebP
                img.src = `/images/resources/${clean}.webp`;
            } else if (img.src.endsWith('.webp')) {
                // If WebP fails, hide the image and let the emoji be visible
                img.style.display = 'none';
            }
        };

        const formatResourceName = (name) => {
            if (!name) return '';
            // Insert space before capital letters and strip underscores
            let formatted = name.replace(/(?<!^)(?=[A-Z])/g, ' ').replace(/_/g, ' ').trim();
            // Capitalize first letter of each word
            return formatted.replace(/\w\S*/g, (w) => w.replace(/^\w/, (c) => c.toUpperCase()));
        };

        const getResourceEmoji = (name) => {
            const emojis = {
                'Wood': '🪵', 'Coal': '⚫', 'Iron': '⚙️', 'Gold': '🥇',
                'Stone': '🪨', 'Granite': '⬛', 'Wheat': '🌾', 'Flour': '🌾',
                'Bread': '🍞', 'Beer': '🍺', 'Coke': '🔥', 'Steel': '🔩',
                'Plank': '🪵', 'Beam': '🏗️', 'MetalFrame': '🔩', 'CutStone': '⬛',
                'Sword': '⚔️', 'Cannon': '💣', 'Musket': '🔫',
            };
            return emojis[name] || '📦';
        };

        const getFriendAvatar = (f) => {
            if (f.avatarId) {
                const idNum = parseInt(f.avatarId);
                if (idNum >= 1 && idNum <= 60) {
                    return `/images/avatars/${idNum}.png`;
                }
                return `https://settlersonlinewiki.eu/images/avatars/avatar_${f.avatarId}.png`;
            }
            return null;
        };

        const formatNumber = (num) => {
            if (num === null || num === undefined) return '0';
            return new Intl.NumberFormat().format(num);
        };

        const formatSyncTime = (timeStr) => {
            if (!timeStr) return '';
            const date = new Date(timeStr);
            const now = new Date();
            const diffMs = now - date;
            const diffMins = Math.floor(diffMs / 60000);

            if (diffMins < 1) return 'just now';
            if (diffMins < 60) return `${diffMins}m ago`;
            const diffHours = Math.floor(diffMins / 60);
            if (diffHours < 24) return `${diffHours}h ago`;
            return date.toLocaleDateString();
        };

        const toggleBuilding = async (b) => {
            actionLoading.value = true;
            const currentlyActive = isBuildingActive(b);
            const actionType = currentlyActive ? 'stop_production' : 'start_production';
            try {
                const res = await axios.post(`/api/accounts/${account.value.id}/action`, {
                    action_type: actionType,
                    grid: b.buildingGrid
                });
                if (res.data.success) {
                    showToast(`Production ${currentlyActive ? 'stopped' : 'started'}!`);
                    b.isProductionActive = !currentlyActive;
                    b.buildingMode = currentlyActive ? 28 : 23;
                } else {
                    showToast(res.data.message || 'Action failed.', 'error');
                }
            } catch (e) {
                showToast(e.response?.data?.message || 'Action failed.', 'error');
            } finally {
                actionLoading.value = false;
            }
        };

        const loadAccount = async () => {
            loading.value = true;
            try {
                const res = await axios.get(`/api/accounts/${route.params.id}`);
                account.value = res.data;
            } catch (e) {
                showToast('Failed to load account.', 'error');
                router.push('/accounts');
            } finally {
                loading.value = false;
            }
        };

        const sessionSubmitting = ref(false);
        const sessionForm = ref({
            dso_auth_token: '',
            dso_auth_user: '',
            bb_url: 'https://r02-ls.thesettlersonline.ru/',
        });

        watch(account, (newVal) => {
            if (newVal) {
                sessionForm.value.dso_auth_token = newVal.dso_auth_token || '';
                sessionForm.value.dso_auth_user = newVal.dso_auth_user || '';
                sessionForm.value.bb_url = newVal.bb_url || 'https://r02-ls.thesettlersonline.ru/';
            }
        }, { immediate: true });

        const saveSession = async () => {
            sessionSubmitting.value = true;
            try {
                const res = await axios.put(`/api/accounts/${account.value.id}/session`, sessionForm.value);
                if (res.data.success) {
                    showToast('Сессия успешно обновлена!');
                    if (res.data.account) {
                        account.value = res.data.account;
                    }
                } else {
                    showToast(res.data.message || 'Ошибка сохранения сессии.', 'error');
                }
            } catch (e) {
                showToast(e.response?.data?.message || 'Ошибка сохранения сессии.', 'error');
            } finally {
                sessionSubmitting.value = false;
            }
        };

        onMounted(() => {
            loadAccount();
            fetch('/api/lang/res')
                .then(r => r.json())
                .then(data => { translations.value = data; })
                .catch(() => {});
        });

        return {
            loading,
            account,
            statusClass,
            actionLoading,
            avatarError,
            activeTab,
            buildingSearch,
            buildingFilter,
            tabs,
            buildingCategories,
            parsedBuildings,
            parsedSpecialists,
            specialistSearch,
            specialistFilter,
            filteredSpecialists,
            getSpecialistCategory,
            getSpecialistTypeName,
            getSpecialistCategoryCount,
            getTaskName,
            specialistIconErrors,
            handleSpecialistIconError,
            hasSpecialistIconError,
            getSpecialistIcon,

            parsedResources,
            parsedFriends,
            level,
            xp,
            pvpLevel,
            generalsAmount,
            explorersAmount,
            geologistsAmount,
            currentMaximumBuildingsCountAll,
            xpNextTarget,
            availableBuffs,
            resourceLimit,
            playerNickname,
            avatarUrl,
            avatarLetters,
            xpProgress,
            totalResources,
            storagePercentage,
            basicResources,
            improvedResources,
            advancedResources,
            masterResources,
            eventResources,
            collectibleResources,
            eliteResources,
            militaryResources,
            otherResources,
            filteredBuildings,
            getBuildingName,
            getBuildingIcon,
            handleBuildingIconError,
            isStoppable,
            isBuildingActive,
            getBuffName,
            getStarBuffName,
            getBuffIcon,
            handleBuffIconError,
            getSpecialistType,
            getResourceIcon,
            getResourceEmoji,
            getFriendAvatar,
            formatNumber,
            formatSyncTime,
            toggleBuilding,
            handleIconError,
            formatResourceName,
            serverName,
            buildingModeFilter,
            syncing,
            syncAccount,
            visitors,
            getAvatarById,
            translations,
            sessionForm,
            sessionSubmitting,
            saveSession,
        };
    }
};
</script>
