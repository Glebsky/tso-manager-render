<template>
    <div v-if="showModal" @click.self="$emit('close')" class="fixed inset-0 z-[100] flex items-center justify-center p-2 sm:p-4 bg-black/60 backdrop-blur-sm animate-fade-in">
        <div class="glass-card max-w-2xl w-full flex flex-col max-h-[90vh] sm:max-h-[85vh] shadow-2xl border border-white/10">
            <!-- Шапка модального окна -->
            <div class="px-4 sm:px-6 py-3 sm:py-4 border-b border-white/5 flex items-center justify-between flex-wrap gap-2">
                <div class="flex items-center gap-2 sm:gap-3">
                    <h3 class="text-sm sm:text-base font-semibold text-white">{{ t('tasks.modal.select_deposit') }}</h3>
                    <span class="badge badge-emerald text-[10px] sm:text-xs font-mono">{{ t('tasks.modal.selected_count', { count: selectedDeposits.length }) }}</span>
                </div>
                <div class="flex items-center gap-1.5 sm:gap-2">
                    <button type="button" @click="$emit('refresh')" :disabled="loading" class="btn-secondary btn-sm text-[10px] sm:text-[11px] py-1 px-2 sm:px-2.5 flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" :class="{ 'animate-spin': loading }" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                        </svg>
                        <span>{{ t('common.refresh') }}</span>
                    </button>
                    <button type="button" @click="$emit('select-all')" class="btn-secondary btn-sm text-[10px] sm:text-[11px] py-1 px-2 sm:px-2.5">
                        {{ t('tasks.modal.select_all') }}
                    </button>
                    <button type="button" @click="$emit('clear')" class="btn-secondary btn-sm text-[10px] sm:text-[11px] py-1 px-2 sm:px-2.5 text-red-400 hover:text-red-300 border-red-500/20">
                        {{ t('tasks.modal.clear_selection') }}
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
                    <input :value="depositSearch" @input="$emit('update:depositSearch', $event.target.value)" type="text" :placeholder="t('tasks.modal.search_deposit')" :aria-label="t('tasks.modal.search_deposit')" class="glass-input w-full text-xs py-1.5 sm:py-2 pl-3 sm:pl-4">
                </div>
            </div>

            <!-- Список залежей -->
            <div class="p-3 sm:p-6 overflow-y-auto flex-1 bg-dark-950/20">
                <div v-if="loading" class="text-center py-8 text-emerald-400 text-xs flex items-center justify-center gap-2">
                    <svg class="animate-spin h-4 w-4 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span>{{ t('common.loading') }}</span>
                </div>
                <div v-else-if="filteredDeposits.length > 0" class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 sm:gap-3">
                    <div v-for="d in filteredDeposits" :key="d.grid"
                         @click="d.allowed && $emit('toggle-deposit', d)"
                         class="glass-card p-3 transition-all duration-200 flex items-center justify-between gap-3"
                         :class="[
                             d.allowed ? 'cursor-pointer hover:border-emerald-500/40' : 'opacity-50 cursor-not-allowed',
                             isSelectedDeposit(d.grid) ? 'border-emerald-500/70 bg-emerald-500/15 shadow-lg shadow-emerald-500/5' : 'border-transparent hover:bg-white/[0.02]'
                         ]">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0 bg-dark-900/50 border border-white/5">
                                <img v-if="getBuildingIcon(d.mine_name)" :src="getBuildingIcon(d.mine_name)" :alt="d.mine_name" class="w-7 h-7 object-contain" @error="handleBuildingIconError($event, d.mine_name)">
                                <span v-else class="text-sm">⛏️</span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-1.5 min-w-0">
                                    <p class="text-xs font-semibold text-white/90 truncate">{{ getBuildingName(d.mine_name) }}</p>
                                    <span v-if="d.allowed" class="badge badge-emerald text-[9px] flex-shrink-0">
                                        {{ t('tasks.modal.status_allowed') }}
                                    </span>
                                    <span v-else class="badge badge-neutral text-[9px] flex-shrink-0 text-white/50">
                                        {{ reasonLabel(d.reason) }}
                                    </span>
                                </div>
                                <p class="text-[10px] text-white/40 mt-0.5">
                                    {{ t('tasks.grid_number', { id: d.grid }) }} • {{ getDepositName(d.deposit_name) }}: {{ formatNumber(d.amount) }}/{{ formatNumber(d.max_amount) }}
                                </p>
                            </div>
                        </div>
                        <div class="w-5 h-5 rounded-md flex items-center justify-center border transition-all flex-shrink-0"
                             :class="[
                                 isSelectedDeposit(d.grid) ? 'bg-emerald-500 border-emerald-400 text-dark-950 font-bold' : 'border-white/20 bg-white/5',
                                 !d.allowed ? 'opacity-30' : ''
                             ]">
                            <span v-if="isSelectedDeposit(d.grid)" class="text-xs">✓</span>
                        </div>
                    </div>
                </div>
                <div v-else class="text-center py-8 text-white/30 text-xs">
                    {{ t('tasks.modal.no_deposits') }}
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { t } from '../../lang';

const props = defineProps({
    showModal: { type: Boolean, required: true },
    depositSearch: { type: String, default: '' },
    selectedDeposits: { type: Array, default: () => [] },
    deposits: { type: Array, default: () => [] },
    loading: { type: Boolean, default: false },
    isSelectedDeposit: { type: Function, required: true },
    getBuildingIcon: { type: Function, required: true },
    getBuildingName: { type: Function, required: true },
    getDepositName: { type: Function, required: true },
    handleBuildingIconError: { type: Function, required: true },
});

defineEmits(['close', 'select-all', 'clear', 'toggle-deposit', 'update:depositSearch', 'refresh']);

const formatNumber = (num) => {
    return Number(num || 0).toLocaleString();
};

const reasonLabel = (reason) => {
    const reasons = {
        grid_occupied: t('tasks.modal.status_occupied'),
        build_queue_full: t('tasks.modal.status_queue_full'),
        deposit_empty: t('tasks.modal.status_empty'),
        deposit_not_accessible: t('tasks.modal.status_not_accessible'),
        unknown_deposit_type: t('tasks.modal.status_unknown_type'),
        deposit_type_mismatch: t('tasks.modal.status_type_mismatch'),
    };
    return reasons[reason] || reason;
};

const filteredDeposits = computed(() => {
    const q = (props.depositSearch || '').toLowerCase().trim();
    if (!q) return props.deposits;
    return props.deposits.filter(d => {
        const dName = props.getDepositName(d.deposit_name).toLowerCase();
        const mName = props.getBuildingName(d.mine_name).toLowerCase();
        const gridStr = String(d.grid);
        return dName.includes(q) || mName.includes(q) || gridStr.includes(q);
    });
});
</script>
