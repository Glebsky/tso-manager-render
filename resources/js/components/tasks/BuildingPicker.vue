<template>
    <div v-if="showModal" @click.self="$emit('close')" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm animate-fade-in">
        <div class="glass-card max-w-2xl w-full flex flex-col max-h-[85vh] shadow-2xl border border-white/10">
            <!-- Шапка модального окна -->
            <div class="px-6 py-4 border-b border-white/5 flex items-center justify-between flex-wrap gap-2">
                <div class="flex items-center gap-3">
                    <h3 class="text-base font-semibold text-white">{{ t('tasks.modal.select_building') }}</h3>
                    <span class="badge badge-emerald text-xs font-mono">{{ t('tasks.modal.selected_count', { count: selectedBuildings.length }) }}</span>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" @click="$emit('select-all')" class="btn-secondary btn-sm text-[11px] py-1 px-2.5">
                        {{ t('tasks.modal.select_all') }}
                    </button>
                    <button type="button" @click="$emit('clear')" class="btn-secondary btn-sm text-[11px] py-1 px-2.5 text-red-400 hover:text-red-300 border-red-500/20">
                        {{ t('tasks.modal.clear_selection') }}
                    </button>
                    <button type="button" @click="$emit('close')" class="btn-primary btn-sm text-xs py-1 px-3">
                        {{ t('tasks.modal.done') }}
                    </button>
                    <button type="button" @click="$emit('close')" class="text-white/40 hover:text-white transition-colors p-1 rounded-lg hover:bg-white/5 ml-1">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Вкладки и Поиск -->
            <div class="p-4 border-b border-white/5 bg-white/[0.01] flex flex-col gap-3">
                <div class="flex items-center gap-2">
                    <button type="button" @click="$emit('update:tab', 'self')"
                            class="px-3 py-1.5 rounded-lg text-xs font-semibold border transition-all"
                            :class="tab === 'self' ? 'bg-emerald-500/20 text-emerald-400 border-emerald-500/30' : 'bg-white/5 text-white/40 border-transparent hover:bg-white/10'">
                        {{ t('tasks.target.self_island') }}
                    </button>
                    <button type="button" @click="$emit('update:tab', 'friend')"
                            class="px-3 py-1.5 rounded-lg text-xs font-semibold border transition-all"
                            :class="tab === 'friend' ? 'bg-emerald-500/20 text-emerald-400 border-emerald-500/30' : 'bg-white/5 text-white/40 border-transparent hover:bg-white/10'">
                        {{ t('tasks.target.friend_island') }}
                    </button>
                </div>
                <div class="relative">
                    <input v-if="tab === 'self'" :value="buildingSearch" @input="$emit('update:buildingSearch', $event.target.value)" type="text" :placeholder="t('tasks.modal.search_building')" class="glass-input w-full text-xs py-2 pl-4">
                    <input v-else :value="friendBuildingSearch" @input="$emit('update:friendBuildingSearch', $event.target.value)" type="text" :placeholder="t('tasks.modal.search_building')" class="glass-input w-full text-xs py-2 pl-4">
                </div>
            </div>

            <!-- Список зданий self -->
            <div v-if="tab === 'self'" class="p-6 overflow-y-auto flex-1 bg-dark-950/20">
                <div v-if="filteredBuildings.length > 0" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div v-for="b in filteredBuildings" :key="b.buildingGrid"
                         @click="$emit('toggle-building', b, 'self')"
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

            <!-- Список зданий friend -->
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
                         @click="$emit('toggle-building', b, 'friend', selectedFriend)"
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
</template>

<script setup>
import { t } from '../../lang';

defineProps({
    showModal: { type: Boolean, required: true },
    tab: { type: String, default: 'self' },
    buildingSearch: { type: String, default: '' },
    friendBuildingSearch: { type: String, default: '' },
    selectedBuildings: { type: Array, default: () => [] },
    filteredBuildings: { type: Array, default: () => [] },
    searchedFriendBuildings: { type: Array, default: () => [] },
    selectedFriend: { type: Object, default: null },
    loadingFriendZone: { type: Boolean, default: false },
    zone: { type: Object, default: null },
    isSelectedBuilding: { type: Function, required: true },
    getBuildingIcon: { type: Function, required: true },
    getBuildingName: { type: Function, required: true },
    handleBuildingIconError: { type: Function, required: true }
});

defineEmits(['close', 'select-all', 'clear', 'toggle-building', 'update:tab', 'update:buildingSearch', 'update:friendBuildingSearch']);
</script>
