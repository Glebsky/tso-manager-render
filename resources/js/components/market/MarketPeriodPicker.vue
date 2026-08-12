<template>
    <div class="flex items-center gap-1 bg-dark-900/40 p-1 rounded-xl border border-white/5">
        <button v-for="p in periods" :key="p.id"
                type="button"
                @click="$emit('update:period', p.id)"
                class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-all duration-200"
                :class="period === p.id ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 shadow-md shadow-emerald-500/10' : 'text-white/50 hover:text-white hover:bg-white/5'">
            {{ p.label }}
        </button>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { t } from '../../lang';

defineProps({
    period: { type: String, default: '7d' }
});

defineEmits(['update:period']);

const periods = computed(() => [
    { id: '24h', label: t('market.period.24h') || '24h' },
    { id: '7d', label: t('market.period.7d') || '7d' },
    { id: '30d', label: t('market.period.30d') || '30d' },
    { id: 'all', label: t('market.period.all') || 'All' }
]);
</script>
