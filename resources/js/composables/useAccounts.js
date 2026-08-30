import { ref } from 'vue';
import { accountsApi } from '../services/api/accounts';
import { useAsyncResource } from './useAsyncResource';

export function useAccounts() {
    const accounts = ref([]);
    const { loading, error, execute: loadAccounts } = useAsyncResource(async () => {
        const res = await accountsApi.fetchAccounts();
        accounts.value = res.data || res.accounts || [];
        return res;
    });

    async function createAccount(payload) {
        const res = await accountsApi.createAccount(payload);
        await loadAccounts();
        return res;
    }

    async function deleteAccount(id) {
        const res = await accountsApi.deleteAccount(id);
        accounts.value = accounts.value.filter(a => a.id !== id);
        return res;
    }

    async function syncAccount(id) {
        const res = await accountsApi.syncAccount(id);
        await loadAccounts();
        return res;
    }

    return {
        accounts,
        loading,
        error,
        loadAccounts,
        createAccount,
        deleteAccount,
        syncAccount,
    };
}
