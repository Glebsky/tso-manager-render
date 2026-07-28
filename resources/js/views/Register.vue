<template>
    <div class="relative min-h-dvh flex items-center justify-center overflow-hidden font-sans text-white antialiased bg-dark-950">
        <!-- Background ambient effects (replicating the original design exactly) -->
        <div class="liquid-background" aria-hidden="true">
            <div class="liquid-orb liquid-orb--one"></div>
            <div class="liquid-orb liquid-orb--two"></div>
            <div class="liquid-orb liquid-orb--three"></div>
        </div>

        <!-- Language Switcher in Upper Right Corner -->
        <div class="absolute top-5 right-5 z-50">
            <LanguageSwitcher />
        </div>

        <main class="relative z-10 w-full max-w-md px-4 py-8 sm:px-6">
            <div class="liquid-card px-5 py-6 sm:px-8 sm:py-8">
                <header class="mb-6 text-center">
                    <div class="liquid-icon mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl overflow-hidden p-0.5">
                        <img :src="'/android-chrome-512x512.png'" alt="TSO Manager" class="w-full h-full object-cover rounded-[14px]" />
                    </div>

                    <h1 class="text-2xl font-bold tracking-tight text-white">
                        {{ t('register.title') }}
                    </h1>

                    <p class="mt-2 text-sm text-white/55">
                        {{ t('register.subtitle') }}
                    </p>
                </header>

                <div class="liquid-notice mb-6 flex gap-3 rounded-xl px-4 py-3">
                    <svg
                        class="mt-0.5 h-5 w-5 shrink-0 text-amber-300"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke-width="1.7"
                        stroke="currentColor"
                        aria-hidden="true"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M11.25 11.25 12 10.5m0 0 .75.75M12 10.5v4.5m9-3a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"
                        />
                    </svg>

                    <p class="text-sm leading-5 text-amber-100/80">
                        {{ t('register.notice') }}
                    </p>
                </div>

                <form @submit.prevent="submit" class="space-y-5">
                    <div>
                        <label
                            for="name"
                            class="mb-2 block text-sm font-medium text-white/70"
                        >
                            {{ t('register.name') }}
                        </label>

                        <input
                            id="name"
                            v-model="form.name"
                            type="text"
                            required
                            autofocus
                            autocomplete="name"
                            :placeholder="t('register.name_placeholder')"
                            class="liquid-input"
                            :class="{ 'liquid-input--error': errors.name }"
                        >

                        <p v-if="errors.name" class="mt-2 text-sm text-red-300">{{ errors.name[0] }}</p>
                    </div>

                    <div>
                        <label
                            for="email"
                            class="mb-2 block text-sm font-medium text-white/70"
                        >
                            {{ t('register.email') }}
                        </label>

                        <input
                            id="email"
                            v-model="form.email"
                            type="email"
                            required
                            autocomplete="email"
                            inputmode="email"
                            placeholder="admin@example.com"
                            class="liquid-input"
                            :class="{ 'liquid-input--error': errors.email }"
                        >

                        <p v-if="errors.email" class="mt-2 text-sm text-red-300">{{ errors.email[0] }}</p>
                    </div>

                    <div>
                        <label
                            for="password"
                            class="mb-2 block text-sm font-medium text-white/70"
                        >
                            {{ t('register.password') }}
                        </label>

                        <input
                            id="password"
                            v-model="form.password"
                            type="password"
                            required
                            autocomplete="new-password"
                            :placeholder="t('register.password_placeholder')"
                            class="liquid-input"
                            :class="{ 'liquid-input--error': errors.password }"
                        >

                        <p v-if="errors.password" class="mt-2 text-sm text-red-300">{{ errors.password[0] }}</p>
                    </div>

                    <div>
                        <label
                            for="password_confirmation"
                            class="mb-2 block text-sm font-medium text-white/70"
                        >
                            {{ t('register.password_confirm') }}
                        </label>

                        <input
                            id="password_confirmation"
                            v-model="form.password_confirmation"
                            type="password"
                            required
                            autocomplete="new-password"
                            :placeholder="t('register.password_confirm_placeholder')"
                            class="liquid-input"
                        >
                    </div>

                    <button
                        type="submit"
                        :disabled="loading"
                        class="liquid-button flex w-full items-center justify-center gap-2 px-4 py-3 text-sm font-semibold text-white disabled:opacity-50"
                    >
                        <span class="relative z-10">{{ loading ? t('register.creating') : t('register.submit') }}</span>

                        <svg
                            v-if="!loading"
                            class="relative z-10 h-4 w-4"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke-width="2"
                            stroke="currentColor"
                            aria-hidden="true"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"
                            />
                        </svg>
                    </button>
                </form>
            </div>

            <p class="mt-5 text-center text-xs text-white/30">
                TSO Manager · {{ t('register.footer') }}
            </p>
        </main>
    </div>
