<template>
    <div v-if="showModal" @click.self="$emit('close')" class="fixed inset-0 z-[100] flex items-center justify-center p-2 sm:p-4 bg-black/60 backdrop-blur-sm animate-fade-in">
        <div class="glass-card max-w-3xl w-full flex flex-col max-h-[90vh] sm:max-h-[85vh] shadow-2xl border border-white/10">
            <!-- Шапка модального окна -->
            <div class="px-4 sm:px-6 py-3 sm:py-4 border-b border-white/5 flex items-center justify-between flex-wrap gap-2">
                <div class="flex items-center gap-2 sm:gap-3">
                    <h3 class="text-sm sm:text-base font-semibold text-white">
                        {{ selectedProducer ? t('tasks.modal.select_recipe') : t('tasks.modal.select_producer') }}
                    </h3>
                    <span v-if="selectedProducer" class="badge badge-emerald text-[10px] sm:text-xs">
                        {{ getBuildingName(selectedProducer.building_name) }} (Grid #{{ selectedProducer.grid }})
                    </span>
                </div>
                <div class="flex items-center gap-1.5 sm:gap-2">
                    <button v-if="selectedProducer" type="button" @click="clearSelectedProducer" class="btn-secondary btn-sm text-[10px] sm:text-[11px] py-1 px-2 sm:px-2.5">
                        ← {{ t('common.previous') }}
                    </button>
                    <button type="button" @click="$emit('close')" class="btn-primary btn-sm text-xs py-1 px-2.5 sm:px-3">
                        {{ t('tasks.modal.done') }}
                    </button>
                    <button type="button" @click="$emit('close')" aria-label="Close" class="text-white/40 hover:text-white transition-colors p-1 rounded-lg hover:bg-white/5 ml-0.5">
                        <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Поиск -->
            <div class="p-3 sm:p-4 border-b border-white/5 bg-white/[0.01] flex flex-col gap-3">
                <div class="relative">
                    <input v-if="!selectedProducer"
                           :value="producerSearch"
                           @input="$emit('update:producerSearch', $event.target.value)"
                           type="text"
                           :placeholder="t('tasks.modal.search_producer')"
                           :aria-label="t('tasks.modal.search_producer')"
                           class="glass-input w-full text-xs py-1.5 sm:py-2 pl-3 sm:pl-4">
                    <input v-else
                           v-model="recipeSearch"
                           type="text"
                           :placeholder="t('tasks.modal.search_recipe')"
                           :aria-label="t('tasks.modal.search_recipe')"
                           class="glass-input w-full text-xs py-1.5 sm:py-2 pl-3 sm:pl-4">
                </div>
            </div>

            <!-- Контентная область -->
            <div class="p-3 sm:p-6 overflow-y-auto flex-1 bg-dark-950/20">
                <div v-if="loading" class="text-center py-8 text-emerald-400 text-xs flex items-center justify-center gap-2">
                    <svg class="animate-spin h-4 w-4 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span>{{ t('common.loading') }}</span>
                </div>

                <!-- 1. Список мастерских -->
                <div v-else-if="!selectedProducer">
                    <div v-if="filteredProducers.length > 0" class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 sm:gap-3">
                        <div v-for="p in filteredProducers" :key="p.grid"
                             @click="onSelectProducer(p)"
                             class="glass-card p-3 cursor-pointer hover:border-emerald-500/40 transition-all duration-200 flex items-center justify-between gap-3 border-transparent hover:bg-white/[0.02]">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0 bg-dark-900/50 border border-white/5">
                                    <img v-if="getBuildingIcon(p.building_name)"
                                         :src="getBuildingIcon(p.building_name)"
                                         :alt="p.building_name"
                                         class="w-7 h-7 object-contain"
                                         @error="handleBuildingIconError($event, p.building_name)">
                                    <span v-else class="text-sm">🏭</span>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-1.5 min-w-0">
                                        <p class="text-xs font-semibold text-white/90 truncate">{{ getBuildingName(p.building_name) }}</p>
                                        <span v-if="p.upgrade_level" class="badge badge-neutral text-[9px] flex-shrink-0">
                                            Lvl {{ p.upgrade_level }}
                                        </span>
                                        <span v-if="p.upgrade_in_progress" class="badge badge-warning text-[9px] flex-shrink-0">
                                            {{ t('tasks.modal.status_in_progress') }}
                                        </span>
                                    </div>
                                    <p class="text-[10px] text-white/40 mt-0.5">
                                        {{ t('tasks.grid_number', { id: p.grid }) }} • {{ t('tasks.queue_status') }}: {{ p.queue?.used || 0 }} {{ t('tasks.modal.status_in_progress') }} • {{ p.recipes?.length || 0 }} {{ t('tasks.select_recipe').toLowerCase() }}
                                    </p>
                                </div>
                            </div>
                            <span class="text-xs text-white/40">→</span>
                        </div>
                    </div>
                    <div v-else class="text-center py-8 text-white/30 text-xs">
                        {{ t('tasks.modal.no_producers') }}
                    </div>
                </div>

                <!-- 2. Список рецептов для выбранной мастерской -->
                <div v-else>
                    <!-- Состояние 1: Неподдерживаемое здание -->
                    <div v-if="isProducerUnsupported" class="text-center py-10 px-4">
                        <div class="text-2xl mb-2">🚧</div>
                        <p class="text-xs text-amber-300 font-medium">
                            {{ t('tasks.production.unsupported_building') }}
                        </p>
                    </div>

                    <!-- Состояние 2: Каталог не загружен -->
                    <div v-else-if="isCatalogMissing" class="text-center py-10 px-4">
                        <div class="text-2xl mb-2">⚠️</div>
                        <p class="text-xs text-red-300 font-medium">
                            {{ t('tasks.production.catalog_missing') }}
                        </p>
                    </div>

                    <!-- Список рецептов -->
                    <div v-else-if="filteredRecipes.length > 0" class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 sm:gap-3">
                        <div v-for="r in filteredRecipes" :key="r.name"
                             @click="onSelectRecipe(r)"
                             class="glass-card p-3 cursor-pointer transition-all duration-200 flex flex-col justify-between gap-2.5"
                             :class="[
                                 r.is_locked ? 'opacity-50 cursor-not-allowed border-red-500/20 bg-red-500/[0.02]' : 'hover:border-emerald-500/40 hover:bg-white/[0.02]',
                                 isSelectedRecipe(r.name) ? 'border-emerald-500/70 bg-emerald-500/15 shadow-lg shadow-emerald-500/5' : 'border-transparent'
                             ]">
                            <div class="flex items-start gap-3 min-w-0">
                                <div class="w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0 bg-dark-900/50 border border-white/5 mt-0.5">
                                    <img v-if="getBuffIcon(r.name)"
                                         :src="getBuffIcon(r.name)"
                                         :alt="r.name"
                                         class="w-7 h-7 object-contain"
                                         @error="handleBuffIconError($event, r.name)">
                                    <span v-else class="text-sm">🧪</span>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <p class="text-xs font-semibold text-white/90 truncate">{{ getRecipeName(r.name) }}</p>
                                        <span v-if="r.unit_group" class="badge badge-neutral text-[8px] uppercase tracking-wider">
                                            {{ r.unit_group }}
                                        </span>
                                        <span v-if="r.is_locked" class="badge badge-danger text-[9px]">
                                            🔒 Lvl {{ r.requires_upgrade_level_min }}
                                        </span>
                                        <span v-if="r.instant_finish_cost" class="badge badge-primary text-[8px]" :title="'Instant finish cost'">
                                            💎 {{ r.instant_finish_cost }}
                                        </span>
                                    </div>
                                    <p class="text-[10px] text-white/40 mt-0.5 flex items-center gap-2">
                                        <span>⏱️ {{ formatSeconds(r.duration_seconds) }}</span>
                                        <span v-if="r.unverified_protocol" class="text-amber-400/80 text-[9px]" :title="t('tasks.production.unverified_protocol_hint')">
                                            ⚠️ {{ t('tasks.production.unverified_protocol_hint') }}
                                        </span>
                                    </p>
                                </div>
                            </div>

                            <!-- Стоимость в ресурсах с отображением остатка на складе -->
                            <div v-if="r.costs && r.costs.length > 0" class="flex flex-wrap gap-1.5 pt-1.5 border-t border-white/5">
                                <span v-if="r.cost_is_lower_bound" class="text-[10px] text-amber-400/90 font-semibold self-center" :title="t('tasks.production.progressive_cost_hint')">
                                    ≥
                                </span>
                                <span v-for="c in r.costs" :key="c.resource"
                                      class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] transition-colors"
                                      :class="c.is_population || c.resource === 'Population'
                                          ? 'bg-blue-500/10 text-blue-300 border border-blue-500/20'
                                          : (getResourceStock(c.resource) !== null && getResourceStock(c.resource) < c.count
                                              ? 'bg-red-500/10 text-red-300 border border-red-500/20'
                                              : 'bg-white/5 text-white/70')">
                                    <span>{{ formatResourceName(c.resource) }}:</span>
                                    <strong class="font-mono"
                                            :class="c.is_population || c.resource === 'Population'
                                                ? 'text-blue-400'
                                                : (getResourceStock(c.resource) !== null && getResourceStock(c.resource) < c.count ? 'text-red-400' : 'text-emerald-400')">
                                        {{ c.count }}
                                    </strong>
                                    <span v-if="!c.is_population && c.resource !== 'Population' && getResourceStock(c.resource) !== null" class="text-[9px] opacity-60">
                                        ({{ getResourceStock(c.resource) }})
                                    </span>
                                </span>
                            </div>
                            <div v-else-if="!r.costs_known" class="text-[10px] text-white/30 italic pt-1 border-t border-white/5">
                                {{ t('tasks.free_or_unknown_cost') || 'No cost data' }}
                            </div>
                        </div>
                    </div>

                    <!-- Состояние 3: Нет доступных рецептов при фильтрации -->
                    <div v-else class="text-center py-8 text-white/30 text-xs">
                        {{ t('tasks.production.no_available_recipes') }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { t } from '../../lang';
import { resourceName, humanizeGameId } from '../../lang/gameNames';

const props = defineProps({
    showModal: { type: Boolean, required: true },
    producerSearch: { type: String, default: '' },
    selectedProducer: { type: Object, default: null },
    selectedRecipeName: { type: String, default: '' },
    producers: { type: Array, default: () => [] },
    warehouseResources: { type: [Object, Array], default: () => ({}) },
    loading: { type: Boolean, default: false },
    getBuildingIcon: { type: Function, required: true },
    getBuildingName: { type: Function, required: true },
    getBuffIcon: { type: Function, required: true },
    handleBuildingIconError: { type: Function, required: true },
    handleBuffIconError: { type: Function, required: true },
});

const emit = defineEmits([
    'close',
    'select-producer',
    'select-recipe',
    'clear-producer',
    'update:producerSearch',
]);

const recipeSearch = ref('');

const clearSelectedProducer = () => {
    emit('clear-producer');
    recipeSearch.value = '';
};

const onSelectProducer = (producer) => {
    emit('select-producer', producer);
    recipeSearch.value = '';
};

const onSelectRecipe = (recipe) => {
    if (recipe.is_locked) return;
    emit('select-recipe', recipe);
};

const isSelectedRecipe = (name) => {
    return props.selectedRecipeName === name;
};

const isProducerUnsupported = computed(() => {
    if (!props.selectedProducer) return false;
    const src = props.selectedProducer.recipe_source;
    return typeof src === 'string' && src.startsWith('unsupported:');
});

const isCatalogMissing = computed(() => {
    if (!props.selectedProducer) return false;
    if (isProducerUnsupported.value) return false;
    return !props.selectedProducer.recipes || props.selectedProducer.recipes.length === 0;
});

const getResourceStock = (resName) => {
    if (!props.warehouseResources) return null;
    if (Array.isArray(props.warehouseResources)) {
        const item = props.warehouseResources.find(r => r.name_string === resName || r.name === resName);
        return item ? Number(item.amount || 0) : null;
    }
    if (typeof props.warehouseResources === 'object' && resName in props.warehouseResources) {
        return Number(props.warehouseResources[resName] || 0);
    }
    return null;
};

const getRecipeName = (name) => {
    if (!name) return '';
    return resourceName(name) || humanizeGameId(name);
};

const formatResourceName = (res) => {
    if (res === 'Population') {
        return t('tasks.production.population') || 'Population';
    }
    return resourceName(res) || humanizeGameId(res);
};

const formatSeconds = (seconds) => {
    const s = Number(seconds || 0);
    if (s <= 0) return '0s';
    const hours = Math.floor(s / 3600);
    const mins = Math.floor((s % 3600) / 60);
    const secs = s % 60;
    const parts = [];
    if (hours > 0) parts.push(`${hours}${t('tasks.unit.h')}`);
    if (mins > 0) parts.push(`${mins}${t('tasks.unit.m')}`);
    if (secs > 0 && hours === 0) parts.push(`${secs}${t('tasks.unit.s')}`);
    return parts.join(' ');
};

const filteredProducers = computed(() => {
    const q = (props.producerSearch || '').toLowerCase().trim();
    if (!q) return props.producers;
    return props.producers.filter(p => {
        const bName = props.getBuildingName(p.building_name).toLowerCase();
        const gridStr = String(p.grid);
        return bName.includes(q) || gridStr.includes(q);
    });
});

const filteredRecipes = computed(() => {
    if (!props.selectedProducer || !props.selectedProducer.recipes) return [];
    const q = (recipeSearch.value || '').toLowerCase().trim();
    if (!q) return props.selectedProducer.recipes;
    return props.selectedProducer.recipes.filter(r => {
        const rName = getRecipeName(r.name).toLowerCase();
        const rawName = String(r.name).toLowerCase();
        return rName.includes(q) || rawName.includes(q);
    });
});
</script>
