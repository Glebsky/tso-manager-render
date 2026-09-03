<template>
    <div>
        <!-- Desktop / tablet: classic table -->
        <div :class="[tableVisibilityClass, 'overflow-x-auto']">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-white/5 text-[10px] font-semibold text-white/30 uppercase tracking-wider">
                        <th v-for="col in columns" :key="'th-' + col.key"
                            class="py-3 px-3 lg:px-4 whitespace-nowrap"
                            :class="col.align === 'right' ? 'text-right' : ''">
                            {{ col.label }}
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5 text-sm text-white/70">
                    <tr v-for="(row, index) in rows" :key="'tr-' + index"
                        class="hover:bg-white/[0.03] transition-colors duration-300">
                        <td v-for="col in columns" :key="'td-' + col.key"
                            class="py-3 px-3 lg:px-4"
                            :class="[col.align === 'right' ? 'text-right' : '', col.cellClass || '']">
                            <slot :name="'cell-' + col.key" :row="row" :index="index">{{ row[col.key] }}</slot>
                        </td>
                    </tr>
                    <tr v-if="rows.length === 0">
                        <td :colspan="columns.length" class="py-8 text-center text-white/20">
                            {{ emptyText }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Mobile / adaptive: each row becomes a card, so no horizontal scrolling is needed -->
        <div :class="[cardVisibilityClass, cardLayoutClass]">
            <div v-for="(row, index) in rows" :key="'card-' + index"
                 class="rounded-xl border border-white/5 bg-white/[0.02] p-3 sm:p-4 space-y-2 hover:border-white/10 transition-colors">
                <!-- First column acts as the card title -->
                <div class="text-sm font-semibold text-white wrap-anywhere pb-1.5 border-b border-white/5">
                    <slot :name="'cell-' + columns[0].key" :row="row" :index="index">{{ row[columns[0].key] }}</slot>
                </div>
                <div v-for="col in columns.slice(1)" :key="'card-row-' + col.key"
                     class="flex items-start justify-between gap-2.5 text-xs py-0.5">
                    <span class="text-white/35 uppercase tracking-wider text-[10px] font-semibold shrink-0 pt-0.5">
                        {{ col.label }}
                    </span>
                    <div class="text-right text-white/70 min-w-0 flex-1 flex items-center justify-end">
                        <slot :name="'cell-' + col.key" :row="row" :index="index">{{ row[col.key] }}</slot>
                    </div>
                </div>
            </div>
            <div v-if="rows.length === 0" class="py-8 text-center text-white/20 text-xs col-span-full">
                {{ emptyText }}
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';

/**
 * Responsive market table.
 *
 * Can render as a standard table on larger screens or collapse into adaptive
 * cards below the configurable breakpoint (sm, md, lg, xl).
 *
 * Columns: [{ key, label, align?: 'right', cellClass?: string }]
 * Cells are customised through the `cell-<key>` slots.
 */
const props = defineProps({
    columns: { type: Array, required: true },
    rows: { type: Array, default: () => [] },
    emptyText: { type: String, default: '' },
    breakpoint: {
        type: String,
        default: 'sm',
        validator: (v) => ['sm', 'md', 'lg', 'xl'].includes(v),
    },
    cardGrid: { type: Boolean, default: false },
});

const tableVisibilityClass = computed(() => {
    switch (props.breakpoint) {
        case 'md': return 'hidden md:block';
        case 'lg': return 'hidden lg:block';
        case 'xl': return 'hidden xl:block';
        default: return 'hidden sm:block';
    }
});

const cardVisibilityClass = computed(() => {
    switch (props.breakpoint) {
        case 'md': return 'md:hidden';
        case 'lg': return 'lg:hidden';
        case 'xl': return 'xl:hidden';
        default: return 'sm:hidden';
    }
});

const cardLayoutClass = computed(() => {
    if (props.cardGrid) {
        return 'grid grid-cols-1 md:grid-cols-2 gap-3';
    }
    return 'space-y-2.5';
});
</script>
