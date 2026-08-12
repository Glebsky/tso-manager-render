import { ref } from 'vue';

export function useAsyncResource(asyncFn, initialData = null) {
    const data = ref(initialData);
    const loading = ref(false);
    const error = ref(null);
    let currentRequestId = 0;

    async function execute(...args) {
        const requestId = ++currentRequestId;
        loading.value = true;
        error.value = null;

        try {
            const result = await asyncFn(...args);
            if (requestId === currentRequestId) {
                data.value = result;
            }
            return result;
        } catch (err) {
            if (requestId === currentRequestId) {
                error.value = err?.response?.data?.message || err?.message || 'Request failed';
            }
            throw err;
        } finally {
            if (requestId === currentRequestId) {
                loading.value = false;
            }
        }
    }

    return {
        data,
        loading,
        error,
        execute,
    };
}