</template>

<script>
import { ref } from 'vue';
import axios from 'axios';
import LanguageSwitcher from '../components/LanguageSwitcher.vue';

export default {
    name: 'Register',
    components: {
        LanguageSwitcher
    },
    setup() {
        const form = ref({
            name: '',
            email: '',
            password: '',
            password_confirmation: ''
        });

        const errors = ref({});
        const loading = ref(false);

        const submit = async () => {
            if (loading.value) return;

            loading.value = true;
            errors.value = {};

            try {
                const res = await axios.post('/admin/register', form.value);
                if (res.data && res.data.redirect) {
                    window.location.assign(res.data.redirect);
                } else {
                    window.location.assign('/admin');
                }
            } catch (err) {
                if (err.response && err.response.status === 422) {
                    errors.value = err.response.data.errors || {};
                } else {
                    console.error('Registration failed:', err);
                }
            } finally {
                loading.value = false;
            }
        };

        return {
            form,
            errors,
            loading,
            submit
        };
    }
};
</script>

<style scoped>
/* Scoped liquid background & button styling from register.blade.php */
.liquid-background {
    position: fixed;
    inset: 0;
    overflow: hidden;
    pointer-events: none;
}

.liquid-orb {
    position: absolute;
    border-radius: 9999px;
    filter: blur(10px);
    opacity: 0.7;
}

.liquid-orb--one {
    top: -9rem;
    left: -7rem;
    width: 24rem;
    height: 24rem;
    background: linear-gradient(
        135deg,
        rgba(52, 211, 153, 0.32),
        rgba(13, 148, 136, 0.04)
    );
}

.liquid-orb--two {
    right: -8rem;
    bottom: -10rem;
    width: 28rem;
    height: 28rem;
    background: linear-gradient(
        135deg,
        rgba(45, 212, 191, 0.22),
        rgba(16, 185, 129, 0.03)
    );
}

.liquid-orb--three {
    top: 40%;
    left: 55%;
    width: 12rem;
    height: 12rem;
    background: rgba(255, 255, 255, 0.04);
    filter: blur(24px);
}

.liquid-card {
    position: relative;
    isolation: isolate;
    overflow: hidden;
    border: 1px solid rgba(255, 255, 255, 0.16);
    border-radius: 1.75rem;
    background:
        linear-gradient(
            145deg,
            rgba(255, 255, 255, 0.13),
            rgba(255, 255, 255, 0.045)
        );
    box-shadow:
        0 32px 80px rgba(0, 0, 0, 0.42),
        inset 0 1px 0 rgba(255, 255, 255, 0.22),
        inset 0 -1px 0 rgba(255, 255, 255, 0.04);
    backdrop-filter: blur(28px) saturate(150%);
    -webkit-backdrop-filter: blur(28px) saturate(150%);
}

.liquid-card::before {
    content: "";
    position: absolute;
    z-index: -1;
    top: -7rem;
    left: -6rem;
    width: 17rem;
    height: 17rem;
    border-radius: 9999px;
    background: rgba(52, 211, 153, 0.13);
    filter: blur(35px);
}

.liquid-card::after {
    content: "";
    position: absolute;
    top: 0;
    left: 12%;
    width: 76%;
    height: 1px;
    background: linear-gradient(
        90deg,
        transparent,
        rgba(255, 255, 255, 0.7),
        transparent
    );
}

