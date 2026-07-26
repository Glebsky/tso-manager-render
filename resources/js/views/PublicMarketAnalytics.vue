<template>
    <div class="max-w-7xl mx-auto space-y-8 pb-12 transition-all duration-500 ease-out">
        <!-- Page Header -->
        <div class="glass-card p-6 border-white/10 shadow-2xl relative z-30 transition-all duration-500 hover:border-white/20">
            <div class="absolute inset-0 overflow-hidden rounded-[inherit] pointer-events-none">
                <div class="absolute -right-10 -bottom-10 w-64 h-64 bg-emerald-500/10 rounded-full blur-3xl transition-all duration-700"></div>
            </div>

            <!-- Language Switcher in Upper Right Corner -->
            <div class="absolute top-5 right-5 z-50">
                <LanguageSwitcher />
            </div>

            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 relative z-10 pr-16 md:pr-24">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-600 flex items-center justify-center shadow-lg shadow-emerald-500/25 transition-all duration-300 hover:scale-105 flex-shrink-0">
                        <svg class="w-7 h-7 text-white" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18 9 11.25l4.306 4.307a11.95 11.95 0 0 1 5.814-5.518l2.74-1.22m0 0-3.75-1.002m3.75 1.002-1.002 3.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                    </div>
                    <div>
                        <div class="flex items-center gap-2 flex-wrap">
                            <h1 class="text-2xl sm:text-3xl font-bold text-white tracking-tight">TSO Market Analytics</h1>
                            <span class="badge bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 text-[10px] uppercase font-bold px-2.5 py-0.5 rounded-full">{{ t('market.public_portal') }}</span>
                        </div>
                        <p class="text-white/50 text-xs sm:text-sm mt-1">{{ t('market.public_subtitle') }}</p>
                    </div>
                </div>

                <div class="flex justify-end items-center gap-3 flex-wrap">
                    <!-- Server selector: country code + game world name -->
                    <div v-if="servers.length" class="relative">
                        <select v-model="selectedServerId" @change="onServerChange" :aria-label="t('market.server')"
                                class="appearance-none bg-white/5 border border-white/10 rounded-xl pl-3.5 pr-9 py-2 text-xs text-white/80 cursor-pointer transition-all duration-300 hover:bg-white/10 hover:border-white/20 focus:outline-none focus:border-emerald-500/50">
                            <option v-for="srv in servers" :key="srv.server_id" :value="srv.server_id" class="bg-dark-900">
                                {{ serverOptionLabel(srv) }}
                            </option>
                        </select>
                        <svg class="w-3.5 h-3.5 text-white/40 absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                        </svg>
                    </div>

                    <div class="flex items-center gap-2 bg-white/5 border border-white/10 px-3.5 py-2 rounded-xl text-xs text-white/70 transition-all duration-300 hover:bg-white/10">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        <span>{{ t('market.live_title') }}</span>
                    </div>

                    <!-- Admin Panel Link for Authenticated Users -->
                    <router-link v-if="isAuthenticated" to="/admin/market" class="btn-primary py-2 px-3.5 text-xs font-semibold rounded-xl inline-flex items-center gap-1.5 shadow-lg shadow-emerald-500/20 transition-all duration-300 hover:scale-105">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 18H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 12h10.5" />
                        </svg>
                        <span>{{ t('dashboard.title') }}</span>
                    </router-link>
                </div>
            </div>
        </div>

        <!-- Selection Card (Dropdowns or Visual Grid) -->
        <div class="glass-card p-6 relative transition-all duration-500 hover:border-white/20">
            <loading-overlay :show="loading || loadingPairs" :label="loadingPairs ? t('market.loading_pairs') : t('common.loading_data')" />
            <!-- Selector Header: Mode Switch & Mirror Button -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6 border-b border-white/5 pb-4">
                <div class="flex items-center gap-2">
                    <span class="text-xs font-semibold text-white/40 uppercase tracking-wider">{{ t('market.selection_mode') }}</span>
                    <div class="flex items-center gap-1 bg-white/5 border border-white/10 p-0.5 rounded-lg">
                        <button @click="selectionMode = 'dropdown'"
                                class="px-3 py-1 rounded text-[10px] font-bold uppercase transition-all duration-300"
                                :class="selectionMode === 'dropdown' ? 'bg-emerald-500 text-white shadow-md shadow-emerald-500/20' : 'text-white/50 hover:text-white'">
                            {{ t('market.dropdowns') }}
                        </button>
                        <button @click="selectionMode = 'visual'"
                                class="px-3 py-1 rounded text-[10px] font-bold uppercase transition-all duration-300"
                                :class="selectionMode === 'visual' ? 'bg-emerald-500 text-white shadow-md shadow-emerald-500/20' : 'text-white/50 hover:text-white'">
                            {{ t('market.visual_browser') }}
                        </button>
                    </div>
                </div>

                <!-- Reset Selection / Mirror / Copy Link Button -->
                <div class="flex items-center gap-2">
                    <button v-if="selectedItem || selectedTarget" @click="resetSelection" class="btn-secondary py-1 px-3 text-xs bg-white/5 border border-white/10 text-white/50 hover:text-white hover:bg-white/10 rounded-lg transition-all duration-300">
                        {{ t('market.reset_selection') }}
                    </button>
                    <button v-if="selectedItem && selectedTarget" @click="mirrorSelection" class="btn-secondary py-1 px-3 text-xs bg-white/5 border border-white/10 text-white/70 hover:text-white hover:bg-white/10 rounded-lg transition-all duration-300 flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21 3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" />
                        </svg>
                        {{ t('market.mirror_trade') }}
                    </button>
                    <button v-if="selectedItem && selectedTarget" @click="copyPairLink" class="btn-secondary py-1 px-3 text-xs bg-white/5 border border-white/10 text-white/70 hover:text-white hover:bg-white/10 rounded-lg transition-all duration-300 flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244" />
                        </svg>
                        {{ t('market.copy_link') }}
                    </button>
                </div>
            </div>

            <!-- Mode 1: Dropdown Selection -->
            <transition name="smooth-fade" mode="out-in">
                <div v-if="selectionMode === 'dropdown'" key="dropdown" class="grid grid-cols-1 md:grid-cols-2 gap-6 items-end">
                    <!-- Selling Item Selection -->
                    <div>
                        <label class="block text-xs font-medium text-white/40 mb-2 uppercase tracking-wider">{{ t('market.selling_item') }}</label>
                        <div class="relative">
                            <select v-model="selectedItem" @change="onItemChange" :aria-label="t('market.selling_item')" class="glass-select w-full transition-all duration-300">
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
                            <select v-model="selectedTarget" :disabled="!selectedItem" @change="fetchAnalytics" :aria-label="t('market.target_item')" class="glass-select w-full disabled:opacity-40 transition-all duration-300">
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
                <div v-else key="visual" class="space-y-6">
                    <!-- Tab Navigation for Visual steps -->
                    <div class="flex items-center border-b border-white/10 gap-4">
                        <button @click="visualTab = 1"
                                class="pb-3 text-xs font-bold uppercase tracking-wider transition-all duration-300 border-b-2"
                                :class="visualTab === 1 ? 'border-emerald-500 text-emerald-400' : 'border-transparent text-white/40 hover:text-white'">
                            {{ t('market.sell_resource') }}
                            <span v-if="selectedItem" class="ml-1 text-[10px] text-emerald-500 font-mono font-medium">({{ selectedItemName }})</span>
                        </button>
                        <button @click="visualTab = 2"
                                :disabled="!selectedItem"
                                class="pb-3 text-xs font-bold uppercase tracking-wider transition-all duration-300 border-b-2 disabled:opacity-30 disabled:cursor-not-allowed"
                                :class="visualTab === 2 ? 'border-emerald-500 text-emerald-400' : 'border-transparent text-white/40 hover:text-white'">
                            {{ t('market.buy_resource') }}
                            <span v-if="selectedTarget" class="ml-1 text-[10px] text-emerald-500 font-mono font-medium">({{ selectedTargetName }})</span>
                        </button>
                    </div>

                    <!-- Step 1: Selling resource grid -->
                    <transition name="smooth-fade" mode="out-in">
                        <div v-if="visualTab === 1" key="step1" class="grid grid-cols-3 sm:grid-cols-6 md:grid-cols-8 lg:grid-cols-12 gap-2 max-h-60 overflow-y-auto p-1.5 scrollbar-thin">
                            <div v-for="good in allGoods" :key="good.item_id"
                                 @click="selectVisualItem(good.item_id)"
                                 class="flex flex-col items-center justify-center p-1.5 rounded-lg border cursor-pointer hover:border-emerald-500/40 hover:bg-white/[0.05] hover:shadow-md hover:shadow-emerald-500/5 text-center select-none transition-all duration-300 ease-out transform hover:-translate-y-0.5"
                                 :class="selectedItem === good.item_id ? 'bg-emerald-500/10 border-emerald-500 shadow-lg shadow-emerald-500/10 scale-[1.02]' : 'bg-white/[0.02] border-white/5 hover:border-emerald-500/30 hover:bg-white/[0.05] hover:shadow-md'"
                                 :style="good.no_offers ? 'opacity:0.4' : ''"
                                 :title="good.no_offers ? t('market.no_offers') : getItemName(good.item_name, good.item_id)">
                                <img :alt="getItemName(good.item_name, good.item_id)" :src="getResourceIcon(good.item_id)" @error="handleIconError($event, good.item_id)" class="w-6 h-6 object-contain mb-1 pointer-events-none transition-transform duration-300 group-hover:scale-110" />
                                <span class="text-[9px] font-medium text-white/90 truncate w-full text-center" :title="getItemName(good.item_name, good.item_id)">{{ getItemName(good.item_name, good.item_id) }}</span>
                            </div>
                            <div v-if="allGoods.length === 0 && !loading" class="col-span-full py-8 text-center text-xs text-white/30">
                                No resources available in the database.
                            </div>
                        </div>

                        <!-- Step 2: Buying target resource grid -->
                        <div v-else-if="visualTab === 2" key="step2" class="grid grid-cols-3 sm:grid-cols-6 md:grid-cols-8 lg:grid-cols-12 gap-2 max-h-60 overflow-y-auto p-1.5 scrollbar-thin">
                            <div v-for="target in targets" :key="target.target_item_id"
                                 @click="selectVisualTarget(target.target_item_id)"
                                 class="flex flex-col items-center justify-center p-1.5 rounded-lg border cursor-pointer hover:border-emerald-500/40 hover:bg-white/[0.05] hover:shadow-md hover:shadow-emerald-500/5 text-center select-none transition-all duration-300 ease-out transform hover:-translate-y-0.5"
                                 :class="selectedTarget === target.target_item_id ? 'bg-emerald-500/10 border-emerald-500 shadow-lg shadow-emerald-500/10 scale-[1.02]' : 'bg-white/[0.02] border-white/5 hover:border-emerald-500/30 hover:bg-white/[0.05] hover:shadow-md'">
                                <img :alt="getItemName(target.target_item_name, target.target_item_id)" :src="getResourceIcon(target.target_item_id)" @error="handleIconError($event, target.target_item_id)" class="w-6 h-6 object-contain mb-1 pointer-events-none transition-transform duration-300 group-hover:scale-110" />
                                <span class="text-[9px] font-medium text-white/90 truncate w-full text-center" :title="getItemName(target.target_item_name, target.target_item_id)">{{ getItemName(target.target_item_name, target.target_item_id) }}</span>
                            </div>
                            <div v-if="targets.length === 0 && !loadingPairs" class="col-span-full py-8 text-center text-xs text-white/30">
                                Please select a selling item first.
                            </div>
                        </div>
                    </transition>
                </div>
            </transition>

            <!-- Selection Path Indicator -->
            <transition name="smooth-fade">
                <div v-if="selectedItemName && selectedTargetName" class="mt-5 pt-5 border-t border-white/5 flex items-center gap-3 text-lg font-semibold text-emerald-400">
                    <img :alt="selectedItemName" :src="getResourceIcon(selectedItem)" @error="handleIconError($event, selectedItem)" class="w-6 h-6 object-contain" />
                    <span>{{ selectedItemName }}</span>
                    <svg class="w-5 h-5 text-white/30" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                    </svg>
                    <img :alt="selectedTargetName" :src="getResourceIcon(selectedTarget)" @error="handleIconError($event, selectedTarget)" class="w-6 h-6 object-contain" />
                    <span>{{ selectedTargetName }}</span>
                </div>
            </transition>
        </div>

        <!-- Analytics loading placeholder (first fetch for a pair) -->
        <div v-if="loadingChart && !stats" class="glass-card p-12 flex flex-col items-center justify-center gap-3 text-emerald-400">
            <spinner size="lg" />
            <p class="text-xs text-white/40">{{ t('market.loading_chart') }}</p>
        </div>

        <!-- Analysis Dashboard (Visible if both selected) -->
        <transition name="smooth-fade">
            <div v-if="selectedItem && selectedTarget && stats" class="relative grid grid-cols-1 lg:grid-cols-3 gap-6">
                <loading-overlay :show="loadingChart" :label="t('market.loading_chart')" />
                <!-- Stats Swarm (Left columns) -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Pricing Stats -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                        <!-- Current Price -->
                        <div class="glass-card p-4 transition-all duration-300 hover:scale-[1.02]">
                            <span class="text-[10px] font-semibold text-white/30 uppercase tracking-wider block">{{ t('market.current_price') }}</span>
                            <span class="text-xl font-bold text-white mt-1 block">{{ stats.current }}</span>
                            <span class="text-[10px] text-white/40 block mt-0.5">{{ selectedTargetName }}</span>
                        </div>
                        <!-- Average Price -->
                        <div class="glass-card p-4 transition-all duration-300 hover:scale-[1.02]">
                            <span class="text-[10px] font-semibold text-white/30 uppercase tracking-wider block">{{ t('market.average_price') }}</span>
                            <span class="text-xl font-bold text-emerald-400 mt-1 block">{{ stats.average }}</span>
                            <span class="text-[10px] text-white/40 block mt-0.5">{{ selectedTargetName }}</span>
                        </div>
                        <!-- Min Price -->
                        <div class="glass-card p-4 transition-all duration-300 hover:scale-[1.02]">
                            <span class="text-[10px] font-semibold text-white/30 uppercase tracking-wider block">{{ t('market.min_price') }}</span>
                            <span class="text-xl font-bold text-blue-400 mt-1 block">{{ stats.minimum }}</span>
                            <span class="text-[10px] text-white/40 block mt-0.5">{{ selectedTargetName }}</span>
                        </div>
                        <!-- Max Price -->
                        <div class="glass-card p-4 transition-all duration-300 hover:scale-[1.02]">
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
                                <!-- Period Selection Buttons -->
                                <div class="flex items-center bg-white/5 border border-white/10 p-0.5 rounded-lg text-[10px] font-semibold">
                                    <button v-for="p in periods" :key="p.value" @click="changePeriod(p.value)"
                                            class="px-2.5 py-1 rounded transition-all duration-300 uppercase tracking-wider"
                                            :class="selectedPeriod === p.value ? 'bg-emerald-500 text-white shadow' : 'text-white/40 hover:text-white'">
                                        {{ p.label }}
                                    </button>
                                </div>

                                <!-- Chart Price Indicator Legend -->
                                <div class="flex items-center gap-4 text-[10px] text-white/40">
                                    <div class="flex items-center gap-1.5">
                                        <span class="w-2.5 h-0.5 bg-emerald-500 inline-block"></span>
                                        <span>{{ t('market.average_price') }}</span>
                                    </div>
                                    <div class="flex items-center gap-1.5">
                                        <span class="w-2.5 h-0.5 bg-white/20 border-dashed border inline-block"></span>
                                        <span>{{ t('market.global_mean') }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- SVG Price Chart -->
                        <div class="h-64 w-full relative z-40 pt-2">
                            <template v-if="history.length > 0">
                                <svg class="w-full h-full" viewBox="0 0 600 220" preserveAspectRatio="none">
                                    <defs>
                                        <linearGradient id="publicPriceGrad" x1="0" y1="0" x2="0" y2="1">
                                            <stop offset="0%" stop-color="#10b981" stop-opacity="0.25"/>
                                            <stop offset="100%" stop-color="#10b981" stop-opacity="0.0"/>
                                        </linearGradient>
                                    </defs>

                                    <line v-for="grid in 4" :key="'grid-y-'+grid"
                                          x1="40" :y1="20 + (grid - 1) * 50" x2="590" :y2="20 + (grid - 1) * 50"
                                          stroke="rgba(255,255,255,0.03)" stroke-width="1"/>

                                    <text x="35" y="23" fill="rgba(255,255,255,0.3)" font-size="8" text-anchor="end" font-family="monospace">{{ chartMaxPriceLabel }}</text>
                                    <text x="35" y="123" fill="rgba(255,255,255,0.2)" font-size="8" text-anchor="end" font-family="monospace">{{ chartMidPriceLabel }}</text>
                                    <text x="35" y="215" fill="rgba(255,255,255,0.3)" font-size="8" text-anchor="end" font-family="monospace">{{ chartMinPriceLabel }}</text>

                                    <path :d="chartPriceAreaPath" fill="url(#publicPriceGrad)" class="transition-all duration-500 ease-out"/>
                                    <path :d="chartPriceLinePath" fill="none" stroke="#10b981" stroke-width="2" class="transition-all duration-500 ease-out"/>
                                    <line x1="40" :y1="chartMeanY" x2="590" :y2="chartMeanY"
                                          stroke="rgba(255,255,255,0.2)" stroke-dasharray="4,4" stroke-width="1.5" class="transition-all duration-500"/>

                                    <!-- Data Dots with Stable Invisible Hit Targets -->
                                    <g v-for="(p, idx) in chartPoints" :key="'dot-group-'+idx"
                                       class="cursor-pointer"
                                       @mouseenter="hoveredPoint = { ...p, index: idx }"
                                       @mouseleave="hoveredPoint = null">
                                        <!-- Invisible 14px Hit Target area -->
                                        <circle :cx="p.x" :cy="p.y" r="14" fill="transparent" />
                                        <!-- Visible Point -->
                                        <circle :cx="p.x" :cy="p.y" :r="hoveredPoint?.index === idx ? 5.5 : 3.5"
                                                :fill="hoveredPoint?.index === idx ? '#34d399' : '#10b981'"
                                                stroke="#0b171c" stroke-width="1.5"
                                                class="transition-all duration-200" />
                                    </g>
                                </svg>

                                <!-- Floating Interactive Glassmorphism Tooltip -->
                                <div v-if="hoveredPoint"
                                     class="absolute z-50 pointer-events-none transition-all duration-150 ease-out transform"
                                     :class="tooltipPositionClass"
                                     :style="{ left: (hoveredPoint.x / 600 * 100) + '%', top: (hoveredPoint.y / 220 * 100) + '%' }">
                                    <div class="glass-card p-3 shadow-2xl border border-white/20 bg-dark-900/95 backdrop-blur-md rounded-xl text-xs space-y-2 min-w-[210px] animate-fade-in">
                                        <!-- Tooltip Header: Date & Rate -->
                                        <div class="flex items-center justify-between border-b border-white/10 pb-1.5 text-[10px] text-white/50 font-mono">
                                            <span>{{ hoveredPoint.collected_at }}</span>
                                            <span class="text-emerald-400 font-bold">Price: {{ hoveredPoint.price }}</span>
                                        </div>

                                        <!-- Exchange Details: Amount Selling -> Amount Buying -->
                                        <div class="flex items-center justify-between gap-2 py-1.5 bg-white/5 rounded-lg px-2 border border-white/5">
                                            <!-- Selling Item -->
                                            <div class="flex items-center gap-1.5">
                                                <img :alt="selectedItemName" :src="getResourceIcon(selectedItem)" @error="handleIconError($event, selectedItem)" class="w-4 h-4 object-contain" />
                                                <span class="font-mono font-bold text-white text-xs">{{ formatVolume(hoveredPoint.avg_amount) }}</span>
                                                <span class="text-[10px] text-white/60 truncate max-w-[60px]" :title="selectedItemName">{{ selectedItemName }}</span>
                                            </div>

                                            <!-- Arrow -->
                                            <svg class="w-3.5 h-3.5 text-emerald-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                                            </svg>

                                            <!-- Buying Item -->
                                            <div class="flex items-center gap-1.5">
                                                <img :alt="selectedTargetName" :src="getResourceIcon(selectedTarget)" @error="handleIconError($event, selectedTarget)" class="w-4 h-4 object-contain" />
                                                <span class="font-mono font-bold text-emerald-400 text-xs">{{ formatVolume(hoveredPoint.avg_target_amount) }}</span>
                                                <span class="text-[10px] text-emerald-400/80 truncate max-w-[60px]" :title="selectedTargetName">{{ selectedTargetName }}</span>
                                            </div>
                                        </div>

                                        <!-- Point Stats Summary -->
                                        <div class="flex items-center justify-between text-[10px] text-white/40 font-mono pt-0.5">
                                            <span>{{ t('market.offers') }} <strong class="text-white/80">{{ hoveredPoint.offers_count }}</strong></span>
                                            <span>{{ t('market.sellers') }} <strong class="text-white/80">{{ hoveredPoint.sellers_count }}</strong></span>
                                            <span>{{ t('market.vol') }} <strong class="text-white/80">{{ formatVolume(hoveredPoint.volume) }}</strong></span>
                                        </div>
                                    </div>
                                </div>

                                <div v-if="history.length === 1" class="flex justify-center text-[8px] text-white/30 px-9 mt-1 font-mono">
                                    <span>{{ history[0]?.collected_at }}</span>
                                </div>
                                <div v-else class="flex justify-between text-[8px] text-white/30 px-9 mt-1 font-mono">
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

                    <!-- Demand Dynamic Chart Card -->
                    <div class="glass-card p-6 transition-all duration-300">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-sm font-semibold text-white">{{ t('market.volume_offers') }}</h3>
                            <div class="flex items-center gap-4 text-[10px] text-white/40">
                                <div class="flex items-center gap-1.5">
                                    <span class="w-2.5 h-2.5 bg-blue-500/20 border border-blue-500 rounded-sm inline-block"></span>
                                    <span>{{ t('market.sellers_count') }}</span>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <span class="w-2.5 h-2.5 bg-indigo-500/20 border border-indigo-500 rounded-sm inline-block"></span>
                                    <span>{{ t('market.active_offers') }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- SVG Demand Chart -->
                        <div class="h-64 w-full relative pt-2">
                            <template v-if="history.length > 0">
                                <svg class="w-full h-full" viewBox="0 0 600 220" preserveAspectRatio="none">
                                    <line v-for="grid in 4" :key="'grid-dy-'+grid"
                                          x1="40" :y1="20 + (grid - 1) * 50" x2="590" :y2="20 + (grid - 1) * 50"
                                          stroke="rgba(255,255,255,0.03)" stroke-width="1"/>

                                    <text x="35" y="23" fill="rgba(255,255,255,0.3)" font-size="8" text-anchor="end" font-family="monospace">{{ chartMaxOffersLabel }}</text>
                                    <text x="35" y="215" fill="rgba(255,255,255,0.3)" font-size="8" text-anchor="end" font-family="monospace">0</text>

                                    <path :d="chartSellersAreaPath" fill="rgba(59, 130, 246, 0.1)" class="transition-all duration-500 ease-out"/>
                                    <path :d="chartSellersLinePath" fill="none" stroke="#3b82f6" stroke-width="1.5" class="transition-all duration-500 ease-out"/>

                                    <path :d="chartOffersAreaPath" fill="rgba(99, 102, 241, 0.1)" class="transition-all duration-500 ease-out"/>
                                    <path :d="chartOffersLinePath" fill="none" stroke="#6366f1" stroke-width="1.5" class="transition-all duration-500 ease-out"/>

                                    <rect v-for="(b, idx) in chartPoints" :key="'vol-bar-'+idx"
                                          :x="b.x - (history.length === 1 ? 12 : 3)" :y="b.vy" :width="history.length === 1 ? 24 : 6" :height="Math.max(2, 220 - b.vy)"
                                          fill="rgba(255,255,255,0.05)" stroke="rgba(255,255,255,0.1)" stroke-width="0.5" rx="1"
                                          class="transition-all duration-300"/>
                                </svg>
                                <div v-if="history.length === 1" class="flex justify-center text-[8px] text-white/30 px-9 mt-1 font-mono">
                                    <span>{{ history[0]?.collected_at }}</span>
                                </div>
                                <div v-else class="flex justify-between text-[8px] text-white/30 px-9 mt-1 font-mono">
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
                    <!-- Calculator Card -->
                    <div class="glass-card p-6 transition-all duration-300">
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
                                <input type="number" v-model.number="calcAmount" min="1" class="glass-input w-full font-mono text-white text-lg transition-all duration-300"/>
                            </div>

                            <!-- Direct estimated revenue -->
                            <div class="p-4 rounded-xl border border-emerald-500/10 bg-emerald-500/[0.02] transition-all duration-300">
                                <span class="text-[10px] font-semibold text-emerald-400/70 uppercase tracking-wider block">{{ t('market.estimated_revenue') }}</span>
                                <div class="flex items-baseline gap-2 mt-1">
                                    <span class="text-2xl font-bold text-emerald-400 font-mono">{{ calculatedCost }}</span>
                                    <span class="text-xs text-white/40">{{ selectedTargetName }}</span>
                                </div>
                                <span class="text-[9px] text-white/20 block mt-2">Formula: {{ calcAmount || 0 }} * {{ stats.average }} average price</span>
                            </div>

                            <!-- Mirrored estimated cost -->
                            <div v-if="mirroredStats" class="p-4 rounded-xl border border-blue-500/10 bg-blue-500/[0.02] transition-all duration-300">
                                <span class="text-[10px] font-semibold text-blue-400/70 uppercase tracking-wider block">{{ t('market.estimated_cost') }}</span>
                                <div class="flex items-baseline gap-2 mt-1">
                                    <span class="text-2xl font-bold text-blue-400 font-mono">{{ calculatedMirroredCost }}</span>
                                    <span class="text-xs text-white/40">{{ selectedTargetName }}</span>
                                </div>
                                <span class="text-[9px] text-white/20 block mt-2">Formula: {{ calcAmount || 0 }} / {{ mirroredStats.average }} average price</span>
                            </div>
                            <div v-else class="p-4 rounded-xl border border-white/5 bg-white/[0.01] text-center text-xs text-white/30">
                                No mirrored trades ({{ selectedTargetName }} ➔ {{ selectedItemName }}) found to calculate mirrored cost.
                            </div>
                        </div>
                    </div>

                    <!-- Selected pair market details -->
                    <div class="glass-card p-6 transition-all duration-300">
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
        </transition>

        <!-- Popular Items, Arbitrage & Current Active Market -->
        <div class="space-y-6 relative">
            <loading-overlay :show="loading" :label="t('market.loading_data')" />
            <!-- Most Popular Items Card -->
            <div class="glass-card p-6 animate-fade-in-up transition-all duration-500 hover:border-white/20">
                <div class="flex items-center justify-between gap-3 mb-6 border-b border-white/5 pb-3">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center text-white shadow-md shadow-blue-500/20">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18 9 11.25l4.306 4.307a11.95 11.95 0 0 1 5.814-5.518l2.74-1.22m0 0-3.75-1.002m3.75 1.002-1.002 3.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                            </svg>
                        </div>
                        <h2 class="text-lg font-semibold text-white">{{ t('market.popular_items') }}</h2>
                    </div>
                    <button @click="togglePopularItems" :aria-label="t('market.popular_items')" :aria-expanded="showPopularItems ? 'true' : 'false'" class="text-white/40 hover:text-white transition-colors duration-300">
                        <svg v-if="showPopularItems" class="w-5 h-5 transition-transform duration-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7" />
                        </svg>
                        <svg v-else class="w-5 h-5 transition-transform duration-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>
                </div>

                <transition name="smooth-accordion">
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
                                <tr v-for="item in popular" :key="item.item_id" class="hover:bg-white/[0.03] transition-colors duration-300">
                                    <td class="py-3 px-4 font-semibold text-white">
                                        <div class="flex items-center gap-2">
                                            <img :alt="getItemName(item.item_name, item.item_id)" :src="getResourceIcon(item.item_id)" @error="handleIconError($event, item.item_id)" class="w-5 h-5 object-contain" />
                                            <span>{{ getItemName(item.item_name, item.item_id) }}</span>
                                        </div>
                                    </td>
                                    <td class="py-3 px-4 font-mono text-xs text-white/35">{{ item.item_id }}</td>
                                    <td class="py-3 px-4 text-right text-emerald-400 font-mono font-medium">{{ item.offers_count }} offers</td>
                                    <td class="py-3 px-4 text-right text-blue-400 font-mono">{{ item.sellers_count }} sellers</td>
                                    <td class="py-3 px-4 text-right font-mono">{{ formatVolume(item.total_volume) }} {{ t('market.units_short') }}</td>
                                </tr>
                                <tr v-if="popular.length === 0">
                                    <td colspan="5" class="py-8 text-center text-white/20">
                                        No data available. Perform market synchronization first.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </transition>
            </div>

            <!-- Profitable Exchange Schemes Card -->
            <div class="glass-card p-6 animate-fade-in-up transition-all duration-500 hover:border-white/20">
                <div class="flex items-center justify-between mb-6 border-b border-white/5 pb-3">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-emerald-500 to-green-600 flex items-center justify-center text-white shadow-md shadow-emerald-500/20">
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
                    <div v-show="showArbitrageSchemes" class="space-y-4 max-h-[500px] overflow-y-auto pr-2 scrollbar-thin">
                        <div v-for="(scheme, idx) in arbitrageLoops" :key="'scheme-'+idx"
                             class="p-4 rounded-xl border border-white/5 bg-white/[0.02] hover:bg-white/[0.05] hover:border-white/10 transition-all duration-300 flex flex-col gap-4">

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
                                        <div class="text-[10px] uppercase font-bold text-white/30 mb-2">Step {{ sIdx + 1 }}</div>

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

                        <!-- Empty state -->
                        <div v-if="arbitrageLoops.length === 0" class="py-8 text-center text-white/20">
                            No profitable exchange schemes found on the market currently.
                        </div>
                    </div>
                </transition>
            </div>

            <!-- Current Active Market Listings Card (Exact replica of MarketAnalytics.vue) -->
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

        <!-- Subtle peace note -->
        <p class="text-center text-[10px] leading-relaxed text-white/5 hover:text-white/35 transition-colors duration-500 select-none px-6">
            {{ t('market.peace_note') }}
        </p>
    </div>
</template>

<script>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { showToast } from '../toast';
import { t } from '../lang';
import { resourceName, marketItemName } from '../lang/gameNames';
import { TRADABLE_RESOURCES } from '../lang/resourcesCatalog';
import axios from 'axios';
import { cachedGet } from '../services/apiCacheService';
import { getGameImageUrl, handleGameImageError } from '../services/gameImageService';

import Spinner from '../components/Spinner.vue';
import LoadingOverlay from '../components/LoadingOverlay.vue';
import LanguageSwitcher from '../components/LanguageSwitcher.vue';

export default {
    name: 'PublicMarketAnalytics',
    components: { Spinner, LoadingOverlay, LanguageSwitcher },
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
                showToast('Failed to copy link', 'error');
            }
        };

        const loading = ref(false);
        const loadingPairs = ref(false);
        const loadingChart = ref(false);
        const showPopularItems = ref(true);
        const showArbitrageSchemes = ref(true);
        const showActiveListings = ref(true);

        const togglePopularItems = () => showPopularItems.value = !showPopularItems.value;
        const toggleArbitrageSchemes = () => showArbitrageSchemes.value = !showArbitrageSchemes.value;
        const toggleActiveListings = () => showActiveListings.value = !showActiveListings.value;

        // API lists & Translations
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

        const tooltipPositionClass = computed(() => {
            if (!hoveredPoint.value) return '';
            const xRatio = hoveredPoint.value.x / 600;
            const yRatio = hoveredPoint.value.y / 220;

            let translateX = '-translate-x-1/2';
            if (xRatio > 0.75) {
                translateX = '-translate-x-[90%]';
            } else if (xRatio < 0.25) {
                translateX = '-translate-x-[10%]';
            }

            let translateY = '-translate-y-full mb-3';
            if (yRatio < 0.3) {
                translateY = 'translate-y-2 mt-2';
            }

            return `${translateX} ${translateY}`;
        });

        // Selection & Filter variables
        const selectionMode = ref('visual'); // 'dropdown' or 'visual'
        const visualTab = ref(1); // 1 = Sell, 2 = Buy
        const selectedPeriod = ref('all');
        const mirroredStats = ref(null);
        const mirroredHistory = ref(null);
        const activeOffers = ref([]);
        const totalActiveCount = ref(0);
        const activeOffersPage = ref(1);
        const hasMoreActiveOffers = ref(false);
        const loadingMore = ref(false);
        const arbitrageLoops = ref([]);

        const defaultPublicServers = [
            { id: 1, server_id: 'ru', locale: 'RU', display_name: 'RU' },
            { id: 2, server_id: 'de', locale: 'DE', display_name: 'DE' },
            { id: 3, server_id: 'en', locale: 'EN', display_name: 'EN' },
            { id: 4, server_id: 'us', locale: 'EN', display_name: 'US' },
            { id: 5, server_id: 'fr', locale: 'FR', display_name: 'FR' },
            { id: 6, server_id: 'pl', locale: 'PL', display_name: 'PL' },
            { id: 7, server_id: 'es', locale: 'ES', display_name: 'ES' },
        ];

        const servers = ref(defaultPublicServers);
        const selectedServerId = ref(localStorage.getItem('tso_market_selected_server') || 'ru');
        const isAuthenticated = computed(() => !!(window.__AUTH_USER__ && window.__AUTH_USER__.id));

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

        const getServerWorldName = (srv) => {
            if (!srv) return '';
            let name = srv.world_name;
            return name || (srv.server_id ? String(srv.server_id).toUpperCase() : '');
        };

        /** "🇷🇺 RU · Мир" — подпись опции в селекторе серверов. */
        const serverOptionLabel = (srv) => {
            if (!srv) return '';
            const world = getServerWorldName(srv);
            return `${getLocaleFlag(srv.locale)} ${world}`.trim();
        };

        const loadServers = async () => {
            try {
                const data = await cachedGet('/api/public/market/servers', { ttlMs: 300000 });
                servers.value = data.data || data || [];
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
                console.error('Failed to load public market servers:', e);
                servers.value = [];
            }
        };

        const onServerChange = () => {
            localStorage.setItem('tso_market_selected_server', selectedServerId.value);
            resetSelection();
            goods.value = [];
            updateQueryParams();
            loadInitialData({ bypass: true });
        };

        const periods = [
            { value: '1d', label: t('market.range_24h') },
            { value: '7d', label: t('market.range_7d') },
            { value: '30d', label: t('market.range_30d') },
            { value: '1y', label: t('market.range_1y') },
            { value: 'all', label: t('market.range_all') }
        ];

        // Form state
        const selectedItem = ref('');
        const selectedTarget = ref('');
        const calcAmount = ref(100);

        // Единая точка перевода названий предметов рынка (общая с админкой):
        // см. resources/js/lang/gameNames.js -> marketItemName().
        const getItemName = marketItemName;

        // Dynamic translated names
        const selectedItemName = computed(() => {
            const item = allGoods.value.find(g => g.item_id === selectedItem.value);
            const name = item ? item.item_name : selectedItem.value;
            return getItemName(name, selectedItem.value);
        });

        const selectedTargetName = computed(() => {
            const item = targets.value.find(t => t.target_item_id === selectedTarget.value);
            const name = item ? item.target_item_name : selectedTarget.value;
            return getItemName(name, selectedTarget.value);
        });

        // Calculator costs
        const calculatedCost = computed(() => {
            if (!stats.value || !stats.value.average) return 0;
            const amt = parseFloat(calcAmount.value) || 0;
            return Math.round(amt * stats.value.average * 100) / 100;
        });

        const calculatedMirroredCost = computed(() => {
            if (!mirroredStats.value || !mirroredStats.value.average) return 0;
            const amt = parseFloat(calcAmount.value) || 0;
            return Math.round((amt / mirroredStats.value.average) * 100) / 100;
        });

        // Market detail helpers
        const activeVolume = computed(() => {
            if (periodInfo.value) {
                return periodInfo.value.volume;
            }
            if (activeInfo.value && activeInfo.value.offers_count > 0) {
                return activeInfo.value.volume;
            }
            if (!history.value || history.value.length === 0) return 0;
            return history.value.reduce((sum, h) => sum + (h.volume || 0), 0);
        });

        const activeOffersCount = computed(() => {
            if (periodInfo.value) {
                return periodInfo.value.offers_count;
            }
            if (activeInfo.value && activeInfo.value.offers_count > 0) {
                return activeInfo.value.offers_count;
            }
            if (!history.value || history.value.length === 0) return 0;
            return history.value.reduce((sum, h) => sum + (h.offers_count || 0), 0);
        });

        const activeSellersCount = computed(() => {
            if (periodInfo.value) {
                return periodInfo.value.sellers_count;
            }
            if (activeInfo.value && activeInfo.value.offers_count > 0) {
                return activeInfo.value.sellers_count;
            }
            if (!history.value || history.value.length === 0) return 0;
            return history.value[history.value.length - 1].sellers_count;
        });

        // Trend calculation
        const priceTrendText = computed(() => {
            if (history.value.length < 2) return 'Stable';
            const last = history.value[history.value.length - 1].price;
            const prev = history.value[history.value.length - 2].price;
            if (last > prev) return 'Rising';
            if (last < prev) return 'Falling';
            return 'Stable';
        });

        const priceTrendClass = computed(() => {
            if (priceTrendText.value === 'Rising') return 'text-emerald-400';
            if (priceTrendText.value === 'Falling') return 'text-red-400';
            return 'text-white/40';
        });

        // Volume Formatting Helper
        const formatVolume = (val) => {
            if (val >= 1000000) return (val / 1000000).toFixed(1) + 'M';
            if (val >= 1000) return (val / 1000).toFixed(1) + 'K';
            return val;
        };

        // Resource icon lookup: resources first, then other.
        const getResourceIcon = (itemId) =>
            getGameImageUrl('resource', itemId || 'addresource');

        const handleIconError = (event, itemId) =>
            handleGameImageError(event, 'resource', itemId || 'addresource', '/images/resources/addresource.webp');

        const formatTimeLeft = (seconds) => {
            if (seconds <= 0) return t('market.expired');
            const h = Math.floor(seconds / 3600);
            const m = Math.floor((seconds % 3600) / 60);
            const s = seconds % 60;
            return `${h}h ${m}m ${s}s`;
        };

        // Charts calculations
        const chartPoints = computed(() => {
            if (history.value.length === 0) return [];
            const w = 550;
            const isSingle = history.value.length === 1;

            const maxPrice = Math.max(...history.value.map(h => h.price)) || 1;
            const minPrice = Math.min(...history.value.map(h => h.price)) || 0;
            const priceDiff = (maxPrice - minPrice) || 1;

            const maxSellers = Math.max(...history.value.map(h => h.sellers_count)) || 1;
            const maxOffers = Math.max(...history.value.map(h => h.offers_count)) || 1;
            const maxVolume = Math.max(...history.value.map(h => h.volume)) || 1;

            return history.value.map((d, idx) => {
                const stepX = isSingle ? 0 : w / (history.value.length - 1);
                const x = isSingle ? 315 : 40 + idx * stepX;

                const py = maxPrice === minPrice
                    ? 120
                    : 220 - ((d.price - minPrice) / priceDiff) * 180 - 10;

                const sy = 220 - (d.sellers_count / maxSellers) * 180 - 10;
                const oy = 220 - (d.offers_count / maxOffers) * 180 - 10;
                const vy = 220 - (d.volume / maxVolume) * 180 - 10;

                return {
                    x, y: py, sy, oy, vy,
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
            if (pts.length === 1) return `M 40 ${pts[0].y} L 590 ${pts[0].y}`;
            return pts.reduce((path, p, idx) => {
                return idx === 0 ? `M ${p.x} ${p.y}` : `${path} L ${p.x} ${p.y}`;
            }, '');
        });

        const chartPriceAreaPath = computed(() => {
            const pts = chartPoints.value;
            if (pts.length === 0) return '';
            if (pts.length === 1) return `M 40 ${pts[0].y} L 590 ${pts[0].y} L 590 220 L 40 220 Z`;
            const line = chartPriceLinePath.value;
            return `${line} L ${pts[pts.length - 1].x} 220 L ${pts[0].x} 220 Z`;
        });

        const chartMeanY = computed(() => {
            if (!stats.value || !stats.value.average || history.value.length === 0) return 120;
            const maxPrice = Math.max(...history.value.map(h => h.price)) || 1;
            const minPrice = Math.min(...history.value.map(h => h.price)) || 0;
            const priceDiff = (maxPrice - minPrice) || 1;

            const calcY = maxPrice === minPrice
                ? 120
                : 220 - ((stats.value.average - minPrice) / priceDiff) * 180 - 10;
            return Math.max(20, Math.min(210, calcY));
        });

        const chartSellersLinePath = computed(() => {
            const pts = chartPoints.value;
            if (pts.length === 0) return '';
            if (pts.length === 1) return `M 40 ${pts[0].sy} L 590 ${pts[0].sy}`;
            return pts.reduce((path, p, idx) => {
                return idx === 0 ? `M ${p.x} ${p.sy}` : `${path} L ${p.x} ${p.sy}`;
            }, '');
        });

        const chartSellersAreaPath = computed(() => {
            const pts = chartPoints.value;
            if (pts.length === 0) return '';
            if (pts.length === 1) return `M 40 ${pts[0].sy} L 590 ${pts[0].sy} L 590 220 L 40 220 Z`;
            const line = chartSellersLinePath.value;
            return `${line} L ${pts[pts.length - 1].x} 220 L ${pts[0].x} 220 Z`;
        });

        const chartOffersLinePath = computed(() => {
            const pts = chartPoints.value;
            if (pts.length === 0) return '';
            if (pts.length === 1) return `M 40 ${pts[0].oy} L 590 ${pts[0].oy}`;
            return pts.reduce((path, p, idx) => {
                return idx === 0 ? `M ${p.x} ${p.oy}` : `${path} L ${p.x} ${p.oy}`;
            }, '');
        });

        const chartOffersAreaPath = computed(() => {
            const pts = chartPoints.value;
            if (pts.length === 0) return '';
            if (pts.length === 1) return `M 40 ${pts[0].oy} L 590 ${pts[0].oy} L 590 220 L 40 220 Z`;
            const line = chartOffersLinePath.value;
            return `${line} L ${pts[pts.length - 1].x} 220 L ${pts[0].x} 220 Z`;
        });

        const chartMaxPriceLabel = computed(() => {
            if (history.value.length === 0) return '';
            const max = Math.max(...history.value.map(h => h.price));
            return max >= 1000 ? formatVolume(max) : max.toFixed(2);
        });

        const chartMinPriceLabel = computed(() => {
            if (history.value.length === 0) return '';
            const min = Math.min(...history.value.map(h => h.price));
            return min >= 1000 ? formatVolume(min) : min.toFixed(2);
        });

        const chartMidPriceLabel = computed(() => {
            if (history.value.length === 0) return '';
            const max = Math.max(...history.value.map(h => h.price));
            const min = Math.min(...history.value.map(h => h.price));
            const mid = (max + min) / 2;
            return mid >= 1000 ? formatVolume(mid) : mid.toFixed(2);
        });

        const chartMaxOffersLabel = computed(() => {
            if (history.value.length === 0) return '';
            const maxSellers = Math.max(...history.value.map(h => h.sellers_count)) || 0;
            const maxOffers = Math.max(...history.value.map(h => h.offers_count)) || 0;
            const maxVal = Math.max(maxSellers, maxOffers);
            return maxVal >= 1000 ? formatVolume(maxVal) : String(maxVal);
        });

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

        // Public API Methods
        const loadInitialData = async (options = {}) => {
            if (!selectedServerId.value) return;
            loading.value = true;
            try {
                const params = { server_id: selectedServerId.value };
                const applyGoods = (data) => { goods.value = data || []; };
                const applyAnalytics = (data) => {
                    popular.value = data.popular || [];
                    activeOffers.value = data.active_offers || [];
                    totalActiveCount.value = data.total_active_count || 0;
                    activeOffersPage.value = 1;
                    hasMoreActiveOffers.value = data.has_more || false;
                };
                const applyArbitrage = (data) => { arbitrageLoops.value = data || []; };

                const goodsData = await cachedGet('/api/public/market/goods', { params, ttlMs: 1800000, onRevalidate: applyGoods, ...options });
                applyGoods(goodsData);

                const analyticsData = await cachedGet('/api/public/market/analytics', { params, ttlMs: 60000, onRevalidate: applyAnalytics, ...options });
                applyAnalytics(analyticsData);

                const arbitrageData = await cachedGet('/api/public/market/arbitrage', { params, ttlMs: 300000, onRevalidate: applyArbitrage, ...options });
                applyArbitrage(arbitrageData);

                startCountdown();
            } catch (e) {
                showToast(t('market.stats_failed'), 'error');
            } finally {
                loading.value = false;
            }
        };

        const onItemChange = async () => {
            selectedTarget.value = '';
            targets.value = [];
            stats.value = null;
            history.value = [];
            mirroredStats.value = null;
            mirroredHistory.value = null;
            updateQueryParams();

            if (!selectedItem.value || !selectedServerId.value) return;

            loadingPairs.value = true;
            try {
                const data = await cachedGet('/api/public/market/targets', {
                    params: { server_id: selectedServerId.value, item_id: selectedItem.value },
                    ttlMs: 1800000
                });
                targets.value = data || [];
            } catch (e) {
                showToast(t('market.targets_failed'), 'error');
            } finally {
                loadingPairs.value = false;
            }
        };

        const loadMoreActiveOffers = async () => {
            if (loadingMore.value || !hasMoreActiveOffers.value) return;
            loadingMore.value = true;
            try {
                const nextPage = activeOffersPage.value + 1;
                const data = await cachedGet('/api/public/market/analytics', {
                    params: { server_id: selectedServerId.value, page: nextPage },
                    ttlMs: 60000
                });
                const newOffers = data.active_offers || [];
                activeOffers.value.push(...newOffers);
                activeOffersPage.value = nextPage;
                hasMoreActiveOffers.value = data.has_more || false;
            } catch (e) {
                showToast(t('market.listings_failed'), 'error');
            } finally {
                loadingMore.value = false;
            }
        };

        const fetchAnalytics = async (options = {}) => {
            updateQueryParams();
            if (!selectedItem.value || !selectedTarget.value || !selectedServerId.value) {
                stats.value = null;
                history.value = [];
                activeInfo.value = null;
                periodInfo.value = null;
                mirroredStats.value = null;
                mirroredHistory.value = null;
                return;
            }

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
                const data = await cachedGet('/api/public/market/analytics', {
                    params: {
                        server_id: selectedServerId.value,
                        item_id: selectedItem.value,
                        target_item_id: selectedTarget.value,
                        period: selectedPeriod.value
                    },
                    ttlMs: 60000,
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

        // Visual Browser Handlers
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
            activeInfo.value = null;
            periodInfo.value = null;
            mirroredStats.value = null;
            mirroredHistory.value = null;
            visualTab.value = 1;
            updateQueryParams();
        };

        // Swapping / Mirroring Trade pair handler
        const mirrorSelection = async () => {
            if (!selectedItem.value || !selectedTarget.value) return;
            const tempItem = selectedItem.value;
            const tempTarget = selectedTarget.value;

            const canSellTarget = goods.value.some(g => g.item_id === tempTarget);
            if (!canSellTarget) {
                showToast(`Cannot mirror: No listings for selling ${selectedTargetName.value} are available.`, 'warning');
                return;
            }

            selectedItem.value = tempTarget;
            updateQueryParams();
            try {
                const res = await axios.get('/api/public/market/targets', {
                    params: { item_id: selectedItem.value }
                });
                targets.value = res.data || [];

                const hasOldItemAsTarget = targets.value.some(t => t.target_item_id === tempItem);
                if (hasOldItemAsTarget) {
                    selectedTarget.value = tempItem;
                    await fetchAnalytics();
                } else {
                    selectedTarget.value = '';
                    updateQueryParams();
                    showToast(`Opposite trade not found. Targets reloaded.`, 'info');
                }
            } catch (e) {
                showToast('Failed to mirror trade pair.', 'error');
            }
        };

        const changePeriod = (val) => {
            selectedPeriod.value = val;
            fetchAnalytics();
        };

        onMounted(async () => {
            console.log(
                '%c' + t('market.console_message'),
                'color:#34d399;font-size:13px;font-weight:600;line-height:1.6;'
            );
            const queryServer = route.query.server || route.query.server_id;
            if (queryServer) {
                selectedServerId.value = String(queryServer);
            }
            await loadServers();
            await loadInitialData();

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
            loading,
            loadingPairs,
            loadingChart,
            servers,
            selectedServerId,
            isAuthenticated,
            onServerChange,
            getLocaleFlag,
            getServerWorldName,
            serverOptionLabel,
            goods,
            allGoods,
            targets,
            popular,
            history,
            stats,
            selectedItem,
            selectedTarget,
            calcAmount,
            selectedItemName,
            selectedTargetName,
            calculatedCost,
            calculatedMirroredCost,
            activeVolume,
            activeOffersCount,
            activeSellersCount,
            priceTrendText,
            priceTrendClass,
            chartPoints,
            chartPriceLinePath,
            chartPriceAreaPath,
            chartMeanY,
            chartSellersLinePath,
            chartSellersAreaPath,
            chartOffersLinePath,
            chartOffersAreaPath,
            onItemChange,
            fetchAnalytics,
            formatVolume,
            selectionMode,
            visualTab,
            selectedPeriod,
            mirroredStats,
            mirroredHistory,
            activeOffers,
            periods,
            changePeriod,
            selectVisualItem,
            selectVisualTarget,
            resetSelection,
            getResourceIcon,
            formatTimeLeft,
            mirrorSelection,
            copyPairLink,
            handleIconError,
            totalActiveCount,
            activeOffersPage,
            hasMoreActiveOffers,
            loadingMore,
            loadMoreActiveOffers,
            arbitrageLoops,
            showPopularItems,
            showArbitrageSchemes,
            showActiveListings,
            togglePopularItems,
            toggleArbitrageSchemes,
            toggleActiveListings,
            getItemName,
            hoveredPoint,
            tooltipPositionClass
        };
    }
};
</script>

<style scoped>
/* Ultra-smooth cubic-bezier transitions for components & sections */
.smooth-fade-enter-active,
.smooth-fade-leave-active {
  transition: opacity 0.4s cubic-bezier(0.16, 1, 0.3, 1), transform 0.4s cubic-bezier(0.16, 1, 0.3, 1);
}
.smooth-fade-enter-from {
  opacity: 0;
  transform: translateY(10px) scale(0.99);
}
.smooth-fade-leave-to {
  opacity: 0;
  transform: translateY(-10px) scale(0.99);
}

/* Smooth Accordion Height & Opacity Transition */
.smooth-accordion-enter-active,
.smooth-accordion-leave-active {
  transition: max-height 0.5s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.4s ease-out;
  max-height: 1200px;
  overflow: hidden;
}
.smooth-accordion-enter-from,
.smooth-accordion-leave-to {
  max-height: 0;
  opacity: 0;
  overflow: hidden;
}

/* Smooth dot transitions without jittering */
circle {
  transition: r 0.2s ease, fill 0.2s ease;
}
</style>
