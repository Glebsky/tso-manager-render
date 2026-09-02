<template>
    <div>
        <!-- Page Header -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 sm:gap-4 mb-5 sm:mb-8">
            <div>
                <h1 class="text-xl sm:text-3xl font-bold text-white">{{ t('market.title') }}</h1>
                <p class="text-xs sm:text-sm text-white/40 mt-0.5 sm:mt-1">{{ t('market.subtitle') }}</p>
            </div>

            <div class="flex flex-col sm:flex-row sm:items-center gap-2.5 sm:gap-3 w-full sm:w-auto">
                <!-- Server Selector -->
                <div v-if="servers.length > 0" class="flex items-center gap-2 bg-white/5 border border-white/10 p-1.5 rounded-xl w-full sm:w-auto">
                    <span class="text-xs font-semibold text-white/40 uppercase tracking-wider px-2 shrink-0">{{ t('market.server') }}:</span>
                    <select v-model="selectedServerId" @change="onServerChange" :aria-label="t('market.server')" class="bg-dark-900 text-xs font-bold text-emerald-400 py-1.5 px-2.5 sm:px-3 rounded-lg border border-emerald-500/20 focus:outline-none cursor-pointer w-full sm:max-w-[240px] truncate">
                        <optgroup v-for="(groupServers, countryCode) in groupedServers" :key="countryCode" :label="`${getLocaleFlag(countryCode)} ${countryCode}`">
                            <option v-for="srv in groupServers" :key="srv.server_id" :value="srv.server_id">
                                {{ srv.display_name }} ({{ srv.account ? srv.account.username : t('market.no_account') }})
                            </option>
                        </optgroup>
                    </select>
                </div>

                <!-- Tab Navigation -->
                <div class="grid grid-cols-2 sm:flex items-center gap-1 bg-white/5 border border-white/10 p-1 rounded-xl shrink-0 w-full sm:w-auto">
                    <button @click="activeTab = 'analytics'"
                            class="px-3 sm:px-4 py-2 rounded-lg text-xs font-semibold uppercase tracking-wider transition-all text-center"
                            :class="activeTab === 'analytics' ? 'bg-emerald-500 text-white shadow-lg shadow-emerald-500/20' : 'text-white/50 hover:text-white'">
                        {{ t('market.tab_analytics') }}
                    </button>
                    <button @click="activeTab = 'settings'"
                            class="px-3 sm:px-4 py-2 rounded-lg text-xs font-semibold uppercase tracking-wider transition-all text-center"
                            :class="activeTab === 'settings' ? 'bg-emerald-500 text-white shadow-lg shadow-emerald-500/20' : 'text-white/50 hover:text-white'">
                        {{ t('market.tab_settings') }}
                    </button>
                </div>
            </div>
        </div>

        <!-- TAB 1: ANALYTICS -->
        <div v-if="activeTab === 'analytics'" class="space-y-4 sm:space-y-6">
            <!-- Servers loading placeholder -->
            <div v-if="loadingServers && servers.length === 0" class="glass-card p-8 sm:p-10 flex flex-col items-center justify-center gap-3 text-emerald-400">
                <spinner size="lg" />
                <p class="text-xs text-white/40">{{ t('market.loading_servers') }}</p>
            </div>

            <!-- Active Server Status Notice / Empty State -->
            <div v-else-if="servers.length === 0" class="glass-card p-6 sm:p-8 text-center space-y-3 sm:space-y-4">
                <div class="w-12 h-12 rounded-2xl bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-amber-400 mx-auto">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                    </svg>
                </div>
                <h2 class="text-base sm:text-lg font-semibold text-white">{{ t('market.no_servers_title') }}</h2>
                <p class="text-xs text-white/50 max-w-md mx-auto">
                    {{ t('market.no_servers_text') }}
                </p>
                <button @click="activeTab = 'settings'" class="btn-primary py-2 px-6 text-xs inline-flex items-center justify-center gap-2 w-full sm:w-auto">
                    {{ t('market.go_to_settings') }}
                </button>
            </div>

            <div v-else-if="currentServerConnection && (!currentServerConnection.account_id || currentServerConnection.sync_status === 'error' || currentServerConnection.sync_status === 'not_configured')"
                 class="glass-card p-3.5 sm:p-4 border-l-4"
                 :class="currentServerConnection.sync_status === 'error' ? 'border-l-red-500 bg-red-500/5' : 'border-l-amber-500 bg-amber-500/5'">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs">
                    <div class="flex items-center gap-2.5 sm:gap-3">
                        <span class="badge text-[10px] sm:text-xs" :class="getSyncBadgeClass(currentServerConnection.sync_status)">
                            {{ currentServerConnection.sync_status }}
                        </span>
                        <div class="space-y-0.5 min-w-0 flex-1">
                            <p class="font-semibold text-white truncate">
                                Server {{ currentServerConnection.display_name }} ({{ currentServerConnection.locale }}):
                                <span v-if="!currentServerConnection.account_id" class="text-amber-400">{{ t('market.no_account_assigned') }}</span>
                                <span v-else-if="currentServerConnection.last_error" class="text-red-400">{{ currentServerConnection.last_error }}</span>
                                <span v-else class="text-white/60">{{ t('market.not_synced_yet') }}</span>
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 shrink-0 w-full sm:w-auto">
                        <button v-if="currentServerConnection.account_id" @click="syncServerNow(currentServerConnection)" :disabled="syncing" class="btn-secondary py-1.5 px-3 text-xs flex items-center justify-center gap-1.5 disabled:opacity-50 flex-1 sm:flex-initial">
                            <svg class="w-3.5 h-3.5" :class="{ 'animate-spin': syncing }" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182" />
                            </svg>
                            <span>{{ syncing ? t('card.syncing') : t('market.sync_now') }}</span>
                        </button>
                        <button @click="activeTab = 'settings'" class="btn-secondary py-1.5 px-3 text-xs flex-1 sm:flex-initial text-center">
                            {{ t('market.manage_server') }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Selection Card (Dropdowns or Visual Grid) -->
            <div class="glass-card p-4 sm:p-6 relative">
                <loading-overlay :show="loading || loadingPairs" :label="loadingPairs ? t('market.loading_pairs') : t('common.loading_data')" />
                <!-- Selector Header: Left Filters & Right Stacked Search + Actions -->
                <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-3 sm:gap-4 mb-4 sm:mb-6 border-b border-white/5 pb-3 sm:pb-4">
                    <!-- Left: Selection Mode & Kind Filters (inline on >= 1024px, stacked on < 1024px) -->
                    <div class="flex flex-col lg:flex-row lg:items-center gap-2 lg:gap-2.5">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="text-xs font-semibold text-white/40 uppercase tracking-wider shrink-0">{{ t('market.selection_mode') }}</span>
                            <div class="flex items-center gap-1 bg-white/5 border border-white/10 p-0.5 rounded-lg shrink-0">
                                <button @click="selectionMode = 'dropdown'"
                                        class="px-2.5 sm:px-3 py-1 rounded text-[10px] font-bold uppercase transition-all duration-300"
                                        :class="selectionMode === 'dropdown' ? 'bg-emerald-500 text-white shadow-md shadow-emerald-500/20' : 'text-white/50 hover:text-white'">
                                    {{ t('market.dropdowns') }}
                                </button>
                                <button @click="selectionMode = 'visual'"
                                        class="px-2.5 sm:px-3 py-1 rounded text-[10px] font-bold uppercase transition-all duration-300"
                                        :class="selectionMode === 'visual' ? 'bg-emerald-500 text-white shadow-md shadow-emerald-500/20' : 'text-white/50 hover:text-white'">
                                    {{ t('market.visual_browser') }}
                                </button>
                            </div>
                        </div>
                        <div class="flex items-center gap-0.5 bg-white/5 border border-white/10 p-0.5 rounded-lg w-fit flex-wrap">
                            <button v-for="k in ['all', 'resource', 'buff', 'adventure', 'building']" :key="'kind-' + k"
                                    @click="selectedKind = k"
                                    class="px-2 sm:px-2.5 py-0.5 rounded text-[9px] sm:text-[10px] font-bold uppercase transition-all"
                                    :class="selectedKind === k ? 'bg-indigo-600 text-white' : 'text-white/50 hover:text-white'">
                                {{ k === 'all' ? 'All' : (k === 'resource' ? 'Res' : (k === 'buff' ? 'Buff' : (k === 'adventure' ? 'ADV' : 'BLD'))) }}
                            </button>
                        </div>
                    </div>

                    <!-- Right: Search Input (Top) and Actions (Directly Underneath) -->
                    <div class="flex flex-col items-stretch sm:items-end gap-2 shrink-0 w-full sm:w-auto">
                        <!-- Search Box (Top Right) -->
                        <div class="relative w-full sm:w-48 lg:w-56">
                            <input v-model="searchQuery"
                                   type="text"
                                   :placeholder="t('common.search') || 'Поиск...'"
                                   class="w-full bg-white/5 border border-white/10 focus:border-emerald-500/50 focus:bg-white/10 focus:ring-1 focus:ring-emerald-500/30 text-white placeholder-white/30 text-xs rounded-lg pl-7 pr-7 py-1.5 transition-all outline-none" />
                            <div class="absolute inset-y-0 left-2 flex items-center pointer-events-none text-white/30">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                                </svg>
                            </div>
                            <button v-if="searchQuery" @click="searchQuery = ''" class="absolute inset-y-0 right-2 flex items-center text-white/40 hover:text-white">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <!-- Action Buttons (Under Search Box, icons only <= 1440px, text on > 1440px) -->
                        <div class="flex items-center gap-1.5 flex-wrap justify-start sm:justify-end">
                            <button v-if="selectedItem && selectedTarget" @click="mirrorSelection"
                                    :title="t('market.mirror_trade')"
                                    class="btn-secondary py-1 px-2 sm:px-2.5 text-xs bg-white/5 border border-white/10 text-white/70 hover:text-white hover:bg-white/10 rounded-lg transition-all flex items-center gap-1 shrink-0">
                                <svg class="w-3.5 h-3.5 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21 3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" />
                                </svg>
                                <span class="hidden min-[1441px]:inline">{{ t('market.mirror_trade') }}</span>
                            </button>
                            <button v-if="selectedItem && selectedTarget" @click="copyPairLink"
                                    :title="t('market.copy_link')"
                                    class="btn-secondary py-1 px-2 sm:px-2.5 text-xs bg-white/5 border border-white/10 text-white/70 hover:text-white hover:bg-white/10 rounded-lg transition-all flex items-center gap-1 shrink-0">
                                <svg class="w-3.5 h-3.5 text-blue-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244" />
                                </svg>
                                <span class="hidden min-[1441px]:inline">{{ t('market.copy_link') }}</span>
                            </button>
                            <button v-if="selectedItem || selectedTarget" @click="resetSelection"
                                    :title="t('market.reset_selection')"
                                    class="btn-secondary py-1 px-2 sm:px-2.5 text-xs bg-white/5 border border-white/10 text-white/50 hover:text-white hover:bg-white/10 rounded-lg transition-all flex items-center gap-1 shrink-0">
                                <svg class="w-3 h-3 text-red-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                </svg>
                                <span class="hidden min-[1441px]:inline">{{ t('market.reset_selection') }}</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Mode 1: Dropdown Selection -->
                <div v-if="selectionMode === 'dropdown'" class="grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-6 items-end">
                    <!-- Selling Item Selection -->
                    <div>
                        <label class="block text-xs font-medium text-white/40 mb-1.5 sm:mb-2 uppercase tracking-wider">{{ t('market.selling_item') }}</label>
                        <div class="relative">
                            <select v-model="selectedItem" @change="onItemChange" :aria-label="t('market.selling_item')" class="glass-select w-full">
                                <option value="" class="bg-dark-900">{{ t('market.select_selling') }}</option>
                                <option v-for="good in filteredGoods" :key="good.item_id" :value="good.item_id" class="bg-dark-900">
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
                                <option v-for="target in filteredTargets" :key="target.target_item_id" :value="target.target_item_id" class="bg-dark-900">
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
                <div v-else class="space-y-4 sm:space-y-6">
                    <!-- Tab Navigation for Visual steps -->
                    <div class="grid grid-cols-2 gap-2 border-b border-white/10 pb-0 sm:flex sm:items-center sm:gap-6">
                        <button @click="switchVisualTab(1)"
                                class="flex flex-col sm:flex-row items-center justify-center sm:justify-start pb-2 sm:pb-3 text-[11px] sm:text-xs font-bold uppercase tracking-tight sm:tracking-wider transition-all duration-300 border-b-2 min-w-0 w-full sm:w-auto"
                                :class="visualTab === 1 ? 'border-emerald-500 text-emerald-400' : 'border-transparent text-white/40 hover:text-white'">
                            <span class="truncate max-w-full sm:max-w-none whitespace-nowrap">{{ t('market.sell_resource') }}</span>
                            <span v-if="selectedItem" class="text-[10px] sm:text-xs text-emerald-500 font-mono font-medium truncate max-w-full sm:max-w-none sm:ml-1.5">
                                ({{ selectedItemName }})
                            </span>
                        </button>
                        <button @click="switchVisualTab(2)"
                                :disabled="!selectedItem"
                                class="flex flex-col sm:flex-row items-center justify-center sm:justify-start pb-2 sm:pb-3 text-[11px] sm:text-xs font-bold uppercase tracking-tight sm:tracking-wider transition-all duration-300 border-b-2 disabled:opacity-30 disabled:cursor-not-allowed min-w-0 w-full sm:w-auto"
                                :class="visualTab === 2 ? 'border-emerald-500 text-emerald-400' : 'border-transparent text-white/40 hover:text-white'">
                            <span class="truncate max-w-full sm:max-w-none whitespace-nowrap">{{ t('market.buy_resource') }}</span>
                            <span v-if="selectedTarget" class="text-[10px] sm:text-xs text-emerald-500 font-mono font-medium truncate max-w-full sm:max-w-none sm:ml-1.5">
                                ({{ selectedTargetName }})
                            </span>
                        </button>
                    </div>

                    <!-- Step 1: Selling resource grid -->
                    <div v-if="visualTab === 1" class="grid grid-cols-3 xs:grid-cols-4 sm:grid-cols-6 md:grid-cols-8 lg:grid-cols-12 gap-1.5 sm:gap-2 max-h-60 overflow-y-auto p-1 scrollbar-thin">
                        <div v-for="good in filteredGoods" :key="good.item_id"
                             @click="selectVisualItem(good.item_id)"
                             class="flex flex-col items-center justify-center p-1 sm:p-1.5 rounded-lg border cursor-pointer hover:border-emerald-500/40 hover:bg-white/[0.05] hover:shadow-md hover:shadow-emerald-500/5 text-center select-none transition-all duration-300 ease-out transform hover:-translate-y-0.5"
                             :class="selectedItem === good.item_id ? 'bg-emerald-500/10 border-emerald-500 shadow shadow-emerald-500/10 scale-[1.02]' : 'bg-white/[0.02] border-white/5 hover:border-white/20 hover:bg-white/[0.04]'"
                             :style="good.no_offers ? 'opacity:0.4' : ''"
                             :title="good.no_offers ? t('market.no_offers') : getItemName(good.item_name, good.item_id)">
                            <img :alt="getItemName(good.item_name, good.item_id)" :src="getResourceIcon(good.item_id, good.kind)" @error="handleIconError($event, good.item_id, good.kind)" class="w-5 h-5 sm:w-6 sm:h-6 object-contain mb-1 pointer-events-none" />
                            <span class="text-[8px] sm:text-[9px] font-medium text-white/90 truncate w-full text-center" :title="getItemName(good.item_name, good.item_id)">{{ getItemName(good.item_name, good.item_id) }}</span>
                        </div>
                        <div v-if="filteredGoods.length === 0 && !loading" class="col-span-full py-8 text-center text-xs text-white/30">
                            {{ searchQuery ? (t('common.no_results') || 'Ничего не найдено') : t('market.no_resources_for_server', { server: selectedServerId }) }}
                        </div>
                    </div>

                    <!-- Step 2: Buying target resource grid -->
                    <div v-if="visualTab === 2" class="grid grid-cols-3 xs:grid-cols-4 sm:grid-cols-6 md:grid-cols-8 lg:grid-cols-12 gap-1.5 sm:gap-2 max-h-60 overflow-y-auto p-1 scrollbar-thin">
                        <div v-for="target in filteredTargets" :key="target.target_item_id"
                             @click="selectVisualTarget(target.target_item_id)"
                             class="flex flex-col items-center justify-center p-1 sm:p-1.5 rounded-lg border cursor-pointer hover:border-emerald-500/40 hover:bg-white/[0.05] hover:shadow-md hover:shadow-emerald-500/5 text-center select-none transition-all duration-300 ease-out transform hover:-translate-y-0.5"
                             :class="selectedTarget === target.target_item_id ? 'bg-emerald-500/10 border-emerald-500 shadow shadow-emerald-500/10 scale-[1.02]' : 'bg-white/[0.02] border-white/5 hover:border-white/20 hover:bg-white/[0.04]'"
                             :title="getItemName(target.target_item_name, target.target_item_id)">
                            <img :alt="getItemName(target.target_item_name, target.target_item_id)" :src="getResourceIcon(target.target_item_id, target.kind)" @error="handleIconError($event, target.target_item_id, target.kind)" class="w-5 h-5 sm:w-6 sm:h-6 object-contain mb-1 pointer-events-none" />
                            <span class="text-[8px] sm:text-[9px] font-medium text-white/90 truncate w-full text-center" :title="getItemName(target.target_item_name, target.target_item_id)">{{ getItemName(target.target_item_name, target.target_item_id) }}</span>
                        </div>
                        <div v-if="filteredTargets.length === 0 && !loadingPairs" class="col-span-full py-8 text-center text-xs text-white/30">
                            {{ selectedItem ? (targets.length === 0 ? t('market.opposite_trade_not_found') : (t('common.no_results') || 'Ничего не найдено')) : t('market.select_selling_first') }}
                        </div>
                    </div>
                </div>

                <!-- Selection Path Indicator -->
                <div v-if="selectedItemName && selectedTargetName" class="mt-4 sm:mt-5 pt-4 sm:pt-5 border-t border-white/5 flex flex-wrap items-center gap-2 sm:gap-3 text-base sm:text-lg font-semibold text-emerald-400">
                    <div class="flex items-center gap-2">
                        <img :alt="selectedItemName" :src="getResourceIcon(selectedItem)" @error="handleIconError($event, selectedItem)" class="w-5 h-5 sm:w-6 sm:h-6 object-contain" />
                        <span>{{ selectedItemName }}</span>
                    </div>
                    <svg class="w-4 h-4 sm:w-5 sm:h-5 text-white/30" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                    </svg>
                    <div class="flex items-center gap-2">
                        <img :alt="selectedTargetName" :src="getResourceIcon(selectedTarget)" @error="handleIconError($event, selectedTarget)" class="w-5 h-5 sm:w-6 sm:h-6 object-contain" />
                        <span>{{ selectedTargetName }}</span>
                    </div>
                </div>
            </div>

            <!-- Analytics loading placeholder (first fetch for a pair) -->
            <div v-if="loadingChart && !stats" class="glass-card p-8 sm:p-12 flex flex-col items-center justify-center gap-3 text-emerald-400">
                <spinner size="lg" />
                <p class="text-xs text-white/40">{{ t('market.loading_chart') }}</p>
            </div>

            <!-- Analysis Dashboard (Visible if both selected) -->
            <div v-if="selectedItem && selectedTarget && stats" class="relative grid grid-cols-1 lg:grid-cols-3 gap-4 sm:gap-6">
                <loading-overlay :show="loadingChart" :label="t('market.loading_chart')" />
                <!-- Stats Swarm (Left columns) -->
                <div class="lg:col-span-2 space-y-4 sm:space-y-6">
                    <!-- Pricing Stats -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 sm:gap-4">
                        <div class="glass-card p-3 sm:p-4 transition-all duration-300 hover:scale-[1.02]">
                            <span class="text-[10px] font-semibold text-white/30 uppercase tracking-wider block truncate">{{ t('market.current_price') }}</span>
                            <span class="text-lg sm:text-xl font-bold font-mono text-white mt-1 block truncate">{{ stats.current }}</span>
                            <span class="text-[10px] text-white/40 block mt-0.5 truncate">{{ selectedTargetName }}</span>
                        </div>
                        <div class="glass-card p-3 sm:p-4 transition-all duration-300 hover:scale-[1.02]">
                            <span class="text-[10px] font-semibold text-white/30 uppercase tracking-wider block truncate">{{ t('market.average_price') }}</span>
                            <span class="text-lg sm:text-xl font-bold font-mono text-emerald-400 mt-1 block truncate">{{ stats.average }}</span>
                            <span class="text-[10px] text-white/40 block mt-0.5 truncate">{{ selectedTargetName }}</span>
                        </div>
                        <div class="glass-card p-3 sm:p-4 transition-all duration-300 hover:scale-[1.02]">
                            <span class="text-[10px] font-semibold text-white/30 uppercase tracking-wider block truncate">{{ t('market.min_price') }}</span>
                            <span class="text-lg sm:text-xl font-bold font-mono text-blue-400 mt-1 block truncate">{{ stats.minimum }}</span>
                            <span class="text-[10px] text-white/40 block mt-0.5 truncate">{{ selectedTargetName }}</span>
                        </div>
                        <div class="glass-card p-3 sm:p-4 transition-all duration-300 hover:scale-[1.02]">
                            <span class="text-[10px] font-semibold text-white/30 uppercase tracking-wider block truncate">{{ t('market.max_price') }}</span>
                            <span class="text-lg sm:text-xl font-bold font-mono text-red-400 mt-1 block truncate">{{ stats.maximum }}</span>
                            <span class="text-[10px] text-white/40 block mt-0.5 truncate">{{ selectedTargetName }}</span>
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
                <div class="space-y-4 sm:space-y-6">
                    <!-- Calculator Card -->
                    <div class="glass-card p-4 sm:p-6">
                        <div class="flex items-center gap-3 mb-4 sm:mb-5 border-b border-white/5 pb-3">
                            <div class="w-8 h-8 rounded-lg bg-emerald-500/10 flex items-center justify-center text-emerald-400 shrink-0">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 15.75V18m-3-3v3m-3-3v3M9 3h12a2.25 2.25 0 0 1 2.25 2.25v13.5A2.25 2.25 0 0 1 21 21H9a2.25 2.25 0 0 1-2.25-2.25V5.25A2.25 2.25 0 0 1 9 3Zm2.25 3h7.5a.75.75 0 0 0 .75-.75V4.5a.75.75 0 0 0-.75-.75h-7.5a.75.75 0 0 0-.75.75v.75a.75.75 0 0 0 .75.75Z" />
                                </svg>
                            </div>
                            <h2 class="text-sm font-semibold text-white">{{ t('market.cost_calculator') }}</h2>
                        </div>

                        <div class="space-y-4 sm:space-y-5">
                            <div>
                                <div class="flex items-center justify-between gap-2 mb-1.5 sm:mb-2">
                                    <label for="admin-calc-amount-input" class="block text-xs font-medium text-white/40 uppercase tracking-wider truncate">{{ t('market.amount_of', { item: isReversedCalc ? selectedTargetName : selectedItemName }) }}</label>
                                    <button 
                                        type="button"
                                        @click="isReversedCalc = !isReversedCalc"
                                        :title="t('market.reverse_calc_btn')"
                                        :aria-label="t('market.reverse_calc_btn')"
                                        class="flex items-center gap-1.5 px-2 py-0.5 rounded-lg text-[11px] font-medium transition-all duration-200 border shrink-0 cursor-pointer"
                                        :class="isReversedCalc ? 'bg-emerald-500/20 text-emerald-300 border-emerald-500/40 shadow-sm shadow-emerald-500/10' : 'bg-white/5 text-white/50 border-white/10 hover:text-white hover:bg-white/10'"
                                    >
                                        <svg class="w-3.5 h-3.5 transition-transform duration-300" :class="{ 'rotate-180 text-emerald-400': isReversedCalc }" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21 3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" />
                                        </svg>
                                        <span class="text-[10px]">{{ isReversedCalc ? selectedTargetName : selectedItemName }}</span>
                                    </button>
                                </div>
                                <input id="admin-calc-amount-input" type="number" v-model.number="calcAmount" min="1" :aria-label="t('market.amount_of', { item: isReversedCalc ? selectedTargetName : selectedItemName })" class="glass-input w-full font-mono text-white text-base sm:text-lg"/>
                            </div>

                            <!-- Direct estimated revenue -->
                            <div class="p-3.5 sm:p-4 rounded-xl border border-emerald-500/10 bg-emerald-500/[0.02]">
                                <span class="text-[10px] font-semibold text-emerald-400/70 uppercase tracking-wider block">{{ t('market.estimated_revenue') }}</span>
                                <div class="flex items-baseline gap-2 mt-1">
                                    <span class="text-xl sm:text-2xl font-bold text-emerald-400 font-mono">{{ calculatedCost }}</span>
                                    <span class="text-xs text-white/40">{{ isReversedCalc ? selectedItemName : selectedTargetName }}</span>
                                </div>
                                <span class="text-[9px] text-white/20 block mt-2">
                                    {{ isReversedCalc 
                                        ? t('market.formula_mirrored', { amount: calcAmount || 0, price: effectivePrice }) 
                                        : t('market.formula_direct', { amount: calcAmount || 0, price: effectivePrice }) 
                                    }}
                                </span>
                            </div>

                            <!-- Mirrored estimated cost -->
                            <div v-if="mirroredStats && effectiveMirroredPrice > 0" class="p-3.5 sm:p-4 rounded-xl border border-blue-500/10 bg-blue-500/[0.02]">
                                <span class="text-[10px] font-semibold text-blue-400/70 uppercase tracking-wider block">{{ t('market.estimated_cost') }}</span>
                                <div class="flex items-baseline gap-2 mt-1">
                                    <span class="text-xl sm:text-2xl font-bold text-blue-400 font-mono">{{ calculatedMirroredCost }}</span>
                                    <span class="text-xs text-white/40">{{ isReversedCalc ? selectedItemName : selectedTargetName }}</span>
                                </div>
                                <span class="text-[9px] text-white/20 block mt-2">
                                    {{ isReversedCalc 
                                        ? t('market.formula_direct', { amount: calcAmount || 0, price: effectiveMirroredPrice }) 
                                        : t('market.formula_mirrored', { amount: calcAmount || 0, price: effectiveMirroredPrice }) 
                                    }}
                                </span>
                            </div>
                            <div v-else class="p-3.5 sm:p-4 rounded-xl border border-white/5 bg-white/[0.01] text-center text-xs text-white/30">
                                {{ t('market.no_mirrored_trades', { target: selectedTargetName, item: selectedItemName }) }}
                            </div>
                        </div>
                    </div>

                    <!-- Selected pair market details -->
                    <div class="glass-card p-4 sm:p-6">
                        <h2 class="text-sm font-semibold text-white mb-3 sm:mb-4">{{ t('market.info') }}</h2>
                        <div class="space-y-2.5 sm:space-y-3 text-xs">
                            <div class="flex justify-between py-1.5 sm:py-2 border-b border-white/5">
                                <span class="text-white/40">{{ t('market.total_volume') }}</span>
                                <span class="text-white font-mono font-medium">{{ activeVolume }} {{ selectedItemName }}</span>
                            </div>
                            <div class="flex justify-between py-1.5 sm:py-2 border-b border-white/5">
                                <span class="text-white/40">{{ t('market.offers_count') }}</span>
                                <span class="text-white font-mono font-medium">{{ activeOffersCount }}</span>
                            </div>
                            <div class="flex justify-between py-1.5 sm:py-2 border-b border-white/5">
                                <span class="text-white/40">{{ t('market.active_sellers') }}</span>
                                <span class="text-white font-mono font-medium">{{ activeSellersCount }}</span>
                            </div>
                            <div class="flex justify-between py-1.5 sm:py-2 last:border-0">
                                <span class="text-white/40">{{ t('market.trend') }}</span>
                                <span class="font-semibold" :class="priceTrendClass">{{ priceTrendText }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Popular Items & Current Active Market -->
            <div class="mt-6 sm:mt-8 space-y-4 sm:space-y-6 relative">
                <loading-overlay :show="loading" :label="t('market.loading_data')" />
                <!-- Most Popular Items Card -->
                <div class="glass-card p-4 sm:p-6 animate-fade-in-up">
                    <div class="flex items-center justify-between gap-3 mb-4 sm:mb-6 border-b border-white/5 pb-3">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center text-white shrink-0">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18 9 11.25l4.306 4.307a11.95 11.95 0 0 1 5.814-5.518l2.74-1.22m0 0-3.75-1.002m3.75 1.002-1.002 3.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                </svg>
                            </div>
                            <h2 class="text-base sm:text-lg font-semibold text-white">{{ t('market.popular_items') }}</h2>
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

                    <transition name="smooth-accordion">
                        <div v-show="showPopularItems">
                            <market-data-table :columns="popularColumns" :rows="popular" :empty-text="t('market.no_data_for_server', { server: selectedServerId })">
                                <template #cell-item_name="{ row }">
                                    <div class="flex items-center gap-2 justify-end sm:justify-start">
                                        <img :alt="getItemName(row.item_name, row.item_id)" :src="getResourceIcon(row.item_id, row.kind)" @error="handleIconError($event, row.item_id, row.kind)" class="w-5 h-5 object-contain flex-shrink-0" />
                                        <span class="font-semibold text-white truncate">{{ getItemName(row.item_name, row.item_id) }}</span>
                                    </div>
                                </template>
                                <template #cell-item_id="{ row }">
                                    <span class="font-mono text-xs text-white/35">{{ row.item_id }}</span>
                                </template>
                                <template #cell-offers_count="{ row }">
                                    <span class="text-emerald-400 font-mono font-medium">{{ t('market.offers_count_num', { count: row.offers_count }) }}</span>
                                </template>
                                <template #cell-sellers_count="{ row }">
                                    <span class="text-blue-400 font-mono">{{ t('market.sellers_count_num', { count: row.sellers_count }) }}</span>
                                </template>
                                <template #cell-total_volume="{ row }">
                                    <span class="font-mono font-semibold">{{ formatVolume(row.total_volume) }} {{ t('market.units_short') }}</span>
                                </template>
                            </market-data-table>
                        </div>
                    </transition>
                </div>

                <!-- Profitable Arbitrage Schemes Card -->
                <div class="glass-card p-4 sm:p-6 animate-fade-in-up">
                    <div class="flex items-center justify-between gap-3 mb-4 sm:mb-6 border-b border-white/5 pb-3">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-emerald-500 to-green-600 flex items-center justify-center text-white shrink-0 shadow-sm shadow-emerald-500/20">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 12c0-1.232-.046-2.453-.138-3.662a4.006 4.006 0 0 0-3.7-3.7 48.656 48.656 0 0 0-7.324 0 4.006 4.006 0 0 0-3.7 3.7C4.547 9.547 4.5 10.768 4.5 12s.047 2.453.138 3.662a4.006 4.006 0 0 0 3.7 3.7 48.656 48.656 0 0 0 7.324 0 4.006 4.006 0 0 0 3.7-3.7C19.453 14.453 19.5 13.232 19.5 12Zm0 0h.008v.008h-.008V12Zm-3 0h.008v.008h-.008V12c0-1.68-.282-3.297-.802-4.806m-9.396 0A20.732 20.732 0 0 1 12 6.75c1.455 0 2.843.15 4.198.437M12 6.75a20.733 20.733 0 0 0-4.198.437m0 0A20.73 20.73 0 0 0 7 12c0 1.68.282 3.297.802 4.806m9.396 0A20.73 20.73 0 0 1 12 17.25c-1.455 0-2.843-.15-4.198-.437M12 17.25a20.73 20.73 0 0 0 4.198-.437" />
                                </svg>
                            </div>
                            <div class="flex flex-col min-w-0">
                                <h2 class="text-base sm:text-lg font-semibold text-white truncate">{{ t('market.schemes') }}</h2>
                                <span class="text-xs text-white/40 truncate">{{ t('market.schemes_hint') }}</span>
                            </div>
                        </div>
                        <div class="flex items-center gap-2.5 sm:gap-4 shrink-0">
                            <span class="badge bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 text-xs py-1 px-2.5 sm:px-3 whitespace-nowrap">
                                {{ t('market.schemes_found', { count: arbitrageLoops.length }) }}
                            </span>
                            <button @click="toggleArbitrageSchemes" :aria-label="t('market.schemes')" :aria-expanded="showArbitrageSchemes ? 'true' : 'false'" class="text-white/40 hover:text-white transition-colors duration-300">
                                <svg v-if="showArbitrageSchemes" class="w-5 h-5 transition-transform duration-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7" />
                                </svg>
                                <svg v-else class="w-5 h-5 transition-transform duration-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <transition name="smooth-accordion">
                        <div v-show="showArbitrageSchemes" class="space-y-4 max-h-[500px] overflow-y-auto pr-1 sm:pr-2 scrollbar-thin">
                            <div v-for="(scheme, idx) in arbitrageLoops" :key="'scheme-'+idx"
                                 class="p-3.5 sm:p-4 rounded-xl border border-white/5 bg-white/[0.02] hover:bg-white/[0.04] transition-all flex flex-col gap-3 sm:gap-4">
                                <!-- Card Header (Type & Profit) -->
                                <div class="flex items-center justify-between flex-wrap gap-2.5 border-b border-white/5 pb-2.5">
                                    <div class="flex items-center flex-wrap gap-2">
                                        <span class="badge text-[10px] font-semibold tracking-wider uppercase"
                                              :class="scheme.type === '2-step' ? 'bg-blue-500/10 text-blue-400 border border-blue-500/20' : 'bg-purple-500/10 text-purple-400 border border-purple-500/20'">
                                            {{ t('market.loop_type', { type: scheme.type }) }}
                                        </span>

                                        <!-- Leftovers -->
                                        <div v-if="scheme.leftovers && scheme.leftovers.length" class="flex items-center gap-1.5 text-xs text-blue-400 bg-blue-500/10 border border-blue-500/20 rounded-lg py-0.5 px-2">
                                            <span class="text-white/40 text-[11px]">{{ t('market.leftovers') }}</span>
                                            <span v-for="leftover in scheme.leftovers" :key="leftover.item_id" class="flex items-center gap-1 text-white/80 font-mono text-[11px]">
                                                <img alt="" :src="getResourceIcon(leftover.item_id)" @error="handleIconError($event, leftover.item_id)" class="w-3.5 h-3.5 object-contain shrink-0" />
                                                +{{ formatVolume(leftover.amount) }}
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Net Profit -->
                                    <div class="flex items-center gap-1.5 sm:gap-2 ml-auto">
                                        <span class="text-xs text-white/40 hidden xs:inline">{{ t('market.net_profit') }}</span>
                                        <div class="flex items-center gap-1.5 bg-emerald-500/10 border border-emerald-500/20 rounded-lg py-1 px-2">
                                            <img :alt="getItemName(scheme.profit.item_name, scheme.profit.item_id)" :src="getResourceIcon(scheme.profit.item_id)" @error="handleIconError($event, scheme.profit.item_id)" class="w-4 h-4 object-contain shrink-0" />
                                            <span class="font-mono text-sm font-bold text-emerald-400 shrink-0">+{{ formatVolume(scheme.profit.amount) }}</span>
                                            <span class="text-xs text-emerald-400/80 truncate max-w-[90px] xs:max-w-[130px] sm:max-w-[180px]" :title="getItemName(scheme.profit.item_name, scheme.profit.item_id)">
                                                {{ getItemName(scheme.profit.item_name, scheme.profit.item_id) }}
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Steps flowchart -->
                                <div class="flex flex-col lg:flex-row lg:items-stretch gap-2.5 sm:gap-3">
                                    <template v-for="(step, sIdx) in scheme.steps" :key="sIdx">
                                        <!-- Step Card -->
                                        <div class="flex-1 min-w-0 p-3 sm:p-3.5 rounded-lg bg-white/[0.02] border border-white/5 hover:border-white/10 hover:bg-white/[0.04] transition-all flex flex-col justify-between gap-2.5">
                                            <!-- Step header -->
                                            <div class="flex items-center justify-between gap-2 border-b border-white/[0.04] pb-2">
                                                <span class="text-[10px] uppercase font-bold text-emerald-400/80 tracking-wider bg-emerald-500/10 px-1.5 py-0.5 rounded border border-emerald-500/20">
                                                    {{ t('market.step_num', { step: sIdx + 1 }) }}
                                                </span>
                                                <div class="text-[11px] text-white/40 flex items-center gap-1 min-w-0 truncate" :title="step.sender">
                                                    <span class="shrink-0">{{ t('market.by') }}</span>
                                                    <strong class="text-white/85 font-medium truncate">{{ step.sender }}</strong>
                                                </div>
                                            </div>

                                            <!-- Step body -->
                                            <div class="flex flex-col gap-2 bg-black/20 rounded-md p-2.5 border border-white/[0.03]">
                                                <div class="flex items-center justify-between gap-2 text-xs">
                                                    <span class="text-white/40 text-[11px] font-medium shrink-0">{{ t('market.give') }}</span>
                                                    <div class="flex items-center gap-1.5 min-w-0 flex-wrap justify-end">
                                                        <img :alt="getItemName(step.give_name, step.give_item)" :src="getResourceIcon(step.give_item)" @error="handleIconError($event, step.give_item)" class="w-4 h-4 object-contain shrink-0" />
                                                        <span class="font-mono font-semibold text-white/90 shrink-0">{{ formatVolume(step.give_per_lot) }}</span>
                                                        <span class="text-[10px] text-white/35 shrink-0 whitespace-nowrap">({{ formatVolume(step.give_amount) }})</span>
                                                    </div>
                                                </div>
                                                <div class="flex items-center justify-between gap-2 text-xs">
                                                    <span class="text-white/40 text-[11px] font-medium shrink-0">{{ t('market.get') }}</span>
                                                    <div class="flex items-center gap-1.5 min-w-0 flex-wrap justify-end">
                                                        <img :alt="getItemName(step.receive_name, step.receive_item)" :src="getResourceIcon(step.receive_item)" @error="handleIconError($event, step.receive_item)" class="w-4 h-4 object-contain shrink-0" />
                                                        <span class="font-mono font-semibold text-emerald-400 shrink-0">{{ formatVolume(step.receive_per_lot) }}</span>
                                                        <span class="text-[10px] text-emerald-400/50 shrink-0 whitespace-nowrap">({{ formatVolume(step.receive_amount) }})</span>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Step footer -->
                                            <div class="text-[11px] text-white/40 flex items-center justify-between gap-2 pt-0.5">
                                                <span class="shrink-0">{{ t('market.lots') }} <strong class="text-white/85 font-mono">{{ step.lots }}</strong></span>
                                                <span class="text-[10px] text-white/35 truncate max-w-[160px]" :title="getItemName(step.give_name, step.give_item) + ' → ' + getItemName(step.receive_name, step.receive_item)">
                                                    {{ getItemName(step.give_name, step.give_item) }} → {{ getItemName(step.receive_name, step.receive_item) }}
                                                </span>
                                            </div>
                                        </div>

                                        <!-- Arrow between steps -->
                                        <div v-if="sIdx < scheme.steps.length - 1" class="flex items-center justify-center shrink-0 text-white/25 py-1 lg:py-0 lg:px-1">
                                            <svg class="w-5 h-5 hidden lg:block animate-pulse" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                                            </svg>
                                            <svg class="w-4 h-4 lg:hidden animate-pulse" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                            </svg>
                                        </div>
                                    </template>
                                </div>
                            </div>

                            <div v-if="arbitrageLoops.length === 0" class="py-8 text-center text-white/20">
                                {{ t('market.no_schemes_for_server', { server: selectedServerId }) }}
                            </div>
                        </div>
                    </transition>
                </div>

                <!-- Current Active Market Listings Card -->
                <div class="glass-card p-4 sm:p-6 animate-fade-in-up transition-all duration-500 hover:border-white/20">
                    <div class="flex items-center justify-between gap-3 mb-4 sm:mb-6 border-b border-white/5 pb-3">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white shadow-md shadow-indigo-500/20 shrink-0">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" />
                                </svg>
                            </div>
                            <div class="flex flex-col">
                                <h2 class="text-base sm:text-lg font-semibold text-white">{{ t('market.listings') }}</h2>
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
                        <div v-show="showActiveListings">
                            <market-data-table :columns="activeOffersColumns" :rows="activeOffers" :empty-text="t('market.no_active_listings')">
                                <template #cell-sender_name="{ row }">
                                    <span class="font-semibold text-white truncate">{{ row.sender_name }}</span>
                                </template>
                                <template #cell-item_name="{ row }">
                                    <div class="flex items-center gap-1.5 justify-end sm:justify-start">
                                        <img :alt="getItemName(row.item_name, row.item_id)" :src="getResourceIcon(row.item_id, row.item_kind)" @error="handleIconError($event, row.item_id, row.item_kind)" class="w-4 h-4 sm:w-5 sm:h-5 object-contain flex-shrink-0" />
                                        <span class="font-mono text-white/90 font-semibold whitespace-nowrap">{{ formatVolume(row.amount) }}</span>
                                        <span class="text-[11px] sm:text-xs text-white/40 truncate max-w-[75px] xs:max-w-[85px] sm:max-w-[100px]">{{ getItemName(row.item_name, row.item_id) }}</span>
                                        <span v-if="row.item_kind && row.item_kind !== 'resource'" class="px-1.5 py-0.5 rounded text-[9px] font-bold uppercase tracking-wider" :class="getKindBadgeClass(row.item_kind)">
                                            {{ getKindLabel(row.item_kind) }}
                                        </span>
                                    </div>
                                </template>
                                <template #cell-target_item_name="{ row }">
                                    <div class="flex items-center gap-1.5 justify-end sm:justify-start">
                                        <template v-if="row.target_item_id">
                                            <img :alt="getItemName(row.target_item_name, row.target_item_id)" :src="getResourceIcon(row.target_item_id, row.target_item_kind)" @error="handleIconError($event, row.target_item_id, row.target_item_kind)" class="w-4 h-4 sm:w-5 sm:h-5 object-contain flex-shrink-0" />
                                            <span class="font-mono text-white/90 font-semibold whitespace-nowrap">{{ formatVolume(row.target_amount) }}</span>
                                            <span class="text-[11px] sm:text-xs text-white/40 truncate max-w-[75px] xs:max-w-[85px] sm:max-w-[100px]">{{ getItemName(row.target_item_name, row.target_item_id) }}</span>
                                            <span v-if="row.target_item_kind && row.target_item_kind !== 'resource'" class="px-1.5 py-0.5 rounded text-[9px] font-bold uppercase tracking-wider" :class="getKindBadgeClass(row.target_item_kind)">
                                                {{ getKindLabel(row.target_item_kind) }}
                                            </span>
                                        </template>
                                        <span v-else class="text-xs text-white/30 italic">—</span>
                                    </div>
                                </template>
                                <template #cell-price="{ row }">
                                    <span v-if="row.price !== null && row.price !== undefined" class="text-emerald-400 font-mono font-medium">{{ row.price }}</span>
                                    <span v-else class="text-white/30 font-mono text-xs">—</span>
                                </template>
                                <template #cell-lots_remaining="{ row }">
                                    <span class="text-blue-400 font-mono">{{ row.lots_remaining }}</span>
                                </template>
                                <template #cell-time_left="{ row }">
                                    <span class="font-mono text-xs" :class="getOfferTimeLeft(row) > 0 ? 'text-amber-400' : 'text-red-500'">
                                        {{ formatTimeLeft(getOfferTimeLeft(row)) }}
                                    </span>
                                </template>
                            </market-data-table>

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
            <div class="glass-card p-4 sm:p-6 transition-all duration-300">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 sm:gap-4 mb-4 sm:mb-6 border-b border-white/5 pb-3 sm:pb-4">
                    <div class="flex items-center gap-3 cursor-pointer select-none group" @click="isServersCollapsed = !isServersCollapsed">
                        <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white shrink-0 group-hover:scale-105 transition-transform">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 14.25h13.5m-13.5 0a3 3 0 0 1-3-3m3 3a3 3 0 1 0 0 6h13.5a3 3 0 1 0 0-6m-13.5 0H3m16.5 0a3 3 0 0 0 3-3m-3 3a3 3 0 1 1 0 6M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                            </svg>
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <h2 class="text-base sm:text-lg font-semibold text-white group-hover:text-emerald-400 transition-colors">{{ t('market.servers_title') }}</h2>
                                <span class="badge bg-white/10 text-white/70 text-[11px] font-mono py-0.5 px-2">
                                    {{ servers.length }}
                                </span>
                            </div>
                            <p class="text-xs text-white/40">{{ t('market.servers_subtitle') }}</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 w-full sm:w-auto">
                        <button @click="isServersCollapsed = !isServersCollapsed"
                                type="button"
                                class="btn-secondary py-1.5 px-3 text-xs flex items-center justify-center gap-1.5 text-white/70 hover:text-white flex-1 sm:flex-initial"
                                :title="isServersCollapsed ? t('market.expand') : t('market.collapse')">
                            <span>{{ isServersCollapsed ? t('market.expand') : t('market.collapse') }}</span>
                            <svg class="w-3.5 h-3.5 transition-transform duration-300" :class="{ 'rotate-180': !isServersCollapsed }" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                            </svg>
                        </button>

                        <button @click="openAddServerModal" class="btn-primary py-2 px-4 text-xs flex items-center justify-center gap-1.5 flex-1 sm:flex-initial">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                            </svg>
                            {{ t('market.add_server') }}
                        </button>
                    </div>
                </div>

                <div v-show="!isServersCollapsed">
                    <market-data-table :columns="serverColumns" :rows="servers" :empty-text="t('market.no_servers_row')">
                        <template #cell-locale="{ row }">
                            <div class="flex items-center gap-2 min-w-0">
                                <span class="text-base flex-shrink-0">{{ getLocaleFlag(row.locale) }}</span>
                                <div class="min-w-0">
                                    <span class="font-semibold text-white truncate block">{{ row.display_name }}</span>
                                    <span class="block text-[10px] text-white/35 font-mono">{{ row.server_id }} ({{ row.locale }})</span>
                                </div>
                            </div>
                        </template>
                        <template #cell-account="{ row }">
                            <div v-if="row.account" class="flex flex-col items-end sm:flex-row sm:items-center gap-1 sm:gap-1.5 min-w-0 max-w-full text-right sm:text-left">
                                <div class="flex items-center gap-1.5 min-w-0 max-w-full">
                                    <span class="w-2 h-2 rounded-full shrink-0" :class="row.account.status === 'online' ? 'bg-emerald-500' : 'bg-white/30'"></span>
                                    <span class="font-medium text-white/90 truncate max-w-[140px] xs:max-w-[180px] sm:max-w-none" :title="row.account.username">{{ row.account.username }}</span>
                                </div>
                                <div class="flex items-center gap-1.5 flex-wrap justify-end">
                                    <span class="text-xs text-white/40">({{ row.account.nickname || t('market.no_nick') }})</span>
                                    <span v-if="row.account.server_name" class="badge badge-success bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 text-[10px]">
                                        {{ row.account.server_name }}
                                    </span>
                                </div>
                            </div>
                            <span v-else class="text-xs text-white/30 italic">{{ t('market.not_assigned') }}</span>
                        </template>
                        <template #cell-verification_status="{ row }">
                            <span class="badge text-[10px]" :class="getVerificationBadgeClass(row.verification_status)">
                                {{ row.verification_status }}
                            </span>
                        </template>
                        <template #cell-sync_status="{ row }">
                            <span class="badge text-[10px]" :class="getSyncBadgeClass(row.sync_status)">
                                {{ row.sync_status }}
                            </span>
                        </template>
                        <template #cell-last_synced_at="{ row }">
                            <div class="text-right sm:text-left">
                                <span class="font-mono text-xs text-white/60">{{ formatDateTime(row.last_synced_at) }}</span>
                                <span v-if="row.last_error" class="block text-[10px] text-red-400 truncate max-w-[150px]" :title="row.last_error">
                                    {{ row.last_error }}
                                </span>
                            </div>
                        </template>
                        <template #cell-actions="{ row }">
                            <div class="flex items-center justify-end flex-wrap gap-1.5 sm:gap-2">
                                <button @click="verifyServer(row)" :disabled="verifyingId === row.id" class="btn-secondary py-1 px-2.5 text-[11px]" :title="t('market.verify_hint')">
                                    {{ verifyingId === row.id ? '...' : t('market.verify') }}
                                </button>
                                <button @click="syncServerNow(row)" :disabled="syncing || !row.account_id" class="btn-secondary py-1 px-2.5 text-[11px] text-emerald-400 border-emerald-500/20 hover:bg-emerald-500/10 inline-flex items-center gap-1.5 disabled:opacity-50">
                                    <svg v-if="syncingServerId === row.id" class="animate-spin w-3 h-3 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                    </svg>
                                    <span>{{ syncingServerId === row.id ? '...' : t('market.sync_now') }}</span>
                                </button>
                                <button @click="openEditServerModal(row)" class="btn-secondary py-1 px-2.5 text-[11px]">
                                    {{ t('market.edit') }}
                                </button>
                                <button @click="deleteServer(row)" class="btn-secondary py-1 px-2.5 text-[11px] text-red-400 hover:bg-red-500/10 border-red-500/20">
                                    {{ t('market.delete') }}
                                </button>
                            </div>
                        </template>
                    </market-data-table>
                </div>
            </div>

            <!-- Section 2: Global Settings -->
            <div class="glass-card p-4 sm:p-6">
                <div class="flex items-center gap-3 mb-4 sm:mb-5 border-b border-white/5 pb-3">
                    <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-emerald-500 to-teal-600 flex items-center justify-center text-white shrink-0">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                    </div>
                    <h2 class="text-base sm:text-lg font-semibold text-white">{{ t('market.autosync_schedule') }}</h2>
                </div>

                <form @submit.prevent="saveSettings">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-6 mb-4 sm:mb-6">
                        <div>
                            <label for="market-settings-sync-interval" class="block text-xs font-medium text-white/40 mb-1.5 sm:mb-2 uppercase tracking-wider">{{ t('market.sync_interval') }}</label>
                            <div class="relative">
                                <select id="market-settings-sync-interval" v-model="settingsForm.sync_interval" :aria-label="t('market.sync_interval')" class="glass-select w-full text-xs sm:text-sm">
                                    <option value="5" class="bg-dark-900">5 minutes</option>
                                    <option value="15" class="bg-dark-900">15 minutes</option>
                                    <option value="30" class="bg-dark-900">30 minutes</option>
                                    <option value="60" class="bg-dark-900">1 hour</option>
                                    <option value="custom" class="bg-dark-900">{{ t('market.custom') }}</option>
                                </select>
                            </div>
                        </div>

                        <div v-if="settingsForm.sync_interval === 'custom'">
                            <label for="market-settings-custom-interval" class="block text-xs font-medium text-white/40 mb-1.5 sm:mb-2 uppercase tracking-wider">{{ t('market.custom_interval') }}</label>
                            <input id="market-settings-custom-interval" type="number" v-model.number="settingsForm.custom_interval_minutes" min="1" :aria-label="t('market.custom_interval')" class="glass-input w-full font-mono text-white text-xs sm:text-sm"/>
                        </div>
                    </div>

                    <button type="submit" :disabled="saving" class="btn-primary flex items-center justify-center gap-2 text-xs w-full sm:w-auto">
                        {{ t('market.save_schedule') }}
                    </button>
                </form>
            </div>

            <!-- Section 3: Sync Logs -->
            <div class="glass-card p-4 sm:p-6">
                <div class="flex items-center justify-between gap-3 mb-4 sm:mb-6 border-b border-white/5 pb-3">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-teal-500 to-emerald-600 flex items-center justify-center text-white shrink-0">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 0 1 0 3.75H5.625a1.875 1.875 0 0 1 0-3.75Z" />
                            </svg>
                        </div>
                        <h2 class="text-base sm:text-lg font-semibold text-white">{{ t('market.sync_log') }}</h2>
                    </div>
                    <button @click="loadSyncLogs(logsPagination.current_page || 1)" :disabled="loadingSyncLogs" class="btn-secondary py-1 px-3 text-xs flex items-center gap-1.5 hover:border-emerald-500/30 hover:text-emerald-400 disabled:opacity-50">
                        <svg class="w-3.5 h-3.5" :class="{ 'animate-spin': loadingSyncLogs }" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182" />
                        </svg>
                        <span>{{ t('common.refresh') }}</span>
                    </button>
                </div>

                <div>
                    <div v-if="loadingSyncLogs && logs.length === 0" class="py-8 text-center text-xs text-emerald-400 flex items-center justify-center gap-2">
                        <spinner size="sm" />
                        <span>{{ t('common.loading_data') }}</span>
                    </div>
                    <market-data-table v-else :columns="syncLogColumns" :rows="logs" :empty-text="t('market.no_sync_logs')">
                        <template #cell-server_id="{ row }">
                            <span class="font-mono text-xs text-emerald-400 font-bold">{{ row.server_id || '-' }}</span>
                        </template>
                        <template #cell-date="{ row }">
                            <span class="font-mono text-xs text-white/70">{{ formatDateTime(row.date) }}</span>
                        </template>
                        <template #cell-action="{ row }">
                            <span class="font-semibold text-white/95">{{ row.action }}</span>
                        </template>
                        <template #cell-status="{ row }">
                            <span class="badge text-[10px]" :class="getStatusBadgeClass(row.status)">
                                {{ row.status }}
                            </span>
                        </template>
                        <template #cell-message="{ row }">
                            <span class="text-xs text-white/50 break-words">{{ row.message }}</span>
                        </template>
                    </market-data-table>
                </div>

                <!-- Pagination Controls -->
                <div v-if="logsPagination.last_page > 1" class="flex flex-col sm:flex-row items-center justify-between gap-3 mt-4 pt-4 border-t border-white/5">
                    <p class="text-xs text-white/30">
                        {{ t('logs.page_of', { current: logsPagination.current_page, last: logsPagination.last_page }) }}
                    </p>
                    <div class="flex items-center gap-2">
                        <button :disabled="logsPagination.current_page === 1 || loadingSyncLogs" @click="loadSyncLogs(logsPagination.current_page - 1)"
                                class="btn-secondary py-1 px-3 text-xs flex items-center gap-1 hover:border-emerald-500/30 hover:text-emerald-400 disabled:opacity-50">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                            </svg>
                            <span>{{ t('common.previous') }}</span>
                        </button>
                        <button :disabled="logsPagination.current_page === logsPagination.last_page || loadingSyncLogs" @click="loadSyncLogs(logsPagination.current_page + 1)"
                                class="btn-secondary py-1 px-3 text-xs flex items-center gap-1 hover:border-emerald-500/30 hover:text-emerald-400 disabled:opacity-50">
                            <span>{{ t('common.next') }}</span>
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add / Edit Server Connection Modal -->
        <div v-if="showServerModal" class="fixed inset-0 z-50 flex items-center justify-center p-2 sm:p-4 bg-black/70 backdrop-blur-sm animate-fade-in">
            <div class="glass-card max-w-lg w-full p-4 sm:p-6 space-y-4 sm:space-y-6 border border-white/10 shadow-2xl max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between border-b border-white/10 pb-3 sm:pb-4">
                    <h2 class="text-base sm:text-lg font-bold text-white">
                        {{ editingServer ? t('market.edit_server_connection') : t('market.add_server_connection') }}
                    </h2>
                    <button @click="closeServerModal" class="text-white/40 hover:text-white p-1 rounded-lg hover:bg-white/5">
                        <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div v-if="editingServer" class="p-2.5 sm:p-3 rounded-lg bg-white/5 border border-white/10 text-xs text-white/60 space-y-1">
                    <div>{{ t('market.server') }}: <span class="font-mono text-white/80">{{ editingServer.server_id }}</span> ({{ editingServer.locale }})</div>
                    <div>{{ editingServer.display_name }}</div>
                </div>

                <form @submit.prevent="saveServerModal" class="space-y-3.5 sm:space-y-4">
                    <div>
                        <label for="market-server-account" class="block text-xs font-medium text-white/40 mb-1.5 uppercase">{{ t('market.sync_account') }}</label>
                        <select id="market-server-account" v-model="serverForm.account_id" required :aria-label="t('market.sync_account')" class="glass-select w-full text-xs sm:text-sm">
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

                    <div class="flex justify-end gap-2 sm:gap-3 pt-3 sm:pt-4 border-t border-white/10">
                        <button type="button" @click="closeServerModal" class="btn-secondary text-xs py-2 px-3 sm:px-4">{{ t('market.cancel') }}</button>
                        <button type="submit" :disabled="savingServer || !serverForm.account_id || !!(detectedServerInfo && detectedServerInfo.error)" class="btn-primary text-xs py-2 px-3 sm:px-4">
                            {{ savingServer ? t('market.saving') : t('market.save') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { t } from '../lang';
import { marketApi } from '../services/api/market';
import { accountsApi } from '../services/api/accounts';
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
        const showActiveListings = ref(typeof window !== 'undefined' ? window.innerWidth >= 640 : false);
        const isServersCollapsed = ref(typeof window !== 'undefined' ? localStorage.getItem('market_servers_collapsed') === 'true' : false);

        watch(isServersCollapsed, (val) => {
            if (typeof window !== 'undefined') {
                localStorage.setItem('market_servers_collapsed', String(val));
            }
        });

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

        const popularColumns = computed(() => [
            { key: 'item_name', label: t('market.item_name') },
            { key: 'item_id', label: t('market.item_code') },
            { key: 'offers_count', label: t('market.active_offers'), align: 'right' },
            { key: 'sellers_count', label: t('market.unique_sellers'), align: 'right' },
            { key: 'total_volume', label: t('market.active_volume'), align: 'right' },
        ]);

        const activeOffersColumns = computed(() => [
            { key: 'sender_name', label: t('market.player') },
            { key: 'item_name', label: t('market.selling_resource') },
            { key: 'target_item_name', label: t('market.buying_resource') },
            { key: 'price', label: t('market.price'), align: 'right' },
            { key: 'lots_remaining', label: t('market.lots_remaining'), align: 'right' },
            { key: 'time_left', label: t('market.time_left'), align: 'right' },
        ]);

        const serverColumns = computed(() => [
            { key: 'locale', label: t('market.col_server_locale') },
            { key: 'account', label: t('market.col_account') },
            { key: 'verification_status', label: t('market.col_verification') },
            { key: 'sync_status', label: t('market.status') },
            { key: 'last_synced_at', label: t('market.last_sync') },
            { key: 'actions', label: t('market.col_actions'), align: 'right' },
        ]);

        const syncLogColumns = computed(() => [
            { key: 'server_id', label: t('market.server') },
            { key: 'date', label: t('market.date') },
            { key: 'action', label: t('market.action') },
            { key: 'status', label: t('market.status') },
            { key: 'message', label: t('market.message') },
        ]);

        const nowSec = ref(Math.floor(Date.now() / 1000));
        const getOfferTimeLeft = (offer) => {
            if (offer && offer._expiresTimestamp) {
                return Math.max(0, offer._expiresTimestamp - nowSec.value);
            }
            return Math.max(0, Number(offer?.time_left) || 0);
        };

        let countdownInterval = null;
        const startCountdown = () => {
            if (countdownInterval) clearInterval(countdownInterval);
            countdownInterval = setInterval(() => {
                if (typeof document !== 'undefined' && document.hidden) return;
                nowSec.value = Math.floor(Date.now() / 1000);
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
        const selectedKind = ref('all');
        const searchQuery = ref('');

        const resolveItemKind = (item, idKey = 'item_id') => {
            if (item.kind && item.kind !== 'resource') return item.kind;
            const rawId = String(item[idKey] || '');
            if (rawId.startsWith('adventure:')) return 'adventure';
            if (rawId.startsWith('buff:')) return 'buff';
            if (rawId.startsWith('building:')) return 'building';
            return item.kind || 'resource';
        };

        const getKindBadgeClass = (kind) => {
            switch (kind) {
                case 'adventure': return 'bg-amber-500/20 text-amber-300 border border-amber-500/30';
                case 'buff': return 'bg-purple-500/20 text-purple-300 border border-purple-500/30';
                case 'building': return 'bg-blue-500/20 text-blue-300 border border-blue-500/30';
                default: return 'bg-white/10 text-white/60';
            }
        };

        const getKindLabel = (kind) => {
            switch (kind) {
                case 'adventure': return 'ADV';
                case 'buff': return 'Buff';
                case 'building': return 'BLD';
                default: return kind;
            }
        };

        // Single source of truth for market item names (shared with public portal):
        // see resources/js/lang/gameNames.js -> marketItemName().
        const getItemName = (name, id) => marketItemName(name, id);

        // Complete catalog of tradable resources from game XML combined with server items:
        // resources without active offers are also displayed (dimmed) to show the whole market.
        const allGoods = computed(() => {
            const known = new Set(goods.value.map(g => g.item_id));
            const extras = TRADABLE_RESOURCES
                .filter(name => !known.has(name))
                .map(name => ({ item_id: name, item_name: resourceName(name), kind: 'resource', no_offers: true }));
            return [...goods.value, ...extras];
        });

        const filteredGoods = computed(() => {
            let list = allGoods.value;
            if (selectedKind.value !== 'all') {
                list = list.filter(g => resolveItemKind(g, 'item_id') === selectedKind.value);
            }
            const q = searchQuery.value.trim().toLowerCase();
            if (q) {
                list = list.filter(g => {
                    const name = getItemName(g.item_name, g.item_id).toLowerCase();
                    const id = String(g.item_id || '').toLowerCase();
                    return name.includes(q) || id.includes(q);
                });
            }
            return list;
        });

        const filteredTargets = computed(() => {
            let list = targets.value;
            if (selectedKind.value !== 'all') {
                list = list.filter(t => resolveItemKind(t, 'target_item_id') === selectedKind.value);
            }
            const q = searchQuery.value.trim().toLowerCase();
            if (q) {
                list = list.filter(t => {
                    const name = getItemName(t.target_item_name, t.target_item_id).toLowerCase();
                    const id = String(t.target_item_id || '').toLowerCase();
                    return name.includes(q) || id.includes(q);
                });
            }
            return list;
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
        const isReversedCalc = ref(false);

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

        const effectivePrice = computed(() => {
            if (!stats.value) return 0;
            return (stats.value.average && stats.value.average > 0) ? stats.value.average : (stats.value.current || 0);
        });

        const effectiveMirroredPrice = computed(() => {
            if (!mirroredStats.value) return 0;
            return (mirroredStats.value.average && mirroredStats.value.average > 0) ? mirroredStats.value.average : (mirroredStats.value.current || 0);
        });

        const calculatedCost = computed(() => {
            const price = effectivePrice.value;
            if (!price || price === 0) return 0;
            const amt = parseFloat(calcAmount.value) || 0;
            if (isReversedCalc.value) {
                return Math.round((amt / price) * 100) / 100;
            }
            return Math.round(amt * price * 100) / 100;
        });

        const calculatedMirroredCost = computed(() => {
            const price = effectiveMirroredPrice.value;
            if (!price || price === 0) return 0;
            const amt = parseFloat(calcAmount.value) || 0;
            if (isReversedCalc.value) {
                return Math.round(amt * price * 100) / 100;
            }
            return Math.round((amt / price) * 100) / 100;
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

        const getResourceIcon = (itemId, kind = null) =>
            getGameImageUrl(kind || 'resource', itemId || 'addresource');

        const handleIconError = (event, itemId, kind = null) =>
            handleGameImageError(event, kind || 'resource', itemId || 'addresource', '/images/resources/addresource.webp');

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
                const res = await marketApi.fetchMarketServers();
                const data = res.data || res;
                servers.value = data.servers || [];
                accounts.value = data.accounts || [];
                presets.value = data.presets || [];
                settingsForm.value = data.settings || { sync_interval: '15', custom_interval_minutes: 15 };

                const strategy = data.settings?.cache_strategy || data.cache_strategy;
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
                const mapOffers = (rawOffers) => {
                    const now = Math.floor(Date.now() / 1000);
                    return (rawOffers || [])
                        .filter(offer => offer && typeof offer === 'object')
                        .map(offer => {
                            const expiresAt = offer.expires_at ? new Date(offer.expires_at).getTime() : 0;
                            const expSec = expiresAt > 0 ? Math.floor(expiresAt / 1000) : (now + (Number(offer.time_left) || 0));
                            return {
                                ...offer,
                                _expiresTimestamp: expSec,
                                time_left: Math.max(0, expSec - now)
                            };
                        });
                };

                const applyBulkData = (data) => {
                    if (!data) return;
                    goods.value = data.goods || [];
                    const rawPopular = (data.popular && data.popular['1d']) || [];
                    const unwrappedPopular = Array.isArray(rawPopular) ? rawPopular : (rawPopular?.data || []);
                    popular.value = unwrappedPopular.filter(item => item && typeof item === 'object' && item.item_id);
                    activeOffers.value = mapOffers(data.active_offers);
                    totalActiveCount.value = data.total_active_count !== undefined ? data.total_active_count : activeOffers.value.length;
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
                    const onRevalidateOverview = (freshAnalytics) => {
                        if (freshAnalytics) {
                            activeOffers.value = mapOffers(freshAnalytics.active_offers);
                            totalActiveCount.value = freshAnalytics.total_active_count !== undefined ? freshAnalytics.total_active_count : activeOffers.value.length;
                        }
                    };

                    // Individual mode: fetch individual granular endpoints separately
                    const [goodsRes, popularRes, arbitrageRes, analyticsRes] = await Promise.all([
                        cachedGet('/api/market/goods', { params: { server_id: selectedServerId.value }, ...options }),
                        cachedGet('/api/market/popular', { params: { server_id: selectedServerId.value, period: '1d' }, ...options }),
                        cachedGet('/api/market/arbitrage', { params: { server_id: selectedServerId.value }, ...options }),
                        cachedGet('/api/market/analytics', { params: { server_id: selectedServerId.value }, onRevalidate: onRevalidateOverview, ...options }),
                    ]);
                    goods.value = goodsRes || [];
                    const unwrappedPopular = Array.isArray(popularRes) ? popularRes : (popularRes?.data || []);
                    popular.value = unwrappedPopular.filter(item => item && typeof item === 'object' && item.item_id);
                    arbitrageLoops.value = arbitrageRes || [];
                    if (analyticsRes) {
                        activeOffers.value = mapOffers(analyticsRes.active_offers);
                        totalActiveCount.value = analyticsRes.total_active_count !== undefined ? analyticsRes.total_active_count : activeOffers.value.length;
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
                const res = await marketApi.fetchMarketLogs({ server_id: selectedServerId.value, page, limit: 10 });
                const logsList = Array.isArray(res.data) ? res.data : (Array.isArray(res) ? res : (res.logs || []));
                logs.value = logsList;
                logsPagination.value = {
                    current_page: res.current_page || 1,
                    last_page: res.last_page || 1,
                    total: res.total || logsList.length,
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
                const res = await accountsApi.fetchAccounts();
                accounts.value = res.data || res.accounts || [];
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
                    await marketApi.updateMarketServer(editingServer.value.id, payload);
                    showToast(t('market.server_updated'));
                } else {
                    await marketApi.saveMarketServer(payload);
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
                await marketApi.deleteMarketServer(srv.id);
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
                const res = await marketApi.verifyMarketServer(srv.id);
                showToast(res.message || t('market.verification_complete'), res.success ? 'success' : 'warning');
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
                const res = await marketApi.syncMarketServer(targetServer.id);
                showToast(t('market.sync_complete', { message: res.message || '' }));
                clearApiCache();
                await loadServers();
                await loadAnalyticsData({ bypass: true });
                if (selectedItem.value && selectedTarget.value) {
                    await fetchAnalytics({ bypass: true });
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
                await marketApi.updateMarketSettings(settingsForm.value);
                showToast(t('market.schedule_saved'));
            } catch (e) {
                showToast(t('market.save_settings_failed'), 'error');
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

        const switchVisualTab = (tab) => {
            if (tab === 2 && !selectedItem.value) return;
            visualTab.value = tab;
            searchQuery.value = '';
        };

        const selectVisualItem = async (itemId) => {
            selectedItem.value = itemId;
            searchQuery.value = '';
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
            searchQuery.value = '';
            updateQueryParams();
        };

        const mirrorSelection = async () => {
            if (!selectedItem.value || !selectedTarget.value || !selectedServerId.value) return;
            const tempItem = selectedItem.value;
            const tempTarget = selectedTarget.value;
            const targetName = selectedTargetName.value;

            const canSellTarget = goods.value.some(g => g.item_id === tempTarget);
            if (!canSellTarget) {
                showToast(t('market.cannot_mirror_no_listings', { name: targetName }), 'warning');
                return;
            }

            try {
                let targetList = [];
                const bulk = readBulkCache(selectedServerId.value);
                if (bulk && bulk.targets_map && bulk.targets_map[tempTarget]) {
                    targetList = bulk.targets_map[tempTarget];
                } else {
                    const data = await cachedGet('/api/market/targets', {
                        params: { server_id: selectedServerId.value, item_id: tempTarget },
                        ttlMs: bulkCacheTtlMs.value
                    });
                    targetList = data || [];
                }

                const hasOldItemAsTarget = targetList.some(t => t.target_item_id === tempItem);
                if (hasOldItemAsTarget) {
                    selectedItem.value = tempTarget;
                    targets.value = targetList;
                    selectedTarget.value = tempItem;
                    updateQueryParams();
                    await fetchAnalytics();
                } else {
                    showToast(t('market.opposite_trade_not_found'), 'info');
                }
            } catch (e) {
                showToast(t('market.mirror_failed'), 'error');
            }
        };

        const changePeriod = (val) => {
            selectedPeriod.value = val;
            fetchAnalytics();
        };

        watch(activeTab, (newTab) => {
            if (newTab === 'settings') {
                loadSyncLogs(1);
            }
        });

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
</script>
