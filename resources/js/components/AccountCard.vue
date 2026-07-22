<template>
    <div class="glass-card overflow-hidden group hover:border-white/20 transition-all duration-500">
        <!-- Gradient accent stripe -->
        <div class="h-1 bg-gradient-to-r relative overflow-hidden animate-glow" :class="statusClass.gradient" :style="{ '--glow': statusClass.glow }">
            <div class="absolute inset-0 animate-shimmer opacity-60"></div>
        </div>

        <div class="p-5">
            <!-- Header row -->
            <div class="flex items-start justify-between mb-4">
                <div class="flex items-center gap-3">
                    <!-- Avatar -->
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br flex items-center justify-center text-white font-bold text-sm shadow-lg overflow-hidden flex-shrink-0"
                         :class="statusClass.gradient">
                        <img v-if="avatarUrl" :src="avatarUrl" :alt="localAccount.nickname || localAccount.username" class="w-full h-full object-cover" @error="$event.target.style.display='none'">
                        <span v-else>{{ avatarLetters }}</span>
                    </div>
                    <router-link :to="'/accounts/' + localAccount.id" class="block">
                        <h3 class="font-semibold text-white hover:text-emerald-400 transition-colors cursor-pointer">
                            {{ localAccount.nickname || localAccount.username }}
                        </h3>
                        <p class="text-xs text-white/40 truncate max-w-[150px]" :title="localAccount.username">
                            {{ localAccount.username }}
                        </p>
                    </router-link>
                </div>

                <!-- Status -->
                <div class="flex items-center gap-2">
                    <div class="w-2 h-2 rounded-full" :class="statusDotClass"></div>
                    <span class="text-xs text-white/40 capitalize">{{ t('card.status.' + (localAccount.status || 'offline')) }}</span>
                </div>
            </div>

            <!-- Info row -->
            <div class="flex items-center gap-3 mb-4 flex-wrap">
                <!-- Market Connected Badge -->
                <span v-if="localAccount.is_market_connected" class="badge bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 flex items-center gap-1.5" title="Connected to Market Analysis">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                    <span class="font-medium text-xs">{{ t('card.market_connected') }}</span>
                </span>

                <!-- Server name badge -->
                <span v-if="serverName" class="badge badge-success bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 flex items-center gap-1">
                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 14.25h13.5m-13.5 0a3 3 0 0 1-3-3V3.75a3 3 0 0 1 3-3h13.5a3 3 0 0 1 3 3v7.5a3 3 0 0 1-3 3m-13.5 0a3 3 0 0 0-3 3v3.75a3 3 0 0 0 3 3h13.5a3 3 0 0 0 3-3V17.25a3 3 0 0 0-3-3" />
                    </svg>
                    {{ serverName }}
                </span>

                <!-- Building count -->
                <span v-if="buildingCount !== null" class="badge badge-neutral">
                    <svg class="w-3 h-3 mr-1" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Z" />
                    </svg>
                    {{ t('card.buildings_count', { count: buildingCount }) }}
                </span>

                <!-- Last sync -->
                <span v-if="localAccount.last_sync_at" class="text-xs text-white/30 flex items-center gap-1">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                    {{ formatSyncTime(localAccount.last_sync_at) }}
                </span>
            </div>

            <!-- Action buttons -->
            <div class="flex items-center gap-2 pt-3 border-t border-white/5">
                <button @click="syncAccount" :disabled="syncing"
                        class="btn-secondary btn-sm flex items-center gap-1.5 hover:border-emerald-500/30 hover:text-emerald-400 disabled:opacity-50 transition-all duration-300"
                        :class="{ 'animate-pulse shadow-lg shadow-emerald-500/40': syncing }">
                    <svg class="w-3.5 h-3.5" :class="{ 'animate-spin': syncing }" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182" />
                    </svg>
                    {{ syncing ? t('card.syncing') : t('card.sync') }}
                </button>

                <button @click="goToDetail"
                        class="btn-secondary btn-sm flex items-center gap-1.5 hover:border-blue-500/30 hover:text-blue-400">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                    </svg>
                    {{ t('card.detail') }}
                </button>

                <button @click="deleteAccount"
                        class="btn-secondary btn-sm text-red-400/60 hover:text-red-400 hover:border-red-500/30 ml-auto">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                    </svg>
                </button>
            </div>
        </div>
    </div>
</template>

<script>
import { ref, computed, watch } from 'vue';
import { t } from '../lang';
import { useRouter } from 'vue-router';
import axios from 'axios';
import { showToast } from '../toast';

