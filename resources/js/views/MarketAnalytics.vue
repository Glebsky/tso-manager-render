<template>
    <div>
        <!-- Page Header -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6 sm:mb-8">
            <div>
                <h1 class="text-2xl sm:text-3xl font-bold text-white">{{ t('market.title') }}</h1>
                <p class="text-xs sm:text-sm text-white/40 mt-1">{{ t('market.subtitle') }}</p>
            </div>

            <div class="flex items-center gap-3">
                <MarketPeriodPicker v-model:period="period" />
                <button @click="activeTab = activeTab === 'analytics' ? 'settings' : 'analytics'"
                        class="btn-secondary text-xs py-2 px-4">
                    {{ activeTab === 'analytics' ? t('market.tab_settings') : t('market.tab_analytics') }}
                </button>
            </div>
        </div>

        <!-- TAB 1: ANALYTICS -->
        <div v-if="activeTab === 'analytics'" class="space-y-6">
            <MarketFilters v-model:searchQuery="searchQuery"
                           v-model:selectedServerId="selectedServerId"
                           :servers="servers" />

            <PopularItemsPanel :popularItems="popularItems" @select-item="selectedItem = $event" />

            <ArbitragePanel :arbitrageLoops="arbitrageLoops" />

            <MarketOffersTable :offers="rawOffers" :searchQuery="searchQuery" />
        </div>

        <!-- TAB 2: SETTINGS -->
        <div v-else class="glass-card p-6">
            <h3 class="text-base font-semibold text-white mb-4">{{ t('market.server_settings') }}</h3>
            <!-- Servers list -->
            <div class="space-y-4">
                <div v-for="srv in servers" :key="srv.id" class="flex items-center justify-between p-4 rounded-xl bg-white/[0.02] border border-white/5">
                    <div>
                        <p class="text-sm font-semibold text-white">{{ srv.display_name || srv.server_id }}</p>
                        <p class="text-xs text-white/40">{{ srv.account_username }} • {{ srv.locale }}</p>
                    </div>
                    <span class="badge badge-emerald text-xs">{{ srv.is_active ? t('market.active') : t('market.inactive') }}</span>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { t } from '../lang';
import { useMarketAnalytics } from '../composables/useMarketAnalytics';
import MarketFilters from '../components/market/MarketFilters.vue';
import MarketPeriodPicker from '../components/market/MarketPeriodPicker.vue';
import PopularItemsPanel from '../components/market/PopularItemsPanel.vue';
import ArbitragePanel from '../components/market/ArbitragePanel.vue';
import MarketOffersTable from '../components/market/MarketOffersTable.vue';

const activeTab = ref('analytics');

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
} = useMarketAnalytics(false);

onMounted(async () => {
    await loadServers();
});
</script>
