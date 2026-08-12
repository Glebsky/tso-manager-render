<template>
    <div class="glass-card p-4 mb-6 flex flex-wrap items-center justify-between gap-4">
        <!-- Поиск товара -->
        <div class="flex items-center gap-3 flex-1 min-w-[240px]">
            <div class="relative flex-1">
                <input type="text"
                       :value="searchQuery"
                       @input="$emit('update:searchQuery', $event.target.value)"
                       :placeholder="t('market.search_placeholder')"
                       class="glass-input text-xs py-2 px-3 pl-8 w-full">
                <span class="absolute left-2.5 top-2.5 text-white/30 text-xs">🔍</span>
            </div>
        </div>

        <!-- Выбор сервера -->
        <div class="flex items-center gap-3">
            <label class="text-xs font-semibold text-white/40 uppercase tracking-wider hidden sm:inline">{{ t('market.server') }}:</label>
            <select :value="selectedServerId"
                    @change="$emit('update:selectedServerId', Number($event.target.value))"
                    class="glass-select text-xs py-2 px-3">
                <option v-for="srv in servers" :key="srv.id" :value="srv.id">
                    🌐 {{ srv.display_name || srv.server_id }} ({{ srv.account_username }})
                </option>
            </select>
        </div>
    </div>
</template>

<script setup>
import { t } from '../../lang';

defineProps({
    searchQuery: { type: String, default: '' },
    selectedServerId: { type: [Number, String], default: null },
    servers: { type: Array, default: () => [] }
});

defineEmits(['update:searchQuery', 'update:selectedServerId']);
</script>
