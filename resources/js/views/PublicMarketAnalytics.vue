<template>
    <div class="max-w-7xl mx-auto space-y-8 pb-12 transition-all duration-500 ease-out">
        <!-- Header -->
        <div class="glass-card p-4 sm:p-6 border-white/10 shadow-2xl relative z-30">
            <div class="flex items-center justify-between gap-3 flex-wrap">
                <div class="flex items-center gap-3">
                    <div class="w-11 h-11 rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-600 flex items-center justify-center shadow-lg shadow-emerald-500/25">
                        <svg class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18 9 11.25l4.306 4.307a11.95 11.95 0 0 1 5.814-5.518l2.74-1.22m0 0-3.75-1.002m3.75 1.002-1.002 3.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                    </div>
                    <div>
                        <h1 class="text-xl sm:text-3xl font-bold text-white tracking-tight">TSO Market Analytics</h1>
                        <p class="text-white/50 text-xs sm:text-sm">{{ t('market.public_subtitle') }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <MarketPeriodPicker v-model:period="period" />
                    <LanguageSwitcher />
                </div>
            </div>
        </div>

        <!-- Filters -->
        <MarketFilters v-model:searchQuery="searchQuery"
                       v-model:selectedServerId="selectedServerId"
                       :servers="servers" />

        <!-- Popular Items -->
        <PopularItemsPanel :popularItems="popularItems" @select-item="selectedItem = $event" />

        <!-- Arbitrage Loops -->
        <ArbitragePanel :arbitrageLoops="arbitrageLoops" />

        <!-- Offers Table -->
        <MarketOffersTable :offers="rawOffers" :searchQuery="searchQuery" />
    </div>
</template>

<script setup>
import { onMounted } from 'vue';
import { t } from '../lang';
import { useMarketAnalytics } from '../composables/useMarketAnalytics';
import LanguageSwitcher from '../components/LanguageSwitcher.vue';
import MarketFilters from '../components/market/MarketFilters.vue';
import MarketPeriodPicker from '../components/market/MarketPeriodPicker.vue';
import PopularItemsPanel from '../components/market/PopularItemsPanel.vue';
import ArbitragePanel from '../components/market/ArbitragePanel.vue';
import MarketOffersTable from '../components/market/MarketOffersTable.vue';

const {
    loading,
    servers,
    selectedServerId,
    selectedItem,
    selectedTarget,
    period,
    searchQuery,
    rawOffers,
    analyticsData,
    arbitrageLoops,
    popularItems,
    loadServers
} = useMarketAnalytics(true);

onMounted(async () => {
    await loadServers();
});
</script>
