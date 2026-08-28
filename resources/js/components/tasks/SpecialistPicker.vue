<template>
    <div v-if="showModal" @click.self="$emit('close')" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm animate-fade-in">
        <div class="glass-card max-w-2xl w-full flex flex-col max-h-[85vh] shadow-2xl border border-white/10">
            <div class="px-6 py-4 border-b border-white/5 flex items-center justify-between flex-wrap gap-2">
                <div class="flex items-center gap-3">
                    <h3 class="text-base font-semibold text-white">{{ t('tasks.modal.select_specialist') }}</h3>
                    <span class="badge badge-emerald text-xs font-mono">{{ t('tasks.modal.selected_count', { count: selectedSpecialists.length }) }}</span>
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
                    <button type="button" @click="$emit('close')" aria-label="Close" class="text-white/40 hover:text-white transition-colors p-1 rounded-lg hover:bg-white/5 ml-1">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>
            <div class="p-4 border-b border-white/5 bg-white/[0.01]">
                <input :value="specialistSearch" @input="$emit('update:specialistSearch', $event.target.value)" type="text" :placeholder="t('tasks.modal.search_specialist')" :aria-label="t('tasks.modal.search_specialist')" class="glass-input w-full text-xs py-2 pl-4">
            </div>
            <div class="p-6 overflow-y-auto flex-1 bg-dark-950/20">
                <div v-if="filteredSpecialistsModal.length > 0" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div v-for="s in filteredSpecialistsModal" :key="getSpecialistId(s)"
                         @click="$emit('toggle-specialist', s)"
                         class="glass-card p-3 cursor-pointer hover:border-emerald-500/40 transition-all duration-200 flex items-center justify-between gap-3"
                         :class="isSelectedSpecialist(s) ? 'border-emerald-500/70 bg-emerald-500/15 shadow-lg shadow-emerald-500/5' : 'border-transparent hover:bg-white/[0.02]'">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0 bg-dark-900/50 border border-white/5">
                                <img alt="" v-if="getSpecialistIcon(s.type)" :src="getSpecialistIcon(s.type)" class="w-8 h-8 object-contain" @error="handleSpecialistIconError($event, s.type)">
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
</template>

<script setup>
import { t } from '../../lang';

defineProps({
    showModal: { type: Boolean, required: true },
    specialistSearch: { type: String, default: '' },
    selectedSpecialists: { type: Array, default: () => [] },
    filteredSpecialistsModal: { type: Array, default: () => [] },
    getSpecialistId: { type: Function, required: true },
    isSelectedSpecialist: { type: Function, required: true },
    getSpecialistIcon: { type: Function, required: true },
    getSpecialistTypeName: { type: Function, required: true },
    handleSpecialistIconError: { type: Function, required: true }
});

defineEmits(['close', 'select-all', 'clear', 'toggle-specialist', 'update:specialistSearch']);
</script>
