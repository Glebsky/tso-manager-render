<template>
    <div class="glass-card p-5 mb-8">
        <h3 class="text-sm font-semibold text-white mb-4 flex items-center justify-between">
            <span class="flex items-center gap-2">
                ⚖️ {{ t('market.arbitrage_opportunities') }}
            </span>
            <span class="badge badge-emerald text-[10px] font-mono">{{ arbitrageLoops.length }}</span>
        </h3>

        <div v-if="arbitrageLoops.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <div v-for="(loop, idx) in arbitrageLoops" :key="idx"
                 class="glass-card p-4 border border-emerald-500/20 bg-emerald-500/[0.02] hover:border-emerald-500/40 transition-all duration-200">
                <div class="flex items-center justify-between mb-3">
                    <span class="badge badge-emerald text-xs font-mono font-bold">+{{ loop.profit_margin_percent }}%</span>
                    <span class="text-[10px] text-white/40 font-mono">{{ loop.steps_count }} {{ t('market.steps') }}</span>
                </div>
                <div class="space-y-2">
                    <div v-for="(step, sIdx) in loop.steps" :key="sIdx" class="flex items-center justify-between text-xs p-2 rounded bg-black/20">
                        <span class="font-medium text-white/80">{{ resourceName(step.from) }} ➔ {{ resourceName(step.to) }}</span>
                        <span class="font-mono text-emerald-400">1:{{ step.ratio }}</span>
                    </div>
                </div>
            </div>
        </div>
        <div v-else class="text-center py-6 text-xs text-white/30">
            {{ t('market.no_arbitrage') }}
        </div>
    </div>
</template>

<script setup>
import { t } from '../../lang';
import { resourceName } from '../../lang/gameNames';

defineProps({
    arbitrageLoops: { type: Array, default: () => [] }
});
</script>
