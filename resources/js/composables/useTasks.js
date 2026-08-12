import { ref } from 'vue';
import { tasksApi } from '../services/api/tasks';
import { useAsyncResource } from './useAsyncResource';

export function useTasks() {
    const tasks = ref([]);
    const accounts = ref([]);
    const meta = ref(null);

    const { loading, error, execute: loadTasks } = useAsyncResource(async (params = {}) => {
        const res = await tasksApi.fetchTasks(params);
        tasks.value = res.data || res.tasks || [];
        if (res.accounts) {
            accounts.value = res.accounts;
        }
        if (res.meta) {
            meta.value = res.meta;
        }
        return res;
    });

    async function createTask(payload) {
        const res = await tasksApi.createTask(payload);
        await loadTasks();
        return res;
    }

    async function toggleTask(id) {
        const res = await tasksApi.toggleTask(id);
        const updated = res.data || res.task;
        if (updated) {
            const idx = tasks.value.findIndex(t => t.id === id);
            if (idx !== -1) {
                tasks.value[idx] = updated;
            }
        }
        return res;
    }

    async function deleteTask(id) {
        const res = await tasksApi.deleteTask(id);
        tasks.value = tasks.value.filter(t => t.id !== id);
        return res;
    }

    async function executeTask(id) {
        return await tasksApi.executeTask(id);
    }

    return {
        tasks,
        accounts,
        meta,
        loading,
        error,
        loadTasks,
        createTask,
        toggleTask,
        deleteTask,
        executeTask,
    };
}
