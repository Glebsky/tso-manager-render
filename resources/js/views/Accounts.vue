<template>
    <div>
        <!-- Page Header -->
        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-3xl font-bold text-white">{{ t('accounts.title') }}</h1>
                <p class="text-white/40 mt-1">{{ t('accounts.subtitle') }}</p>
            </div>
        </div>

        <!-- Add Account Form -->
        <div class="glass-card p-4 sm:p-6 mb-8">
            <div class="flex items-center gap-3 mb-5">
                <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-emerald-500 to-teal-600 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                </div>
                <h2 class="text-base sm:text-lg font-semibold text-white">{{ t('accounts.add_new') }}</h2>
            </div>

            <form @submit.prevent="addAccount" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 items-end">
                <!-- Username -->
                <div>
                    <label class="block text-xs font-medium text-white/40 mb-2 uppercase tracking-wider">{{ t('accounts.username_label') }}</label>
                    <input type="email" required v-model="form.username" placeholder="email@domain.com" class="glass-input w-full">
                </div>

                <!-- Password -->
                <div>
                    <label class="block text-xs font-medium text-white/40 mb-2 uppercase tracking-wider">{{ t('accounts.password') }}</label>
                    <input type="password" required v-model="form.password" placeholder="••••••••" class="glass-input w-full">
                </div>

                <!-- Region & Submit -->
                <div class="flex flex-col sm:flex-row gap-3 items-end sm:col-span-2 lg:col-span-1">
                    <div class="flex-1 relative w-full">
                        <label class="block text-xs font-medium text-white/40 mb-2 uppercase tracking-wider">{{ t('accounts.region') }}</label>
                        <select required v-model="form.region" class="glass-select w-full">
                            <option value="ru" class="bg-dark-900">RU (Realm 2)</option>
                            <option value="en" class="bg-dark-900">EN (US/UK)</option>
                            <option value="de" class="bg-dark-900">DE (Germany)</option>
                            <option value="fr" class="bg-dark-900">FR (France)</option>
                            <option value="pl" class="bg-dark-900">PL (Poland)</option>
                            <option value="es" class="bg-dark-900">ES (Spain)</option>
                            <option value="it" class="bg-dark-900">IT (Italy)</option>
                            <option value="nl" class="bg-dark-900">NL (Netherlands)</option>
                            <option value="cz" class="bg-dark-900">CZ (Czech Republic)</option>
                            <option value="pt" class="bg-dark-900">PT (Portugal)</option>
                            <option value="br" class="bg-dark-900">BR (Brazil)</option>
                            <option value="ro" class="bg-dark-900">RO (Romania)</option>
                            <option value="gr" class="bg-dark-900">GR (Greece)</option>
                            <option value="tr" class="bg-dark-900">TR (Turkey)</option>
                        </select>
                        <div class="absolute inset-y-0 right-3 flex items-center pointer-events-none mt-6">
                            <svg class="w-4 h-4 text-white/30" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                            </svg>
                        </div>
                    </div>

                    <button type="submit" :disabled="submitting" class="btn-primary w-full sm:w-auto px-6 h-[46px] flex items-center justify-center gap-2 shrink-0">
                        <svg v-if="!submitting" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        <svg v-else class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182" />
                        </svg>
                        <span>{{ t('accounts.add') }}</span>
                    </button>
                </div>
            </form>
        </div>

        <!-- Accounts Listing -->
        <div>
            <h2 class="text-base sm:text-lg font-semibold text-white flex items-center gap-2 mb-5">
                <svg class="w-5 h-5 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                </svg>
                <span>{{ t('accounts.registered') }}</span>
                <span class="badge badge-neutral text-[10px]">{{ accounts.length }}</span>
            </h2>

            <!-- Skeleton placeholders while accounts are loading -->
            <div v-if="loading && accounts.length === 0" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 sm:gap-6">
                <div v-for="i in 3" :key="'acc-skeleton-' + i" class="glass-card overflow-hidden">
                    <div class="h-[3px] skeleton"></div>
                    <div class="p-5">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="w-10 h-10 rounded-xl skeleton flex-shrink-0"></div>
                            <div class="flex-1 min-w-0">
                                <div class="w-24 h-4 rounded skeleton mb-2"></div>
                                <div class="w-32 h-3 rounded skeleton"></div>
                            </div>
                            <div class="w-16 h-5 rounded-full skeleton"></div>
                        </div>
                        <div class="flex gap-2 mb-4">
                            <div class="w-20 h-5 rounded-full skeleton"></div>
                            <div class="w-16 h-5 rounded-full skeleton"></div>
                        </div>
                        <div class="flex gap-2">
                            <div class="w-20 h-8 rounded-lg skeleton"></div>
                            <div class="w-24 h-8 rounded-lg skeleton"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div v-else-if="accounts.length > 0" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 sm:gap-6">
                <account-card v-for="acc in accounts" :key="acc.id" :account="acc"
                              @sync-success="loadAccounts" @delete-success="loadAccounts" @action-success="loadAccounts" />
            </div>
            <div v-else class="glass-card p-8 sm:p-12 text-center">
                <p class="text-white/30 text-xs sm:text-sm">{{ t('accounts.empty') }}</p>
            </div>
        </div>
    </div>
</template>

<script>
import { ref, onMounted } from 'vue';
import { t } from '../lang';
import { accountsApi } from '../services/api/accounts';
import AccountCard from '../components/AccountCard.vue';
import { showToast } from '../toast';

export default {
    name: 'Accounts',
    components: { AccountCard },
    setup() {
        const accounts = ref([]);
        const loading = ref(true);
        const submitting = ref(false);
        const form = ref({
            username: '',
            password: '',
            region: 'ru'
        });

        const loadAccounts = async () => {
            if (accounts.value.length === 0) loading.value = true;
            try {
                const res = await accountsApi.fetchAccounts();
                accounts.value = res.data || res.accounts || [];
            } catch (e) {
                showToast(t('accounts.load_failed'), 'error');
            } finally {
                loading.value = false;
            }
        };

        const addAccount = async () => {
            submitting.value = true;
            try {
                const res = await accountsApi.createAccount(form.value);
                if (res.data || res.success) {
                    showToast(t('accounts.added'));
                    form.value.username = '';
                    form.value.password = '';
                    loadAccounts();
                }
            } catch (e) {
                showToast(e.response?.data?.message || t('accounts.add_failed'), 'error');
            } finally {
                submitting.value = false;
            }
        };

        onMounted(() => {
            loadAccounts();
        });

        return {
            accounts,
            loading,
            form,
            submitting,
            loadAccounts,
            addAccount
        };
    }
};
</script>
