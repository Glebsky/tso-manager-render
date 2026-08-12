import windowAxios from 'axios';

const http = window.axios || windowAxios;

export const dashboardApi = {
    async fetchDashboard() {
        const res = await http.get('/api/dashboard');
        return res.data;
    },
};
