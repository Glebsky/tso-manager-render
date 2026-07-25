<template>
    <div class="relative inline-block text-left" ref="dropdownRef">
        <button
            type="button"
            @click="isOpen = !isOpen"
            class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-xl bg-white/5 border border-white/10 text-xs font-semibold text-white/80 hover:text-white hover:bg-white/10 hover:border-white/20 transition-all duration-200 shadow-sm"
            :aria-expanded="isOpen"
            title="Switch Language / Сменить язык / Змінити мову"
        >
            <span class="text-sm leading-none">{{ currentLangInfo.flag }}</span>
            <span class="font-mono text-[11px] uppercase tracking-wider">{{ currentLangInfo.code }}</span>
            <svg
                class="w-3.5 h-3.5 text-white/40 transition-transform duration-200"
                :class="{ 'rotate-180': isOpen }"
                fill="none"
                viewBox="0 0 24 24"
                stroke-width="2"
                stroke="currentColor"
            >
                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
            </svg>
        </button>

        <transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="transform scale-95 opacity-0"
            enter-to-class="transform scale-100 opacity-100"
            leave-active-class="transition duration-100 ease-in"
            leave-from-class="transform scale-100 opacity-100"
            leave-to-class="transform scale-95 opacity-0"
        >
            <div
                v-if="isOpen"
                class="absolute right-0 mt-2 w-36 rounded-xl bg-dark-900/95 border border-white/10 shadow-2xl backdrop-blur-xl py-1.5 z-[100] overflow-hidden"
            >
                <button
                    v-for="lang in availableLanguages"
                    :key="lang.code"
                    type="button"
                    @click="selectLanguage(lang.code)"
                    class="w-full px-3 py-2 text-xs flex items-center justify-between transition-colors hover:bg-white/10 text-left"
                    :class="lang.code === currentLocale ? 'text-emerald-400 font-semibold bg-emerald-500/10' : 'text-white/80 hover:text-white'"
                >
                    <span class="flex items-center gap-2">
                        <span class="text-base leading-none">{{ lang.flag }}</span>
                        <span>{{ lang.name }}</span>
                    </span>
                    <span v-if="lang.code === currentLocale" class="w-1.5 h-1.5 rounded-full bg-emerald-400 shadow-sm shadow-emerald-400"></span>
                </button>
            </div>
        </transition>
    </div>
</template>

<script>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { locale as activeLocale, setLocale, AVAILABLE_LOCALES } from '../lang';

export default {
    name: 'LanguageSwitcher',
    setup() {
        const isOpen = ref(false);
        const dropdownRef = ref(null);
        const currentLocale = ref(activeLocale);

        // Rendered from the single locale registry in ../lang (no duplication).
        const availableLanguages = AVAILABLE_LOCALES;

        const currentLangInfo = computed(() => {
            return availableLanguages.find(l => l.code === currentLocale.value) || availableLanguages[0];
        });

        const selectLanguage = (code) => {
            isOpen.value = false;
            if (code !== currentLocale.value) {
                setLocale(code);
            }
        };

        const handleClickOutside = (e) => {
            if (dropdownRef.value && !dropdownRef.value.contains(e.target)) {
                isOpen.value = false;
            }
        };

        onMounted(() => {
            document.addEventListener('click', handleClickOutside);
        });

        onUnmounted(() => {
            document.removeEventListener('click', handleClickOutside);
        });

        return {
            isOpen,
            dropdownRef,
            currentLocale,
            availableLanguages,
            currentLangInfo,
            selectLanguage
        };
    }
};
</script>
