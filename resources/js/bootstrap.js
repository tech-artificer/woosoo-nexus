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

//   const response = await axios.get('/api/service-status');
//  console.log(response);
