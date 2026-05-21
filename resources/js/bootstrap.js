import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
window.axios.defaults.withCredentials = true;

const csrfMeta = document.head.querySelector('meta[name="csrf-token"]');
if (csrfMeta?.content) {
  window.axios.defaults.headers.common['X-CSRF-TOKEN'] = csrfMeta.content;
}

function readCookie(name) {
  const match = document.cookie.match(new RegExp('(?:^|; )' + name.replace(/[$()*+./?[\\\]^{|}-]/g, '\\$&') + '=([^;]*)'));
  return match ? decodeURIComponent(match[1]) : null;
}

axios.interceptors.request.use((config) => {
  const xsrf = readCookie('XSRF-TOKEN');
  if (xsrf && !config.headers['X-XSRF-TOKEN']) {
    config.headers['X-XSRF-TOKEN'] = xsrf;
  }
  return config;
});

// Tambahkan interceptor agar Authorization token otomatis dikirim
axios.interceptors.request.use(config => {
  const token = localStorage.getItem('token');
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  
  // Debug: Log untuk request ke /ai/ask
  if (config.url && config.url.includes('/ai/ask')) {
    console.log('🔍 Axios Interceptor - Request to /ai/ask:', {
      method: config.method,
      url: config.url,
      hasData: !!config.data,
      headers: config.headers
    });
    
    // Force POST method untuk /ai/ask
    if (config.method && config.method.toUpperCase() !== 'POST') {
      console.warn('⚠️ Method bukan POST, forcing to POST:', config.method);
      config.method = 'POST';
    }
  }
  
  return config;
});
