import windowAxios from 'axios';

const http = window.axios || windowAxios;

export const settingsApi = {
    async fetchSettings() {
        const res = await http.get('/api/settings');
        return res.data;
    },
    async updateSettings(payload) {
        const res = await http.put('/api/settings', payload);
        return res.data;
    },
    async clearLogs() {
        const res = await http.delete('/api/settings/logs');
        return res.data;
    },
    async stopAllTasks() {
        const res = await http.post('/api/settings/stop-all-tasks');
        return res.data;
    },
};
