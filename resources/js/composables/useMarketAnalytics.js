import { ref, computed } from 'vue';
import { marketApi } from '../services/api/market';
import { cachedGet } from '../services/apiCacheService';

export function useMarketAnalytics(isPublic = false) {
    const loading = ref(false);
    const servers = ref([]);
    const selectedServerId = ref(null);
    const selectedItem = ref('Wood');
    const selectedTarget = ref('Coins');
    const period = ref('7d');
    const searchQuery = ref('');

    const rawOffers = ref([]);
    const analyticsData = ref(null);
    const arbitrageLoops = ref([]);
    const popularItems = ref([]);

    const currentServer = computed(() => {
        return servers.value.find(s => s.id === selectedServerId.value) || servers.value[0] || null;
    });

    const loadServers = async () => {
        try {
            const res = isPublic
                ? await cachedGet('/api/market/servers')
                : await marketApi.fetchMarketServers();
            const data = res.data || res;
            servers.value = data.servers || [];
            if (!selectedServerId.value && servers.value.length > 0) {
                selectedServerId.value = servers.value[0].id;
            }
            return data;
        } catch (e) {
            console.error('Failed to load market servers:', e);
            return null;
        }
    };

    return {
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
        currentServer,
        loadServers,
    };
}
