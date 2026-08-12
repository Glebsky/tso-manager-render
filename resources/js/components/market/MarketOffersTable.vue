<template>
    <div class="glass-card overflow-hidden mb-8">
        <div class="px-5 py-4 border-b border-white/5 flex items-center justify-between flex-wrap gap-2">
            <h3 class="text-sm font-semibold text-white flex items-center gap-2">
                📋 {{ t('market.offers_table') }}
                <span class="badge badge-neutral text-xs font-mono">{{ offers.length }}</span>
            </h3>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-white/80">
                <thead class="text-[10px] text-white/40 uppercase bg-white/[0.02] border-b border-white/5">
                    <tr>
                        <th class="px-4 py-3">{{ t('market.offer') }}</th>
                        <th class="px-4 py-3">{{ t('market.request') }}</th>
                        <th class="px-4 py-3 text-right">{{ t('market.ratio') }}</th>
                        <th class="px-4 py-3 text-right">{{ t('market.seller') }}</th>
                        <th class="px-4 py-3 text-right">{{ t('market.time') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5">
                    <tr v-for="off in filteredOffers" :key="off.id" class="hover:bg-white/[0.02] transition-colors">
                        <td class="px-4 py-3 font-semibold text-emerald-400">
                            {{ off.offer_amount }} {{ resourceName(off.offer_resource) }}
                        </td>
                        <td class="px-4 py-3 text-white/90">
                            {{ off.request_amount }} {{ resourceName(off.request_resource) }}
                        </td>
                        <td class="px-4 py-3 text-right font-mono text-xs text-white/60">
                            1:{{ (off.request_amount / (off.offer_amount || 1)).toFixed(2) }}
                        </td>
                        <td class="px-4 py-3 text-right text-white/50">
                            {{ off.player_name || '—' }}
                        </td>
                        <td class="px-4 py-3 text-right font-mono text-white/40 text-[11px]">
                            {{ off.created_at || '—' }}
                        </td>
                    </tr>
                    <tr v-if="filteredOffers.length === 0">
                        <td colspan="5" class="px-4 py-8 text-center text-white/30">
                            {{ t('market.no_offers') }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { t } from '../../lang';
import { resourceName } from '../../lang/gameNames';

const props = defineProps({
    offers: { type: Array, default: () => [] },
    searchQuery: { type: String, default: '' }
});

const filteredOffers = computed(() => {
    if (!props.searchQuery) return props.offers;
    const q = props.searchQuery.toLowerCase();
    return props.offers.filter(o =>
        (o.offer_resource && o.offer_resource.toLowerCase().includes(q)) ||
        (o.request_resource && o.request_resource.toLowerCase().includes(q)) ||
        (o.player_name && o.player_name.toLowerCase().includes(q))
    );
});
</script>
