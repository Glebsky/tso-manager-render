<template>
    <div class="glass-card p-4 sm:p-6 relative transition-all duration-300" :class="hoveredPoint ? 'z-40' : 'z-10'">
        <!-- Header: title, period switch, legend -->
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3 mb-4">
            <h3 class="text-[13px] sm:text-sm font-semibold text-white wrap-anywhere">{{ title }}</h3>

            <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
                <!-- Period Selection Buttons -->
                <div class="flex items-center bg-white/5 border border-white/10 p-0.5 rounded-lg text-[10px] font-semibold shrink-0">
                    <button v-for="p in periods" :key="p.value" type="button" @click="$emit('change-period', p.value)"
                            class="px-2 sm:px-2.5 py-1 rounded transition-all duration-300 uppercase tracking-wider whitespace-nowrap"
                            :class="selectedPeriod === p.value ? 'bg-emerald-500 text-white shadow' : 'text-white/40 hover:text-white'">
                        {{ p.label }}
                    </button>
                </div>

                <!-- Legend -->
                <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-[10px] text-white/40">
                    <div class="flex items-center gap-1.5 whitespace-nowrap">
                        <span class="w-2.5 h-0.5 bg-emerald-500 inline-block"></span>
                        <span>{{ averageLabel }}</span>
                    </div>
                    <div class="flex items-center gap-1.5 whitespace-nowrap">
                        <span class="w-2.5 h-0.5 bg-white/20 border-dashed border inline-block"></span>
                        <span>{{ meanLabel }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- SVG Price Chart -->
        <div ref="chartBox" class="h-56 sm:h-64 w-full relative z-40 pt-2">
            <template v-if="history.length > 0">
                <svg class="w-full h-full overflow-visible" :viewBox="`0 0 ${VB_W} ${VB_H}`" preserveAspectRatio="none">
                    <defs>
                        <linearGradient :id="gradientId" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="#10b981" stop-opacity="0.25"/>
                            <stop offset="100%" stop-color="#10b981" stop-opacity="0.0"/>
                        </linearGradient>
                    </defs>

                    <line v-for="grid in 4" :key="'grid-y-' + grid"
                          :x1="PAD_L" :y1="20 + (grid - 1) * 50" :x2="PAD_R" :y2="20 + (grid - 1) * 50"
                          stroke="rgba(255,255,255,0.03)" stroke-width="1" vector-effect="non-scaling-stroke"/>

                    <!-- Axis labels are counter-scaled so they stay legible on narrow screens -->
                    <g :transform="labelTransform">
                        <text :x="labelX" y="23" fill="rgba(255,255,255,0.35)" font-size="9" text-anchor="end" font-family="monospace">{{ maxPriceLabel }}</text>
                        <text :x="labelX" y="123" fill="rgba(255,255,255,0.22)" font-size="9" text-anchor="end" font-family="monospace">{{ midPriceLabel }}</text>
                        <text :x="labelX" y="215" fill="rgba(255,255,255,0.35)" font-size="9" text-anchor="end" font-family="monospace">{{ minPriceLabel }}</text>
                    </g>

                    <path :d="priceAreaPath" :fill="`url(#${gradientId})`" class="transition-all duration-500 ease-out"/>
                    <path :d="priceLinePath" fill="none" stroke="#10b981" stroke-width="2" vector-effect="non-scaling-stroke"
                          stroke-linejoin="round" stroke-linecap="round" class="transition-all duration-500 ease-out"/>
                    <line :x1="PAD_L" :y1="meanY" :x2="PAD_R" :y2="meanY"
                          stroke="rgba(255,255,255,0.2)" stroke-dasharray="4,4" stroke-width="1.5"
                          vector-effect="non-scaling-stroke" class="transition-all duration-500"/>

                    <!-- Data dots. Counter-scaled so they stay round on any viewport width. -->
                    <g v-for="(p, idx) in points" :key="'dot-group-' + idx"
                       class="cursor-pointer"
                       @mouseenter="hoveredPoint = { ...p, index: idx }"
                       @mouseleave="hoveredPoint = null"
                       @touchstart.passive="hoveredPoint = { ...p, index: idx }">
                        <g :transform="`translate(${p.x} ${p.y}) scale(${dotScaleX} 1)`">
                            <!-- Invisible hit target -->
                            <circle cx="0" cy="0" r="16" fill="transparent" />
                            <!-- Visible point -->
                            <circle cx="0" cy="0" :r="hoveredPoint?.index === idx ? 5.5 : 3.5"
                                    :fill="hoveredPoint?.index === idx ? '#34d399' : '#10b981'"
                                    stroke="#0b171c" stroke-width="1.5" vector-effect="non-scaling-stroke"
                                    class="transition-all duration-200" />
                        </g>
                    </g>
                </svg>

                <!-- Floating tooltip, clamped inside the card on every viewport -->
                <div v-if="hoveredPoint"
                     class="absolute z-50 pointer-events-none transition-all duration-150 ease-out"
                     :style="tooltipStyle">
                    <div class="glass-card p-2.5 sm:p-3 shadow-2xl border border-white/20 bg-dark-900/95 backdrop-blur-md rounded-xl text-xs space-y-2 w-[min(15rem,calc(100vw-3rem))] animate-fade-in">
                        <!-- Header: date & rate -->
                        <div class="flex items-center justify-between gap-2 border-b border-white/10 pb-1.5 text-[10px] text-white/50 font-mono">
                            <span class="truncate">{{ hoveredPoint.collected_at }}</span>
                            <span class="text-emerald-400 font-bold whitespace-nowrap">{{ priceLabel }}: {{ hoveredPoint.price }}</span>
                        </div>

                        <!-- Exchange details -->
                        <div class="flex items-center justify-between gap-1.5 py-1.5 bg-white/5 rounded-lg px-2 border border-white/5">
                            <div class="flex items-center gap-1 min-w-0">
                                <img :alt="itemName" :src="getResourceIcon(itemId)" @error="handleIconError($event, itemId)" class="w-4 h-4 object-contain shrink-0" />
                                <span class="font-mono font-bold text-white text-xs whitespace-nowrap">{{ formatVolume(hoveredPoint.avg_amount) }}</span>
                                <span class="text-[10px] text-white/60 truncate" :title="itemName">{{ itemName }}</span>
                            </div>

                            <svg class="w-3.5 h-3.5 text-emerald-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                            </svg>

                            <div class="flex items-center gap-1 min-w-0">
                                <img :alt="targetName" :src="getResourceIcon(targetId)" @error="handleIconError($event, targetId)" class="w-4 h-4 object-contain shrink-0" />
                                <span class="font-mono font-bold text-emerald-400 text-xs whitespace-nowrap">{{ formatVolume(hoveredPoint.avg_target_amount) }}</span>
                                <span class="text-[10px] text-emerald-400/80 truncate" :title="targetName">{{ targetName }}</span>
                            </div>
                        </div>

                        <!-- Point stats -->
                        <div class="flex flex-wrap items-center justify-between gap-x-2 gap-y-0.5 text-[10px] text-white/40 font-mono pt-0.5">
                            <span class="whitespace-nowrap">{{ offersLabel }} <strong class="text-white/80">{{ hoveredPoint.offers_count }}</strong></span>
                            <span class="whitespace-nowrap">{{ sellersLabel }} <strong class="text-white/80">{{ hoveredPoint.sellers_count }}</strong></span>
                            <span class="whitespace-nowrap">{{ volLabel }} <strong class="text-white/80">{{ formatVolume(hoveredPoint.volume) }}</strong></span>
                        </div>
                    </div>
                </div>

                <!-- X axis date labels -->
                <div v-if="history.length === 1" class="flex justify-center text-[8px] sm:text-[9px] text-white/30 px-2 sm:px-9 mt-1 font-mono">
                    <span>{{ history[0]?.collected_at }}</span>
                </div>
                <div v-else class="flex justify-between gap-1 text-[8px] sm:text-[9px] text-white/30 px-2 sm:px-9 mt-1 font-mono">
                    <span class="truncate">{{ history[0]?.collected_at }}</span>
                    <span class="truncate hidden xs:inline">{{ history[Math.floor(history.length / 2)]?.collected_at }}</span>
                    <span class="truncate">{{ history[history.length - 1]?.collected_at }}</span>
                </div>
            </template>
            <div v-else class="absolute inset-0 flex items-center justify-center text-xs text-white/20 text-center px-4">
                {{ emptyLabel }}
            </div>
        </div>
    </div>
</template>

<script>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import { t } from '../../lang';
import { buildChartPoints, chartLinePath, chartAreaPath, VB_W, VB_H, PAD_L, PAD_R } from './chartGeometry';

export default {
    name: 'MarketPriceChart',
    props: {
        history: { type: Array, default: () => [] },
        stats: { type: Object, default: null },
        periods: { type: Array, default: () => [] },
        selectedPeriod: { type: String, default: 'all' },
        title: { type: String, default: '' },
        itemId: { type: [String, Number], default: '' },
        targetId: { type: [String, Number], default: '' },
        itemName: { type: String, default: '' },
        targetName: { type: String, default: '' },
        gradientId: { type: String, default: 'priceGrad' },
        getResourceIcon: { type: Function, required: true },
        handleIconError: { type: Function, required: true },
        formatVolume: { type: Function, required: true }
    },
    emits: ['change-period'],
    setup(props) {
        const hoveredPoint = ref(null);
        const chartBox = ref(null);
        const boxWidth = ref(VB_W);

        let observer = null;
        onMounted(() => {
            if (!chartBox.value) return;
            const measure = () => {
                boxWidth.value = chartBox.value?.clientWidth || VB_W;
            };
            measure();
            if (typeof ResizeObserver !== 'undefined') {
                observer = new ResizeObserver(measure);
                observer.observe(chartBox.value);
            } else {
                window.addEventListener('resize', measure);
            }
        });
        onBeforeUnmount(() => {
            if (observer) observer.disconnect();
        });

        // With preserveAspectRatio="none" the viewBox is stretched horizontally.
        // Counter-scale text and dots so they never look squashed on phones.
        const scaleX = computed(() => boxWidth.value / VB_W);
        const dotScaleX = computed(() => (scaleX.value > 0 ? 1 / scaleX.value : 1));
        const labelTransform = computed(() => `scale(${dotScaleX.value} 1)`);
        const labelX = computed(() => (PAD_L - 5) * scaleX.value);

        const points = computed(() => buildChartPoints(props.history));
        const priceLinePath = computed(() => chartLinePath(points.value, 'y'));
        const priceAreaPath = computed(() => chartAreaPath(points.value, 'y'));

        const meanY = computed(() => {
            if (!props.stats || !props.stats.average || props.history.length === 0) return 120;
            const prices = props.history.map(h => h.price);
            const maxPrice = Math.max(...prices) || 1;
            const minPrice = Math.min(...prices) || 0;
            const priceDiff = (maxPrice - minPrice) || 1;
            const calcY = maxPrice === minPrice
                ? 120
                : VB_H - ((props.stats.average - minPrice) / priceDiff) * 180 - 10;
            return Math.max(20, Math.min(210, calcY));
        });

        const fmt = (val) => (val >= 1000 ? props.formatVolume(val) : Number(val).toFixed(2));
        const maxPriceLabel = computed(() => (props.history.length ? fmt(Math.max(...props.history.map(h => h.price))) : ''));
        const minPriceLabel = computed(() => (props.history.length ? fmt(Math.min(...props.history.map(h => h.price))) : ''));
        const midPriceLabel = computed(() => {
            if (!props.history.length) return '';
            const prices = props.history.map(h => h.price);
            return fmt((Math.max(...prices) + Math.min(...prices)) / 2);
        });

        // Tooltip is clamped so it can never overflow the card on mobile.
        const tooltipStyle = computed(() => {
            if (!hoveredPoint.value) return {};
            const xRatio = hoveredPoint.value.x / VB_W;
            const yRatio = hoveredPoint.value.y / VB_H;
            const above = yRatio > 0.45;
            return {
                left: `clamp(0px, ${(xRatio * 100).toFixed(2)}% - 50%, 100% - min(15rem, 100vw - 3rem))`,
                top: `${(yRatio * 100).toFixed(2)}%`,
                transform: above ? 'translateY(calc(-100% - 12px))' : 'translateY(12px)'
            };
        });

        return {
            VB_W, VB_H, PAD_L, PAD_R,
            chartBox,
            hoveredPoint,
            points,
            priceLinePath,
            priceAreaPath,
            meanY,
            maxPriceLabel,
            minPriceLabel,
            midPriceLabel,
            dotScaleX,
            labelTransform,
            labelX,
            tooltipStyle,
            averageLabel: t('market.average_price'),
            meanLabel: t('market.global_mean'),
            priceLabel: t('market.price'),
            offersLabel: t('market.offers'),
            sellersLabel: t('market.sellers'),
            volLabel: t('market.vol'),
            emptyLabel: t('market.not_enough_history')
        };
    }
};
</script>

<style scoped>
circle {
  transition: r 0.2s ease, fill 0.2s ease;
}
</style>
