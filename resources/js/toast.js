import { ref } from 'vue';

export const toasts = ref([]);

export function showToast(message, type = 'success', duration = 5000) {
    const id = Date.now() + Math.random();
    toasts.value.push({ id, message, type });

    setTimeout(() => {
        toasts.value = toasts.value.filter(t => t.id !== id);
    }, duration);
}
