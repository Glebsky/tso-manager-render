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
        const res = await http.put(`/api/tasks/${id}`, payload);
        return res.data;
    },
    async toggleTask(id) {
        const res = await http.post(`/api/tasks/${id}/toggle`);
        return res.data;
    },
    async deleteTask(id) {
        const res = await http.delete(`/api/tasks/${id}`);
        return res.data;
    },
    async executeTask(id) {
        const res = await http.post(`/api/tasks/${id}/execute`);
        return res.data;
    },
};