.liquid-icon {
    border: 1px solid rgba(255, 255, 255, 0.22);
    background:
        linear-gradient(
            145deg,
            rgba(52, 211, 153, 0.85),
            rgba(13, 148, 136, 0.62)
        );
    box-shadow:
        0 16px 40px rgba(16, 185, 129, 0.24),
        inset 0 1px 0 rgba(255, 255, 255, 0.45);
    backdrop-filter: blur(16px);
}

.liquid-notice {
    border: 1px solid rgba(251, 191, 36, 0.18);
    background: linear-gradient(
        135deg,
        rgba(251, 191, 36, 0.1),
        rgba(255, 255, 255, 0.035)
    );
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.08);
    backdrop-filter: blur(16px);
}

.liquid-input {
    width: 100%;
    min-height: 3rem;
    border: 1px solid rgba(255, 255, 255, 0.13);
    border-radius: 0.875rem;
    padding: 0.75rem 1rem;
    color: #ffffff;
    caret-color: #6ee7b7;
    outline: none;
    background:
        linear-gradient(
            145deg,
            rgba(255, 255, 255, 0.09),
            rgba(255, 255, 255, 0.035)
        );
    box-shadow:
        inset 0 1px 0 rgba(255, 255, 255, 0.08),
        0 1px 2px rgba(0, 0, 0, 0.08);
    transition:
        border-color 180ms ease,
        background-color 180ms ease,
        box-shadow 180ms ease,
        transform 180ms ease;
}

.liquid-input:hover {
    border-color: rgba(255, 255, 255, 0.23);
    background-color: rgba(255, 255, 255, 0.08);
}

.liquid-input:focus {
    border-color: rgba(110, 231, 183, 0.7);
    box-shadow:
        0 0 0 4px rgba(16, 185, 129, 0.12),
        inset 0 1px 0 rgba(255, 255, 255, 0.12);
    transform: translateY(-1px);
}

.liquid-input:-webkit-autofill,
.liquid-input:-webkit-autofill:hover,
.liquid-input:-webkit-autofill:focus {
    -webkit-text-fill-color: #ffffff;
    transition: background-color 9999s ease-in-out 0s;
    box-shadow:
        0 0 0 1000px rgba(11, 28, 24, 0.9) inset,
        0 0 0 4px rgba(16, 185, 129, 0.1);
}

.liquid-input--error {
    border-color: rgba(248, 113, 113, 0.65);
}

.liquid-button {
    position: relative;
    overflow: hidden;
    min-height: 3.25rem;
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 0.95rem;
    background: linear-gradient(110deg, #10b981, #0d9488);
    box-shadow:
        0 14px 34px rgba(16, 185, 129, 0.24),
        inset 0 1px 0 rgba(255, 255, 255, 0.35);
    transition:
        transform 180ms ease,
        box-shadow 180ms ease,
        filter 180ms ease;
}

.liquid-button::before {
    content: "";
    position: absolute;
    top: 0;
    left: -80%;
    width: 50%;
    height: 100%;
    transform: skewX(-22deg);
    background: linear-gradient(
        90deg,
        transparent,
        rgba(255, 255, 255, 0.28),
        transparent
    );
    transition: left 500ms ease;
}

.liquid-button:hover {
    transform: translateY(-2px);
    filter: brightness(1.07);
    box-shadow:
        0 20px 42px rgba(16, 185, 129, 0.34),
        inset 0 1px 0 rgba(255, 255, 255, 0.4);
}

.liquid-button:hover::before {
    left: 130%;
}

.liquid-button:active {
    transform: translateY(0);
}

.liquid-button:focus-visible {
    outline: 3px solid rgba(110, 231, 183, 0.35);
    outline-offset: 3px;
}

@media (max-width: 480px) {
    .liquid-card {
        border-radius: 1.4rem;
    }
}

@media (prefers-reduced-motion: reduce) {
    .liquid-input,
    .liquid-button,
    .liquid-button::before {
        transition: none;
    }
}
</style>
