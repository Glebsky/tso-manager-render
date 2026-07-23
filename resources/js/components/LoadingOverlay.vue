<template>
    <transition name="fade">
        <div v-if="show" class="loading-overlay" aria-busy="true">
            <div class="flex flex-col items-center gap-3 text-emerald-400">
                <spinner :size="size" />
                <span v-if="label" class="text-xs font-medium text-white/60">{{ label }}</span>
            </div>
        </div>
    </transition>
</template>

<script>
/**
 * Non-blocking loading overlay for panels that refresh in place.
 * Keeps the old content visible (slightly dimmed and blurred) instead of
 * abruptly swapping it, which prevents layout jumps.
 * The parent container must be `relative`.
 */
import Spinner from './Spinner.vue';

export default {
    name: 'LoadingOverlay',
    components: { Spinner },
    props: {
        show: {
            type: Boolean,
            default: false
        },
        label: {
            type: String,
            default: ''
        },
        size: {
            type: String,
            default: 'md'
        }
    }
};
</script>
