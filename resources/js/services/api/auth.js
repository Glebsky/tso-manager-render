import windowAxios from 'axios';

const http = window.axios || windowAxios;

export const authApi = {
    async login(credentials) {
        const res = await http.post('/login', credentials);
        return res.data;
    },
    async register(payload) {
        const res = await http.post('/admin/register', payload);
        return res.data;
    },
    async logout() {
        try {
            const res = await http.post('/admin/logout');
            return res.data;
        } catch (e) {
            const res = await http.post('/logout');
            return res.data;
        }
    },
};
