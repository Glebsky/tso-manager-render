import windowAxios from 'axios';

const http = window.axios || windowAxios;

export const accountsApi = {
    async fetchAccounts() {
        const res = await http.get('/api/accounts');
        return res.data;
    },
    async createAccount(payload) {
        const res = await http.post('/api/accounts', payload);
        return res.data;
    },
    async fetchAccountDetail(id) {
        const res = await http.get(`/api/accounts/${id}`);
        return res.data;
    },
    async syncAccount(id) {
        const res = await http.post(`/api/accounts/${id}/sync`);
        return res.data;
    },
    async deleteAccount(id) {
        const res = await http.delete(`/api/accounts/${id}`);
        return res.data;
    },
    async updateAccountSession(id, payload) {
        const res = await http.put(`/api/accounts/${id}/session`, payload);
        return res.data;
    },
    async executeAccountAction(id, actionType, payload = {}) {
        const res = await http.post(`/api/accounts/${id}/action`, {
            action_type: actionType,
            ...payload,
        });
        return res.data;
    },
    async fetchFriendZone(accountId, friendId) {
        const res = await http.get(`/api/accounts/${accountId}/friends/${friendId}/zone`);
        return res.data;
    },
};
