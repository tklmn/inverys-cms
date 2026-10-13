import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// CSP-safe delegated event handlers (replace repetitive inline on* attributes)
import './csp-actions';
