<template>
    <div>
        <!-- Page Header -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
            <div>
                <h1 class="text-3xl font-bold text-white">{{ t('market.title') }}</h1>
                <p class="text-white/40 mt-1">{{ t('market.subtitle') }}</p>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <!-- Server Selector -->
                <div v-if="servers.length > 0" class="flex items-center gap-2 bg-white/5 border border-white/10 p-1.5 rounded-xl">
                    <span class="text-xs font-semibold text-white/40 uppercase tracking-wider px-2">{{ t('market.server') }}:</span>
                    <select v-model="selectedServerId" @change="onServerChange" class="bg-dark-900 text-xs font-bold text-emerald-400 py-1.5 px-3 rounded-lg border border-emerald-500/20 focus:outline-none cursor-pointer">
                        <option v-for="srv in servers" :key="srv.server_id" :value="srv.server_id">
                            {{ getLocaleFlag(srv.locale) }} {{ srv.display_name }} ({{ srv.account ? srv.account.username : t('market.no_account') }})
                        </option>
                    </select>
                </div>

                <!-- Tab Navigation -->
                <div class="flex items-center gap-1 bg-white/5 border border-white/10 p-1 rounded-xl">
                    <button @click="activeTab = 'analytics'"
                            class="px-4 py-2 rounded-lg text-xs font-semibold uppercase tracking-wider transition-all"
                            :class="activeTab === 'analytics' ? 'bg-emerald-500 text-white shadow-lg shadow-emerald-500/20' : 'text-white/50 hover:text-white'">
                        {{ t('market.tab_analytics') }}
                    </button>
                    <button @click="activeTab = 'settings'"
                            class="px-4 py-2 rounded-lg text-xs font-semibold uppercase tracking-wider transition-all"
                            :class="activeTab === 'settings' ? 'bg-emerald-500 text-white shadow-lg shadow-emerald-500/20' : 'text-white/50 hover:text-white'">
                        {{ t('market.tab_settings') }}
                    </button>
                </div>
            </div>
        </div>

        <!-- TAB 1: ANALYTICS -->
        <div v-if="activeTab === 'analytics'" class="space-y-6">
            <!-- Active Server Status Notice / Empty State -->
            <div v-if="servers.length === 0" class="glass-card p-8 text-center space-y-4">
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
                                <span v-if="!currentServerConnection.account_id" class="text-amber-400">No account assigned</span>
                                <span v-else-if="currentServerConnection.last_error" class="text-red-400">{{ currentServerConnection.last_error }}</span>
                                <span v-else class="text-white/60">Not synchronized yet</span>
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <button v-if="currentServerConnection.account_id" @click="syncServerNow(currentServerConnection)" :disabled="syncing" class="btn-secondary py-1.5 px-3 text-xs flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5" :class="{ 'animate-spin': syncing }" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182" />
                            </svg>
                            Sync Now
                        </button>
                        <button @click="activeTab = 'settings'" class="btn-secondary py-1.5 px-3 text-xs">
                            Manage Server
                        </button>
                    </div>
                </div>
            </div>

            <!-- Selection Card (Dropdowns or Visual Grid) -->
            <div class="glass-card p-6">
                <!-- Selector Header: Mode Switch & Mirror Button -->
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6 border-b border-white/5 pb-4">
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-semibold text-white/40 uppercase tracking-wider">{{ t('market.selection_mode') }}</span>
                        <div class="flex items-center gap-1 bg-white/5 border border-white/10 p-0.5 rounded-lg">
                            <button @click="selectionMode = 'dropdown'"
                                    class="px-3 py-1 rounded text-[10px] font-bold uppercase transition-all"
                                    :class="selectionMode === 'dropdown' ? 'bg-emerald-500 text-white' : 'text-white/50 hover:text-white'">
                                Dropdowns
                            </button>
                            <button @click="selectionMode = 'visual'"
                                    class="px-3 py-1 rounded text-[10px] font-bold uppercase transition-all"
                                    :class="selectionMode === 'visual' ? 'bg-emerald-500 text-white' : 'text-white/50 hover:text-white'">
                                Visual Browser
                            </button>
                        </div>
                    </div>

                    <!-- Reset Selection / Mirror Button -->
                    <div class="flex items-center gap-2">
                        <button v-if="selectedItem || selectedTarget" @click="resetSelection" class="btn-secondary py-1 px-3 text-xs bg-white/5 border border-white/10 text-white/50 hover:text-white hover:bg-white/10 rounded-lg transition-all">
                            Reset Selection
                        </button>
                        <button v-if="selectedItem && selectedTarget" @click="mirrorSelection" class="btn-secondary py-1 px-3 text-xs bg-white/5 border border-white/10 text-white/70 hover:text-white hover:bg-white/10 rounded-lg transition-all flex items-center gap-1">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21 3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" />
                            </svg>
                            Mirror Trade
                        </button>
                    </div>
                </div>

                <!-- Mode 1: Dropdown Selection -->
                <div v-if="selectionMode === 'dropdown'" class="grid grid-cols-1 md:grid-cols-2 gap-6 items-end">
                    <!-- Selling Item Selection -->
                    <div>
                        <label class="block text-xs font-medium text-white/40 mb-2 uppercase tracking-wider">{{ t('market.selling_item') }}</label>
                        <div class="relative">
                            <select v-model="selectedItem" @change="onItemChange" class="glass-select w-full">
                                <option value="" class="bg-dark-900">{{ t('market.select_selling') }}</option>
                                <option v-for="good in goods" :key="good.item_id" :value="good.item_id" class="bg-dark-900">
                                    {{ good.item_name }} ({{ good.item_id }})
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
                            <select v-model="selectedTarget" :disabled="!selectedItem" @change="fetchAnalytics" class="glass-select w-full disabled:opacity-40">
                                <option value="" class="bg-dark-900">{{ t('market.select_target') }}</option>
                                <option v-for="target in targets" :key="target.target_item_id" :value="target.target_item_id" class="bg-dark-900">
                                    {{ target.target_item_name }} ({{ target.target_item_id }})
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
                            1. Sell Resource
                            <span v-if="selectedItem" class="ml-1 text-[10px] text-emerald-500 font-mono font-medium">({{ selectedItemName }})</span>
                        </button>
                        <button @click="visualTab = 2"
                                :disabled="!selectedItem"
                                class="pb-3 text-xs font-bold uppercase tracking-wider transition-all border-b-2 disabled:opacity-30 disabled:cursor-not-allowed"
                                :class="visualTab === 2 ? 'border-emerald-500 text-emerald-400' : 'border-transparent text-white/40 hover:text-white'">
                            2. Buy Resource
                            <span v-if="selectedTarget" class="ml-1 text-[10px] text-emerald-500 font-mono font-medium">({{ selectedTargetName }})</span>
                        </button>
                    </div>

                    <!-- Step 1: Selling resource grid -->
                    <div v-if="visualTab === 1" class="grid grid-cols-3 sm:grid-cols-5 md:grid-cols-8 lg:grid-cols-10 gap-2 max-h-60 overflow-y-auto p-1.5">
                        <div v-for="good in allGoods" :key="good.item_id"
                             @click="selectVisualItem(good.item_id)"
                             class="flex flex-col items-center justify-center p-1.5 rounded-lg border cursor-pointer hover:border-emerald-500/40 hover:bg-white/[0.05] hover:shadow-md hover:shadow-emerald-500/5 text-center select-none transition-all duration-200"
                             :class="selectedItem === good.item_id ? 'bg-emerald-500/10 border-emerald-500 shadow shadow-emerald-500/10' : 'bg-white/[0.02] border-white/5 hover:border-white/20 hover:bg-white/[0.04]'"
                             :style="good.no_offers ? 'opacity:0.4' : ''"
                             :title="good.no_offers ? t('market.no_offers') : good.item_name">
                            <img :src="getResourceIcon(good.item_id)" @error="handleIconError($event, good.item_id)" class="w-6 h-6 object-contain mb-1 pointer-events-none" />
                            <span class="text-[9px] font-medium text-white/90 truncate w-full" :title="good.item_name">{{ good.item_name }}</span>
                        </div>
                        <div v-if="allGoods.length === 0" class="col-span-full py-8 text-center text-xs text-white/30">
                            {{ t('market.no_resources_for_server', { server: selectedServerId }) }}
                        </div>
                    </div>

                    <!-- Step 2: Buying target resource grid -->
                    <div v-if="visualTab === 2" class="grid grid-cols-3 sm:grid-cols-5 md:grid-cols-8 lg:grid-cols-10 gap-2 max-h-60 overflow-y-auto p-1.5">
                        <div v-for="target in targets" :key="target.target_item_id"
                             @click="selectVisualTarget(target.target_item_id)"
                             class="flex flex-col items-center justify-center p-1.5 rounded-lg border cursor-pointer hover:border-emerald-500/40 hover:bg-white/[0.05] hover:shadow-md hover:shadow-emerald-500/5 text-center select-none transition-all duration-200"
                             :class="selectedTarget === target.target_item_id ? 'bg-emerald-500/10 border-emerald-500 shadow shadow-emerald-500/10' : 'bg-white/[0.02] border-white/5 hover:border-white/20 hover:bg-white/[0.04]'">
                            <img :src="getResourceIcon(target.target_item_id)" @error="handleIconError($event, target.target_item_id)" class="w-6 h-6 object-contain mb-1 pointer-events-none" />
                            <span class="text-[9px] font-medium text-white/90 truncate w-full" :title="target.target_item_name">{{ target.target_item_name }}</span>
                        </div>
                        <div v-if="targets.length === 0" class="col-span-full py-8 text-center text-xs text-white/30">
                            Please select a selling item first.
                        </div>
                    </div>
                </div>

                <!-- Selection Path Indicator -->
                <div v-if="selectedItemName && selectedTargetName" class="mt-5 pt-5 border-t border-white/5 flex items-center gap-3 text-lg font-semibold text-emerald-400">
                    <img :src="getResourceIcon(selectedItem)" @error="handleIconError($event, selectedItem)" class="w-6 h-6 object-contain" />
                    <span>{{ selectedItemName }}</span>
                    <svg class="w-5 h-5 text-white/30" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                    </svg>
                    <img :src="getResourceIcon(selectedTarget)" @error="handleIconError($event, selectedTarget)" class="w-6 h-6 object-contain" />
                    <span>{{ selectedTargetName }}</span>
                </div>
            </div>

            <!-- Analysis Dashboard (Visible if both selected) -->
            <div v-if="selectedItem && selectedTarget && stats" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
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

                    <!-- Price Dynamic Chart Card -->
                    <div class="glass-card p-6 relative transition-all duration-300" :class="hoveredPoint ? 'z-40' : 'z-10'">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-4">
                            <h3 class="text-sm font-semibold text-white">Price History (1 {{ selectedItemName }} = X {{ selectedTargetName }})</h3>
                            
                            <div class="flex items-center gap-4">
                                <div class="flex items-center bg-white/5 border border-white/10 p-0.5 rounded-lg text-[10px] font-semibold">
                                    <button v-for="p in periods" :key="p.value" @click="changePeriod(p.value)"
                                            class="px-2.5 py-1 rounded transition-all uppercase tracking-wider"
                                            :class="selectedPeriod === p.value ? 'bg-emerald-500 text-white shadow' : 'text-white/40 hover:text-white'">
                                        {{ p.label }}
                                    </button>
                                </div>

                                <div class="flex items-center gap-4 text-[10px] text-white/40">
                                    <div class="flex items-center gap-1.5">
                                        <span class="w-2.5 h-0.5 bg-emerald-500 inline-block"></span>
                                        Avg Price
                                    </div>
                                    <div class="flex items-center gap-1.5">
                                        <span class="w-2.5 h-0.5 bg-white/20 border-dashed border inline-block"></span>
                                        Mean
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- SVG Price Chart -->
                        <div class="h-64 w-full relative z-40 pt-2">
                            <template v-if="history.length > 0">
                                <svg class="w-full h-full" viewBox="0 0 600 220" preserveAspectRatio="none">
                                    <defs>
                                        <linearGradient id="priceGrad" x1="0" y1="0" x2="0" y2="1">
                                            <stop offset="0%" stop-color="#10b981" stop-opacity="0.2"/>
                                            <stop offset="100%" stop-color="#10b981" stop-opacity="0.0"/>
                                        </linearGradient>
                                    </defs>
                                    
                                    <line v-for="grid in 4" :key="'grid-y-'+grid"
                                          x1="40" :y1="20 + (grid - 1) * 50" x2="590" :y2="20 + (grid - 1) * 50"
                                          stroke="rgba(255,255,255,0.03)" stroke-width="1"/>

                                    <path :d="chartPriceAreaPath" fill="url(#priceGrad)"/>
                                    <path :d="chartPriceLinePath" fill="none" stroke="#10b981" stroke-width="2"/>
                                    <line x1="40" :y1="chartMeanY" x2="590" :y2="chartMeanY"
                                          stroke="rgba(255,255,255,0.2)" stroke-dasharray="4,4" stroke-width="1.5"/>

                                    <g v-for="(p, idx) in chartPoints" :key="'dot-group-'+idx"
                                       class="cursor-pointer"
                                       @mouseenter="hoveredPoint = { ...p, index: idx }"
                                       @mouseleave="hoveredPoint = null">
                                        <circle :cx="p.x" :cy="p.y" r="14" fill="transparent" />
                                        <circle :cx="p.x" :cy="p.y" :r="hoveredPoint?.index === idx ? 5.5 : 3.5"
                                                :fill="hoveredPoint?.index === idx ? '#34d399' : '#10b981'"
                                                stroke="#0b171c" stroke-width="1.5"
                                                class="transition-all duration-200" />
                                    </g>
                                </svg>

                                <div v-if="hoveredPoint"
                                     class="absolute z-50 pointer-events-none transition-all duration-150 ease-out transform"
                                     :class="tooltipPositionClass"
                                     :style="{ left: (hoveredPoint.x / 600 * 100) + '%', top: (hoveredPoint.y / 220 * 100) + '%' }">
                                    <div class="glass-card p-3 shadow-2xl border border-white/20 bg-dark-900/95 backdrop-blur-md rounded-xl text-xs space-y-2 min-w-[210px] animate-fade-in">
                                        <div class="flex items-center justify-between border-b border-white/10 pb-1.5 text-[10px] text-white/50 font-mono">
                                            <span>{{ hoveredPoint.collected_at }}</span>
                                            <span class="text-emerald-400 font-bold">Price: {{ hoveredPoint.price }}</span>
                                        </div>

                                        <div class="flex items-center justify-between gap-2 py-1.5 bg-white/5 rounded-lg px-2 border border-white/5">
                                            <div class="flex items-center gap-1.5">
                                                <img :src="getResourceIcon(selectedItem)" @error="handleIconError($event, selectedItem)" class="w-4 h-4 object-contain" />
                                                <span class="font-mono font-bold text-white text-xs">{{ formatVolume(hoveredPoint.avg_amount) }}</span>
                                                <span class="text-[10px] text-white/60 truncate max-w-[60px]" :title="selectedItemName">{{ selectedItemName }}</span>
                                            </div>

                                            <svg class="w-3.5 h-3.5 text-emerald-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                                            </svg>

                                            <div class="flex items-center gap-1.5">
                                                <img :src="getResourceIcon(selectedTarget)" @error="handleIconError($event, selectedTarget)" class="w-4 h-4 object-contain" />
                                                <span class="font-mono font-bold text-emerald-400 text-xs">{{ formatVolume(hoveredPoint.avg_target_amount) }}</span>
                                                <span class="text-[10px] text-emerald-400/80 truncate max-w-[60px]" :title="selectedTargetName">{{ selectedTargetName }}</span>
                                            </div>
                                        </div>

                                        <div class="flex items-center justify-between text-[10px] text-white/40 font-mono pt-0.5">
                                            <span>Offers: <strong class="text-white/80">{{ hoveredPoint.offers_count }}</strong></span>
                                            <span>Sellers: <strong class="text-white/80">{{ hoveredPoint.sellers_count }}</strong></span>
                                            <span>Vol: <strong class="text-white/80">{{ formatVolume(hoveredPoint.volume) }}</strong></span>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex justify-between text-[8px] text-white/30 px-9 mt-1 font-mono">
                                    <span>{{ history[0]?.collected_at }}</span>
                                    <span>{{ history[Math.floor(history.length / 2)]?.collected_at }}</span>
                                    <span>{{ history[history.length - 1]?.collected_at }}</span>
                                </div>
                            </template>
                            <div v-else class="absolute inset-0 flex items-center justify-center text-xs text-white/20">
                                Not enough historical data to display the chart
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Calculator Side Panel (Right columns) -->
                <div class="space-y-6">
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
                                <label class="block text-xs font-medium text-white/40 mb-2 uppercase tracking-wider">Amount of {{ selectedItemName }}</label>
                                <input type="number" v-model.number="calcAmount" min="1" class="glass-input w-full font-mono text-white text-lg"/>
                            </div>

                            <div class="p-4 rounded-xl border border-emerald-500/10 bg-emerald-500/[0.02]">
                                <span class="text-[10px] font-semibold text-emerald-400/70 uppercase tracking-wider block">{{ t('market.estimated_revenue') }}</span>
                                <div class="flex items-baseline gap-2 mt-1">
                                    <span class="text-2xl font-bold text-emerald-400 font-mono">{{ calculatedCost }}</span>
                                    <span class="text-xs text-white/40">{{ selectedTargetName }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Popular Items & Current Active Market (Visible when nothing is selected) -->
            <div v-else class="space-y-6">
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
                        <button @click="togglePopularItems" class="text-white/40 hover:text-white transition-colors">
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
                                            <img :src="getResourceIcon(item.item_id)" @error="handleIconError($event, item.item_id)" class="w-5 h-5 object-contain" />
                                            <span>{{ item.item_name }}</span>
                                        </div>
                                    </td>
                                    <td class="py-3 px-4 font-mono text-xs text-white/35">{{ item.item_id }}</td>
                                    <td class="py-3 px-4 text-right text-emerald-400 font-mono font-medium">{{ item.offers_count }} offers</td>
                                    <td class="py-3 px-4 text-right text-blue-400 font-mono">{{ item.sellers_count }} sellers</td>
                                    <td class="py-3 px-4 text-right font-mono">{{ formatVolume(item.total_volume) }} units</td>
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
                                {{ arbitrageLoops.length }} schemes found
                            </span>
                            <button @click="toggleArbitrageSchemes" class="text-white/40 hover:text-white transition-colors">
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
                                    {{ scheme.type }} loop
                                </span>

                                <div class="flex items-center gap-3">
                                    <!-- Leftovers -->
                                    <div v-if="scheme.leftovers && scheme.leftovers.length" class="flex items-center gap-2 text-xs text-blue-400">
                                        <span class="text-white/30">{{ t('market.leftovers') }}</span>
                                        <span v-for="leftover in scheme.leftovers" :key="leftover.item_id" class="flex items-center gap-1 text-white/70">
                                            <img :src="getResourceIcon(leftover.item_id)" @error="handleIconError($event, leftover.item_id)" class="w-3.5 h-3.5 object-contain" />
                                            +{{ formatVolume(leftover.amount) }}
                                        </span>
                                    </div>
                                    <!-- Net Profit -->
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs text-white/40">{{ t('market.net_profit') }}</span>
                                        <div class="flex items-center gap-1.5 bg-emerald-500/10 border border-emerald-500/20 rounded-lg py-1 px-2">
                                            <img :src="getResourceIcon(scheme.profit.item_id)" @error="handleIconError($event, scheme.profit.item_id)" class="w-4 h-4 object-contain" />
                                            <span class="font-mono text-sm font-bold text-emerald-400">+{{ formatVolume(scheme.profit.amount) }}</span>
                                            <span class="text-xs text-emerald-400/70 truncate max-w-[80px]">{{ scheme.profit.item_name }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Steps flowchart -->
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-center">
                                <div v-for="(step, sIdx) in scheme.steps" :key="sIdx" class="flex items-center gap-3">
                                    <div class="flex-1 p-3 rounded-lg bg-white/[0.02] border border-white/5 relative">
                                        <div class="text-[10px] uppercase font-bold text-white/30 mb-2">Step {{ sIdx + 1 }}</div>

                                        <div class="flex flex-col gap-1.5">
                                            <div class="flex items-center gap-1.5 text-xs">
                                                <span class="text-white/40 w-8">{{ t('market.give') }}</span>
                                                <img :src="getResourceIcon(step.give_item)" @error="handleIconError($event, step.give_item)" class="w-4.5 h-4.5 object-contain" />
                                                <span class="font-mono font-semibold text-white/90">{{ formatVolume(step.give_per_lot) }}</span>
                                                <span class="text-[10px] text-white/30">(total: {{ formatVolume(step.give_amount) }})</span>
                                            </div>
                                            <div class="flex items-center gap-1.5 text-xs">
                                                <span class="text-white/40 w-8">{{ t('market.get') }}</span>
                                                <img :src="getResourceIcon(step.receive_item)" @error="handleIconError($event, step.receive_item)" class="w-4.5 h-4.5 object-contain" />
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
                                <span class="text-xs text-white/40">{{ totalActiveCount }} active trades</span>
                            </div>
                        </div>
                        <button @click="toggleActiveListings" class="text-white/40 hover:text-white transition-colors duration-300">
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
                                                <img :src="getResourceIcon(offer.item_id)" @error="handleIconError($event, offer.item_id)" class="w-5 h-5 object-contain" />
                                                <span class="font-mono text-white/90">{{ formatVolume(offer.amount) }}</span>
                                                <span class="text-xs text-white/40 truncate max-w-[100px]">{{ offer.item_name }}</span>
                                            </div>
                                        </td>
                                        <td class="py-3 px-4">
                                            <div class="flex items-center gap-2">
                                                <img :src="getResourceIcon(offer.target_item_id)" @error="handleIconError($event, offer.target_item_id)" class="w-5 h-5 object-contain" />
                                                <span class="font-mono text-white/90">{{ formatVolume(offer.target_amount) }}</span>
                                                <span class="text-xs text-white/40 truncate max-w-[100px]">{{ offer.target_item_name }}</span>
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
                                            No active listings found in database. Perform synchronization first.
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
                                    {{ loadingMore ? 'Loading more...' : 'Load More Listings' }}
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
                                <td class="py-3 px-4 text-right space-x-2">
                                    <button @click="verifyServer(srv)" :disabled="verifyingId === srv.id" class="btn-secondary py-1 px-2.5 text-[11px]" :title="t('market.verify_hint')">
                                        {{ verifyingId === srv.id ? '...' : t('market.verify') }}
                                    </button>
                                    <button @click="syncServerNow(srv)" :disabled="syncing || !srv.account_id" class="btn-secondary py-1 px-2.5 text-[11px] text-emerald-400 border-emerald-500/20 hover:bg-emerald-500/10">
                                        {{ t('market.sync_now') }}
                                    </button>
                                    <button @click="openEditServerModal(srv)" class="btn-secondary py-1 px-2.5 text-[11px]">
                                        {{ t('market.edit') }}
                                    </button>
                                    <button @click="deleteServer(srv)" class="btn-secondary py-1 px-2.5 text-[11px] text-red-400 hover:bg-red-500/10 border-red-500/20">
                                        {{ t('market.delete') }}
                                    </button>
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
                            <tr v-if="logs.length === 0">
                                <td colspan="5" class="py-8 text-center text-white/20">
                                    No synchronization logs yet.
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
import { t } from '../lang';
import axios from 'axios';
import { showToast } from '../toast';
import { getGameImageUrl, handleGameImageError } from '../services/gameImageService';
import { resourceName } from '../lang/gameNames';
import { TRADABLE_RESOURCES } from '../lang/resourcesCatalog';

export default {
    name: 'MarketAnalytics',
    setup() {
        const activeTab = ref('analytics');
        const loading = ref(false);
        const saving = ref(false);
        const syncing = ref(false);
        const showPopularItems = ref(true);
        const showArbitrageSchemes = ref(true);
        const showActiveListings = ref(true);

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
            if (!seconds || seconds <= 0) return 'Expired';
            const h = Math.floor(seconds / 3600);
            const m = Math.floor((seconds % 3600) / 60);
            const s = seconds % 60;
            if (h > 0) return `${h}h ${m}m`;
            if (m > 0) return `${m}m ${s}s`;
            return `${s}s`;
        };

        const loadMoreActiveOffers = async () => {
            if (loadingMore.value || !hasMoreActiveOffers.value) return;
            loadingMore.value = true;
            try {
                const nextPage = activeOffersPage.value + 1;
                const res = await axios.get('/api/market/analytics', {
                    params: { server_id: selectedServerId.value, page: nextPage }
                });
                const newOffers = res.data.active_offers || [];
                activeOffers.value.push(...newOffers);
                activeOffersPage.value = nextPage;
                hasMoreActiveOffers.value = res.data.has_more || false;
            } catch (e) {
                showToast('Failed to load active listings', 'error');
            } finally {
                loadingMore.value = false;
            }
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

        // Полный каталог торгуемых ресурсов из игрового XML, объединённый с товарами с сервера:
        // ресурсы без активных предложений тоже отображаются (приглушёнными), чтобы был виден весь рынок.
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
        const hoveredPoint = ref(null);

        const logs = ref([]);
        const logsPagination = ref({ current_page: 1, last_page: 1, total: 0 });

        const selectionMode = ref('visual');
        const visualTab = ref(1);
        const selectedPeriod = ref('all');
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
            return item ? item.item_name : '';
        });

        const selectedTargetName = computed(() => {
            const item = targets.value.find(t => t.target_item_id === selectedTarget.value);
            return item ? item.target_item_name : '';
        });

        const calculatedCost = computed(() => {
            if (!stats.value || !stats.value.average) return 0;
            const amt = parseFloat(calcAmount.value) || 0;
            return Math.round(amt * stats.value.average * 100) / 100;
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

        const chartPoints = computed(() => {
            if (history.value.length === 0) return [];
            const w = 550;
            const h = 200;
            const maxPrice = Math.max(...history.value.map(h => h.price)) || 1;
            const minPrice = Math.min(...history.value.map(h => h.price)) || 0;
            const priceDiff = (maxPrice - minPrice) || 1;

            return history.value.map((d, idx) => {
                const stepX = history.value.length > 1 ? w / (history.value.length - 1) : w;
                const x = 40 + idx * stepX;
                const py = maxPrice === minPrice ? 120 : 220 - ((d.price - minPrice) / priceDiff) * 180 - 10;
                return {
                    x, y: py,
                    price: d.price,
                    volume: d.volume,
                    sellers_count: d.sellers_count,
                    offers_count: d.offers_count,
                    avg_amount: d.avg_amount || 1,
                    avg_target_amount: d.avg_target_amount || 1,
                    collected_at: d.collected_at
                };
            });
        });

        const chartPriceLinePath = computed(() => {
            const pts = chartPoints.value;
            if (pts.length === 0) return '';
            return pts.reduce((path, p, idx) => (idx === 0 ? `M ${p.x} ${p.y}` : `${path} L ${p.x} ${p.y}`), '');
        });

        const chartPriceAreaPath = computed(() => {
            const pts = chartPoints.value;
            if (pts.length === 0) return '';
            return `${chartPriceLinePath.value} L ${pts[pts.length - 1].x} 220 L ${pts[0].x} 220 Z`;
        });

        const chartMeanY = computed(() => {
            if (!stats.value || !stats.value.average) return 120;
            const maxPrice = Math.max(...history.value.map(h => h.price)) || 1;
            const minPrice = Math.min(...history.value.map(h => h.price)) || 0;
            const priceDiff = (maxPrice - minPrice) || 1;
            return maxPrice === minPrice ? 120 : 220 - ((stats.value.average - minPrice) / priceDiff) * 180 - 10;
        });

        const tooltipPositionClass = computed(() => {
            if (!hoveredPoint.value) return '';
            const xRatio = hoveredPoint.value.x / 600;
            const yRatio = hoveredPoint.value.y / 220;
            let translateX = '-translate-x-1/2';
            if (xRatio > 0.75) translateX = '-translate-x-[90%]';
            else if (xRatio < 0.25) translateX = '-translate-x-[10%]';
            let translateY = '-translate-y-full mb-3';
            if (yRatio < 0.3) translateY = 'translate-y-2 mt-2';
            return `${translateX} ${translateY}`;
        });

        // Server API methods
        const loadServers = async () => {
            try {
                const res = await axios.get('/api/market/servers');
                servers.value = res.data.servers || [];
                accounts.value = res.data.accounts || [];
                presets.value = res.data.presets || [];
                settingsForm.value = res.data.settings || { sync_interval: '15', custom_interval_minutes: 15 };

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
            }
        };

        const onServerChange = () => {
            localStorage.setItem('tso_market_selected_server', selectedServerId.value);
            resetSelection();
            loadAnalyticsData();
        };

        const loadAnalyticsData = async () => {
            if (!selectedServerId.value) return;
            loading.value = true;
            try {
                const params = { server_id: selectedServerId.value };
                const goodsRes = await axios.get('/api/market/goods', { params });
                goods.value = goodsRes.data || [];

                const analyticsRes = await axios.get('/api/market/analytics', { params });
                popular.value = analyticsRes.data.popular || [];
                activeOffers.value = analyticsRes.data.active_offers || [];
                totalActiveCount.value = analyticsRes.data.total_active_count || 0;
                activeOffersPage.value = 1;
                hasMoreActiveOffers.value = analyticsRes.data.has_more || false;

                const arbitrageRes = await axios.get('/api/market/arbitrage', { params });
                arbitrageLoops.value = arbitrageRes.data || [];

                startCountdown();
                await loadSyncLogs(1);
            } catch (e) {
                console.error('Failed to load analytics data:', e);
            } finally {
                loading.value = false;
            }
        };

        const loadSyncLogs = async (page = 1) => {
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
                loadAnalyticsData();
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
                loadAnalyticsData();
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
            syncing.value = true;
            try {
                const targetServer = srv || currentServerConnection.value;
                if (!targetServer) return;
                const res = await axios.post(`/api/market/servers/${targetServer.id}/sync`);
                if (res.data.success) {
                    showToast(t('market.sync_complete', { message: res.data.message }));
                    await loadServers();
                    await loadAnalyticsData();
                    if (selectedItem.value && selectedTarget.value) {
                        await fetchAnalytics();
                    }
                }
            } catch (e) {
                const msg = e.response?.data?.message || t('market.sync_failed_toast');
                showToast(msg, 'error');
            } finally {
                syncing.value = false;
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

            if (!selectedItem.value || !selectedServerId.value) return;

            try {
                const res = await axios.get('/api/market/targets', {
                    params: { server_id: selectedServerId.value, item_id: selectedItem.value }
                });
                targets.value = res.data || [];
            } catch (e) {
                showToast(t('market.targets_failed'), 'error');
            }
        };

        const fetchAnalytics = async () => {
            if (!selectedItem.value || !selectedTarget.value || !selectedServerId.value) return;

            try {
                const res = await axios.get('/api/market/analytics', {
                    params: {
                        server_id: selectedServerId.value,
                        item_id: selectedItem.value,
                        target_item_id: selectedTarget.value,
                        period: selectedPeriod.value
                    }
                });
                stats.value = res.data.stats || null;
                history.value = res.data.history || [];
            } catch (e) {
                showToast(t('market.charts_failed'), 'error');
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
        };

        const mirrorSelection = async () => {
            if (!selectedItem.value || !selectedTarget.value) return;
            const tempItem = selectedItem.value;
            const tempTarget = selectedTarget.value;
            selectedItem.value = tempTarget;
            try {
                const res = await axios.get('/api/market/targets', {
                    params: { server_id: selectedServerId.value, item_id: selectedItem.value }
                });
                targets.value = res.data || [];
                const hasOldItem = targets.value.some(t => t.target_item_id === tempItem);
                if (hasOldItem) {
                    selectedTarget.value = tempItem;
                    await fetchAnalytics();
                } else {
                    selectedTarget.value = '';
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
            await loadServers();
            await loadAnalyticsData();
        });

        onUnmounted(() => {
            if (countdownInterval) clearInterval(countdownInterval);
        });

        return {
            activeTab,
            loading,
            saving,
            syncing,
            servers,
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
            formatVolume,
            getResourceIcon,
            handleIconError,
            getStatusBadgeClass,
            formatDateTime,
            chartPoints,
            chartPriceLinePath,
            chartPriceAreaPath,
            chartMeanY,
            hoveredPoint,
            tooltipPositionClass,
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
            changePeriod,
        };
    }
};
</script>
