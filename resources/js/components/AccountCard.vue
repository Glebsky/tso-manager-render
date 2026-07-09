<template>
    <div class="glass-card overflow-hidden group hover:border-white/20 transition-all duration-500">
        <!-- Gradient accent stripe -->
        <div class="h-1 bg-gradient-to-r" :class="statusClass"></div>

        <div class="p-5">
            <!-- Header row -->
            <div class="flex items-start justify-between mb-4">
                <div class="flex items-center gap-3">
                    <!-- Avatar -->
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br flex items-center justify-center text-white font-bold text-sm shadow-lg"
                         :class="statusClass">
                        {{ avatarLetters }}
                    </div>
                    <div>
                        <h3 class="font-semibold text-white group-hover:text-emerald-400 transition-colors">
                            {{ account.nickname || account.username }}
                        </h3>
                        <p class="text-xs text-white/40 truncate max-w-[150px]" :title="account.username">
                            {{ account.username }}
                        </p>
                    </div>
                </div>

                <!-- Status -->
                <div class="flex items-center gap-2">
                    <div class="w-2 h-2 rounded-full" :class="statusDotClass"></div>
                    <span class="text-xs text-white/40 capitalize">{{ account.status || 'offline' }}</span>
                </div>
            </div>

            <!-- Info row -->
            <div class="flex items-center gap-3 mb-4">
                <!-- Region badge -->
                <span class="badge badge-info uppercase">
                    <svg class="w-3 h-3 mr-1" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 0 1 7.843 4.582M12 3a8.997 8.997 0 0 0-7.843 4.582m15.686 0A11.953 11.953 0 0 1 12 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0 1 21 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0 1 12 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 0 1 3 12c0-1.605.42-3.113 1.157-4.418" />
                    </svg>
                    {{ account.region || 'N/A' }}
                </span>

                <!-- Building count -->
                <span v-if="buildingCount !== null" class="badge badge-neutral">
                    <svg class="w-3 h-3 mr-1" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Z" />
                    </svg>
                    {{ buildingCount }} buildings
                </span>

                <!-- Last sync -->
                <span v-if="account.last_sync_at" class="text-xs text-white/30 flex items-center gap-1">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                    {{ formatSyncTime(account.last_sync_at) }}
                </span>
            </div>

            <!-- Action buttons -->
            <div class="flex items-center gap-2 pt-3 border-t border-white/5">
                <button @click="syncAccount" :disabled="syncing"
                        class="btn-secondary btn-sm flex items-center gap-1.5 hover:border-emerald-500/30 hover:text-emerald-400 disabled:opacity-50">
                    <svg class="w-3.5 h-3.5" :class="{ 'animate-spin': syncing }" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182" />
                    </svg>
                    {{ syncing ? 'Syncing...' : 'Sync' }}
                </button>

                <button v-if="buildingCount !== null" @click="expanded = !expanded"
                        class="btn-secondary btn-sm flex items-center gap-1.5 hover:border-blue-500/30 hover:text-blue-400">
                    <svg class="w-3.5 h-3.5 transition-transform duration-300" :class="{ 'rotate-180': expanded }" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                    </svg>
                    Buildings
                </button>

                <button @click="deleteAccount"
                        class="btn-secondary btn-sm text-red-400/60 hover:text-red-400 hover:border-red-500/30 ml-auto">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                    </svg>
                </button>
            </div>
        </div>

        <!-- Expanded Buildings List -->
        <div v-show="expanded" class="border-t border-white/5 bg-white/[0.01]">
            <div class="p-4 space-y-1.5">
                <p class="text-xs text-white/30 font-medium uppercase tracking-wider mb-3 px-1">Buildings</p>
                <div class="max-h-72 overflow-y-auto space-y-1.5">
                    <building-row v-for="b in parsedBuildings" :key="b.buildingGrid"
                                  :building="b" :account-id="account.id"
                                  @action-success="$emit('action-success')" />
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { ref, computed } from 'vue';
import axios from 'axios';
import { showToast } from '../toast';
import BuildingRow from './BuildingRow.vue';

export default {
    name: 'AccountCard',
    components: { BuildingRow },
    props: {
        account: {
            type: Object,
            required: true
        }
    },
    emits: ['sync-success', 'delete-success', 'action-success'],
    setup(props, { emit }) {
        const expanded = ref(false);
        const syncing = ref(false);

        const statusClass = computed(() => {
            const colors = {
                online: 'from-emerald-500 to-teal-500',
                syncing: 'from-amber-500 to-orange-500',
                error: 'from-red-500 to-rose-500',
                offline: 'from-gray-500 to-gray-600'
            };
            return colors[props.account.status] || colors.offline;
        });

        const statusDotClass = computed(() => {
            return 'status-' + (props.account.status || 'offline');
        });

        const avatarLetters = computed(() => {
            const name = props.account.nickname || props.account.username || '?';
            return name.substring(0, 2).toUpperCase();
        });

        const zoneObject = computed(() => {
            if (!props.account.zone_data) return null;
            try {
                return typeof props.account.zone_data === 'string'
                    ? JSON.parse(props.account.zone_data)
                    : props.account.zone_data;
            } catch (e) {
                return null;
            }
        });

        const buildingCount = computed(() => {
            return zoneObject.value && zoneObject.value.buildings
                ? zoneObject.value.buildings.length
                : null;
        });

        const parsedBuildings = computed(() => {
            return zoneObject.value && zoneObject.value.buildings
                ? zoneObject.value.buildings
                : [];
        });

        const formatSyncTime = (timeStr) => {
            if (!timeStr) return '';
            const date = new Date(timeStr);
            const now = new Date();
            const diffMs = now - date;
            const diffMins = Math.floor(diffMs / 60000);
            
            if (diffMins < 1) return 'just now';
            if (diffMins < 60) return `${diffMins}m ago`;
            const diffHours = Math.floor(diffMins / 60);
            if (diffHours < 24) return `${diffHours}h ago`;
            return date.toLocaleDateString();
        };

        const syncAccount = async () => {
            syncing.value = true;
            try {
                const res = await axios.post(`/api/accounts/${props.account.id}/sync`);
                if (res.data.success) {
                    showToast('Account synced successfully!');
                    emit('sync-success', res.data.zone_data);
                } else {
                    showToast(res.data.message || 'Sync failed.', 'error');
                }
            } catch (e) {
                showToast(e.response?.data?.message || 'Sync request failed.', 'error');
            } finally {
                syncing.value = false;
            }
        };

        const deleteAccount = async () => {
            if (!confirm('Are you sure you want to delete this account?')) return;
            try {
                const res = await axios.delete(`/api/accounts/${props.account.id}`);
                if (res.data.success) {
                    showToast('Account deleted.');
                    emit('delete-success');
                }
            } catch (e) {
                showToast('Failed to delete account.', 'error');
            }
        };

        return {
            expanded,
            syncing,
            statusClass,
            statusDotClass,
            avatarLetters,
            buildingCount,
            parsedBuildings,
            formatSyncTime,
            syncAccount,
            deleteAccount
        };
    }
};
</script>
