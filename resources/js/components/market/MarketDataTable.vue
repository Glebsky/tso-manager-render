<template>
    <div>
        <!-- Desktop / tablet: classic table -->
        <div class="hidden sm:block overflow-x-auto">
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

        <!-- Mobile: each row becomes a card, so no horizontal scrolling is needed -->
        <div class="sm:hidden space-y-2.5">
            <div v-for="(row, index) in rows" :key="'card-' + index"
                 class="rounded-xl border border-white/5 bg-white/[0.02] p-3 space-y-1.5">
                <!-- First column acts as the card title -->
                <div class="text-sm font-semibold text-white wrap-anywhere pb-1.5 border-b border-white/5">
                    <slot :name="'cell-' + columns[0].key" :row="row" :index="index">{{ row[columns[0].key] }}</slot>
                </div>
                <div v-for="col in columns.slice(1)" :key="'card-row-' + col.key"
                     class="flex items-start justify-between gap-3 text-xs">
                    <span class="text-white/35 uppercase tracking-wider text-[10px] font-semibold shrink-0 pt-0.5">
                        {{ col.label }}
                    </span>
                    <span class="text-right text-white/70 min-w-0 wrap-anywhere">
                        <slot :name="'cell-' + col.key" :row="row" :index="index">{{ row[col.key] }}</slot>
                    </span>
                </div>
            </div>
            <div v-if="rows.length === 0" class="py-8 text-center text-white/20 text-xs">
                {{ emptyText }}
            </div>
        </div>
    </div>
</template>

<script>
/**
 * Responsive market table.
 *
 * On >= sm it renders a normal table; below that every row collapses into a
 * label/value card so the page never needs a horizontal scrollbar on phones.
 *
 * Columns: [{ key, label, align?: 'right', cellClass?: string }]
 * Cells are customised through the `cell-<key>` slots.
 */
export default {
    name: 'MarketDataTable',
    props: {
        columns: { type: Array, required: true },
        rows: { type: Array, default: () => [] },
        emptyText: { type: String, default: '' }
    }
};
</script>
