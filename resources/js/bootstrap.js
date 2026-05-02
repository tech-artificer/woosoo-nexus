import axios from 'axios';

window.axios = axios;

axios.defaults.withCredentials = true;
axios.defaults.withXSRFToken = true;
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');


if (token) {
    axios.defaults.headers.common['X-CSRF-TOKEN'] = token;
} else {
    console.error('CSRF token not found!');
}

// Auto-recover from stale CSRF token (419). Refresh the cookie and retry once.
let csrfRefreshing = false;
axios.interceptors.response.use(
    (response) => response,
    async (error) => {
        if (error?.response?.status === 419 && !error.config?._csrfRetried && !csrfRefreshing) {
            csrfRefreshing = true;
            try {
                await axios.get('/sanctum/csrf-cookie');
                const freshToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                if (freshToken) {
                    axios.defaults.headers.common['X-CSRF-TOKEN'] = freshToken;
                }
                error.config._csrfRetried = true;
                return axios(error.config);
            } finally {
                csrfRefreshing = false;
            }
        }
        return Promise.reject(error);
    },
);

//   const response = await axios.get('/api/service-status');
//  console.log(response);
