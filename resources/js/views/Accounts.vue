<template>
    <div>
        <!-- Page Header -->
        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-3xl font-bold text-white">Accounts</h1>
                <p class="text-white/40 mt-1">Manage game accounts, sync data, and control production</p>
            </div>
        </div>

        <!-- Add Account Form -->
        <div class="glass-card p-6 mb-8">
            <div class="flex items-center gap-3 mb-5">
                <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-emerald-500 to-teal-600 flex items-center justify-center">
                    <svg class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                </div>
                <h2 class="text-lg font-semibold text-white">Add New Account</h2>
            </div>

            <form @submit.prevent="addAccount" class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                <!-- Username -->
                <div>
                    <label class="block text-xs font-medium text-white/40 mb-2 uppercase tracking-wider">Uplay Email / Username</label>
                    <input type="email" required v-model="form.username" placeholder="email@domain.com" class="glass-input w-full">
                </div>

                <!-- Password -->
                <div>
                    <label class="block text-xs font-medium text-white/40 mb-2 uppercase tracking-wider">Password</label>
                    <input type="password" required v-model="form.password" placeholder="••••••••" class="glass-input w-full">
                </div>

                <!-- Region & Submit -->
                <div class="flex gap-3">
                    <div class="flex-1 relative">
                        <label class="block text-xs font-medium text-white/40 mb-2 uppercase tracking-wider">Region</label>
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

                    <button type="submit" :disabled="submitting" class="btn-primary px-6 h-[46px] flex items-center gap-2">
                        <svg v-if="!submitting" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        <svg v-else class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182" />
                        </svg>
                        Add
                    </button>
                </div>
            </form>
        </div>

        <!-- Accounts Listing -->
        <div>
            <h2 class="text-lg font-semibold text-white flex items-center gap-2 mb-5">
                <svg class="w-5 h-5 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                </svg>
                Registered Accounts
                <span class="badge badge-neutral text-[10px]">{{ accounts.length }}</span>
            </h2>

            <div v-if="accounts.length > 0" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
                <account-card v-for="acc in accounts" :key="acc.id" :account="acc"
                              @sync-success="loadAccounts" @delete-success="loadAccounts" @action-success="loadAccounts" />
            </div>
            <div v-else class="glass-card p-12 text-center">
                <p class="text-white/30 text-sm">No accounts registered yet.</p>
            </div>
        </div>
    </div>
</template>

<script>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import AccountCard from '../components/AccountCard.vue';
import { showToast } from '../toast';

export default {
    name: 'Accounts',
    components: { AccountCard },
    setup() {
        const accounts = ref([]);
        const submitting = ref(false);
        const form = ref({
            username: '',
            password: '',
            region: 'ru'
        });

        const loadAccounts = async () => {
            try {
                const res = await axios.get('/api/accounts');
                accounts.value = res.data || [];
            } catch (e) {
                showToast('Failed to load accounts.', 'error');
            }
        };

        const addAccount = async () => {
            submitting.value = true;
            try {
                const res = await axios.post('/api/accounts', form.value);
                if (res.data.success) {
                    showToast('Account added successfully.');
                    form.value.username = '';
                    form.value.password = '';
                    loadAccounts();
                }
            } catch (e) {
                showToast(e.response?.data?.message || 'Failed to add account.', 'error');
            } finally {
                submitting.value = false;
            }
        };

        onMounted(() => {
            loadAccounts();
        });

        return {
            accounts,
            form,
            submitting,
            loadAccounts,
            addAccount
        };
    }
};
</script>
