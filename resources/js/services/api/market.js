import windowAxios from 'axios';

const http = window.axios || windowAxios;

export const marketApi = {
    async fetchPublicSettings() {
        const res = await http.get('/api/public/market/settings');
        return res.data;
    },
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
    async fetchGoods(serverId, kind = null) {
        const params = { server_id: serverId };
        if (kind && kind !== 'all') params.kind = kind;
        const res = await http.get('/api/market/goods', { params });
        return res.data;
    },
    async fetchTargets(serverId, itemId, kind = null) {
        const params = { server_id: serverId, item_id: itemId };
        if (kind && kind !== 'all') params.kind = kind;
        const res = await http.get('/api/market/targets', { params });
        return res.data;
    },
    async fetchPopular(serverId, period = '1d', kind = null) {
        const params = { server_id: serverId, period };
        if (kind && kind !== 'all') params.kind = kind;
        const res = await http.get('/api/market/popular', { params });
        return res.data;
    },
    async fetchAnalytics(params = {}) {
        const res = await http.get('/api/market/analytics', { params });
        return res.data;
    },
};
