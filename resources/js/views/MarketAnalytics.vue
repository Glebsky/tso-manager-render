<template>
    <div>
        <!-- Page Header -->
        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-3xl font-bold text-white">Market Analytics</h1>
                <p class="text-white/40 mt-1">Analyze trade prices, demand history, and popular items on the game market</p>
            </div>
            <!-- Tab Navigation -->
            <div class="flex items-center gap-1 bg-white/5 border border-white/10 p-1 rounded-xl">
                <button @click="activeTab = 'analytics'"
                        class="px-4 py-2 rounded-lg text-xs font-semibold uppercase tracking-wider transition-all"
                        :class="activeTab === 'analytics' ? 'bg-emerald-500 text-white shadow-lg shadow-emerald-500/20' : 'text-white/50 hover:text-white'">
                    Analytics
                </button>
                <button @click="activeTab = 'settings'"
                        class="px-4 py-2 rounded-lg text-xs font-semibold uppercase tracking-wider transition-all"
                        :class="activeTab === 'settings' ? 'bg-emerald-500 text-white shadow-lg shadow-emerald-500/20' : 'text-white/50 hover:text-white'">
                    Settings
                </button>
            </div>
        </div>

        <!-- TAB 1: ANALYTICS -->
        <div v-if="activeTab === 'analytics'" class="space-y-6">
            <!-- Selection Card (Dropdowns or Visual Grid) -->
            <div class="glass-card p-6">
                <!-- Selector Header: Mode Switch & Mirror Button -->
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6 border-b border-white/5 pb-4">
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-semibold text-white/40 uppercase tracking-wider">Selection Mode:</span>
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
                        <label class="block text-xs font-medium text-white/40 mb-2 uppercase tracking-wider">Selling Item</label>
                        <div class="relative">
                            <select v-model="selectedItem" @change="onItemChange" class="glass-select w-full">
                                <option value="" class="bg-dark-900">Select selling item...</option>
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
                        <label class="block text-xs font-medium text-white/40 mb-2 uppercase tracking-wider">Target Item</label>
                        <div class="relative">
                            <select v-model="selectedTarget" :disabled="!selectedItem" @change="fetchAnalytics" class="glass-select w-full disabled:opacity-40">
                                <option value="" class="bg-dark-900">Select target item...</option>
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
                        <div v-for="good in goods" :key="good.item_id"
                             @click="selectVisualItem(good.item_id)"
                             class="flex flex-col items-center justify-center p-1.5 rounded-lg border cursor-pointer hover:border-emerald-500/40 hover:bg-white/[0.05] hover:shadow-md hover:shadow-emerald-500/5 text-center select-none transition-all duration-200"
                             :class="selectedItem === good.item_id ? 'bg-emerald-500/10 border-emerald-500 shadow shadow-emerald-500/10' : 'bg-white/[0.02] border-white/5 hover:border-white/20 hover:bg-white/[0.04]'">
                            <img :src="getResourceIcon(good.item_id)" @error="handleIconError($event, good.item_id)" class="w-6 h-6 object-contain mb-1 pointer-events-none" />
                            <span class="text-[9px] font-medium text-white/90 truncate w-full" :title="good.item_name">{{ good.item_name }}</span>
                        </div>
                        <div v-if="goods.length === 0" class="col-span-full py-8 text-center text-xs text-white/30">
                            No resources available in the database.
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
                        <!-- Current Price -->
                        <div class="glass-card p-4">
                            <span class="text-[10px] font-semibold text-white/30 uppercase tracking-wider block">Current Price</span>
                            <span class="text-xl font-bold text-white mt-1 block">{{ stats.current }}</span>
                            <span class="text-[10px] text-white/40 block mt-0.5">{{ selectedTargetName }}</span>
                        </div>
                        <!-- Average Price -->
                        <div class="glass-card p-4">
                            <span class="text-[10px] font-semibold text-white/30 uppercase tracking-wider block">Average Price</span>
                            <span class="text-xl font-bold text-emerald-400 mt-1 block">{{ stats.average }}</span>
                            <span class="text-[10px] text-white/40 block mt-0.5">{{ selectedTargetName }}</span>
                        </div>
                        <!-- Min Price -->
                        <div class="glass-card p-4">
                            <span class="text-[10px] font-semibold text-white/30 uppercase tracking-wider block">Minimum Price</span>
                            <span class="text-xl font-bold text-blue-400 mt-1 block">{{ stats.minimum }}</span>
                            <span class="text-[10px] text-white/40 block mt-0.5">{{ selectedTargetName }}</span>
                        </div>
                        <!-- Max Price -->
                        <div class="glass-card p-4">
                            <span class="text-[10px] font-semibold text-white/30 uppercase tracking-wider block">Maximum Price</span>
                            <span class="text-xl font-bold text-red-400 mt-1 block">{{ stats.maximum }}</span>
                            <span class="text-[10px] text-white/40 block mt-0.5">{{ selectedTargetName }}</span>
                        </div>
                    </div>

                    <!-- Price Dynamic Chart Card -->
                    <div class="glass-card p-6">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-4">
                            <h3 class="text-sm font-semibold text-white">Price History (1 {{ selectedItemName }} = X {{ selectedTargetName }})</h3>
                            
                            <div class="flex items-center gap-4">
                                <!-- Period Selection Buttons -->
                                <div class="flex items-center bg-white/5 border border-white/10 p-0.5 rounded-lg text-[10px] font-semibold">
                                    <button v-for="p in periods" :key="p.value" @click="changePeriod(p.value)"
                                            class="px-2.5 py-1 rounded transition-all uppercase tracking-wider"
                                            :class="selectedPeriod === p.value ? 'bg-emerald-500 text-white shadow' : 'text-white/40 hover:text-white'">
                                        {{ p.label }}
                                    </button>
                                </div>

                                <!-- Chart Price Indicator Legend -->
                                <div class="flex items-center gap-4 text-[10px] text-white/40">
                                    <div class="flex items-center gap-1.5">
                                        <span class="w-2.5 h-0.5 bg-emerald-500 inline-block"></span>
                                        Avg Price
                                        <span>Average Price</span>
                                    </div>
                                    <div class="flex items-center gap-1.5">
                                        <span class="w-2.5 h-0.5 bg-white/20 border-dashed border inline-block"></span>
                                        Mean
                                        <span>Global Mean</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- SVG Price Chart -->
                        <div class="h-64 w-full relative pt-2">
                            <template v-if="history.length > 0">
                                <svg class="w-full h-full" viewBox="0 0 600 220" preserveAspectRatio="none">
                                    <!-- Gradients -->
                                    <defs>
                                        <linearGradient id="priceGrad" x1="0" y1="0" x2="0" y2="1">
                                            <stop offset="0%" stop-color="#10b981" stop-opacity="0.2"/>
                                            <stop offset="100%" stop-color="#10b981" stop-opacity="0.0"/>
                                        </linearGradient>
                                    </defs>
                                    
                                    <!-- Grid lines Y -->
                                    <line v-for="grid in 4" :key="'grid-y-'+grid"
                                          x1="40" :y1="20 + (grid - 1) * 50" x2="590" :y2="20 + (grid - 1) * 50"
                                          stroke="rgba(255,255,255,0.03)" stroke-width="1"/>

                                    <!-- Price Line Area Fill -->
                                    <path :d="chartPriceAreaPath" fill="url(#priceGrad)"/>

                                    <!-- Price Line -->
                                    <path :d="chartPriceLinePath" fill="none" stroke="#10b981" stroke-width="2"/>

                                    <!-- Global Mean Line -->
                                    <line x1="40" :y1="chartMeanY" x2="590" :y2="chartMeanY"
                                          stroke="rgba(255,255,255,0.2)" stroke-dasharray="4,4" stroke-width="1.5"/>

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
                                     class="absolute z-30 pointer-events-none transition-all duration-150 ease-out transform -translate-x-1/2 -translate-y-full mb-3"
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
                                                <img :src="getResourceIcon(selectedItem)" @error="handleIconError($event, selectedItem)" class="w-4 h-4 object-contain" />
                                                <span class="font-mono font-bold text-white text-xs">{{ formatVolume(hoveredPoint.avg_amount) }}</span>
                                                <span class="text-[10px] text-white/60 truncate max-w-[60px]" :title="selectedItemName">{{ selectedItemName }}</span>
                                            </div>

                                            <!-- Arrow -->
                                            <svg class="w-3.5 h-3.5 text-emerald-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                                            </svg>

                                            <!-- Buying Item -->
                                            <div class="flex items-center gap-1.5">
                                                <img :src="getResourceIcon(selectedTarget)" @error="handleIconError($event, selectedTarget)" class="w-4 h-4 object-contain" />
                                                <span class="font-mono font-bold text-emerald-400 text-xs">{{ formatVolume(hoveredPoint.avg_target_amount) }}</span>
                                                <span class="text-[10px] text-emerald-400/80 truncate max-w-[60px]" :title="selectedTargetName">{{ selectedTargetName }}</span>
                                            </div>
                                        </div>

                                        <!-- Point Stats Summary -->
                                        <div class="flex items-center justify-between text-[10px] text-white/40 font-mono pt-0.5">
                                            <span>Offers: <strong class="text-white/80">{{ hoveredPoint.offers_count }}</strong></span>
                                            <span>Sellers: <strong class="text-white/80">{{ hoveredPoint.sellers_count }}</strong></span>
                                            <span>Vol: <strong class="text-white/80">{{ formatVolume(hoveredPoint.volume) }}</strong></span>
                                        </div>
                                    </div>
                                </div>

                                <!-- X-Axis Labels (Timeline) -->
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

                    <!-- Demand Dynamic Chart Card -->
                    <div class="glass-card p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-sm font-semibold text-white">Market Volume & Active Offers</h3>
                            <div class="flex items-center gap-4 text-[10px] text-white/40">
                                <div class="flex items-center gap-1.5">
                                    <span class="w-2.5 h-2.5 bg-blue-500/20 border border-blue-500 rounded-sm inline-block"></span>
                                    Sellers
                                    <span>Sellers Count</span>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <span class="w-2.5 h-2.5 bg-indigo-500/20 border border-indigo-500 rounded-sm inline-block"></span>
                                    Active Offers
                                    <span>Active Offers</span>
                                </div>
                            </div>
                        </div>

                        <!-- SVG Demand Chart -->
                        <div class="h-64 w-full relative pt-2">
                            <template v-if="history.length > 0">
                                <svg class="w-full h-full" viewBox="0 0 600 220" preserveAspectRatio="none">
                                    <!-- Grid lines Y -->
                                    <line v-for="grid in 4" :key="'grid-dy-'+grid"
                                          x1="40" :y1="20 + (grid - 1) * 50" x2="590" :y2="20 + (grid - 1) * 50"
                                          stroke="rgba(255,255,255,0.03)" stroke-width="1"/>

                                    <!-- Sellers Area Fill -->
                                    <path :d="chartSellersAreaPath" fill="rgba(59, 130, 246, 0.1)"/>
                                    <!-- Sellers Line -->
                                    <path :d="chartSellersLinePath" fill="none" stroke="#3b82f6" stroke-width="1.5"/>

                                    <!-- Offers Area Fill -->
                                    <path :d="chartOffersAreaPath" fill="rgba(99, 102, 241, 0.1)"/>
                                    <!-- Offers Line -->
                                    <path :d="chartOffersLinePath" fill="none" stroke="#6366f1" stroke-width="1.5"/>

                                    <!-- Volume Bars (drawn as faint vertical glass cylinders) -->
                                    <rect v-for="(b, idx) in chartPoints" :key="'vol-bar-'+idx"
                                          :x="b.x - 3" :y="b.vy" width="6" :height="220 - b.vy"
                                          fill="rgba(255,255,255,0.05)" stroke="rgba(255,255,255,0.1)" stroke-width="0.5" rx="1"/>
                                </svg>
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
                    <!-- Calculator Card -->
                    <div class="glass-card p-6">
                        <div class="flex items-center gap-3 mb-5 border-b border-white/5 pb-3">
                            <div class="w-8 h-8 rounded-lg bg-emerald-500/10 flex items-center justify-center text-emerald-400">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 15.75V18m-3-3v3m-3-3v3M9 3h12a2.25 2.25 0 0 1 2.25 2.25v13.5A2.25 2.25 0 0 1 21 21H9a2.25 2.25 0 0 1-2.25-2.25V5.25A2.25 2.25 0 0 1 9 3Zm2.25 3h7.5a.75.75 0 0 0 .75-.75V4.5a.75.75 0 0 0-.75-.75h-7.5a.75.75 0 0 0-.75.75v.75a.75.75 0 0 0 .75.75Z" />
                                </svg>
                            </div>
                            <h3 class="text-sm font-semibold text-white">Cost Calculator</h3>
                        </div>

                        <div class="space-y-5">
                            <div>
                                <label class="block text-xs font-medium text-white/40 mb-2 uppercase tracking-wider">Amount of {{ selectedItemName }}</label>
                                <input type="number" v-model.number="calcAmount" min="1" class="glass-input w-full font-mono text-white text-lg"/>
                            </div>

                            <!-- Direct estimated revenue -->
                            <div class="p-4 rounded-xl border border-emerald-500/10 bg-emerald-500/[0.02]">
                                <span class="text-[10px] font-semibold text-emerald-400/70 uppercase tracking-wider block">Estimated Revenue (Direct Trade: Sell A for B)</span>
                                <div class="flex items-baseline gap-2 mt-1">
                                    <span class="text-2xl font-bold text-emerald-400 font-mono">{{ calculatedCost }}</span>
                                    <span class="text-xs text-white/40">{{ selectedTargetName }}</span>
                                </div>
                                <span class="text-[9px] text-white/20 block mt-2">Formula: {{ calcAmount || 0 }} * {{ stats.average }} average price</span>
                            </div>

                            <!-- Mirrored estimated cost -->
                            <div v-if="mirroredStats" class="p-4 rounded-xl border border-blue-500/10 bg-blue-500/[0.02]">
                                <span class="text-[10px] font-semibold text-blue-400/70 uppercase tracking-wider block">Estimated Cost (Mirrored Trade: Buy A by selling B)</span>
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
                    <div class="glass-card p-6">
                        <h3 class="text-sm font-semibold text-white mb-4">Market Information</h3>
                        <div class="space-y-3 text-xs">
                            <div class="flex justify-between py-2 border-b border-white/5">
                                <span class="text-white/40">Total Active Volume:</span>
                                <span class="text-white font-mono font-medium">{{ activeVolume }} {{ selectedItemName }}</span>
                            </div>
                            <div class="flex justify-between py-2 border-b border-white/5">
                                <span class="text-white/40">Active Offers Count:</span>
                                <span class="text-white font-mono font-medium">{{ activeOffersCount }}</span>
                            </div>
                            <div class="flex justify-between py-2 border-b border-white/5">
                                <span class="text-white/40">Active Sellers:</span>
                                <span class="text-white font-mono font-medium">{{ activeSellersCount }}</span>
                            </div>
                            <div class="flex justify-between py-2 last:border-0">
                                <span class="text-white/40">Current Trend:</span>
                                <span class="font-semibold" :class="priceTrendClass">{{ priceTrendText }}</span>
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
                            <h2 class="text-lg font-semibold text-white">Most Popular Items</h2>
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
                                    <th class="py-3 px-4">Item Name</th>
                                    <th class="py-3 px-4">Item Code</th>
                                    <th class="py-3 px-4 text-right">Active Offers</th>
                                    <th class="py-3 px-4 text-right">Unique Sellers</th>
                                    <th class="py-3 px-4 text-right">Active Volume</th>
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
                                        No data available. Perform market synchronization first.
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
                                <h2 class="text-lg font-semibold text-white">Profitable Exchange Schemes</h2>
                                <span class="text-xs text-white/40">Real-time market arbitrage detection</span>
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
                                        <span class="text-white/30">Leftovers:</span>
                                        <span v-for="leftover in scheme.leftovers" :key="leftover.item_id" class="flex items-center gap-1 text-white/70">
                                            <img :src="getResourceIcon(leftover.item_id)" @error="handleIconError($event, leftover.item_id)" class="w-3.5 h-3.5 object-contain" />
                                            +{{ formatVolume(leftover.amount) }}
                                        </span>
                                    </div>
                                    <!-- Net Profit -->
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs text-white/40">Net Profit:</span>
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
                                        <!-- Step label -->
                                        <div class="text-[10px] uppercase font-bold text-white/30 mb-2">Step {{ sIdx + 1 }}</div>
                                        
                                        <!-- Exchange description -->
                                        <div class="flex flex-col gap-1.5">
                                            <div class="flex items-center gap-1.5 text-xs">
                                                <span class="text-white/40 w-8">Give:</span>
                                                <img :src="getResourceIcon(step.give_item)" @error="handleIconError($event, step.give_item)" class="w-4.5 h-4.5 object-contain" />
                                                <span class="font-mono font-semibold text-white/90">{{ formatVolume(step.give_per_lot) }}</span>
                                                <span class="text-[10px] text-white/30">(total: {{ formatVolume(step.give_amount) }})</span>
                                            </div>
                                            <div class="flex items-center gap-1.5 text-xs">
                                                <span class="text-white/40 w-8">Get:</span>
                                                <img :src="getResourceIcon(step.receive_item)" @error="handleIconError($event, step.receive_item)" class="w-4.5 h-4.5 object-contain" />
                                                <span class="font-mono font-semibold text-emerald-400">{{ formatVolume(step.receive_per_lot) }}</span>
                                                <span class="text-[10px] text-emerald-400/40">(total: {{ formatVolume(step.receive_amount) }})</span>
                                            </div>
                                            <div class="text-[10px] text-white/30 mt-1 border-t border-white/5 pt-1 flex justify-between">
                                                <span>Lots: <strong class="text-white/80">{{ step.lots }}</strong></span>
                                                <span class="truncate max-w-[100px]" :title="step.sender">By: <strong class="text-white/85">{{ step.sender }}</strong></span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Flow arrow icon -->
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
                </div>

                <!-- Current Active Market Listings Card -->
                <div class="glass-card p-6 animate-fade-in-up">
                    <div class="flex items-center justify-between gap-3 mb-6 border-b border-white/5 pb-3">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" />
                                </svg>
                            </div>
                            <div class="flex flex-col">
                                <h2 class="text-lg font-semibold text-white">Current Active Market Listings</h2>
                                <span class="text-xs text-white/40">{{ totalActiveCount }} active trades</span>
                            </div>
                        </div>
                        <button @click="toggleActiveListings" class="text-white/40 hover:text-white transition-colors">
                            <svg v-if="showActiveListings" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7" />
                            </svg>
                            <svg v-else class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                    </div>

                    <div v-show="showActiveListings" class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-b border-white/5 text-[10px] font-semibold text-white/30 uppercase tracking-wider">
                                    <th class="py-3 px-4">Player</th>
                                    <th class="py-3 px-4">Selling Resource</th>
                                    <th class="py-3 px-4">Buying Resource</th>
                                    <th class="py-3 px-4 text-right">Price</th>
                                    <th class="py-3 px-4 text-right">Lots Remaining</th>
                                    <th class="py-3 px-4 text-right">Time Left</th>
                                    <th class="py-3 px-4 text-right">Sync Time</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/5 text-sm text-white/70">
                                <tr v-for="offer in activeOffers" :key="offer.offer_id" class="hover:bg-white/[0.01] transition-all">
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
                    </div>

                    <!-- Load More Button -->
                    <div v-if="hasMoreActiveOffers" class="flex justify-center mt-4 pt-4 border-t border-white/5">
                        <button @click="loadMoreActiveOffers" :disabled="loadingMore" class="btn-secondary py-2 px-6 flex items-center gap-2 bg-white/5 border border-white/10 text-white/80 hover:text-white hover:bg-white/10 rounded-lg transition-all text-xs font-semibold">
                            <svg v-if="loadingMore" class="animate-spin w-4 h-4 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                            </svg>
                            {{ loadingMore ? 'Loading more...' : 'Load More Listings' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB 2: SETTINGS & SYNC LOGS -->
        <div v-else-if="activeTab === 'settings'" class="space-y-6">
            <div class="glass-card p-6">
                <div class="flex items-center gap-3 mb-5 border-b border-white/5 pb-3">
                    <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-emerald-500 to-teal-600 flex items-center justify-center text-white">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                        </svg>
                    </div>
                    <h2 class="text-lg font-semibold text-white">Synchronization Settings</h2>
                </div>

                <form @submit.prevent="saveSettings">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <!-- Account Selection -->
                        <div>
                            <label class="block text-xs font-medium text-white/40 mb-2 uppercase tracking-wider">Account</label>
                            <div class="relative">
                                <select v-model="settingsForm.account_id" class="glass-select w-full">
                                    <option :value="null" class="bg-dark-900">Select game account...</option>
                                    <option v-for="acc in accounts" :key="acc.id" :value="acc.id" class="bg-dark-900">
                                        {{ acc.username }} ({{ acc.nickname || 'No Nickname' }})
                                    </option>
                                </select>
                                <div class="absolute inset-y-0 right-3 flex items-center pointer-events-none">
                                    <svg class="w-4 h-4 text-white/30" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                    </svg>
                                </div>
                            </div>
                        </div>

                        <!-- Sync Interval Selection -->
                        <div>
                            <label class="block text-xs font-medium text-white/40 mb-2 uppercase tracking-wider">Sync Interval</label>
                            <div class="relative">
                                <select v-model="settingsForm.sync_interval" class="glass-select w-full">
                                    <option value="5" class="bg-dark-900">5 minutes</option>
                                    <option value="15" class="bg-dark-900">15 minutes</option>
                                    <option value="30" class="bg-dark-900">30 minutes</option>
                                    <option value="60" class="bg-dark-900">1 hour</option>
                                    <option value="custom" class="bg-dark-900">Custom</option>
                                </select>
                                <div class="absolute inset-y-0 right-3 flex items-center pointer-events-none">
                                    <svg class="w-4 h-4 text-white/30" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                    </svg>
                                </div>
                            </div>
                        </div>

                        <!-- Custom Interval Input -->
                        <div v-if="settingsForm.sync_interval === 'custom'">
                            <label class="block text-xs font-medium text-white/40 mb-2 uppercase tracking-wider">Custom Interval (Minutes)</label>
                            <input type="number" v-model.number="settingsForm.custom_interval_minutes" min="1" class="glass-input w-full font-mono text-white"/>
                        </div>

                        <!-- Account Status Information -->
                        <div>
                            <label class="block text-xs font-medium text-white/40 mb-2 uppercase tracking-wider">Account ID</label>
                            <div class="glass-input w-full font-mono text-white/50 select-none bg-white/[0.02]">
                                {{ settingsForm.account_id || 'Not selected' }}
                            </div>
                        </div>

                        <!-- Connection Status -->
                        <div>
                            <label class="block text-xs font-medium text-white/40 mb-2 uppercase tracking-wider">Connection Status</label>
                            <div class="flex items-center gap-2 pt-2">
                                <span class="w-2.5 h-2.5 rounded-full"
                                      :class="connectionStatus === 'Connected' ? 'bg-emerald-500 shadow-lg shadow-emerald-500/50 animate-pulse' : 'bg-red-500 shadow-lg shadow-red-500/50'"></span>
                                <span class="text-sm font-semibold text-white">{{ connectionStatus }}</span>
                            </div>
                        </div>

                        <!-- Last Synchronization -->
                        <div>
                            <label class="block text-xs font-medium text-white/40 mb-2 uppercase tracking-wider">Last Synchronization</label>
                            <div class="text-sm font-semibold text-white/70 pt-2 font-mono">
                                {{ lastSync }}
                            </div>
                        </div>
                    </div>

                    <div class="flex gap-4">
                        <button type="submit" :disabled="saving" class="btn-primary flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                            </svg>
                            {{ saving ? 'Saving...' : 'Save Settings' }}
                        </button>
                        <button type="button" @click="triggerManualSync" :disabled="syncing || !settingsForm.account_id" class="btn-secondary flex items-center gap-2">
                            <svg class="w-4 h-4" :class="{ 'animate-spin': syncing }" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182" />
                            </svg>
                            {{ syncing ? 'Syncing...' : 'Sync Now' }}
                        </button>
                    </div>
                </form>
            </div>

            <!-- Sync Logs -->
            <div class="glass-card p-6">
                <div class="flex items-center gap-3 mb-6 border-b border-white/5 pb-3">
                    <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-teal-500 to-emerald-600 flex items-center justify-center text-white">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 0 1 0 3.75H5.625a1.875 1.875 0 0 1 0-3.75Z" />
                        </svg>
                    </div>
                    <h2 class="text-lg font-semibold text-white">Synchronization Log</h2>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-white/5 text-[10px] font-semibold text-white/30 uppercase tracking-wider">
                                <th class="py-3 px-4">Date</th>
                                <th class="py-3 px-4">Action</th>
                                <th class="py-3 px-4">Status</th>
                                <th class="py-3 px-4">Message</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5 text-sm text-white/70">
                            <tr v-for="(log, idx) in logs" :key="'log-'+idx" class="hover:bg-white/[0.01] transition-all">
                                <td class="py-3 px-4 font-mono text-xs">{{ log.date }}</td>
                                <td class="py-3 px-4 font-semibold text-white/95">{{ log.action }}</td>
                                <td class="py-3 px-4">
                                    <span class="badge text-[10px]"
                                          :class="getStatusBadgeClass(log.status)">
                                        {{ log.status }}
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-xs text-white/50">{{ log.message }}</td>
                            </tr>
                            <tr v-if="logs.length === 0">
                                <td colspan="4" class="py-8 text-center text-white/20">
                                    No synchronization logs yet.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import axios from 'axios';
import { showToast } from '../toast';

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


        // API lists
        const goods = ref([]);
        const targets = ref([]);
        const popular = ref([]);
        const history = ref([]);
        const stats = ref(null);
        const activeInfo = ref(null);
        const periodInfo = ref(null);
        const hoveredPoint = ref(null);
        const accounts = ref([]);
        const logs = ref([]);

        // New Mirrored Trade and period variables
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

        const periods = [
            { value: '1d', label: '24h' },
            { value: '7d', label: '7d' },
            { value: '30d', label: '30d' },
            { value: '1y', label: '1y' },
            { value: 'all', label: 'All' }
        ];

        // Form state
        const selectedItem = ref('');
        const selectedTarget = ref('');
        const calcAmount = ref(100);
        
        const settingsForm = ref({
            account_id: null,
            sync_interval: '15',
            custom_interval_minutes: 15,
        });

        const connectionStatus = ref('Disconnected');
        const lastSync = ref('Never');

        // Dynamic translated names
        const selectedItemName = computed(() => {
            const item = goods.value.find(g => g.item_id === selectedItem.value);
            return item ? item.item_name : '';
        });

        const selectedTargetName = computed(() => {
            const item = targets.value.find(t => t.target_item_id === selectedTarget.value);
            return item ? item.target_item_name : '';
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
            const trend = priceTrendText.value;
            if (trend === 'Rising') return 'text-emerald-400';
            if (trend === 'Falling') return 'text-red-400';
            return 'text-white/40';
        });

        // Volume Formatting Helper
        const formatVolume = (val) => {
            if (val >= 1000000) return (val / 1000000).toFixed(1) + 'M';
            if (val >= 1000) return (val / 1000).toFixed(1) + 'K';
            return val;
        };

        // Resource Icons helper
        const getResourceIcon = (itemId) => {
            if (!itemId) return '/images/resources/addresource.png';
            const lower = itemId.toLowerCase();
            
            const webpResources = [
                'advancedtools', 'adventurerelics', 'adventuretale', 'archebuse', 'battlehorse', 'battlelance',
                'beer', 'bow', 'bread', 'cannon', 'coal', 'compositebow', 'crossbow', 'crystal', 'deadtreewood',
                'fish', 'flour', 'gold', 'goldore', 'granite', 'iron', 'ironore', 'marble', 'meat', 'mortar',
                'oil', 'pike', 'plank', 'platinumore', 'platinumsword', 'realwood', 'saddlecloth', 'steel', 'steelsword',
                'stone', 'titaniumsword', 'water', 'wood', 'wool'
            ];
            
            const pngResources = [
                'beer', 'bow', 'bread', 'bronze', 'bronzeore', 'bronzesword', 'clock', 'cloth', 'codex', 'coin',
                'crystal', 'crystalshard', 'exoticwood', 'flour', 'granite', 'gunpowder', 'iron', 'ironsword',
                'mahoganywood', 'meat', 'platinumore', 'saddlecloth', 'stone', 'titaniumsword', 'tool', 'water', 'wood'
            ];
            
            if (pngResources.includes(lower)) {
                return `/images/resources/${lower}.png`;
            }
            if (webpResources.includes(lower)) {
                return `/images/resources/${lower}.webp`;
            }
            return `/images/resources/${lower}.webp`;
        };

        const handleIconError = (event, itemId) => {
            const img = event.target;
            if (!img || !itemId) return;
            
            const src = img.getAttribute('src') || '';
            const lower = itemId.toLowerCase();
            if (src.endsWith('.webp')) {
                img.src = `/images/resources/${lower}.png`;
            } else if (src.endsWith('.png') && !src.includes('addresource.png')) {
                img.src = '/images/resources/addresource.png';
            }
        };

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

        // Real-time Countdown formatting
        const formatTimeLeft = (seconds) => {
            if (seconds <= 0) return 'Expired';
            const h = Math.floor(seconds / 3600);
            const m = Math.floor((seconds % 3600) / 60);
            const s = seconds % 60;
            return `${h}h ${m}m ${s}s`;
        };

        // Charts calculations
        const chartPoints = computed(() => {
            if (history.value.length === 0) return [];
            const w = 550; // width bounds (from 40 to 590)
            const h = 200; // height bounds (from 20 to 220, so height is 200)

            const maxPrice = Math.max(...history.value.map(h => h.price)) || 1;
            const minPrice = Math.min(...history.value.map(h => h.price)) || 0;
            const priceDiff = (maxPrice - minPrice) || 1;

            const maxSellers = Math.max(...history.value.map(h => h.sellers_count)) || 1;
            const maxOffers = Math.max(...history.value.map(h => h.offers_count)) || 1;
            const maxVolume = Math.max(...history.value.map(h => h.volume)) || 1;

            return history.value.map((d, idx) => {
                const stepX = history.value.length > 1 ? w / (history.value.length - 1) : w;
                const x = 40 + idx * stepX;
                
                // Y for price: invert coordinate
                const py = maxPrice === minPrice 
                    ? 120 
                    : 220 - ((d.price - minPrice) / priceDiff) * 180 - 10;
                
                // Y for sellers & offers
                const sy = 220 - (d.sellers_count / maxSellers) * 180 - 10;
                const oy = 220 - (d.offers_count / maxOffers) * 180 - 10;
                
                // Y for volume bar (vy starts at 220 and goes up)
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
            return pts.reduce((path, p, idx) => {
                return idx === 0 ? `M ${p.x} ${p.y}` : `${path} L ${p.x} ${p.y}`;
            }, '');
        });

        const chartPriceAreaPath = computed(() => {
            const pts = chartPoints.value;
            if (pts.length === 0) return '';
            const line = chartPriceLinePath.value;
            return `${line} L ${pts[pts.length - 1].x} 220 L ${pts[0].x} 220 Z`;
        });

        const chartMeanY = computed(() => {
            if (!stats.value || !stats.value.average) return 120;
            const maxPrice = Math.max(...history.value.map(h => h.price)) || 1;
            const minPrice = Math.min(...history.value.map(h => h.price)) || 0;
            const priceDiff = (maxPrice - minPrice) || 1;
            
            return maxPrice === minPrice 
                ? 120 
                : 220 - ((stats.value.average - minPrice) / priceDiff) * 180 - 10;
        });

        // Demand Charts Paths
        const chartSellersLinePath = computed(() => {
            const pts = chartPoints.value;
            if (pts.length === 0) return '';
            return pts.reduce((path, p, idx) => {
                return idx === 0 ? `M ${p.x} ${p.sy}` : `${path} L ${p.x} ${p.sy}`;
            }, '');
        });

        const chartSellersAreaPath = computed(() => {
            const pts = chartPoints.value;
            if (pts.length === 0) return '';
            const line = chartSellersLinePath.value;
            return `${line} L ${pts[pts.length - 1].x} 220 L ${pts[0].x} 220 Z`;
        });

        const chartOffersLinePath = computed(() => {
            const pts = chartPoints.value;
            if (pts.length === 0) return '';
            return pts.reduce((path, p, idx) => {
                return idx === 0 ? `M ${p.x} ${p.oy}` : `${path} L ${p.x} ${p.oy}`;
            }, '');
        });

        const chartOffersAreaPath = computed(() => {
            const pts = chartPoints.value;
            if (pts.length === 0) return '';
            const line = chartOffersLinePath.value;
            return `${line} L ${pts[pts.length - 1].x} 220 L ${pts[0].x} 220 Z`;
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

        // API Methods
        const loadInitialData = async () => {
            loading.value = true;
            try {
                // Fetch settings
                const settingsRes = await axios.get('/api/market/settings');
                settingsForm.value = settingsRes.data.settings || {
                    account_id: null,
                    sync_interval: '15',
                    custom_interval_minutes: 15
                };
                accounts.value = settingsRes.data.accounts || [];
                connectionStatus.value = settingsRes.data.connection_status || 'Disconnected';
                lastSync.value = settingsRes.data.last_sync || 'Never';

                // Fetch logs
                const logsRes = await axios.get('/api/market/logs');
                logs.value = logsRes.data || [];

                // Fetch popular items & goods
                const goodsRes = await axios.get('/api/market/goods');
                goods.value = goodsRes.data || [];

                // Fetch basic analytics (for popular table and active listings)
                const analyticsRes = await axios.get('/api/market/analytics');
                popular.value = analyticsRes.data.popular || [];
                activeOffers.value = analyticsRes.data.active_offers || [];
                totalActiveCount.value = analyticsRes.data.total_active_count || 0;
                activeOffersPage.value = 1;
                hasMoreActiveOffers.value = analyticsRes.data.has_more || false;

                // Fetch arbitrage loops
                const arbitrageRes = await axios.get('/api/market/arbitrage');
                arbitrageLoops.value = arbitrageRes.data || [];

                startCountdown();
            } catch (e) {
                showToast('Failed to load market statistics.', 'error');
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

            if (!selectedItem.value) return;

            try {
                const res = await axios.get('/api/market/targets', {
                    params: { item_id: selectedItem.value }
                });
                targets.value = res.data || [];
            } catch (e) {
                showToast('Failed to load target items.', 'error');
            }
        };

        const loadMoreActiveOffers = async () => {
            if (loadingMore.value || !hasMoreActiveOffers.value) return;
            loadingMore.value = true;
            try {
                const nextPage = activeOffersPage.value + 1;
                const res = await axios.get('/api/market/analytics', {
                    params: { page: nextPage }
                });
                const newOffers = res.data.active_offers || [];
                activeOffers.value.push(...newOffers);
                activeOffersPage.value = nextPage;
                hasMoreActiveOffers.value = res.data.has_more || false;
            } catch (e) {
                showToast('Failed to load more listings.', 'error');
            } finally {
                loadingMore.value = false;
            }
        };

        const fetchAnalytics = async () => {
            if (!selectedItem.value || !selectedTarget.value) {
                stats.value = null;
                history.value = [];
                activeInfo.value = null;
                periodInfo.value = null;
                mirroredStats.value = null;
                mirroredHistory.value = null;
                return;
            }

            try {
                const res = await axios.get('/api/market/analytics', {
                    params: {
                        item_id: selectedItem.value,
                        target_item_id: selectedTarget.value,
                        period: selectedPeriod.value
                    }
                });
                stats.value = res.data.stats || null;
                history.value = res.data.history || [];
                activeInfo.value = res.data.active_info || null;
                periodInfo.value = res.data.period_info || null;
                mirroredStats.value = res.data.mirrored_stats || null;
                mirroredHistory.value = res.data.mirrored_history || null;
            } catch (e) {
                showToast('Failed to load analytics charts.', 'error');
            }
        };

        const saveSettings = async () => {
            saving.value = true;
            try {
                const res = await axios.put('/api/market/settings', settingsForm.value);
                if (res.data.success) {
                    showToast('Market settings updated.');
                    // Reload status
                    const settingsRes = await axios.get('/api/market/settings');
                    connectionStatus.value = settingsRes.data.connection_status;
                    lastSync.value = settingsRes.data.last_sync;
                }
            } catch (e) {
                showToast('Failed to save settings.', 'error');
            } finally {
                saving.value = false;
            }
        };

        const triggerManualSync = async () => {
            syncing.value = true;
            try {
                const res = await axios.post('/api/market/sync');
                if (res.data.success) {
                    showToast(`Synchronization complete! ${res.data.message}`);
                    await loadInitialData();
                    if (selectedItem.value && selectedTarget.value) {
                        await fetchAnalytics();
                    }
                }
            } catch (e) {
                const msg = e.response?.data?.message || 'Sync failed.';
                showToast(msg, 'error');
            } finally {
                syncing.value = false;
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
        };

        // Swapping / Mirroring Trade pair handler
        const mirrorSelection = async () => {
            if (!selectedItem.value || !selectedTarget.value) return;
            const tempItem = selectedItem.value;
            const tempTarget = selectedTarget.value;
            
            // Check if target is a valid selling item
            const canSellTarget = goods.value.some(g => g.item_id === tempTarget);
            if (!canSellTarget) {
                showToast(`Cannot mirror: No listings for selling ${selectedTargetName.value} are available.`, 'warning');
                return;
            }
            
            selectedItem.value = tempTarget;
            try {
                const res = await axios.get('/api/market/targets', {
                    params: { item_id: selectedItem.value }
                });
                targets.value = res.data || [];
                
                const hasOldItemAsTarget = targets.value.some(t => t.target_item_id === tempItem);
                if (hasOldItemAsTarget) {
                    selectedTarget.value = tempItem;
                    await fetchAnalytics();
                } else {
                    selectedTarget.value = '';
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

        onMounted(() => {
            loadInitialData();
        });

        onUnmounted(() => {
            if (countdownInterval) clearInterval(countdownInterval);
        });

        return {
            activeTab,
            loading,
            saving,
            syncing,
            goods,
            targets,
            popular,
            history,
            stats,
            accounts,
            logs,
            selectedItem,
            selectedTarget,
            calcAmount,
            settingsForm,
            connectionStatus,
            lastSync,
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
            saveSettings,
            triggerManualSync,
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
            handleIconError,
            getStatusBadgeClass,
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
            hoveredPoint
        };
    }
};
</script>

<style scoped>
/* Smooth dot transitions without jittering */
circle {
  transition: r 0.2s ease, fill 0.2s ease;
}
</style>
