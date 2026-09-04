import windowAxios from 'axios';

const http = window.axios || windowAxios;

export const tasksApi = {
    async fetchTasks(params = {}) {
        const res = await http.get('/api/tasks', { params });
        return res.data;
    },
    async createTask(payload) {
        const res = await http.post('/api/tasks', payload);
        return res.data;
    },
    async updateTask(id, payload) {
        if (!id || id === 'undefined') throw new Error('Task ID is required');
        const res = await http.put(`/api/tasks/${id}`, payload);
        return res.data;
    },
    async toggleTask(id) {
        if (!id || id === 'undefined') throw new Error('Task ID is required');
        const res = await http.post(`/api/tasks/${id}/toggle`);
        return res.data;
    },
    async deleteTask(id) {
        if (!id || id === 'undefined') throw new Error('Task ID is required');
        const res = await http.delete(`/api/tasks/${id}`);
        return res.data;
    },
    async duplicateTask(id) {
        if (!id || id === 'undefined') throw new Error('Task ID is required');
        const res = await http.post(`/api/tasks/${id}/duplicate`);
        return res.data;
    },
    async reorderTasks(taskIds) {
        if (!Array.isArray(taskIds)) throw new Error('taskIds must be an array');
        const res = await http.post('/api/tasks/reorder', { task_ids: taskIds });
        return res.data;
    },
    async executeTask(id) {
        if (!id || id === 'undefined') throw new Error('Task ID is required');
        const res = await http.post(`/api/tasks/${id}/execute`);
        return res.data;
    },
};
