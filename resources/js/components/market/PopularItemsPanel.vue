<template>
    <div class="glass-card p-5 mb-8">
        <h3 class="text-sm font-semibold text-white mb-4 flex items-center justify-between">
            <span class="flex items-center gap-2">
                🔥 {{ t('market.popular_items') }}
            </span>
        </h3>

        <div v-if="popularItems.length > 0" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
            <div v-for="item in popularItems" :key="item.name"
                 @click="$emit('select-item', item.name)"
                 class="glass-card p-3 cursor-pointer hover:border-emerald-500/40 hover:scale-[1.02] transition-all duration-200 text-center">
                <div class="w-10 h-10 rounded-xl bg-dark-900/50 border border-white/5 flex items-center justify-center mx-auto mb-2">
                    <img v-if="getGameImageUrl(item.name)" :src="getGameImageUrl(item.name)" :alt="item.name" class="w-7 h-7 object-contain" @error="handleGameImageError($event, item.name)">
                    <span v-else class="text-base">📦</span>
                </div>
                <p class="text-xs font-semibold text-white/90 truncate">{{ resourceName(item.name) }}</p>
                <p class="text-[10px] text-emerald-400 font-mono mt-0.5">{{ item.offers_count || 0 }} {{ t('market.offers_short') }}</p>
            </div>
        </div>
        <div v-else class="text-center py-6 text-xs text-white/30">
            {{ t('market.no_popular_data') }}
        </div>
    </div>
</template>

<script setup>
import { t } from '../../lang';
import { resourceName } from '../../lang/gameNames';
import { getGameImageUrl, handleGameImageError } from '../../services/gameImageService';

defineProps({
    popularItems: { type: Array, default: () => [] }
});

defineEmits(['select-item']);
</script>
