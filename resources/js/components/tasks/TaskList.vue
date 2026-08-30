<template>
    <div>
        <!-- Панель управления и фильтров -->
        <div class="glass-card p-3.5 sm:p-4 mb-4 sm:mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-3 sm:gap-4">
            <div class="flex items-center gap-2.5 sm:gap-3">
                <h2 class="text-sm sm:text-base font-semibold text-white">{{ t('tasks.active_series') }}</h2>
                <span class="badge badge-emerald font-mono text-[10px] sm:text-xs">{{ searchQuery || accountFilter || statusFilter ? `${filteredTasks.length} / ${tasks.length}` : tasks.length }}</span>
            </div>

            <!-- Фильтры задач -->
            <div class="flex items-center gap-2 flex-wrap w-full sm:w-auto">
                <div class="relative flex-1 sm:flex-initial">
                    <input type="text" :value="searchQuery" @input="$emit('update:searchQuery', $event.target.value)"
                           :placeholder="t('tasks.search_tasks')" :aria-label="t('tasks.search_tasks')" class="glass-input text-xs py-1.5 px-3 w-full sm:w-56">
                </div>

                <select :value="accountFilter" @change="$emit('update:accountFilter', $event.target.value)"
                        :aria-label="t('tasks.all_accounts')" class="glass-select text-xs py-1.5 px-3 flex-1 sm:flex-initial">
                    <option value="" class="bg-dark-900 text-white">{{ t('tasks.all_accounts') }}</option>
                    <option v-for="acc in accounts" :key="acc.id" :value="acc.id" class="bg-dark-900 text-white">
                        {{ acc.nickname || acc.username }}
                    </option>
                </select>

                <select :value="statusFilter" @change="$emit('update:statusFilter', $event.target.value)"
                        :aria-label="t('tasks.all_statuses')" class="glass-select text-xs py-1.5 px-3 flex-1 sm:flex-initial">
                    <option value="" class="bg-dark-900 text-white">{{ t('tasks.all_statuses') }}</option>
                    <option value="active" class="bg-dark-900 text-white">{{ t('tasks.status.active') }}</option>
                    <option value="paused" class="bg-dark-900 text-white">{{ t('tasks.status.paused') }}</option>
                </select>
            </div>
        </div>

        <!-- Список задач -->
        <div v-if="filteredTasks.length > 0" class="space-y-4">
            <slot />
        </div>

        <!-- Пустое состояние -->
        <div v-else class="glass-card p-8 sm:p-12 text-center">
            <div class="w-12 h-12 sm:w-16 sm:h-16 rounded-2xl bg-white/5 border border-white/10 flex items-center justify-center text-xl sm:text-2xl mx-auto mb-3 sm:mb-4 text-white/30">
                📋
            </div>
            <h3 class="text-base sm:text-lg font-semibold text-white mb-1">{{ t('tasks.no_tasks') }}</h3>
            <p class="text-xs sm:text-sm text-white/40 max-w-sm mx-auto">
                {{ searchQuery || accountFilter || statusFilter ? t('tasks.no_matching_tasks') : t('tasks.create_first_hint') }}
            </p>
        </div>
    </div>
</template>

<script setup>
import { t } from '../../lang';

defineProps({
    tasks: { type: Array, default: () => [] },
    filteredTasks: { type: Array, default: () => [] },
    accounts: { type: Array, default: () => [] },
    searchQuery: { type: String, default: '' },
    accountFilter: { type: [String, Number], default: '' },
    statusFilter: { type: String, default: '' }
});

defineEmits(['update:searchQuery', 'update:accountFilter', 'update:statusFilter']);
</script>
