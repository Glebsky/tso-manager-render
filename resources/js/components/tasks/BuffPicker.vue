<template>
    <div v-if="showModal" @click.self="$emit('close')" class="fixed inset-0 z-[100] flex items-center justify-center p-2 sm:p-4 bg-black/60 backdrop-blur-sm animate-fade-in">
        <div class="glass-card max-w-2xl w-full flex flex-col max-h-[90vh] sm:max-h-[80vh] shadow-2xl border border-white/10">
            <div class="px-4 sm:px-6 py-3 sm:py-4 border-b border-white/5 flex items-center justify-between">
                <h3 class="text-sm sm:text-base font-semibold text-white">{{ t('tasks.modal.select_buff') }}</h3>
                <button type="button" @click="$emit('close')" aria-label="Close" class="text-white/40 hover:text-white transition-colors p-1 rounded-lg hover:bg-white/5">
                    <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div class="p-3 sm:p-4 border-b border-white/5 bg-white/[0.01]">
                <input :value="buffSearch" @input="$emit('update:buffSearch', $event.target.value)" type="text" :placeholder="t('tasks.modal.search_buff')" :aria-label="t('tasks.modal.search_buff')" class="glass-input w-full text-xs py-1.5 sm:py-2 pl-3 sm:pl-4">
            </div>
            <div class="p-3 sm:p-6 overflow-y-auto flex-1 bg-dark-950/20">
                <div v-if="filteredBuffsModal.length > 0" class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 sm:gap-3">
                    <div v-for="bf in filteredBuffsModal" :key="bf.uniqueId1 + '-' + bf.uniqueId2"
                         @click="$emit('select-buff', bf)"
                         class="glass-card p-3 cursor-pointer hover:border-emerald-500/40 hover:scale-[1.01] transition-all duration-200 flex items-center gap-3"
                         :class="selectedBuffId === bf.uniqueId1 ? 'border-emerald-500/50 bg-emerald-500/10' : 'border-transparent'">
                        <div class="w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0 bg-dark-900/50 border border-white/5">
                            <img v-if="getBuffIcon(bf)" :src="getBuffIcon(bf)" :alt="getStarBuffName(bf)" class="w-8 h-8 object-contain" @error="handleBuffIconError($event, bf)">
                            <span v-else class="text-sm">✨</span>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-semibold text-white/90 truncate">{{ getStarBuffName(bf) }}</p>
                            <p class="text-[10px] text-white/40 mt-0.5">
                                {{ t('tasks.modal.in_stock') }}: {{ bf.amount }}
                                <span v-if="buffDurationLabel(bf)" class="text-emerald-400/80"> • ⏱ {{ buffDurationLabel(bf) }}</span>
                            </p>
                        </div>
                    </div>
                </div>
                <div v-else class="text-center py-8 text-white/30 text-xs">
                    {{ t('tasks.modal.no_buffs') }}
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { t } from '../../lang';

defineProps({
    showModal: { type: Boolean, required: true },
    buffSearch: { type: String, default: '' },
    selectedBuffId: { type: Number, default: 0 },
    filteredBuffsModal: { type: Array, default: () => [] },
    getStarBuffName: { type: Function, required: true },
    buffDurationLabel: { type: Function, required: true },
    getBuffIcon: { type: Function, required: true },
    handleBuffIconError: { type: Function, required: true }
});

defineEmits(['close', 'select-buff', 'update:buffSearch']);
</script>
