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
            <div class="h-2 bg-gradient-to-r from-emerald-500 to-teal-500"></div>
            <div class="p-6">
                <div class="flex items-center justify-between gap-6 flex-wrap md:flex-nowrap">
                    <div class="flex items-center gap-6">
                        <!-- Avatar -->
                        <div class="w-20 h-20 rounded-2xl overflow-hidden bg-gradient-to-br from-emerald-500 to-teal-600 flex items-center justify-center shadow-lg shadow-emerald-500/20">
                            <img v-if="avatarUrl" :src="avatarUrl" :alt="playerNickname" class="w-full h-full object-cover" @error="avatarError = true">
                            <span v-else class="text-2xl font-bold text-white">{{ avatarLetters }}</span>
                        </div>

                        <div>
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
                                <span v-if="xpNextTarget">До {{ level + 1 }} уровня: {{ formatNumber(xpNextTarget - xp) }} XP (Всего {{ formatNumber(xpNextTarget) }})</span>
                            </div>
                            <div class="h-2 bg-white/5 rounded-full overflow-hidden">
                                <div class="h-full bg-gradient-to-r from-emerald-500 to-teal-500 rounded-full transition-all duration-500"
                                     :style="{ width: xpProgress + '%' }"></div>
                            </div>
                        </div>
                    </div>
                </div>

                    <!-- Right Side: Visitors and Sync Button -->
                    <div class="flex items-center gap-4 flex-shrink-0 flex-wrap">
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
        </div>

        <!-- Tabs Navigation -->
        <div class="flex items-center gap-2 mb-6 overflow-x-auto pb-2">
            <button v-for="tab in tabs" :key="tab.id" @click="activeTab = tab.id"
                    class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-medium transition-all duration-300 whitespace-nowrap"
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
                    <div class="flex-1 relative">
                        <svg class="w-4 h-4 text-white/30 absolute left-3 top-1/2 -translate-y-1/2" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                        </svg>
                        <input v-model="buildingSearch" type="text" placeholder="Search buildings..." class="glass-input w-full pl-10">
                    </div>
                    <div class="flex gap-2 flex-wrap">
                        <button v-for="cat in buildingCategories" :key="cat" @click="buildingFilter = cat"
                                class="px-3 py-2 rounded-lg text-xs font-medium transition-all duration-300"
                                :class="buildingFilter === cat
                                    ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30'
                                    : 'bg-white/5 text-white/40 border border-transparent hover:bg-white/10'">
                            {{ cat }}
                        </button>
                    </div>
                </div>

                <!-- Buildings Grid -->
                <div v-if="filteredBuildings.length > 0" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
                    <div v-for="b in filteredBuildings" :key="b.buildingGrid" class="glass-card p-4 hover:border-white/20 transition-all duration-300">
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
                <div v-if="parsedSpecialists.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div v-for="s in parsedSpecialists" :key="s.uniqueId || s.uniqueId1" class="glass-card p-4">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-blue-500/10 flex items-center justify-center">
                                <svg class="w-5 h-5 text-blue-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                                </svg>
                            </div>
                            <div class="flex-1">
                                <p class="text-sm font-medium text-white/80">{{ s.name || 'Specialist' }}</p>
                                <p class="text-[10px] text-white/30">{{ getSpecialistType(s.type) }}</p>
                            </div>
                        </div>
                        <div v-if="s.taskType || s.taskSubType" class="mt-3 pt-3 border-t border-white/5">
                            <p class="text-[10px] text-white/30">Current Task</p>
                            <p class="text-xs text-white/60">{{ s.taskType }} - {{ s.taskSubType }}</p>
                        </div>
                    </div>
                </div>
                <div v-else class="text-center py-12">
                    <p class="text-white/30 text-sm">No specialists found.</p>
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
                    <div v-if="availableBuffs.length > 0" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
                        <div v-for="(b, idx) in availableBuffs" :key="idx" class="glass-card p-4 hover:border-white/10 transition-all duration-200">
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

                <!-- Active Zone Buffs -->
                <div>
                    <h3 class="text-sm font-semibold text-white/50 uppercase tracking-wider mb-4 flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                        Активные баффы в зоне (Active Buffs)
                    </h3>
                    <div v-if="parsedBuffs.length > 0" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
                        <div v-for="b in parsedBuffs" :key="b.uniqueId || b.uniqueId1" class="glass-card p-4">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl bg-emerald-500/10 flex items-center justify-center flex-shrink-0 overflow-hidden">
                                    <img :src="getBuffIcon(b)" :alt="b.name" class="w-7 h-7 object-contain" @error="handleBuffIconError($event, b)">
                                    <svg class="w-5 h-5 text-emerald-400" style="display: none;" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09Z" />
                                    </svg>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-white/80 truncate">{{ b.name || 'Buff' }}</p>
                                    <p class="text-[10px] text-white/30">ID: {{ b.buffId || b.uniqueId || 'N/A' }}</p>
                                </div>
                                <span v-if="b.amount" class="badge badge-warning text-[10px]">x{{ b.amount }}</span>
                            </div>
                        </div>
                    </div>
                    <div v-else class="text-center py-6 glass-card border-dashed">
                        <p class="text-white/30 text-sm">Нет активных баффов в зоне.</p>
                    </div>
                </div>
            </div>

            <!-- Resources Tab -->
            <div v-show="activeTab === 'resources'">
                <!-- Storage limit info -->
                <div v-if="resourceLimit" class="glass-card p-4 mb-6 flex justify-between items-center">
                    <div>
                        <h3 class="text-sm font-semibold text-white/80">Вместимость склада (на каждый ресурс)</h3>
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
                            Basic Resources (Базовые)
                        </h4>
                        <div class="grid gap-2" style="grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));">
                            <div v-for="r in basicResources" :key="r.name" class="glass-card p-2 flex items-center gap-2 hover:border-white/10 transition-all duration-200 w-full">
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
                            Improved Resources (Улучшенные)
                        </h4>
                        <div class="grid gap-2" style="grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));">
                            <div v-for="r in improvedResources" :key="r.name" class="glass-card p-2 flex items-center gap-2 hover:border-white/10 transition-all duration-200 w-full">
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
                            Advanced Resources (Усовершенствованные)
                        </h4>
                        <div class="grid gap-2" style="grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));">
                            <div v-for="r in advancedResources" :key="r.name" class="glass-card p-2 flex items-center gap-2 hover:border-white/10 transition-all duration-200 w-full">
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
                            Master Resources (Искусные)
                        </h4>
                        <div class="grid gap-2" style="grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));">
                            <div v-for="r in masterResources" :key="r.name" class="glass-card p-2 flex items-center gap-2 hover:border-white/10 transition-all duration-200 w-full">
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
                            Event Resources (Событие)
                        </h4>
                        <div class="grid gap-2" style="grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));">
                            <div v-for="r in eventResources" :key="r.name" class="glass-card p-2 flex items-center gap-2 hover:border-white/10 transition-all duration-200 w-full">
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
                            Collections (Коллекции)
                        </h4>
                        <div class="grid gap-2" style="grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));">
                            <div v-for="r in collectibleResources" :key="r.name" class="glass-card p-2 flex items-center gap-2 hover:border-white/10 transition-all duration-200 w-full">
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
                            Elite Resources (Элита)
                        </h4>
                        <div class="grid gap-2" style="grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));">
                            <div v-for="r in eliteResources" :key="r.name" class="glass-card p-2 flex items-center gap-2 hover:border-white/10 transition-all duration-200 w-full">
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
                            <div v-for="r in otherResources" :key="r.name" class="glass-card p-2 flex items-center gap-2 hover:border-white/10 transition-all duration-200 w-full">
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
        </div>
    </div>

    <div v-else class="text-center py-12">
        <p class="text-white/30 text-sm">Account not found.</p>
    </div>
