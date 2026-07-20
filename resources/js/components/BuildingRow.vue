<template>
    <div class="flex items-center justify-between p-3 rounded-xl bg-white/[0.02] hover:bg-white/5 border border-transparent hover:border-white/5 transition-all duration-300 group">
        <div class="flex items-center gap-3">
            <!-- Building icon -->
            <div class="w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0"
                 :class="isProducing ? 'bg-emerald-500/10 text-emerald-400' : 'bg-white/5 text-white/30'">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Z" />
                </svg>
            </div>

            <div>
                <p class="text-sm font-medium text-white/80 group-hover:text-white transition-colors">
                    {{ formattedName }} <span class="text-white/30 text-xs">Lvl {{ building.upgradeLevel || 1 }}</span>
                </p>
                <div class="flex items-center gap-2 mt-0.5">
                    <span class="text-[10px] text-white/30 font-mono">Grid #{{ grid }}</span>
                </div>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <!-- Status badge -->
            <span class="badge text-[10px]" :class="isProducing ? 'badge-success' : 'badge-neutral'">
                {{ isProducing ? 'Producing' : 'Stopped' }}
            </span>

            <!-- Action buttons -->
            <button @click="toggleProduction" :disabled="loading"
                    class="btn-secondary btn-sm text-[10px] disabled:opacity-50"
                    :class="isProducing 
                        ? 'text-amber-400/60 hover:text-amber-400 hover:border-amber-500/30' 
                        : 'text-emerald-400/60 hover:text-emerald-400 hover:border-emerald-500/30'">
                {{ loading ? '...' : (isProducing ? 'Stop' : 'Start') }}
            </button>
        </div>
    </div>
</template>

<script>
import { ref, computed } from 'vue';
import { buildingName } from '../lang/gameNames';
import axios from 'axios';
import { showToast } from '../toast';

export default {
    name: 'BuildingRow',
    props: {
        building: {
            type: Object,
            required: true
        },
        accountId: {
            type: Number,
            required: true
        }
    },
    emits: ['action-success'],
    setup(props, { emit }) {
        const loading = ref(false);
        const grid = computed(() => props.building.buildingGrid || 0);
        
        const isProducing = computed(() => {
            return !!props.building.isProductionActive;
        });

        const formattedName = computed(() => buildingName(props.building.buildingName_string || props.building.buildingName || 'Building'));

        const toggleProduction = async () => {
            loading.value = true;
            const actionType = isProducing.value ? 'stop_production' : 'start_production';
            try {
                const res = await axios.post(`/api/accounts/${props.accountId}/action`, {
                    action_type: actionType,
                    grid: grid.value
                });

                if (res.data.success) {
                    showToast(`Production ${isProducing.value ? 'stopped' : 'started'}!`);
                    emit('action-success');
                } else {
                    showToast(res.data.message || 'Action failed.', 'error');
                }
            } catch (e) {
                showToast(e.response?.data?.message || 'Action failed.', 'error');
            } finally {
                loading.value = false;
            }
        };

        return {
            grid,
            isProducing,
            formattedName,
            loading,
            toggleProduction
        };
    }
};
</script>
