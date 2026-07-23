<template>
    <svg class="animate-spin shrink-0" :class="sizeClass" fill="none" viewBox="0 0 24 24" role="status" aria-label="Loading">
        <circle class="opacity-20" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
    </svg>
</template>

<script>
/**
 * Unified loading spinner.
 * Inherits its color from the parent (currentColor), so it fits
 * buttons, overlays and inline hints without extra props.
 */
export default {
    name: 'Spinner',
    props: {
        // xs | sm | md | lg
        size: {
            type: String,
            default: 'md'
        }
    },
    computed: {
        sizeClass() {
            return {
                xs: 'w-3 h-3',
                sm: 'w-4 h-4',
                md: 'w-6 h-6',
                lg: 'w-10 h-10'
            }[this.size] || 'w-6 h-6';
        }
    }
};
</script>
