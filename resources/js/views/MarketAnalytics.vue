<template>
    <div>
        <!-- Page Header -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6 sm:mb-8">
            <div>
                <h1 class="text-2xl sm:text-3xl font-bold text-white">{{ t('market.title') }}</h1>
                <p class="text-xs sm:text-sm text-white/40 mt-1">{{ t('market.subtitle') }}</p>
            </div>

            <div class="flex flex-col sm:flex-row sm:items-center gap-3 w-full sm:w-auto">
                <!-- Server Selector -->
                <div v-if="servers.length > 0" class="flex items-center gap-2 bg-white/5 border border-white/10 p-1.5 rounded-xl w-full sm:w-auto">
                    <span class="text-xs font-semibold text-white/40 uppercase tracking-wider px-2 shrink-0">{{ t('market.server') }}:</span>
                    <select v-model="selectedServerId" @change="onServerChange" :aria-label="t('market.server')" class="bg-dark-900 text-xs font-bold text-emerald-400 py-1.5 px-3 rounded-lg border border-emerald-500/20 focus:outline-none cursor-pointer w-full sm:max-w-[240px] truncate">
                        <optgroup v-for="(groupServers, countryCode) in groupedServers" :key="countryCode" :label="`${getLocaleFlag(countryCode)} ${countryCode}`">
                            <option v-for="srv in groupServers" :key="srv.server_id" :value="srv.server_id">
                                {{ srv.display_name }} ({{ srv.account ? srv.account.username : t('market.no_account') }})
                            </option>
                        </optgroup>
                    </select>
                </div>

                <!-- Tab Navigation -->
                <div class="flex items-center gap-1 bg-white/5 border border-white/10 p-1 rounded-xl shrink-0">
                    <button @click="activeTab = 'analytics'"
                            class="px-3 sm:px-4 py-2 rounded-lg text-xs font-semibold uppercase tracking-wider transition-all"
                            :class="activeTab === 'analytics' ? 'bg-emerald-500 text-white shadow-lg shadow-emerald-500/20' : 'text-white/50 hover:text-white'">
                        {{ t('market.tab_analytics') }}
                    </button>
                    <button @click="activeTab = 'settings'"
                            class="px-3 sm:px-4 py-2 rounded-lg text-xs font-semibold uppercase tracking-wider transition-all"
                            :class="activeTab === 'settings' ? 'bg-emerald-500 text-white shadow-lg shadow-emerald-500/20' : 'text-white/50 hover:text-white'">
                        {{ t('market.tab_settings') }}
                    </button>
                </div>
            </div>
        </div>

        <!-- TAB 1: ANALYTICS -->
        <div v-if="activeTab === 'analytics'" class="space-y-6">
            <!-- Servers loading placeholder -->
            <div v-if="loadingServers && servers.length === 0" class="glass-card p-10 flex flex-col items-center justify-center gap-3 text-emerald-400">
                <spinner size="lg" />
                <p class="text-xs text-white/40">{{ t('market.loading_servers') }}</p>
            </div>

            <!-- Active Server Status Notice / Empty State -->
            <div v-else-if="servers.length === 0" class="glass-card p-8 text-center space-y-4">
                <div class="w-12 h-12 rounded-2xl bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-amber-400 mx-auto">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-white">{{ t('market.no_servers_title') }}</h3>
                <p class="text-xs text-white/50 max-w-md mx-auto">
                    {{ t('market.no_servers_text') }}
                </p>
                <button @click="activeTab = 'settings'" class="btn-primary py-2 px-6 text-xs inline-flex items-center gap-2">
                    {{ t('market.go_to_settings') }}
                </button>
            </div>

            <div v-else-if="currentServerConnection && (!currentServerConnection.account_id || currentServerConnection.sync_status === 'error' || currentServerConnection.sync_status === 'not_configured')"
                 class="glass-card p-4 border-l-4"
                 :class="currentServerConnection.sync_status === 'error' ? 'border-l-red-500 bg-red-500/5' : 'border-l-amber-500 bg-amber-500/5'">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs">
                    <div class="flex items-center gap-3">
                        <span class="badge" :class="getSyncBadgeClass(currentServerConnection.sync_status)">
                            {{ currentServerConnection.sync_status }}
                        </span>
                        <div class="space-y-0.5">
                            <p class="font-semibold text-white">
                                Server {{ currentServerConnection.display_name }} ({{ currentServerConnection.locale }}):
                                <span v-if="!currentServerConnection.account_id" class="text-amber-400">{{ t('market.no_account_assigned') }}</span>
                                <span v-else-if="currentServerConnection.last_error" class="text-red-400">{{ currentServerConnection.last_error }}</span>
                                <span v-else class="text-white/60">{{ t('market.not_synced_yet') }}</span>
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <button v-if="currentServerConnection.account_id" @click="syncServerNow(currentServerConnection)" :disabled="syncing" class="btn-secondary py-1.5 px-3 text-xs flex items-center gap-1.5 disabled:opacity-50">
                            <svg class="w-3.5 h-3.5" :class="{ 'animate-spin': syncing }" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182" />
                            </svg>
                            <span>{{ syncing ? t('card.syncing') : t('market.sync_now') }}</span>
                        </button>
                        <button @click="activeTab = 'settings'" class="btn-secondary py-1.5 px-3 text-xs">
                            {{ t('market.manage_server') }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Selection Card (Dropdowns or Visual Grid) -->
            <div class="glass-card p-6 relative">
                <loading-overlay :show="loading || loadingPairs" :label="loadingPairs ? t('market.loading_pairs') : t('common.loading_data')" />
                <!-- Selector Header: Mode Switch & Mirror Button -->
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6 border-b border-white/5 pb-4">
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-semibold text-white/40 uppercase tracking-wider">{{ t('market.selection_mode') }}</span>
                        <div class="flex items-center gap-1 bg-white/5 border border-white/10 p-0.5 rounded-lg">
                            <button @click="selectionMode = 'dropdown'"
                                    class="px-3 py-1 rounded text-[10px] font-bold uppercase transition-all"
                                    :class="selectionMode === 'dropdown' ? 'bg-emerald-500 text-white' : 'text-white/50 hover:text-white'">
                                {{ t('market.dropdowns') }}
                            </button>
                            <button @click="selectionMode = 'visual'"
                                    class="px-3 py-1 rounded text-[10px] font-bold uppercase transition-all"
                                    :class="selectionMode === 'visual' ? 'bg-emerald-500 text-white' : 'text-white/50 hover:text-white'">
                                {{ t('market.visual_browser') }}
                            </button>
                        </div>
                    </div>

                    <!-- Reset Selection / Mirror / Copy Link Button -->
                    <div class="flex items-center gap-2">
                        <button v-if="selectedItem || selectedTarget" @click="resetSelection" class="btn-secondary py-1 px-3 text-xs bg-white/5 border border-white/10 text-white/50 hover:text-white hover:bg-white/10 rounded-lg transition-all">
                            {{ t('market.reset_selection') }}
                        </button>
                        <button v-if="selectedItem && selectedTarget" @click="mirrorSelection" class="btn-secondary py-1 px-3 text-xs bg-white/5 border border-white/10 text-white/70 hover:text-white hover:bg-white/10 rounded-lg transition-all flex items-center gap-1">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21 3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" />
                            </svg>
                            {{ t('market.mirror_trade') }}
                        </button>
                        <button v-if="selectedItem && selectedTarget" @click="copyPairLink" class="btn-secondary py-1 px-3 text-xs bg-white/5 border border-white/10 text-white/70 hover:text-white hover:bg-white/10 rounded-lg transition-all flex items-center gap-1">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244" />
                            </svg>
                            {{ t('market.copy_link') }}
                        </button>
                    </div>
                </div>

                <!-- Mode 1: Dropdown Selection -->
                <div v-if="selectionMode === 'dropdown'" class="grid grid-cols-1 md:grid-cols-2 gap-6 items-end">
                    <!-- Selling Item Selection -->
                    <div>
                        <label class="block text-xs font-medium text-white/40 mb-2 uppercase tracking-wider">{{ t('market.selling_item') }}</label>
                        <div class="relative">
                            <select v-model="selectedItem" @change="onItemChange" :aria-label="t('market.selling_item')" class="glass-select w-full">
                                <option value="" class="bg-dark-900">{{ t('market.select_selling') }}</option>
                                <option v-for="good in goods" :key="good.item_id" :value="good.item_id" class="bg-dark-900">
                                    {{ getItemName(good.item_name, good.item_id) }} ({{ good.item_id }})
                                </option>
                            </select>
                            <div class="absolute inset-y-0 right-3 flex items-center pointer-events-none">
                                <svg class="w-4 h-4 text-white/30" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                </svg>
                            </div>
                        </div>
                    </div>

                    <!-- Target Item Selection -->
                    <div>
                        <label class="block text-xs font-medium text-white/40 mb-2 uppercase tracking-wider">{{ t('market.target_item') }}</label>
                        <div class="relative">
                            <select v-model="selectedTarget" :disabled="!selectedItem" @change="fetchAnalytics" :aria-label="t('market.target_item')" class="glass-select w-full disabled:opacity-40">
                                <option value="" class="bg-dark-900">{{ t('market.select_target') }}</option>
                                <option v-for="target in targets" :key="target.target_item_id" :value="target.target_item_id" class="bg-dark-900">
                                    {{ getItemName(target.target_item_name, target.target_item_id) }} ({{ target.target_item_id }})
                                </option>
                            </select>
                            <div class="absolute inset-y-0 right-3 flex items-center pointer-events-none">
                                <svg class="w-4 h-4 text-white/30" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Mode 2: Visual Browser Selection -->
                <div v-else class="space-y-6">
                    <!-- Tab Navigation for Visual steps -->
                    <div class="flex items-center border-b border-white/10 gap-4">
                        <button @click="visualTab = 1"
                                class="pb-3 text-xs font-bold uppercase tracking-wider transition-all border-b-2"
                                :class="visualTab === 1 ? 'border-emerald-500 text-emerald-400' : 'border-transparent text-white/40 hover:text-white'">
                            {{ t('market.sell_resource') }}
                            <span v-if="selectedItem" class="ml-1 text-[10px] text-emerald-500 font-mono font-medium">({{ selectedItemName }})</span>
                        </button>
                        <button @click="visualTab = 2"
                                :disabled="!selectedItem"
                                class="pb-3 text-xs font-bold uppercase tracking-wider transition-all border-b-2 disabled:opacity-30 disabled:cursor-not-allowed"
                                :class="visualTab === 2 ? 'border-emerald-500 text-emerald-400' : 'border-transparent text-white/40 hover:text-white'">
                            {{ t('market.buy_resource') }}
                            <span v-if="selectedTarget" class="ml-1 text-[10px] text-emerald-500 font-mono font-medium">({{ selectedTargetName }})</span>
                        </button>
                    </div>

                    <!-- Step 1: Selling resource grid -->
                    <div v-if="visualTab === 1" class="grid grid-cols-3 sm:grid-cols-6 md:grid-cols-8 lg:grid-cols-12 gap-2 max-h-60 overflow-y-auto p-1.5">
                        <div v-for="good in allGoods" :key="good.item_id"
                             @click="selectVisualItem(good.item_id)"
                             class="flex flex-col items-center justify-center p-1.5 rounded-lg border cursor-pointer hover:border-emerald-500/40 hover:bg-white/[0.05] hover:shadow-md hover:shadow-emerald-500/5 text-center select-none transition-all duration-200"
                             :class="selectedItem === good.item_id ? 'bg-emerald-500/10 border-emerald-500 shadow shadow-emerald-500/10' : 'bg-white/[0.02] border-white/5 hover:border-white/20 hover:bg-white/[0.04]'"
                             :style="good.no_offers ? 'opacity:0.4' : ''"
                             :title="good.no_offers ? t('market.no_offers') : getItemName(good.item_name, good.item_id)">
                            <img :alt="getItemName(good.item_name, good.item_id)" :src="getResourceIcon(good.item_id)" @error="handleIconError($event, good.item_id)" class="w-6 h-6 object-contain mb-1 pointer-events-none" />
                            <span class="text-[9px] font-medium text-white/90 truncate w-full" :title="getItemName(good.item_name, good.item_id)">{{ getItemName(good.item_name, good.item_id) }}</span>
                        </div>
                        <div v-if="allGoods.length === 0 && !loading" class="col-span-full py-8 text-center text-xs text-white/30">
                            {{ t('market.no_resources_for_server', { server: selectedServerId }) }}
                        </div>
                    </div>

                    <!-- Step 2: Buying target resource grid -->
                    <div v-if="visualTab === 2" class="grid grid-cols-3 sm:grid-cols-6 md:grid-cols-8 lg:grid-cols-12 gap-2 max-h-60 overflow-y-auto p-1.5">
                        <div v-for="target in targets" :key="target.target_item_id"
                             @click="selectVisualTarget(target.target_item_id)"
                             class="flex flex-col items-center justify-center p-1.5 rounded-lg border cursor-pointer hover:border-emerald-500/40 hover:bg-white/[0.05] hover:shadow-md hover:shadow-emerald-500/5 text-center select-none transition-all duration-200"
                             :class="selectedTarget === target.target_item_id ? 'bg-emerald-500/10 border-emerald-500 shadow shadow-emerald-500/10' : 'bg-white/[0.02] border-white/5 hover:border-white/20 hover:bg-white/[0.04]'">
                            <img :alt="getItemName(target.target_item_name, target.target_item_id)" :src="getResourceIcon(target.target_item_id)" @error="handleIconError($event, target.target_item_id)" class="w-6 h-6 object-contain mb-1 pointer-events-none" />
                            <span class="text-[9px] font-medium text-white/90 truncate w-full" :title="getItemName(target.target_item_name, target.target_item_id)">{{ getItemName(target.target_item_name, target.target_item_id) }}</span>
                        </div>
                        <div v-if="targets.length === 0 && !loadingPairs" class="col-span-full py-8 text-center text-xs text-white/30">
                            Please select a selling item first.
                        </div>
                    </div>
                </div>

                <!-- Selection Path Indicator -->
                <div v-if="selectedItemName && selectedTargetName" class="mt-5 pt-5 border-t border-white/5 flex items-center gap-3 text-lg font-semibold text-emerald-400">
                    <img :alt="selectedItemName" :src="getResourceIcon(selectedItem)" @error="handleIconError($event, selectedItem)" class="w-6 h-6 object-contain" />
                    <span>{{ selectedItemName }}</span>
                    <svg class="w-5 h-5 text-white/30" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                    </svg>
                    <img :alt="selectedTargetName" :src="getResourceIcon(selectedTarget)" @error="handleIconError($event, selectedTarget)" class="w-6 h-6 object-contain" />
                    <span>{{ selectedTargetName }}</span>
                </div>
            </div>

            <!-- Analytics loading placeholder (first fetch for a pair) -->
            <div v-if="loadingChart && !stats" class="glass-card p-12 flex flex-col items-center justify-center gap-3 text-emerald-400">
                <spinner size="lg" />
                <p class="text-xs text-white/40">{{ t('market.loading_chart') }}</p>
            </div>

            <!-- Analysis Dashboard (Visible if both selected) -->
            <div v-if="selectedItem && selectedTarget && stats" class="relative grid grid-cols-1 lg:grid-cols-3 gap-6">
                <loading-overlay :show="loadingChart" :label="t('market.loading_chart')" />
                <!-- Stats Swarm (Left columns) -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Pricing Stats -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                        <div class="glass-card p-4">
                            <span class="text-[10px] font-semibold text-white/30 uppercase tracking-wider block">{{ t('market.current_price') }}</span>
                            <span class="text-xl font-bold text-white mt-1 block">{{ stats.current }}</span>
                            <span class="text-[10px] text-white/40 block mt-0.5">{{ selectedTargetName }}</span>
                        </div>
                        <div class="glass-card p-4">
                            <span class="text-[10px] font-semibold text-white/30 uppercase tracking-wider block">{{ t('market.average_price') }}</span>
                            <span class="text-xl font-bold text-emerald-400 mt-1 block">{{ stats.average }}</span>
                            <span class="text-[10px] text-white/40 block mt-0.5">{{ selectedTargetName }}</span>
                        </div>
                        <div class="glass-card p-4">
                            <span class="text-[10px] font-semibold text-white/30 uppercase tracking-wider block">{{ t('market.min_price') }}</span>
                            <span class="text-xl font-bold text-blue-400 mt-1 block">{{ stats.minimum }}</span>
                            <span class="text-[10px] text-white/40 block mt-0.5">{{ selectedTargetName }}</span>
                        </div>
                        <div class="glass-card p-4">
                            <span class="text-[10px] font-semibold text-white/30 uppercase tracking-wider block">{{ t('market.max_price') }}</span>
                            <span class="text-xl font-bold text-red-400 mt-1 block">{{ stats.maximum }}</span>
                            <span class="text-[10px] text-white/40 block mt-0.5">{{ selectedTargetName }}</span>
                        </div>
                    </div>

                    <!-- Price Dynamic Chart Card (shared component) -->
                    <market-price-chart
                        :history="history"
                        :stats="stats"
                        :periods="periods"
                        :selected-period="selectedPeriod"
                        :title="t('market.price_history', { item: selectedItemName, target: selectedTargetName })"
                        :item-id="selectedItem"
                        :target-id="selectedTarget"
                        :item-name="selectedItemName"
                        :target-name="selectedTargetName"
                        gradient-id="adminPriceGrad"
                        :get-resource-icon="getResourceIcon"
                        :handle-icon-error="handleIconError"
                        :format-volume="formatVolume"
                        @change-period="changePeriod" />

                    <!-- Demand Dynamic Chart Card (shared component) -->
                    <market-demand-chart
                        :history="history"
                        :title="t('market.volume_offers')"
                        :format-volume="formatVolume" />
                </div>

                <!-- Calculator & Market Details Side Panel (Right columns) -->
                <div class="space-y-6">
                    <!-- Calculator Card -->
                    <div class="glass-card p-6">
                        <div class="flex items-center gap-3 mb-5 border-b border-white/5 pb-3">
                            <div class="w-8 h-8 rounded-lg bg-emerald-500/10 flex items-center justify-center text-emerald-400">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 15.75V18m-3-3v3m-3-3v3M9 3h12a2.25 2.25 0 0 1 2.25 2.25v13.5A2.25 2.25 0 0 1 21 21H9a2.25 2.25 0 0 1-2.25-2.25V5.25A2.25 2.25 0 0 1 9 3Zm2.25 3h7.5a.75.75 0 0 0 .75-.75V4.5a.75.75 0 0 0-.75-.75h-7.5a.75.75 0 0 0-.75.75v.75a.75.75 0 0 0 .75.75Z" />
                                </svg>
                            </div>
                            <h3 class="text-sm font-semibold text-white">{{ t('market.cost_calculator') }}</h3>
                        </div>

                        <div class="space-y-5">
                            <div>
                                <label class="block text-xs font-medium text-white/40 mb-2 uppercase tracking-wider">{{ t('market.amount_of', { item: selectedItemName }) }}</label>
                                <input type="number" v-model.number="calcAmount" min="1" class="glass-input w-full font-mono text-white text-lg"/>
                            </div>

                            <!-- Direct estimated revenue -->
                            <div class="p-4 rounded-xl border border-emerald-500/10 bg-emerald-500/[0.02]">
                                <span class="text-[10px] font-semibold text-emerald-400/70 uppercase tracking-wider block">{{ t('market.estimated_revenue') }}</span>
                                <div class="flex items-baseline gap-2 mt-1">
                                    <span class="text-2xl font-bold text-emerald-400 font-mono">{{ calculatedCost }}</span>
                                    <span class="text-xs text-white/40">{{ selectedTargetName }}</span>
                                </div>
                                <span class="text-[9px] text-white/20 block mt-2">{{ t('market.formula_direct', { amount: calcAmount || 0, price: stats.average }) }}</span>
                            </div>

                            <!-- Mirrored estimated cost -->
                            <div v-if="mirroredStats" class="p-4 rounded-xl border border-blue-500/10 bg-blue-500/[0.02]">
                                <span class="text-[10px] font-semibold text-blue-400/70 uppercase tracking-wider block">{{ t('market.estimated_cost') }}</span>
                                <div class="flex items-baseline gap-2 mt-1">
                                    <span class="text-2xl font-bold text-blue-400 font-mono">{{ calculatedMirroredCost }}</span>
                                    <span class="text-xs text-white/40">{{ selectedTargetName }}</span>
                                </div>
                                <span class="text-[9px] text-white/20 block mt-2">{{ t('market.formula_mirrored', { amount: calcAmount || 0, price: mirroredStats.average }) }}</span>
                            </div>
                            <div v-else class="p-4 rounded-xl border border-white/5 bg-white/[0.01] text-center text-xs text-white/30">
                                {{ t('market.no_mirrored_trades', { target: selectedTargetName, item: selectedItemName }) }}
                            </div>
                        </div>
                    </div>

                    <!-- Selected pair market details -->
                    <div class="glass-card p-6">
                        <h3 class="text-sm font-semibold text-white mb-4">{{ t('market.info') }}</h3>
                        <div class="space-y-3 text-xs">
                            <div class="flex justify-between py-2 border-b border-white/5">
                                <span class="text-white/40">{{ t('market.total_volume') }}</span>
                                <span class="text-white font-mono font-medium">{{ activeVolume }} {{ selectedItemName }}</span>
                            </div>
                            <div class="flex justify-between py-2 border-b border-white/5">
                                <span class="text-white/40">{{ t('market.offers_count') }}</span>
                                <span class="text-white font-mono font-medium">{{ activeOffersCount }}</span>
                            </div>
                            <div class="flex justify-between py-2 border-b border-white/5">
                                <span class="text-white/40">{{ t('market.active_sellers') }}</span>
                                <span class="text-white font-mono font-medium">{{ activeSellersCount }}</span>
                            </div>
                            <div class="flex justify-between py-2 last:border-0">
                                <span class="text-white/40">{{ t('market.trend') }}</span>
                                <span class="font-semibold" :class="priceTrendClass">{{ priceTrendText }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Popular Items & Current Active Market -->
            <div class="mt-8 space-y-6 relative">
                <loading-overlay :show="loading" :label="t('market.loading_data')" />
                <!-- Most Popular Items Card -->
                <div class="glass-card p-6 animate-fade-in-up">
                    <div class="flex items-center justify-between gap-3 mb-6 border-b border-white/5 pb-3">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center text-white">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18 9 11.25l4.306 4.307a11.95 11.95 0 0 1 5.814-5.518l2.74-1.22m0 0-3.75-1.002m3.75 1.002-1.002 3.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                </svg>
                            </div>
                            <h2 class="text-lg font-semibold text-white">{{ t('market.popular_items') }}</h2>
                        </div>
                        <button @click="togglePopularItems" :aria-label="t('market.popular_items')" :aria-expanded="showPopularItems ? 'true' : 'false'" class="text-white/40 hover:text-white transition-colors">
                            <svg v-if="showPopularItems" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7" />
                            </svg>
                            <svg v-else class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                    </div>

                    <div v-show="showPopularItems" class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-b border-white/5 text-[10px] font-semibold text-white/30 uppercase tracking-wider">
                                    <th class="py-3 px-4">{{ t('market.item_name') }}</th>
                                    <th class="py-3 px-4">{{ t('market.item_code') }}</th>
                                    <th class="py-3 px-4 text-right">{{ t('market.active_offers') }}</th>
                                    <th class="py-3 px-4 text-right">{{ t('market.unique_sellers') }}</th>
                                    <th class="py-3 px-4 text-right">{{ t('market.active_volume') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/5 text-sm text-white/70">
                                <tr v-for="item in popular" :key="item.item_id" class="hover:bg-white/[0.01] transition-all">
                                    <td class="py-3 px-4 font-semibold text-white">
                                        <div class="flex items-center gap-2">
                                            <img :alt="getItemName(item.item_name, item.item_id)" :src="getResourceIcon(item.item_id)" @error="handleIconError($event, item.item_id)" class="w-5 h-5 object-contain" />
                                            <span>{{ getItemName(item.item_name, item.item_id) }}</span>
                                        </div>
                                    </td>
                                    <td class="py-3 px-4 font-mono text-xs text-white/35">{{ item.item_id }}</td>
                                    <td class="py-3 px-4 text-right text-emerald-400 font-mono font-medium">{{ t('market.offers_count_num', { count: item.offers_count }) }}</td>
                                    <td class="py-3 px-4 text-right text-blue-400 font-mono">{{ t('market.sellers_count_num', { count: item.sellers_count }) }}</td>
                                    <td class="py-3 px-4 text-right font-mono">{{ formatVolume(item.total_volume) }} {{ t('market.units_short') }}</td>
                                </tr>
                                <tr v-if="popular.length === 0">
                                    <td colspan="5" class="py-8 text-center text-white/20">
                                        {{ t('market.no_data_for_server', { server: selectedServerId }) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Profitable Arbitrage Schemes Card -->
                <div class="glass-card p-6 animate-fade-in-up">
                    <div class="flex items-center justify-between mb-6 border-b border-white/5 pb-3">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-emerald-500 to-green-600 flex items-center justify-center text-white">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 12c0-1.232-.046-2.453-.138-3.662a4.006 4.006 0 0 0-3.7-3.7 48.656 48.656 0 0 0-7.324 0 4.006 4.006 0 0 0-3.7 3.7C4.547 9.547 4.5 10.768 4.5 12s.047 2.453.138 3.662a4.006 4.006 0 0 0 3.7 3.7 48.656 48.656 0 0 0 7.324 0 4.006 4.006 0 0 0 3.7-3.7C19.453 14.453 19.5 13.232 19.5 12Zm0 0h.008v.008h-.008V12Zm-3 0h.008v.008h-.008V12c0-1.68-.282-3.297-.802-4.806m-9.396 0A20.732 20.732 0 0 1 12 6.75c1.455 0 2.843.15 4.198.437M12 6.75a20.733 20.733 0 0 0-4.198.437m0 0A20.73 20.73 0 0 0 7 12c0 1.68.282 3.297.802 4.806m9.396 0A20.73 20.73 0 0 1 12 17.25c-1.455 0-2.843-.15-4.198-.437M12 17.25a20.73 20.73 0 0 0 4.198-.437" />
                                </svg>
                            </div>
                            <div class="flex flex-col">
                                <h2 class="text-lg font-semibold text-white">{{ t('market.schemes') }}</h2>
                                <span class="text-xs text-white/40">{{ t('market.schemes_hint') }}</span>
                            </div>
                        </div>
                        <div class="flex items-center gap-4">
                            <span class="badge bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 text-xs py-1 px-3">
                                {{ t('market.schemes_found', { count: arbitrageLoops.length }) }}
                            </span>
                            <button @click="toggleArbitrageSchemes" :aria-label="t('market.schemes')" :aria-expanded="showArbitrageSchemes ? 'true' : 'false'" class="text-white/40 hover:text-white transition-colors">
                                <svg v-if="showArbitrageSchemes" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7" />
                                </svg>
                                <svg v-else class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <div v-show="showArbitrageSchemes" class="space-y-4 max-h-[500px] overflow-y-auto pr-2 scrollbar-thin">
                        <div v-for="(scheme, idx) in arbitrageLoops" :key="'scheme-'+idx"
                             class="p-4 rounded-xl border border-white/5 bg-white/[0.02] hover:bg-white/[0.04] transition-all flex flex-col gap-4">
                            <!-- Card Header (Type & Profit) -->
                            <div class="flex items-center justify-between flex-wrap gap-2 border-b border-white/5 pb-2">
                                <span class="badge text-[10px] font-semibold tracking-wider uppercase"
                                      :class="scheme.type === '2-step' ? 'bg-blue-500/10 text-blue-400 border border-blue-500/20' : 'bg-purple-500/10 text-purple-400 border border-purple-500/20'">
                                    {{ t('market.loop_type', { type: scheme.type }) }}
                                </span>

                                <div class="flex items-center gap-3">
                                    <!-- Leftovers -->
                                    <div v-if="scheme.leftovers && scheme.leftovers.length" class="flex items-center gap-2 text-xs text-blue-400">
                                        <span class="text-white/30">{{ t('market.leftovers') }}</span>
                                        <span v-for="leftover in scheme.leftovers" :key="leftover.item_id" class="flex items-center gap-1 text-white/70">
                                            <img alt="" :src="getResourceIcon(leftover.item_id)" @error="handleIconError($event, leftover.item_id)" class="w-3.5 h-3.5 object-contain" />
                                            +{{ formatVolume(leftover.amount) }}
                                        </span>
                                    </div>
                                    <!-- Net Profit -->
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs text-white/40">{{ t('market.net_profit') }}</span>
                                        <div class="flex items-center gap-1.5 bg-emerald-500/10 border border-emerald-500/20 rounded-lg py-1 px-2">
                                            <img :alt="getItemName(scheme.profit.item_name, scheme.profit.item_id)" :src="getResourceIcon(scheme.profit.item_id)" @error="handleIconError($event, scheme.profit.item_id)" class="w-4 h-4 object-contain" />
                                            <span class="font-mono text-sm font-bold text-emerald-400">+{{ formatVolume(scheme.profit.amount) }}</span>
                                            <span class="text-xs text-emerald-400/70 truncate max-w-[80px]">{{ getItemName(scheme.profit.item_name, scheme.profit.item_id) }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Steps flowchart -->
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-center">
                                <div v-for="(step, sIdx) in scheme.steps" :key="sIdx" class="flex items-center gap-3">
                                    <div class="flex-1 p-3 rounded-lg bg-white/[0.02] border border-white/5 relative">
                                        <div class="text-[10px] uppercase font-bold text-white/30 mb-2">{{ t('market.step_num', { step: sIdx + 1 }) }}</div>

                                        <div class="flex flex-col gap-1.5">
                                            <div class="flex items-center gap-1.5 text-xs">
                                                <span class="text-white/40 w-8">{{ t('market.give') }}</span>
                                                <img alt="" :src="getResourceIcon(step.give_item)" @error="handleIconError($event, step.give_item)" class="w-4.5 h-4.5 object-contain" />
                                                <span class="font-mono font-semibold text-white/90">{{ formatVolume(step.give_per_lot) }}</span>
                                                <span class="text-[10px] text-white/30">(total: {{ formatVolume(step.give_amount) }})</span>
                                            </div>
                                            <div class="flex items-center gap-1.5 text-xs">
                                                <span class="text-white/40 w-8">{{ t('market.get') }}</span>
                                                <img alt="" :src="getResourceIcon(step.receive_item)" @error="handleIconError($event, step.receive_item)" class="w-4.5 h-4.5 object-contain" />
                                                <span class="font-mono font-semibold text-emerald-400">{{ formatVolume(step.receive_per_lot) }}</span>
                                                <span class="text-[10px] text-emerald-400/40">(total: {{ formatVolume(step.receive_amount) }})</span>
                                            </div>
                                            <div class="text-[10px] text-white/30 mt-1 border-t border-white/5 pt-1 flex justify-between">
                                                <span>{{ t('market.lots') }} <strong class="text-white/80">{{ step.lots }}</strong></span>
                                                <span class="truncate max-w-[100px]" :title="step.sender">{{ t('market.by') }} <strong class="text-white/85">{{ step.sender }}</strong></span>
                                            </div>
                                        </div>
                                    </div>

                                    <div v-if="sIdx < scheme.steps.length - 1" class="hidden md:flex text-white/20">
                                        <svg class="w-5 h-5 animate-pulse" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                                        </svg>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-if="arbitrageLoops.length === 0" class="py-8 text-center text-white/20">
                            {{ t('market.no_schemes_for_server', { server: selectedServerId }) }}
                        </div>
                    </div>
                </div>

                <!-- Current Active Market Listings Card -->
                <div class="glass-card p-6 animate-fade-in-up transition-all duration-500 hover:border-white/20">
                    <div class="flex items-center justify-between gap-3 mb-6 border-b border-white/5 pb-3">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white shadow-md shadow-indigo-500/20">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" />
                                </svg>
                            </div>
                            <div class="flex flex-col">
                                <h2 class="text-lg font-semibold text-white">{{ t('market.listings') }}</h2>
                                <span class="text-xs text-white/40">{{ t('market.active_trades_count', { count: totalActiveCount }) }}</span>
                            </div>
                        </div>
                        <button @click="toggleActiveListings" :aria-label="t('market.listings')" :aria-expanded="showActiveListings ? 'true' : 'false'" class="text-white/40 hover:text-white transition-colors duration-300">
                            <svg v-if="showActiveListings" class="w-5 h-5 transition-transform duration-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7" />
                            </svg>
                            <svg v-else class="w-5 h-5 transition-transform duration-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                    </div>

                    <transition name="smooth-accordion">
                        <div v-show="showActiveListings" class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="border-b border-white/5 text-[10px] font-semibold text-white/30 uppercase tracking-wider">
                                        <th class="py-3 px-4">{{ t('market.player') }}</th>
                                        <th class="py-3 px-4">{{ t('market.selling_resource') }}</th>
                                        <th class="py-3 px-4">{{ t('market.buying_resource') }}</th>
                                        <th class="py-3 px-4 text-right">{{ t('market.price') }}</th>
                                        <th class="py-3 px-4 text-right">{{ t('market.lots_remaining') }}</th>
                                        <th class="py-3 px-4 text-right">{{ t('market.time_left') }}</th>
                                        <th class="py-3 px-4 text-right">{{ t('market.sync_time') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-white/5 text-sm text-white/70">
                                    <tr v-for="offer in activeOffers" :key="offer.offer_id" class="hover:bg-white/[0.03] transition-colors duration-300">
                                        <td class="py-3 px-4 font-semibold text-white">{{ offer.sender_name }}</td>
                                        <td class="py-3 px-4">
                                            <div class="flex items-center gap-2">
                                                <img :alt="getItemName(offer.item_name, offer.item_id)" :src="getResourceIcon(offer.item_id)" @error="handleIconError($event, offer.item_id)" class="w-5 h-5 object-contain" />
                                                <span class="font-mono text-white/90">{{ formatVolume(offer.amount) }}</span>
                                                <span class="text-xs text-white/40 truncate max-w-[100px]">{{ getItemName(offer.item_name, offer.item_id) }}</span>
                                            </div>
                                        </td>
                                        <td class="py-3 px-4">
                                            <div class="flex items-center gap-2">
                                                <img :alt="getItemName(offer.target_item_name, offer.target_item_id)" :src="getResourceIcon(offer.target_item_id)" @error="handleIconError($event, offer.target_item_id)" class="w-5 h-5 object-contain" />
                                                <span class="font-mono text-white/90">{{ formatVolume(offer.target_amount) }}</span>
                                                <span class="text-xs text-white/40 truncate max-w-[100px]">{{ getItemName(offer.target_item_name, offer.target_item_id) }}</span>
                                            </div>
                                        </td>
                                        <td class="py-3 px-4 text-right text-emerald-400 font-mono font-medium">
                                            {{ offer.price }}
                                        </td>
                                        <td class="py-3 px-4 text-right text-blue-400 font-mono">{{ offer.lots_remaining }}</td>
                                        <td class="py-3 px-4 text-right font-mono text-xs" :class="offer.time_left > 0 ? 'text-amber-400' : 'text-red-500'">
                                            {{ formatTimeLeft(offer.time_left) }}
                                        </td>
                                        <td class="py-3 px-4 text-right text-[10px] text-white/30 font-mono">{{ offer.created_at }}</td>
                                    </tr>
                                    <tr v-if="activeOffers.length === 0">
                                        <td colspan="7" class="py-8 text-center text-white/20">
                                            {{ t('market.no_active_listings') }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>

                            <!-- Load More Button -->
                            <div v-if="hasMoreActiveOffers" class="flex justify-center mt-4 pt-4 border-t border-white/5">
                                <button @click="loadMoreActiveOffers" :disabled="loadingMore" class="btn-secondary py-2 px-6 flex items-center gap-2 bg-white/5 border border-white/10 text-white/80 hover:text-white hover:bg-white/10 rounded-lg transition-all duration-300 text-xs font-semibold">
                                    <svg v-if="loadingMore" class="animate-spin w-4 h-4 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                    </svg>
                                    {{ loadingMore ? t('common.loading_more') : t('market.load_more_listings') }}
                                </button>
                            </div>
                        </div>
                    </transition>
                </div>
            </div>
        </div>

        <!-- TAB 2: SETTINGS & SERVERS -->
        <div v-else-if="activeTab === 'settings'" class="space-y-6">
            <!-- Section 1: Server Connections Table -->
            <div class="glass-card p-6">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6 border-b border-white/5 pb-4">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 14.25h13.5m-13.5 0a3 3 0 0 1-3-3m3 3a3 3 0 1 0 0 6h13.5a3 3 0 1 0 0-6m-13.5 0H3m16.5 0a3 3 0 0 0 3-3m-3 3a3 3 0 1 1 0 6M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                            </svg>
                        </div>
                        <div>
                            <h2 class="text-lg font-semibold text-white">{{ t('market.servers_title') }}</h2>
                            <p class="text-xs text-white/40">{{ t('market.servers_subtitle') }}</p>
                        </div>
                    </div>

                    <button @click="openAddServerModal" class="btn-primary py-2 px-4 text-xs flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        {{ t('market.add_server') }}
                    </button>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-white/5 text-[10px] font-semibold text-white/30 uppercase tracking-wider">
                                <th class="py-3 px-4">{{ t('market.col_server_locale') }}</th>
                                <th class="py-3 px-4">{{ t('market.col_account') }}</th>
                                <th class="py-3 px-4">{{ t('market.col_verification') }}</th>
                                <th class="py-3 px-4">{{ t('market.status') }}</th>
                                <th class="py-3 px-4">{{ t('market.last_sync') }}</th>
                                <th class="py-3 px-4 text-right">{{ t('market.col_actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5 text-sm text-white/70">
                            <tr v-for="srv in servers" :key="srv.id" class="hover:bg-white/[0.01] transition-all">
                                <td class="py-3 px-4 font-semibold text-white">
                                    <div class="flex items-center gap-2">
                                        <span class="text-base">{{ getLocaleFlag(srv.locale) }}</span>
                                        <div>
                                            <span>{{ srv.display_name }}</span>
                                            <span class="block text-[10px] text-white/35 font-mono">{{ srv.server_id }} ({{ srv.locale }})</span>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3 px-4">
                                    <div v-if="srv.account" class="flex items-center gap-1.5 flex-wrap">
                                        <span class="w-2 h-2 rounded-full" :class="srv.account.status === 'online' ? 'bg-emerald-500' : 'bg-white/30'"></span>
                                        <span class="font-medium text-white/90">{{ srv.account.username }}</span>
                                        <span class="text-xs text-white/40">({{ srv.account.nickname || t('market.no_nick') }})</span>
                                        <span v-if="srv.account.server_name" class="badge badge-success bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 text-[10px]">
                                            {{ srv.account.server_name }}
                                        </span>
                                    </div>
                                    <span v-else class="text-xs text-white/30 italic">{{ t('market.not_assigned') }}</span>
                                </td>
                                <td class="py-3 px-4">
                                    <span class="badge text-[10px]" :class="getVerificationBadgeClass(srv.verification_status)">
                                        {{ srv.verification_status }}
                                    </span>
                                </td>
                                <td class="py-3 px-4">
                                    <span class="badge text-[10px]" :class="getSyncBadgeClass(srv.sync_status)">
                                        {{ srv.sync_status }}
                                    </span>
                                </td>
                                <td class="py-3 px-4 font-mono text-xs text-white/60">
                                    {{ formatDateTime(srv.last_synced_at) }}
                                    <span v-if="srv.last_error" class="block text-[10px] text-red-400 truncate max-w-[150px]" :title="srv.last_error">
                                        {{ srv.last_error }}
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-right">
                                    <div class="flex items-center justify-end flex-wrap gap-2">
                                        <button @click="verifyServer(srv)" :disabled="verifyingId === srv.id" class="btn-secondary py-1 px-2.5 text-[11px]" :title="t('market.verify_hint')">
                                            {{ verifyingId === srv.id ? '...' : t('market.verify') }}
                                        </button>
                                        <button @click="syncServerNow(srv)" :disabled="syncing || !srv.account_id" class="btn-secondary py-1 px-2.5 text-[11px] text-emerald-400 border-emerald-500/20 hover:bg-emerald-500/10 inline-flex items-center gap-1.5 disabled:opacity-50">
                                            <svg v-if="syncingServerId === srv.id" class="animate-spin w-3 h-3 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                            </svg>
                                            <span>{{ syncingServerId === srv.id ? '...' : t('market.sync_now') }}</span>
                                        </button>
                                        <button @click="openEditServerModal(srv)" class="btn-secondary py-1 px-2.5 text-[11px]">
                                            {{ t('market.edit') }}
                                        </button>
                                        <button @click="deleteServer(srv)" class="btn-secondary py-1 px-2.5 text-[11px] text-red-400 hover:bg-red-500/10 border-red-500/20">
                                            {{ t('market.delete') }}
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="servers.length === 0">
                                <td colspan="6" class="py-8 text-center text-white/20">
                                    {{ t('market.no_servers_row') }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Section 2: Global Settings -->
            <div class="glass-card p-6">
                <div class="flex items-center gap-3 mb-5 border-b border-white/5 pb-3">
                    <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-emerald-500 to-teal-600 flex items-center justify-center text-white">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                    </div>
                    <h2 class="text-lg font-semibold text-white">{{ t('market.autosync_schedule') }}</h2>
                </div>

                <form @submit.prevent="saveSettings">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div>
                            <label class="block text-xs font-medium text-white/40 mb-2 uppercase tracking-wider">{{ t('market.sync_interval') }}</label>
                            <div class="relative">
                                <select v-model="settingsForm.sync_interval" class="glass-select w-full">
                                    <option value="5" class="bg-dark-900">5 minutes</option>
                                    <option value="15" class="bg-dark-900">15 minutes</option>
                                    <option value="30" class="bg-dark-900">30 minutes</option>
                                    <option value="60" class="bg-dark-900">1 hour</option>
                                    <option value="custom" class="bg-dark-900">{{ t('market.custom') }}</option>
                                </select>
                            </div>
                        </div>

                        <div v-if="settingsForm.sync_interval === 'custom'">
                            <label class="block text-xs font-medium text-white/40 mb-2 uppercase tracking-wider">{{ t('market.custom_interval') }}</label>
                            <input type="number" v-model.number="settingsForm.custom_interval_minutes" min="1" class="glass-input w-full font-mono text-white"/>
                        </div>
                    </div>

                    <button type="submit" :disabled="saving" class="btn-primary flex items-center gap-2 text-xs">
                        {{ t('market.save_schedule') }}
                    </button>
                </form>
            </div>

            <!-- Section 3: Sync Logs -->
            <div class="glass-card p-6">
                <div class="flex items-center gap-3 mb-6 border-b border-white/5 pb-3">
                    <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-teal-500 to-emerald-600 flex items-center justify-center text-white">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 0 1 0 3.75H5.625a1.875 1.875 0 0 1 0-3.75Z" />
                        </svg>
                    </div>
                    <h2 class="text-lg font-semibold text-white">{{ t('market.sync_log') }}</h2>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-white/5 text-[10px] font-semibold text-white/30 uppercase tracking-wider">
                                <th class="py-3 px-4">{{ t('market.server') }}</th>
                                <th class="py-3 px-4">{{ t('market.date') }}</th>
                                <th class="py-3 px-4">{{ t('market.action') }}</th>
                                <th class="py-3 px-4">{{ t('market.status') }}</th>
                                <th class="py-3 px-4">{{ t('market.message') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5 text-sm text-white/70">
                            <tr v-for="(log, idx) in logs" :key="'log-'+idx" class="hover:bg-white/[0.01] transition-all">
                                <td class="py-3 px-4 font-mono text-xs text-emerald-400 font-bold">{{ log.server_id || '-' }}</td>
                                <td class="py-3 px-4 font-mono text-xs">{{ formatDateTime(log.date) }}</td>
                                <td class="py-3 px-4 font-semibold text-white/95">{{ log.action }}</td>
                                <td class="py-3 px-4">
                                    <span class="badge text-[10px]" :class="getStatusBadgeClass(log.status)">
                                        {{ log.status }}
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-xs text-white/50">{{ log.message }}</td>
                            </tr>
                            <tr v-if="loadingSyncLogs && logs.length === 0">
                                <td colspan="5" class="py-8">
                                    <div class="flex items-center justify-center gap-2 text-xs text-emerald-400">
                                        <spinner size="sm" />
                                        <span>{{ t('common.loading_data') }}</span>
                                    </div>
                                </td>
                            </tr>
                            <tr v-else-if="logs.length === 0">
                                <td colspan="5" class="py-8 text-center text-white/20">
                                    {{ t('market.no_sync_logs') }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Add / Edit Server Connection Modal -->
        <div v-if="showServerModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm animate-fade-in">
            <div class="glass-card max-w-lg w-full p-6 space-y-6 border border-white/10 shadow-2xl">
                <div class="flex items-center justify-between border-b border-white/10 pb-4">
                    <h3 class="text-lg font-bold text-white">
                        {{ editingServer ? t('market.edit_server_connection') : t('market.add_server_connection') }}
                    </h3>
                    <button @click="closeServerModal" class="text-white/40 hover:text-white">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div v-if="editingServer" class="p-3 rounded-lg bg-white/5 border border-white/10 text-xs text-white/60 space-y-1">
                    <div>{{ t('market.server') }}: <span class="font-mono text-white/80">{{ editingServer.server_id }}</span> ({{ editingServer.locale }})</div>
                    <div>{{ editingServer.display_name }}</div>
                </div>

                <form @submit.prevent="saveServerModal" class="space-y-4">
                    <div>
                        <label class="block text-xs font-medium text-white/40 mb-1.5 uppercase">{{ t('market.sync_account') }}</label>
                        <select v-model="serverForm.account_id" required class="glass-select w-full">
                            <option :value="null" disabled class="bg-dark-900 text-white/50">{{ t('market.choose_account') }}</option>
                            <option v-for="acc in accounts" :key="acc.id" :value="acc.id" class="bg-dark-900 text-white">
                                {{ acc.username }} ({{ acc.nickname || t('market.no_nick') }}) [{{ t('market.region_label') }}: {{ acc.region || '?' }}{{ acc.server_name ? ` | ${acc.server_name}` : '' }}]
                            </option>
                        </select>
                        <p v-if="accounts.length === 0" class="mt-2 text-xs text-amber-400 font-medium">
                            ⚠️ {{ t('market.err_no_accounts_found') || 'Игровые аккаунты не найдены. Добавьте аккаунт в разделе «Аккаунты».' }}
                        </p>
                        <p v-else class="mt-1.5 text-[11px] text-white/35">{{ t('market.auto_detect_hint') }}</p>
                    </div>

                    <!-- Auto-detected server info -->
                    <div v-if="detectedServerInfo" class="p-3 rounded-lg text-xs space-y-1"
                         :class="detectedServerInfo.error ? 'bg-amber-500/10 border border-amber-500/20 text-amber-400' : 'bg-emerald-500/10 border border-emerald-500/20 text-emerald-400'">
                        <template v-if="detectedServerInfo.error">
                            {{ detectedServerInfo.error }}
                        </template>
                        <template v-else>
                            <div class="font-semibold">{{ getLocaleFlag(detectedServerInfo.locale) }} {{ t('market.detected_title') }}</div>
                            <div v-if="detectedServerInfo.server_name" class="font-bold text-emerald-300 flex items-center gap-1.5 pt-0.5">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 14.25h13.5m-13.5 0a3 3 0 0 1-3-3V3.75a3 3 0 0 1 3-3h13.5a3 3 0 0 1 3 3v7.5a3 3 0 0 1-3 3m-13.5 0a3 3 0 0 0-3 3v3.75a3 3 0 0 0 3 3h13.5a3 3 0 0 0 3-3V17.25a3 3 0 0 0-3-3" />
                                </svg>
                                Игровой мир: {{ detectedServerInfo.server_name }}
                            </div>
                            <div>{{ t('market.detected_id') }}: <span class="font-mono">{{ detectedServerInfo.server_id }}</span></div>
                            <div>{{ t('market.detected_name') }}: {{ detectedServerInfo.display_name }}</div>
                            <div>{{ t('market.detected_locale') }}: {{ detectedServerInfo.locale }}</div>
                        </template>
                    </div>

                    <div class="flex justify-end gap-3 pt-4 border-t border-white/10">
                        <button type="button" @click="closeServerModal" class="btn-secondary text-xs py-2 px-4">{{ t('market.cancel') }}</button>
                        <button type="submit" :disabled="savingServer || !serverForm.account_id || !!(detectedServerInfo && detectedServerInfo.error)" class="btn-primary text-xs py-2 px-4">
                            {{ savingServer ? t('market.saving') : t('market.save') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<script>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { t } from '../lang';
import axios from 'axios';
import { cachedGet, clearApiCache, cachedGetBulk, readBulkCache, getMarketCacheStrategy, setMarketCacheStrategy } from '../services/apiCacheService';
import { showToast } from '../toast';
import { getGameImageUrl, handleGameImageError } from '../services/gameImageService';
import { resourceName, marketItemName } from '../lang/gameNames';
import { TRADABLE_RESOURCES } from '../lang/resourcesCatalog';

import Spinner from '../components/Spinner.vue';
import LoadingOverlay from '../components/LoadingOverlay.vue';
import MarketPriceChart from '../components/market/MarketPriceChart.vue';
import MarketDemandChart from '../components/market/MarketDemandChart.vue';
import MarketDataTable from '../components/market/MarketDataTable.vue';

export default {
    name: 'MarketAnalytics',
    components: { Spinner, LoadingOverlay, MarketPriceChart, MarketDemandChart, MarketDataTable },
    setup() {
        const route = useRoute();
        const router = useRouter();

        const updateQueryParams = () => {
            const query = { ...route.query };
            if (selectedServerId.value) {
                query.server = selectedServerId.value;
            } else {
                delete query.server;
                delete query.server_id;
            }
            if (selectedItem.value) {
                query.item = selectedItem.value;
            } else {
                delete query.item;
                delete query.item_id;
            }
            if (selectedTarget.value) {
                query.target = selectedTarget.value;
            } else {
                delete query.target;
                delete query.target_item_id;
            }
            router.replace({ query }).catch(() => {});
        };

        const copyPairLink = async () => {
            if (!selectedItem.value || !selectedTarget.value) return;

            const url = new URL(window.location.href);
            url.searchParams.set('item', selectedItem.value);
            url.searchParams.set('target', selectedTarget.value);
            if (selectedServerId.value) {
                url.searchParams.set('server', selectedServerId.value);
            }

            try {
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    await navigator.clipboard.writeText(url.toString());
                } else {
                    const textarea = document.createElement('textarea');
                    textarea.value = url.toString();
                    textarea.style.position = 'fixed';
                    textarea.style.opacity = '0';
                    document.body.appendChild(textarea);
                    textarea.select();
                    document.execCommand('copy');
                    document.body.removeChild(textarea);
                }
                showToast(t('market.link_copied'), 'success');
            } catch (e) {
                showToast(t('market.copy_link_failed'), 'error');
            }
        };

        const activeTab = ref('analytics');
        const cacheStrategy = ref(getMarketCacheStrategy());
        const bulkCacheTtlMs = ref(300000);
        const loading = ref(false);
        const loadingServers = ref(true);
        const loadingPairs = ref(false);
        const loadingChart = ref(false);
        const loadingSyncLogs = ref(false);
        const saving = ref(false);
        const syncing = ref(false);
        const syncingServerId = ref(null);
        const showPopularItems = ref(true);
        const showArbitrageSchemes = ref(true);
        const showActiveListings = ref(true);

        const onCacheStrategyChange = (strategy) => {
            cacheStrategy.value = setMarketCacheStrategy(strategy);
            clearApiCache();
            loadAnalyticsData({ bypass: true });
            if (selectedItem.value && selectedTarget.value) {
                fetchAnalytics({ bypass: true });
            }
        };

        const togglePopularItems = () => showPopularItems.value = !showPopularItems.value;
        const toggleArbitrageSchemes = () => showArbitrageSchemes.value = !showArbitrageSchemes.value;
        const toggleActiveListings = () => showActiveListings.value = !showActiveListings.value;

        const activeOffers = ref([]);
        const totalActiveCount = ref(0);
        const activeOffersPage = ref(1);
        const hasMoreActiveOffers = ref(false);
        const loadingMore = ref(false);

        let countdownInterval = null;
        const startCountdown = () => {
            if (countdownInterval) clearInterval(countdownInterval);
            countdownInterval = setInterval(() => {
                activeOffers.value.forEach(offer => {
                    if (offer.time_left > 0) {
                        offer.time_left--;
                    }
                });
            }, 1000);
        };

        const formatTimeLeft = (seconds) => {
            if (!seconds || seconds <= 0) return t('market.expired');
            const h = Math.floor(seconds / 3600);
            const m = Math.floor((seconds % 3600) / 60);
            const s = seconds % 60;
            if (h > 0) return `${h}h ${m}m`;
            if (m > 0) return `${m}m ${s}s`;
            return `${s}s`;
        };

        const loadMoreActiveOffers = async () => {
            // All active offers are already loaded in bulk, no pagination needed
            // This function is kept for backward compatibility but does nothing
        };

        // Multi-server state
        const servers = ref([]);
        const presets = ref([]);
        const accounts = ref([]);
        const selectedServerId = ref(localStorage.getItem('tso_market_selected_server') || '');
        const verifyingId = ref(null);

        // Server Modal
        const showServerModal = ref(false);
        const editingServer = ref(null);
        const savingServer = ref(false);
        const serverForm = ref({
            account_id: null,
        });

        const currentServerConnection = computed(() => {
            return servers.value.find(s => s.server_id === selectedServerId.value) || servers.value[0] || null;
        });

        const groupedServers = computed(() => {
            const groups = {};
            for (const srv of servers.value) {
                const countryCode = String(srv.locale || 'OTHER').toUpperCase();
                if (!groups[countryCode]) {
                    groups[countryCode] = [];
                }
                groups[countryCode].push(srv);
            }
            return groups;
        });

        const REGION_LOCALES = { ru: 'RU', de: 'DE', en: 'EN', us: 'EN', fr: 'FR', pl: 'PL', es: 'ES', es2: 'ES', nl: 'NL', cz: 'CZ', pt: 'PT', it: 'IT', el: 'EL', ro: 'RO' };

        const detectedServerInfo = computed(() => {
            if (!serverForm.value.account_id) return null;
            const acc = accounts.value.find(a => a.id === serverForm.value.account_id);
            if (!acc) return null;
            const region = String(acc.region || '').toLowerCase();
            if (!region) {
                return { error: t('market.err_no_region', { username: acc.username }) };
            }
            if (editingServer.value && region !== String(editingServer.value.server_id).toLowerCase()) {
                return { error: t('market.err_wrong_server', { username: acc.username, detected: region, server: editingServer.value.server_id }) };
            }
            if (!editingServer.value && servers.value.some(s => String(s.server_id).toLowerCase() === region)) {
                return { error: t('market.err_server_exists', { server: region }) };
            }
            const locale = REGION_LOCALES[region] || region.toUpperCase();
            const displayName = acc.server_name
                ? `${acc.server_name} Settlers Market`
                : `${region.toUpperCase()} Settlers Market`;

            return {
                server_id: region,
                locale,
                display_name: displayName,
                server_name: acc.server_name || null,
            };
        });

        const getLocaleFlag = (locale) => {
            switch (String(locale).toUpperCase()) {
                case 'RU': return '🇷🇺';
                case 'DE': return '🇩🇪';
                case 'EN': return '🇬🇧';
                case 'US': return '🇺🇸';
                case 'FR': return '🇫🇷';
                case 'PL': return '🇵🇱';
                case 'ES': return '🇪🇸';
                case 'IT': return '🇮🇹';
                case 'NL': return '🇳🇱';
                case 'CZ': return '🇨🇿';
                case 'RO': return '🇷🇴';
                default: return '🌐';
            }
        };

        const getVerificationBadgeClass = (status) => {
            switch (status) {
                case 'verified':
                    return 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20';
                case 'mismatch':
                    return 'bg-amber-500/10 text-amber-400 border border-amber-500/20';
                case 'error':
                    return 'bg-red-500/10 text-red-400 border border-red-500/20';
                case 'unverified':
                default:
                    return 'bg-white/10 text-white/50 border border-white/10';
            }
        };

        const getSyncBadgeClass = (status) => {
            switch (status) {
                case 'connected':
                    return 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20';
                case 'syncing':
                    return 'bg-blue-500/10 text-blue-400 border border-blue-500/20 animate-pulse';
                case 'error':
                    return 'bg-red-500/10 text-red-400 border border-red-500/20';
                case 'not_configured':
                case 'disabled':
                default:
                    return 'bg-white/10 text-white/40 border border-white/10';
            }
        };

        // Analytics state
        const goods = ref([]);
        const targets = ref([]);

        // Single source of truth for market item names (shared with public portal):
        // see resources/js/lang/gameNames.js -> marketItemName().
        const getItemName = (name, id) => marketItemName(name, id);

        // Complete catalog of tradable resources from game XML combined with server items:
        // resources without active offers are also displayed (dimmed) to show the whole market.
        const allGoods = computed(() => {
            const known = new Set(goods.value.map(g => g.item_id));
            const extras = TRADABLE_RESOURCES
                .filter(name => !known.has(name))
                .map(name => ({ item_id: name, item_name: resourceName(name), no_offers: true }));
            return [...goods.value, ...extras];
        });
        const popular = ref([]);
        const history = ref([]);
        const stats = ref(null);
        const activeInfo = ref(null);
        const periodInfo = ref(null);
        const mirroredStats = ref(null);
        const mirroredHistory = ref(null);

        const logs = ref([]);
        const logsPagination = ref({ current_page: 1, last_page: 1, total: 0 });

        const selectionMode = ref('visual');
        const visualTab = ref(1);
        const selectedPeriod = ref('7d');
        const arbitrageLoops = ref([]);

        const periods = [
            { value: '1d', label: t('market.range_24h') },
            { value: '7d', label: t('market.range_7d') },
            { value: '30d', label: t('market.range_30d') },
            { value: '1y', label: t('market.range_1y') },
            { value: 'all', label: t('market.range_all') }
        ];

        const selectedItem = ref('');
        const selectedTarget = ref('');
        const calcAmount = ref(100);

        const settingsForm = ref({
            sync_interval: '15',
            custom_interval_minutes: 15,
        });

        const selectedItemName = computed(() => {
            const item = allGoods.value.find(g => g.item_id === selectedItem.value);
            return item ? getItemName(item.item_name, item.item_id) : '';
        });

        const selectedTargetName = computed(() => {
            const item = targets.value.find(t => t.target_item_id === selectedTarget.value);
            return item ? getItemName(item.target_item_name, item.target_item_id) : '';
        });

        const calculatedCost = computed(() => {
            if (!stats.value || !stats.value.average) return 0;
            const amt = parseFloat(calcAmount.value) || 0;
            return Math.round(amt * stats.value.average * 100) / 100;
        });

        const calculatedMirroredCost = computed(() => {
            if (!mirroredStats.value || !mirroredStats.value.average || mirroredStats.value.average === 0) return 0;
            const amt = parseFloat(calcAmount.value) || 0;
            return Math.round((amt / mirroredStats.value.average) * 100) / 100;
        });

        const activeVolume = computed(() => formatVolume(activeInfo.value?.volume || 0));
        const activeOffersCount = computed(() => activeInfo.value?.offers_count || 0);
        const activeSellersCount = computed(() => activeInfo.value?.sellers_count || 0);

        const priceTrendText = computed(() => {
            if (!stats.value || !stats.value.current || !stats.value.average) return 'Stable';
            const diff = stats.value.current - stats.value.average;
            if (Math.abs(diff) < 0.01) return 'Stable';
            return diff > 0 ? `+${((diff / stats.value.average) * 100).toFixed(1)}%` : `${((diff / stats.value.average) * 100).toFixed(1)}%`;
        });

        const priceTrendClass = computed(() => {
            if (!stats.value || !stats.value.current || !stats.value.average) return 'text-white/60';
            const diff = stats.value.current - stats.value.average;
            if (Math.abs(diff) < 0.01) return 'text-white/60';
            return diff > 0 ? 'text-emerald-400' : 'text-red-400';
        });

        const formatVolume = (val) => {
            if (val >= 1000000) return (val / 1000000).toFixed(1) + 'M';
            if (val >= 1000) return (val / 1000).toFixed(1) + 'K';
            return val;
        };

        const getResourceIcon = (itemId) =>
            getGameImageUrl('resource', itemId || 'addresource');

        const handleIconError = (event, itemId) =>
            handleGameImageError(event, 'resource', itemId || 'addresource', '/images/resources/addresource.webp');

        const getStatusBadgeClass = (status) => {
            switch (status) {
                case 'SUCCESS':
                    return 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20';
                case 'INFO':
                    return 'bg-blue-500/10 text-blue-400 border border-blue-500/20';
                case 'WARNING':
                    return 'bg-amber-500/10 text-amber-400 border border-amber-500/20';
                case 'FAILED':
                case 'ERROR':
                default:
                    return 'bg-red-500/10 text-red-400 border border-red-500/20';
            }
        };

        const formatDateTime = (dateStr) => {
            if (!dateStr || dateStr === 'Never') return 'Never';
            try {
                const date = new Date(dateStr);
                if (isNaN(date.getTime())) return dateStr;
                const day = String(date.getDate()).padStart(2, '0');
                const month = String(date.getMonth() + 1).padStart(2, '0');
                const year = date.getFullYear();
                const hours = String(date.getHours()).padStart(2, '0');
                const minutes = String(date.getMinutes()).padStart(2, '0');
                const seconds = String(date.getSeconds()).padStart(2, '0');
                return `${day}.${month}.${year} ${hours}:${minutes}:${seconds}`;
            } catch (e) {
                return dateStr;
            }
        };


        // Server API methods
        const loadServers = async () => {
            try {
                const res = await axios.get('/api/market/servers');
                servers.value = res.data.servers || [];
                accounts.value = res.data.accounts || [];
                presets.value = res.data.presets || [];
                settingsForm.value = res.data.settings || { sync_interval: '15', custom_interval_minutes: 15 };

                const strategy = res.data.settings?.cache_strategy || res.data.cache_strategy;
                if (strategy) {
                    cacheStrategy.value = setMarketCacheStrategy(strategy);
                }

                if (servers.value.length > 0) {
                    const exists = servers.value.some(s => s.server_id === selectedServerId.value);
                    if (!exists) {
                        selectedServerId.value = servers.value[0].server_id;
                        localStorage.setItem('tso_market_selected_server', selectedServerId.value);
                    }
                } else {
                    selectedServerId.value = '';
                }
            } catch (e) {
                console.error('Failed to load market servers:', e);
            } finally {
                loadingServers.value = false;
            }
        };

        const onServerChange = () => {
            localStorage.setItem('tso_market_selected_server', selectedServerId.value);
            resetSelection();
            goods.value = [];
            updateQueryParams();
            loadAnalyticsData({ bypass: true });
        };

        const loadAnalyticsData = async (options = {}) => {
            if (!selectedServerId.value) return;
            loading.value = true;
            try {
                const applyBulkData = (data) => {
                    goods.value = data.goods || [];
                    popular.value = (data.popular && data.popular['1d']) || [];
                    activeOffers.value = (data.active_offers || []).map(offer => {
                        if (offer && offer.expires_at) {
                            const expiresAt = new Date(offer.expires_at).getTime();
                            const timeLeft = Math.max(0, Math.floor((expiresAt - Date.now()) / 1000));
                            return { ...offer, time_left: timeLeft };
                        }
                        return offer;
                    });
                    totalActiveCount.value = data.total_active_count || 0;
                    activeOffersPage.value = 1;
                    hasMoreActiveOffers.value = false;
                    arbitrageLoops.value = data.arbitrage || [];
                    if (data.cache_ttl_seconds) {
                        bulkCacheTtlMs.value = data.cache_ttl_seconds * 1000;
                    }
                };

                if (cacheStrategy.value === 'bulk') {
                    const bulkData = await cachedGetBulk('/api/market/bulk', selectedServerId.value, {
                        onRevalidate: applyBulkData,
                        ...options
                    });
                    applyBulkData(bulkData);
                } else {
                    // Individual mode: fetch individual granular endpoints separately
                    const [goodsRes, popularRes, arbitrageRes, analyticsRes] = await Promise.all([
                        cachedGet('/api/market/goods', { params: { server_id: selectedServerId.value }, ...options }),
                        cachedGet('/api/market/popular', { params: { server_id: selectedServerId.value, period: '1d' }, ...options }),
                        cachedGet('/api/market/arbitrage', { params: { server_id: selectedServerId.value }, ...options }),
                        cachedGet('/api/market/analytics', { params: { server_id: selectedServerId.value }, ...options }),
                    ]);
                    goods.value = goodsRes || [];
                    popular.value = popularRes || [];
                    arbitrageLoops.value = arbitrageRes || [];
                    if (analyticsRes) {
                        activeOffers.value = (analyticsRes.active_offers || []).map(offer => {
                            if (offer && offer.expires_at) {
                                const expiresAt = new Date(offer.expires_at).getTime();
                                const timeLeft = Math.max(0, Math.floor((expiresAt - Date.now()) / 1000));
                                return { ...offer, time_left: timeLeft };
                            }
                            return offer;
                        });
                        totalActiveCount.value = analyticsRes.total_active_count || 0;
                    }
                }

                startCountdown();
                await loadSyncLogs(1);
            } catch (e) {
                console.error('Failed to load analytics data:', e);
            } finally {
                loading.value = false;
            }
        };

        const loadSyncLogs = async (page = 1) => {
            loadingSyncLogs.value = true;
            try {
                const res = await axios.get('/api/market/logs', {
                    params: { server_id: selectedServerId.value, page, limit: 10 }
                });
                logs.value = res.data.data || [];
                logsPagination.value = {
                    current_page: res.data.current_page || 1,
                    last_page: res.data.last_page || 1,
                    total: res.data.total || 0,
                };
            } catch (e) {
                console.error('Failed to load logs:', e);
            } finally {
                loadingSyncLogs.value = false;
            }
        };

        // Server Modal Handlers
        const refreshAccounts = async () => {
            try {
                const res = await axios.get('/api/accounts');
                accounts.value = res.data || [];
            } catch (e) {
                console.error('Failed to refresh accounts:', e);
            }
        };

        const openAddServerModal = async () => {
            await refreshAccounts();
            editingServer.value = null;
            serverForm.value = {
                account_id: null,
            };
            showServerModal.value = true;
        };

        const openEditServerModal = async (srv) => {
            await refreshAccounts();
            editingServer.value = srv;
            serverForm.value = {
                account_id: srv.account_id,
            };
            showServerModal.value = true;
        };

        const closeServerModal = () => {
            showServerModal.value = false;
            editingServer.value = null;
        };

        const saveServerModal = async () => {
            savingServer.value = true;
            try {
                const payload = { account_id: serverForm.value.account_id };
                if (editingServer.value) {
                    await axios.put(`/api/market/servers/${editingServer.value.id}`, payload);
                    showToast(t('market.server_updated'));
                } else {
                    await axios.post('/api/market/servers', payload);
                    showToast(t('market.server_created'));
                }
                closeServerModal();
                await loadServers();
                loadAnalyticsData({ bypass: true });
            } catch (e) {
                const msg = e.response?.data?.message || t('market.server_save_failed');
                showToast(msg, 'error');
            } finally {
                savingServer.value = false;
            }
        };

        const deleteServer = async (srv) => {
            if (!confirm(t('market.confirm_delete_server', { name: srv.display_name }))) return;
            try {
                await axios.delete(`/api/market/servers/${srv.id}`);
                showToast(t('market.server_deleted'));
                await loadServers();
                loadAnalyticsData({ bypass: true });
            } catch (e) {
                showToast(t('market.server_delete_failed'), 'error');
            }
        };

        const verifyServer = async (srv) => {
            verifyingId.value = srv.id;
            try {
                const res = await axios.post(`/api/market/servers/${srv.id}/verify`);
                showToast(res.data.message, res.data.success ? 'success' : 'warning');
                await loadServers();
            } catch (e) {
                const msg = e.response?.data?.message || t('market.verification_failed');
                showToast(msg, 'error');
            } finally {
                verifyingId.value = null;
            }
        };

        const syncServerNow = async (srv) => {
            const targetServer = srv || currentServerConnection.value;
            if (!targetServer) return;
            syncing.value = true;
            syncingServerId.value = targetServer.id;
            try {
                const res = await axios.post(`/api/market/servers/${targetServer.id}/sync`);
                if (res.data.success) {
                    showToast(t('market.sync_complete', { message: res.data.message }));
                    clearApiCache();
                    await loadServers();
                    await loadAnalyticsData({ bypass: true });
                    if (selectedItem.value && selectedTarget.value) {
                        await fetchAnalytics({ bypass: true });
                    }
                }
            } catch (e) {
                const msg = e.response?.data?.message || t('market.sync_failed_toast');
                showToast(msg, 'error');
            } finally {
                syncing.value = false;
                syncingServerId.value = null;
            }
        };

        const saveSettings = async () => {
            saving.value = true;
            try {
                await axios.put('/api/market/settings', settingsForm.value);
                showToast(t('market.schedule_saved'));
            } catch (e) {
                showToast('Failed to save settings.', 'error');
            } finally {
                saving.value = false;
            }
        };

        const onItemChange = async () => {
            selectedTarget.value = '';
            targets.value = [];
            stats.value = null;
            history.value = [];
            activeInfo.value = null;
            periodInfo.value = null;
            mirroredStats.value = null;
            mirroredHistory.value = null;
            updateQueryParams();

            if (!selectedItem.value || !selectedServerId.value) return;

            loadingPairs.value = true;
            try {
                // Try to get targets from bulk cache first (0 network requests)
                const bulk = readBulkCache(selectedServerId.value);
                if (bulk && bulk.targets_map && bulk.targets_map[selectedItem.value]) {
                    targets.value = bulk.targets_map[selectedItem.value];
                } else {
                    // Fallback to individual request with sync-based TTL
                    const data = await cachedGet('/api/market/targets', {
                        params: { server_id: selectedServerId.value, item_id: selectedItem.value },
                        ttlMs: bulkCacheTtlMs.value
                    });
                    targets.value = data || [];
                }
            } catch (e) {
                showToast(t('market.targets_failed'), 'error');
            } finally {
                loadingPairs.value = false;
            }
        };

        const fetchAnalytics = async (options = {}) => {
            updateQueryParams();
            if (!selectedItem.value || !selectedTarget.value || !selectedServerId.value) return;

            loadingChart.value = true;
            try {
                const applyPairAnalytics = (data) => {
                    stats.value = data.stats || null;
                    history.value = data.history || [];
                    activeInfo.value = data.active_info || null;
                    periodInfo.value = data.period_info || null;
                    mirroredStats.value = data.mirrored_stats || null;
                    mirroredHistory.value = data.mirrored_history || null;
                };

                const period = selectedPeriod.value;
                const itemId = selectedItem.value;
                const targetId = selectedTarget.value;

                // For 1d/7d periods, try to serve from bulk cache
                if ((period === '1d' || period === '7d') && !options.bypass) {
                    const bulk = readBulkCache(selectedServerId.value);
                    if (bulk && bulk.pairs) {
                        const pairKey = `${itemId}|${targetId}`;
                        const pairData = bulk.pairs[pairKey]?.[period];
                        if (pairData) {
                            const result = { ...pairData };

                            // Get active_info from pair level
                            if (bulk.pairs[pairKey]?.active_info) {
                                result.active_info = bulk.pairs[pairKey].active_info;
                            }

                            // Get mirrored data from reverse pair
                            const mirroredKey = `${targetId}|${itemId}`;
                            const mirroredPairData = bulk.pairs[mirroredKey]?.[period];
                            if (mirroredPairData) {
                                result.mirrored_stats = mirroredPairData.stats || null;
                                result.mirrored_history = mirroredPairData.history || null;
                            } else {
                                result.mirrored_stats = null;
                                result.mirrored_history = null;
                            }

                            applyPairAnalytics(result);
                            loadingChart.value = false;
                            return;
                        }
                    }
                }

                // Fallback to API request for periods > 7d or when bulk cache is unavailable
                const data = await cachedGet('/api/market/analytics', {
                    params: {
                        server_id: selectedServerId.value,
                        item_id: itemId,
                        target_item_id: targetId,
                        period: period
                    },
                    ttlMs: bulkCacheTtlMs.value,
                    onRevalidate: applyPairAnalytics,
                    ...options
                });
                applyPairAnalytics(data);
            } catch (e) {
                showToast(t('market.charts_failed'), 'error');
            } finally {
                loadingChart.value = false;
            }
        };

        const selectVisualItem = async (itemId) => {
            selectedItem.value = itemId;
            await onItemChange();
            visualTab.value = 2;
        };

        const selectVisualTarget = async (targetId) => {
            selectedTarget.value = targetId;
            await fetchAnalytics();
        };

        const resetSelection = () => {
            selectedItem.value = '';
            selectedTarget.value = '';
            targets.value = [];
            stats.value = null;
            history.value = [];
            visualTab.value = 1;
            updateQueryParams();
        };

        const mirrorSelection = async () => {
            if (!selectedItem.value || !selectedTarget.value || !selectedServerId.value) return;
            const tempItem = selectedItem.value;
            const tempTarget = selectedTarget.value;
            selectedItem.value = tempTarget;
            updateQueryParams();
            try {
                const bulk = readBulkCache(selectedServerId.value);
                if (bulk && bulk.targets_map && bulk.targets_map[selectedItem.value]) {
                    targets.value = bulk.targets_map[selectedItem.value];
                } else {
                    const data = await cachedGet('/api/market/targets', {
                        params: { server_id: selectedServerId.value, item_id: selectedItem.value },
                        ttlMs: bulkCacheTtlMs.value
                    });
                    targets.value = data || [];
                }
                const hasOldItem = targets.value.some(t => t.target_item_id === tempItem);
                if (hasOldItem) {
                    selectedTarget.value = tempItem;
                    await fetchAnalytics();
                } else {
                    selectedTarget.value = '';
                    updateQueryParams();
                }
            } catch (e) {
                showToast(t('market.mirror_failed'), 'error');
            }
        };

        const changePeriod = (val) => {
            selectedPeriod.value = val;
            fetchAnalytics();
        };

        onMounted(async () => {
            const queryServer = route.query.server || route.query.server_id;
            if (queryServer) {
                selectedServerId.value = String(queryServer);
            }
            await loadServers();
            await loadAnalyticsData();

            const queryItem = route.query.item || route.query.item_id;
            const queryTarget = route.query.target || route.query.target_item_id;

            if (queryItem) {
                const itemStr = String(queryItem);
                const matchedGood = goods.value.find(g => String(g.item_id) === itemStr || String(g.item_name).toLowerCase() === itemStr.toLowerCase());
                if (matchedGood) {
                    selectedItem.value = matchedGood.item_id;
                    await onItemChange();
                    if (queryTarget) {
                        const targetStr = String(queryTarget);
                        const matchedTarget = targets.value.find(t => String(t.target_item_id) === targetStr || String(t.target_item_name).toLowerCase() === targetStr.toLowerCase());
                        if (matchedTarget) {
                            selectedTarget.value = matchedTarget.target_item_id;
                            await fetchAnalytics();
                        }
                    }
                }
            }
        });

        onUnmounted(() => {
            if (countdownInterval) clearInterval(countdownInterval);
        });

        return {
            activeTab,
            loading,
            getItemName,
            loadingServers,
            loadingPairs,
            loadingChart,
            loadingSyncLogs,
            saving,
            syncing,
            syncingServerId,
            servers,
            groupedServers,
            presets,
            accounts,
            selectedServerId,
            currentServerConnection,
            verifyingId,
            showServerModal,
            editingServer,
            savingServer,
            serverForm,
            detectedServerInfo,
            getLocaleFlag,
            getVerificationBadgeClass,
            getSyncBadgeClass,
            openAddServerModal,
            openEditServerModal,
            closeServerModal,
            saveServerModal,
            deleteServer,
            verifyServer,
            syncServerNow,
            onServerChange,
            goods,
            allGoods,
            targets,
            popular,
            history,
            stats,
            activeOffers,
            totalActiveCount,
            hasMoreActiveOffers,
            loadingMore,
            loadMoreActiveOffers,
            formatTimeLeft,
            logs,
            logsPagination,
            loadSyncLogs,
            selectionMode,
            cacheStrategy,
            onCacheStrategyChange,
            visualTab,
            selectedPeriod,
            arbitrageLoops,
            periods,
            selectedItem,
            selectedTarget,
            calcAmount,
            settingsForm,
            selectedItemName,
            selectedTargetName,
            calculatedCost,
            calculatedMirroredCost,
            activeVolume,
            activeOffersCount,
            activeSellersCount,
            priceTrendText,
            priceTrendClass,
            formatVolume,
            getResourceIcon,
            handleIconError,
            getStatusBadgeClass,
            formatDateTime,
            showPopularItems,
            showArbitrageSchemes,
            showActiveListings,
            togglePopularItems,
            toggleArbitrageSchemes,
            toggleActiveListings,
            saveSettings,
            onItemChange,
            fetchAnalytics,
            selectVisualItem,
            selectVisualTarget,
            resetSelection,
            mirrorSelection,
            copyPairLink,
            changePeriod,
        };
    }
};
</script>
