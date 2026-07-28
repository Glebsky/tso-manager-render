<template>
    <div class="glass-card p-4 sm:p-6 transition-all duration-300">
        <!-- Header + legend -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-4">
            <h3 class="text-[13px] sm:text-sm font-semibold text-white wrap-anywhere">{{ title }}</h3>
            <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-[10px] text-white/40">
                <div class="flex items-center gap-1.5 whitespace-nowrap">
                    <span class="w-2.5 h-2.5 bg-blue-500/20 border border-blue-500 rounded-sm inline-block"></span>
                    <span>{{ sellersLabel }}</span>
                </div>
                <div class="flex items-center gap-1.5 whitespace-nowrap">
                    <span class="w-2.5 h-2.5 bg-indigo-500/20 border border-indigo-500 rounded-sm inline-block"></span>
                    <span>{{ offersLabel }}</span>
                </div>
            </div>
        </div>

        <!-- SVG Demand Chart -->
        <div ref="chartBox" class="h-56 sm:h-64 w-full relative pt-2">
            <template v-if="history.length > 0">
                <svg class="w-full h-full overflow-visible" :viewBox="`0 0 ${VB_W} ${VB_H}`" preserveAspectRatio="none">
                    <line v-for="grid in 4" :key="'grid-dy-' + grid"
                          :x1="PAD_L" :y1="20 + (grid - 1) * 50" :x2="PAD_R" :y2="20 + (grid - 1) * 50"
                          stroke="rgba(255,255,255,0.03)" stroke-width="1" vector-effect="non-scaling-stroke"/>

                    <!-- Counter-scaled axis labels -->
                    <g :transform="labelTransform">
                        <text :x="labelX" y="23" fill="rgba(255,255,255,0.35)" font-size="9" text-anchor="end" font-family="monospace">{{ maxOffersLabel }}</text>
                        <text :x="labelX" y="215" fill="rgba(255,255,255,0.35)" font-size="9" text-anchor="end" font-family="monospace">0</text>
                    </g>

                    <path :d="sellersAreaPath" fill="rgba(59, 130, 246, 0.1)" class="transition-all duration-500 ease-out"/>
                    <path :d="sellersLinePath" fill="none" stroke="#3b82f6" stroke-width="1.5" vector-effect="non-scaling-stroke"
                          stroke-linejoin="round" class="transition-all duration-500 ease-out"/>

                    <path :d="offersAreaPath" fill="rgba(99, 102, 241, 0.1)" class="transition-all duration-500 ease-out"/>
                    <path :d="offersLinePath" fill="none" stroke="#6366f1" stroke-width="1.5" vector-effect="non-scaling-stroke"
                          stroke-linejoin="round" class="transition-all duration-500 ease-out"/>

                    <!-- Volume bars, counter-scaled so they keep a constant pixel width -->
                    <g v-for="(b, idx) in points" :key="'vol-bar-' + idx" :transform="`translate(${b.x} 0) scale(${dotScaleX} 1)`">
                        <rect :x="barHalfWidth * -1" :y="b.vy" :width="barHalfWidth * 2" :height="Math.max(2, VB_H - b.vy)"
                              fill="rgba(255,255,255,0.05)" stroke="rgba(255,255,255,0.1)" stroke-width="0.5" rx="1"
                              vector-effect="non-scaling-stroke" class="transition-all duration-300"/>
                    </g>
                </svg>

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
import {
    buildChartPoints,
    chartLinePath,
    chartAreaPath,
    maxDemandValue,
    VB_W,
    VB_H,
    PAD_L,
    PAD_R
} from './chartGeometry';

export default {
    name: 'MarketDemandChart',
    props: {
        history: { type: Array, default: () => [] },
        title: { type: String, default: '' },
        formatVolume: { type: Function, required: true }
    },
    setup(props) {
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

        const scaleX = computed(() => boxWidth.value / VB_W);
        const dotScaleX = computed(() => (scaleX.value > 0 ? 1 / scaleX.value : 1));
        const labelTransform = computed(() => `scale(${dotScaleX.value} 1)`);
        const labelX = computed(() => (PAD_L - 5) * scaleX.value);

        const points = computed(() => buildChartPoints(props.history));
        const barHalfWidth = computed(() => (props.history.length === 1 ? 12 : 3));

        const sellersLinePath = computed(() => chartLinePath(points.value, 'sy'));
        const sellersAreaPath = computed(() => chartAreaPath(points.value, 'sy'));
        const offersLinePath = computed(() => chartLinePath(points.value, 'oy'));
        const offersAreaPath = computed(() => chartAreaPath(points.value, 'oy'));

        const maxOffersLabel = computed(() => {
            const maxVal = maxDemandValue(props.history);
            return maxVal >= 1000 ? props.formatVolume(maxVal) : String(maxVal);
        });

        return {
            VB_W, VB_H, PAD_L, PAD_R,
            chartBox,
            points,
            barHalfWidth,
            dotScaleX,
            labelTransform,
            labelX,
            sellersLinePath,
            sellersAreaPath,
            offersLinePath,
            offersAreaPath,
            maxOffersLabel,
            sellersLabel: t('market.sellers_count'),
            offersLabel: t('market.active_offers'),
            emptyLabel: t('market.not_enough_history')
        };
    }
};
</script>
