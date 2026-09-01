/**
 * We'll load the axios HTTP library which allows us to easily issue requests
 * to our Laravel back-end. This library automatically handles sending the
 * CSRF token as a header based on the value of the "XSRF" token cookie.
 */

import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
window.axios.defaults.withCredentials = true;
window.axios.defaults.xsrfCookieName = 'XSRF-TOKEN';
window.axios.defaults.xsrfHeaderName = 'X-XSRF-TOKEN';

let isRefreshingCsrf = false;
let failedRequestsQueue = [];

const processQueue = (error) => {
    failedRequestsQueue.forEach(prom => {
        if (error) {
            prom.reject(error);
        } else {
            prom.resolve();
        }
    });
    failedRequestsQueue = [];
};

window.axios.interceptors.response.use(
    response => response,
    async error => {
        const originalRequest = error.config;

        if (error.response?.status === 401 && !window.location.pathname.startsWith('/admin/login') && !window.location.pathname.startsWith('/admin/register')) {
            window.location.assign('/admin/login');
            return Promise.reject(error);
        }

        // Automatic transparent recovery on 419 CSRF token mismatch
        if (error.response?.status === 419 && originalRequest && !originalRequest._retry) {
            if (isRefreshingCsrf) {
                return new Promise((resolve, reject) => {
                    failedRequestsQueue.push({ resolve, reject });
                }).then(() => {
                    return window.axios(originalRequest);
                }).catch(err => {
                    return Promise.reject(err);
                });
            }

            originalRequest._retry = true;
            isRefreshingCsrf = true;

            try {
                await window.axios.get('/sanctum/csrf-cookie');
                isRefreshingCsrf = false;
                processQueue(null);

                return window.axios(originalRequest);
            } catch (refreshError) {
                isRefreshingCsrf = false;
                processQueue(refreshError);

                if (!window.location.pathname.startsWith('/admin/login') && !window.location.pathname.startsWith('/admin/register')) {
                    window.location.assign('/admin/login');
                }

                return Promise.reject(refreshError);
            }
        }

        return Promise.reject(error);
    }
);


/**
 * Echo exposes an expressive API for subscribing to channels and listening
 * for events that are broadcast by Laravel. Echo and event broadcasting
 * allows your team to easily build robust real-time web applications.
 */

// import Echo from 'laravel-echo';

// import Pusher from 'pusher-js';
// window.Pusher = Pusher;

// window.Echo = new Echo({
//     broadcaster: 'pusher',
//     key: import.meta.env.VITE_PUSHER_APP_KEY,
//     cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER ?? 'mt1',
//     wsHost: import.meta.env.VITE_PUSHER_HOST ? import.meta.env.VITE_PUSHER_HOST : `ws-${import.meta.env.VITE_PUSHER_APP_CLUSTER}.pusher.com`,
//     wsPort: import.meta.env.VITE_PUSHER_PORT ?? 80,
//     wssPort: import.meta.env.VITE_PUSHER_PORT ?? 443,
//     forceTLS: (import.meta.env.VITE_PUSHER_SCHEME ?? 'https') === 'https',
//     enabledTransports: ['ws', 'wss'],
// });
