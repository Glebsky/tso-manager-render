<template>
    <div class="flex flex-col sm:flex-row items-start gap-1.5 sm:gap-4 p-3 sm:p-4 hover:bg-white/[0.01] transition-all duration-200">
        <!-- Level Badge -->
        <div class="flex-shrink-0 sm:mt-0.5">
            <span class="badge text-[10px] sm:text-xs py-0.5 px-2 font-semibold uppercase" :class="badgeClass">
                {{ levelLabel }}
            </span>
        </div>

        <!-- Message & Info -->
        <div class="flex-1 min-w-0 w-full">
            <p class="text-[13px] sm:text-sm text-white/80 leading-relaxed wrap-anywhere">
                {{ log.message }}
            </p>
            <div class="flex flex-wrap items-center gap-x-2 gap-y-0.5 mt-1 text-[11px] sm:text-xs text-white/30">
                <span class="font-medium text-white/40 wrap-anywhere">
                    {{ log.account ? (log.account.nickname || log.account.username) : t('common.system') }}
                </span>
                <span class="hidden xs:inline">·</span>
                <span class="whitespace-nowrap">{{ formattedDate }}</span>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { t } from '../lang';

const props = defineProps({
    log: {
        type: Object,
        required: true
    }
});

const badgeClass = computed(() => {
    const classes = {
        success: 'badge-success',
        warning: 'badge-warning',
        error: 'badge-danger',
        info: 'badge-info'
    };
    return classes[props.log.level] || 'badge-neutral';
});

const levelLabel = computed(() => {
    const known = ['info', 'success', 'warning', 'error'];
    return known.includes(props.log.level) ? t('logs.level_' + props.log.level) : (props.log.level || '');
});

const formattedDate = computed(() => {
    if (!props.log.created_at) return '';
    const date = new Date(props.log.created_at);
    return date.toLocaleString();
});
</script>
