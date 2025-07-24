import axios from 'axios';

axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
if (token) {
    axios.defaults.headers.common['X-CSRF-TOKEN'] = token;
} else {
    console.error('CSRF token not found!');
}

// import axios from 'axios';

// window.axios = axios;

// axios.defaults.withCredentials = true;
// axios.defaults.withXSRFToken = true;
// // // --- Load and set Authorization token if it exists ---
// // let authToken = localStorage.getItem('auth_token');
// // if (authToken) {
// //     window.axios.defaults.headers.common['Authorization'] = `Bearer ${authToken}`;
// // }

// const token = document.head.querySelector('meta[name="csrf-token"]');

// if (token) {
//     window.axios.defaults.headers.common['X-CSRF-TOKEN'] = token.content;
// } else {
//     console.error('CSRF token not found: https://laravel.com/docs/csrf#csrf-x-csrf-token');
// }