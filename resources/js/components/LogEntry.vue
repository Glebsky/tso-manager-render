<template>
    <div class="flex items-start gap-4 p-4 hover:bg-white/[0.01] transition-all duration-200">
        <!-- Level Badge -->
        <div class="flex-shrink-0 mt-0.5">
            <span class="badge" :class="badgeClass">
                {{ levelLabel }}
            </span>
        </div>

        <!-- Message & Info -->
        <div class="flex-1 min-w-0">
            <p class="text-sm text-white/80 leading-relaxed">
                {{ log.message }}
            </p>
            <div class="flex items-center gap-2 mt-1 text-xs text-white/30">
                <span class="font-medium text-white/40">
                    {{ log.account ? (log.account.nickname || log.account.username) : t('common.system') }}
                </span>
                <span>·</span>
                <span>{{ formattedDate }}</span>
            </div>
        </div>
    </div>
</template>

<script>
import { computed } from 'vue';
import { t } from '../lang';

export default {
    name: 'LogEntry',
    props: {
        log: {
            type: Object,
            required: true
        }
    },
    setup(props) {
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

        return {
            badgeClass,
            levelLabel,
            formattedDate
        };
    }
};
</script>