</template>

<script>
import { ref, computed, onMounted, h } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import axios from 'axios';
import { showToast } from '../toast';

// Icon components
const BuildingIcon = { render() { return h('svg', { class: 'w-4 h-4', fill: 'none', viewBox: '0 0 24 24', 'stroke-width': '1.5', stroke: 'currentColor' }, [h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', d: 'M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Z' })]); } };
const SpecialistIcon = { render() { return h('svg', { class: 'w-4 h-4', fill: 'none', viewBox: '0 0 24 24', 'stroke-width': '1.5', stroke: 'currentColor' }, [h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', d: 'M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z' })]); } };
const BuffIcon = { render() { return h('svg', { class: 'w-4 h-4', fill: 'none', viewBox: '0 0 24 24', 'stroke-width': '1.5', stroke: 'currentColor' }, [h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', d: 'M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.455 2.456L21.75 6l-1.036.259a3.375 3.375 0 0 0-2.455 2.456ZM16.894 20.567 16.5 21.75l-.394-1.183a2.25 2.25 0 0 0-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 0 0 1.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 0 0 1.423 1.423l1.183.394-1.183.394a2.25 2.25 0 0 0-1.423 1.423Z' })]); } };
const ResourceIcon = { render() { return h('svg', { class: 'w-4 h-4', fill: 'none', viewBox: '0 0 24 24', 'stroke-width': '1.5', stroke: 'currentColor' }, [h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', d: 'M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75m16.5 0c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125' })]); } };
const FriendIcon = { render() { return h('svg', { class: 'w-4 h-4', fill: 'none', viewBox: '0 0 24 24', 'stroke-width': '1.5', stroke: 'currentColor' }, [h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', d: 'M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z' })]); } };

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

export default {
    name: 'AccountDetail',
    components: { BuildingIcon, SpecialistIcon, BuffIcon, ResourceIcon, FriendIcon },
    setup() {
        const route = useRoute();
        const router = useRouter();
        const loading = ref(true);
        const account = ref(null);
        const actionLoading = ref(false);
        const avatarError = ref(false);
        const activeTab = ref('buildings');
        const buildingSearch = ref('');
        const buildingFilter = ref('All');
        const buildingModeFilter = ref('all');
        const translations = ref({});

        const tabs = computed(() => [
            { id: 'buildings', label: 'Buildings', icon: BuildingIcon, count: parsedBuildings.value.length },
            { id: 'specialists', label: 'Specialists', icon: SpecialistIcon, count: parsedSpecialists.value.length },
            { id: 'buffs', label: 'Buffs', icon: BuffIcon, count: availableBuffs.value.length + parsedBuffs.value.length },
            { id: 'resources', label: 'Resources', icon: ResourceIcon, count: parsedResources.value.length },
            { id: 'friends', label: 'Friends', icon: FriendIcon, count: parsedFriends.value.length },
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
        const parsedBuffs = computed(() => zoneData.value?.buffs || []);
        const RESOURCE_CATEGORIES = {
            'WarehouseTab1': [
                'Wood', 'wood', 'Plank', 'plank',
                'Stone', 'stone', 'Fish', 'fish', 'Water', 'water', 'Coal', 'coal',
                'BronzeOre', 'bronzeore', 'CopperOre', 'copperore',
                'Bronze', 'bronze', 'Copper', 'copper',
                'BronzeSword', 'bronzesword', 'Bow', 'bow', 'Tool', 'tool', 'tools',
                'SimplePaper', 'simplepaper'
            ],
            'WarehouseTab2': [
                'Marble', 'marble',
                'IronOre', 'ironore',
                'Iron', 'iron',
                'IronSword', 'ironsword',
                'Longbow', 'longbow',
                'Horse', 'horse', 'horses',
                'Wheat', 'wheat', 'corn', 'Corn',
                'Flour', 'flour', 'Bread', 'bread',
                'Beer', 'beer', 'brew', 'Brew',
                'Meat', 'meat', 'Sausage', 'sausage',
                'IntermediatePaper', 'intermediatepaper'
            ],
            'WarehouseTab3': [
                'RealWood', 'realwood', 'RealPlank', 'realplank',
                'Steel', 'steel', 'SteelSword', 'steelsword',
                'GoldOre', 'goldore', 'Gold', 'gold', 'Coin', 'coins', 'Coinage',
                'TitaniumOre', 'titaniumore', 'Titanium', 'titanium',
                'TitaniumSword', 'titaniumsword',
                'Salpeter', 'salpeter', 'Gunpowder', 'gunpowder',
                'Carriage', 'carriage', 'Wagon', 'wagon', 'Wheel', 'wheel',
                'AdvancedPaper', 'advancedpaper'
            ],
            'WarehouseTab4': [
                'ExoticWood', 'exoticwood', 'ExoticPlank', 'exoticplank',
                'Crossbow', 'crossbow', 'DamasceneSword', 'damascenesword',
                'Cannon', 'cannon',
                'Granite', 'granite',
                'Grout', 'grout', 'mortar', 'Mortar',
                'Manuscript', 'tome', 'Codex', 'Nib',
                'BookFitting', 'Tome'
            ],
            'WarehouseTab8': [
                'MahoganyWood', 'mahoganywood', 'MahoganyPlank', 'mahoganyplank',
                'PlatinumOre', 'platinumore', 'Platinum', 'platinum', 'PlatinumSword', 'platinumsword',
                'Archebuse', 'archebuse',
                'Oilseed', 'seed', 'Oil', 'oil',
                'ObsidianOre', 'obsidianore', 'Crystal', 'crystal', 'CrystalShard', 'crystalshard'
            ],
            'WarehouseTab6': [
                'Event', 'balloons', 'flower', 'flowers', 'plant', 'wool', 'cloth',
                'EventResource', 'EMEventResource', 'StripedEggs', 'ChristmasResource', 'HalloweenResource'
            ],
            'WarehouseTab7': [
                'Collectibles', 'CollectibleBanner', 'CollectibleBronzeCauldron', 'CollectibleChristmasBells',
                'CollectibleChristmasCandy', 'CollectibleChristmasGingerbread', 'CollectibleClue', 'CollectibleEggpaint',
                'CollectibleFoodCart', 'CollectibleFurs', 'CollectibleFurs2', 'CollectibleFursTMC', 'CollectibleGrainSacks',
                'CollectibleHerbs', 'CollectibleKettle', 'CollectibleMagicStone', 'CollectibleObsidianShard',
                'CollectiblePlainEgg', 'CollectibleRobustTools', 'CollectibleSacredStone', 'CollectibleScarecrow',
                'CollectibleWickerBasket', 'CollectibleWineBarrel', 'AdventureRelics', 'AdventureTale'
            ]
        };
        const CATEGORY_ORDER = ['WarehouseTab1', 'WarehouseTab2', 'WarehouseTab3', 'WarehouseTab4', 'WarehouseTab8', 'WarehouseTab6', 'WarehouseTab7'];
        const getCategory = (name) => {
            if (!name) return 'Other';
            const normName = name.trim().toLowerCase();
            
            // Check explicit patterns
            if (normName.includes('balloon') || normName.includes('egg') || normName.includes('gift') || normName.includes('pumpkin') || normName.includes('present')) {
                return 'WarehouseTab6';
            }
            
            for (const cat of CATEGORY_ORDER) {
                if (RESOURCE_CATEGORIES[cat].some(r => r.toLowerCase() === normName)) return cat;
            }
            return 'Other';
        };

        const parsedResources = computed(() => {
            const raw = zoneData.value?.resources || [];
            return raw.map(r => ({
                ...r,
                name: r.name || r.name_string || 'Unknown',
                category: getCategory(r.name || r.name_string || ''),
            }));
        });
        const parsedFriends = computed(() => zoneData.value?.friends || []);
        const level = computed(() => zoneData.value?.level);
        const xp = computed(() => zoneData.value?.xp);
        const pvpLevel = computed(() => zoneData.value?.pvpLevel);
        const generalsAmount = computed(() => zoneData.value?.generalsAmount);
        const explorersAmount = computed(() => zoneData.value?.explorersAmount);
        const geologistsAmount = computed(() => zoneData.value?.geologistsAmount);
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

        const LEVEL_XP_TABLE = {
            1: 0, 2: 10, 3: 40, 4: 100, 5: 200, 6: 400, 7: 800, 8: 1500, 9: 2500, 10: 4000,
            11: 6000, 12: 8500, 13: 11500, 14: 15000, 15: 19000, 16: 23500, 17: 28500, 18: 34000, 19: 40000, 20: 46500,
            21: 53500, 22: 61000, 23: 69000, 24: 77500, 25: 86500, 26: 96000, 27: 106000, 28: 118000, 29: 132000, 30: 148000,
            31: 166000, 32: 186000, 33: 208000, 34: 233000, 35: 261000, 36: 293000, 37: 118000, 38: 158000, 39: 236000, 40: 314000,
            50: 3200000, 60: 16000000, 70: 45000000, 80: 120000000
        };

        const xpNextTarget = computed(() => {
            if (!level.value) return 0;
            const lvl = level.value;
            return LEVEL_XP_TABLE[lvl + 1] !== undefined ? LEVEL_XP_TABLE[lvl + 1] : ((lvl + 1) * 10000);
        });

        const xpProgress = computed(() => {
            if (xp.value === null || xp.value === undefined || !level.value) return 0;
            const lvl = level.value;
            const currentXp = xp.value;
            let startXp = LEVEL_XP_TABLE[lvl] !== undefined ? LEVEL_XP_TABLE[lvl] : (lvl * 10000);
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
                return translations.value[name];
            }

            if (name === 'AddResource') {
                const tpl = translations.value['AddResource'] || 'Добавить ресурс';
                const prefix = tpl.split('{')[0].trim();
                return `${prefix}: ${formatResourceName(b.resourceName_string)}`;
            }
            if (name === 'BuildBuilding') {
                const tpl = translations.value['BuildBuilding'] || 'Лицензия';
                const prefix = tpl.split('{')[0].trim();
                return `${prefix}: ${formatResourceName(b.resourceName_string)}`;
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

        const getSpecialistType = (type) => {
            const types = { 0: 'Geologist', 1: 'Explorer', 2: 'General' };
            return types[type] || 'Specialist';
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
            actionLoading,
            avatarError,
            activeTab,
            buildingSearch,
            buildingFilter,
            tabs,
            buildingCategories,
            parsedBuildings,
            parsedSpecialists,
            parsedBuffs,
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
        };
    }
};
</script>