export default {
    name: 'AccountCard',
    props: {
        account: {
            type: Object,
            required: true
        }
    },
    emits: ['sync-success', 'delete-success', 'action-success'],
    setup(props, { emit }) {
        const router = useRouter();
        const syncing = ref(false);
        const localAccount = ref({ ...props.account });

        watch(() => props.account, (newVal) => {
            localAccount.value = { ...newVal };
        }, { deep: true });

        const statusClass = computed(() => {
            const colors = {
                online: {
                    gradient: 'from-emerald-500 to-teal-500',
                    glow: '#10b981'
                },
                syncing: {
                    gradient: 'from-amber-500 to-orange-500',
                    glow: '#f59e0b'
                },
                error: {
                    gradient: 'from-red-500 to-rose-500',
                    glow: '#ef4444'
                },
                offline: {
                    gradient: 'from-gray-500 to-gray-600',
                    glow: '#6b7280'
                }
            };
            return colors[localAccount.value.status] || colors.offline;
        });

        const statusDotClass = computed(() => {
            return 'status-' + (localAccount.value.status || 'offline');
        });

        const avatarLetters = computed(() => {
            const name = localAccount.value.nickname || localAccount.value.username || '?';
            return name.substring(0, 2).toUpperCase();
        });

        const avatarUrl = computed(() => {
            const avatarId = zoneObject.value?.avatarId;
            if (!avatarId) return null;
            const idNum = parseInt(avatarId);
            if (idNum >= 1 && idNum <= 60) {
                return `/images/avatars/${idNum}.webp`;
            }
            return `https://settlersonlinewiki.eu/images/avatars/avatar_${avatarId}.webp`;
        });

        const zoneObject = computed(() => {
            if (!localAccount.value.zone_data) return null;
            try {
                return typeof localAccount.value.zone_data === 'string'
                    ? JSON.parse(localAccount.value.zone_data)
                    : localAccount.value.zone_data;
            } catch (e) {
                return null;
            }
        });

        const buildingCount = computed(() => {
            return zoneObject.value && zoneObject.value.buildings
                ? zoneObject.value.buildings.length
                : null;
        });

        const formatSyncTime = (timeStr) => {
            if (!timeStr) return '';
            const date = new Date(timeStr);
            const now = new Date();
            const diffMs = now - date;
            const diffMins = Math.floor(diffMs / 60000);

            if (diffMins < 1) return t('card.just_now');
            if (diffMins < 60) return t('card.minutes_ago', { count: diffMins });
            const diffHours = Math.floor(diffMins / 60);
            if (diffHours < 24) return t('card.hours_ago', { count: diffHours });
            return date.toLocaleDateString();
        };

        const syncAccount = async () => {
            syncing.value = true;
            localAccount.value.status = 'syncing';
            try {
                const res = await axios.post(`/api/accounts/${localAccount.value.id}/sync`);
                if (res.data.success) {
                    showToast(t('card.synced'));
                    if (res.data.account) {
                        localAccount.value = res.data.account;
                    }
                    emit('sync-success', res.data.account || localAccount.value);
                } else {
                    localAccount.value.status = 'error';
                    showToast(res.data.message || t('card.sync_failed'), 'error');
                    emit('sync-success');
                }
            } catch (e) {
                if (e.response?.data?.account) {
                    localAccount.value = e.response.data.account;
                } else {
                    localAccount.value.status = 'error';
                }
                showToast(e.response?.data?.message || t('card.sync_request_failed'), 'error');
                emit('sync-success');
            } finally {
                syncing.value = false;
            }
        };

        const deleteAccount = async () => {
            if (!confirm(t('card.confirm_delete'))) return;
            try {
                const res = await axios.delete(`/api/accounts/${localAccount.value.id}`);
                if (res.data.success) {
                    showToast(t('card.deleted'));
                    emit('delete-success');
                }
            } catch (e) {
                showToast(t('card.delete_failed'), 'error');
            }
        };

        const goToDetail = () => {
            router.push(`/accounts/${localAccount.value.id}`);
        };

        const REGION_SERVERS = {
            ru: { flag: '🇷🇺', name: 'RU Market', locale: 'RU' },
            de: { flag: '🇩🇪', name: 'DE Market', locale: 'DE' },
            en: { flag: '🇬🇧', name: 'EN Market', locale: 'EN' },
            us: { flag: '🇺🇸', name: 'US Market', locale: 'EN' },
            fr: { flag: '🇫🇷', name: 'FR Market', locale: 'FR' },
            pl: { flag: '🇵🇱', name: 'PL Market', locale: 'PL' },
            es: { flag: '🇪🇸', name: 'ES Market', locale: 'ES' },
        };

        const marketServerInfo = computed(() => {
            const reg = String(localAccount.value?.region || '').toLowerCase();
            if (!reg) return null;
            const info = REGION_SERVERS[reg];
            if (info) return { ...info, region: reg };
            return { flag: '🌐', name: `${reg.toUpperCase()} Market`, locale: reg.toUpperCase(), region: reg };
        });

        const serverName = computed(() => {
            return zoneObject.value?.gameWorldName || null;
        });

        return {
            syncing,
            localAccount,
            statusClass,
            statusDotClass,
            avatarLetters,
            avatarUrl,
            buildingCount,
            formatSyncTime,
            syncAccount,
            deleteAccount,
            goToDetail,
            serverName,
            marketServerInfo,
        };
    }
};</script>
