import windowAxios from 'axios';

const http = window.axios || windowAxios;

export const marketApi = {
    async fetchMarketServers() {
        const res = await http.get('/api/market/servers');
        return res.data;
    },
    async saveMarketServer(payload) {
        const res = await http.post('/api/market/servers', payload);
        return res.data;
    },
    async updateMarketServer(id, payload) {
        const res = await http.put(`/api/market/servers/${id}`, payload);
        return res.data;
    },
    async deleteMarketServer(id) {
        const res = await http.delete(`/api/market/servers/${id}`);
        return res.data;
    },
    async verifyMarketServer(id) {
        const res = await http.post(`/api/market/servers/${id}/verify`);
        return res.data;
    },
    async syncMarketServer(id) {
        const res = await http.post(`/api/market/servers/${id}/sync`);
        return res.data;
    },
    async fetchMarketLogs(params = {}) {
        const res = await http.get('/api/market/logs', { params });
        return res.data;
    },
    async updateMarketSettings(payload) {
        const res = await http.put('/api/market/settings', payload);
        return res.data;
    },
};
