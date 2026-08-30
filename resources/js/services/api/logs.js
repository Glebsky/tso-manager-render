import windowAxios from 'axios';

const http = window.axios || windowAxios;

export const logsApi = {
    async fetchLogs(params = {}) {
        const res = await http.get('/api/logs', { params });
        return res.data;
    },
};
