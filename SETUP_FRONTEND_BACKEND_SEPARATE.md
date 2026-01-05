# 🏗️ Setup Frontend dan Backend di Server Terpisah

## 📋 Konsep Arsitektur

### **Arsitektur Saat Ini (Monolith)**
```
┌─────────────────────────────────┐
│   Server 1 (ymsofterp)          │
│   ┌──────────────────────────┐ │
│   │  Laravel Backend          │ │
│   │  + Inertia.js             │ │
│   │  + Vue 3 Frontend         │ │
│   │  (Semua dalam 1 server)   │ │
│   └──────────────────────────┘ │
└─────────────────────────────────┘
```

### **Arsitektur Baru (Separated)**
```
┌─────────────────────┐         ┌─────────────────────┐
│  Server 1           │         │  Server 2            │
│  (Backend)          │◄────────┤  (Frontend)          │
│  ┌───────────────┐  │  API    │  ┌───────────────┐  │
│  │ Laravel API   │  │ Calls   │  │ Vue 3 SPA     │  │
│  │ (REST/GraphQL)│  │         │  │ (Static Files)│  │
│  └───────────────┘  │         │  └───────────────┘  │
│  - API Routes       │         │  - Build Files      │
│  - Database         │         │  - index.html       │
│  - Business Logic   │         │  - Assets (JS/CSS)  │
└─────────────────────┘         └─────────────────────┘
```

---

## 🔑 Konsep Penting

### **1. API-First Architecture**
- Backend hanya return JSON (tidak ada Inertia response)
- Frontend consume API via HTTP requests (axios/fetch)
- Authentication menggunakan token (Sanctum/JWT)

### **2. CORS (Cross-Origin Resource Sharing)**
- Backend harus allow request dari frontend domain
- Setup CORS di Laravel untuk allow frontend origin

### **3. Authentication**
- Session-based auth tidak bisa digunakan (beda domain)
- Gunakan token-based auth:
  - **Laravel Sanctum** (recommended untuk SPA)
  - **JWT** (alternatif)
  - **OAuth2** (untuk complex scenarios)

### **4. Build & Deploy**
- Frontend di-build menjadi static files
- Deploy static files ke server terpisah (Nginx/Apache)
- Backend tetap Laravel di server terpisah

---

## 📝 Langkah-Langkah Setup

### **PHASE 1: Backend Setup (Laravel API)**

#### **1.1. Install Laravel Sanctum**
```bash
composer require laravel/sanctum
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
php artisan migrate
```

#### **1.2. Configure Sanctum**
File: `config/sanctum.php`
```php
'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', sprintf(
    '%s%s',
    'localhost,localhost:3000,127.0.0.1,127.0.0.1:8000,::1',
    env('FRONTEND_URL') ? ','.parse_url(env('FRONTEND_URL'), PHP_URL_HOST) : ''
))),
```

#### **1.3. Configure CORS**
File: `config/cors.php`
```php
'paths' => ['api/*', 'sanctum/csrf-cookie'],
'allowed_origins' => [env('FRONTEND_URL', 'http://localhost:3000')],
'allowed_origins_patterns' => [],
'allowed_headers' => ['*'],
'allowed_methods' => ['*'],
'exposed_headers' => [],
'max_age' => 0,
'supports_credentials' => true,
```

#### **1.4. Update .env**
```env
FRONTEND_URL=https://frontend.yourdomain.com
SANCTUM_STATEFUL_DOMAINS=frontend.yourdomain.com
SESSION_DOMAIN=.yourdomain.com  # Jika pakai subdomain
```

#### **1.5. Update Kernel.php**
File: `app/Http/Kernel.php`
```php
'api' => [
    \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
    'throttle:api',
    \Illuminate\Routing\Middleware\SubstituteBindings::class,
],
```

#### **1.6. Convert Routes ke API**
- Hapus Inertia responses
- Return JSON responses
- Tambahkan authentication middleware

---

### **PHASE 2: Frontend Setup (Vue 3 SPA)**

#### **2.1. Setup Axios Instance**
File: `resources/js/axios.js` (create new)
```javascript
import axios from 'axios'

const api = axios.create({
    baseURL: import.meta.env.VITE_API_URL || 'http://localhost:8000/api',
    withCredentials: true, // Untuk Sanctum
    headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
    }
})

// Request interceptor untuk attach token
api.interceptors.request.use(config => {
    const token = localStorage.getItem('auth_token')
    if (token) {
        config.headers.Authorization = `Bearer ${token}`
    }
    return config
})

// Response interceptor untuk handle errors
api.interceptors.response.use(
    response => response,
    error => {
        if (error.response?.status === 401) {
            // Redirect ke login
            window.location.href = '/login'
        }
        return Promise.reject(error)
    }
)

export default api
```

#### **2.2. Setup Environment Variables**
File: `.env` (frontend)
```env
VITE_API_URL=https://backend.yourdomain.com/api
VITE_APP_NAME=YMSoft ERP
```

#### **2.3. Update Vite Config**
File: `vite.config.js`
```javascript
export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
    build: {
        outDir: 'dist', // Output directory untuk static files
    },
    server: {
        proxy: {
            '/api': {
                target: 'http://localhost:8000',
                changeOrigin: true,
            }
        }
    }
})
```

#### **2.4. Setup Vue Router**
File: `resources/js/router.js`
```javascript
import { createRouter, createWebHistory } from 'vue-router'

const routes = [
    { path: '/', component: () => import('./Pages/Dashboard.vue') },
    { path: '/login', component: () => import('./Pages/Auth/Login.vue') },
    // ... other routes
]

const router = createRouter({
    history: createWebHistory(),
    routes
})

// Auth guard
router.beforeEach((to, from, next) => {
    const token = localStorage.getItem('auth_token')
    const isAuthPage = to.path === '/login'
    
    if (!token && !isAuthPage) {
        next('/login')
    } else if (token && isAuthPage) {
        next('/')
    } else {
        next()
    }
})

export default router
```

#### **2.5. Build Frontend**
```bash
npm run build
# Output: dist/ folder dengan static files
```

---

### **PHASE 3: Deploy**

#### **3.1. Backend Server (Nginx)**
```nginx
server {
    listen 80;
    server_name backend.yourdomain.com;
    root /var/www/ymsofterp/public;

    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # API routes
    location /api {
        try_files $uri $uri/ /index.php?$query_string;
    }
}
```

#### **3.2. Frontend Server (Nginx)**
```nginx
server {
    listen 80;
    server_name frontend.yourdomain.com;
    root /var/www/frontend/dist;
    index index.html;

    # SPA fallback
    location / {
        try_files $uri $uri/ /index.html;
    }

    # Static assets
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
}
```

---

## 🔐 Authentication Flow

### **Login Flow**
```
1. Frontend: POST /api/login
   Body: { email, password }
   
2. Backend: Validate credentials
   Return: { token, user }

3. Frontend: Store token
   localStorage.setItem('auth_token', token)

4. Frontend: Attach token to all requests
   Headers: { Authorization: Bearer {token} }
```

### **API Request Flow**
```
1. Frontend: GET /api/users
   Headers: { Authorization: Bearer {token} }

2. Backend: Validate token
   Middleware: auth:sanctum

3. Backend: Return data
   Response: { data: [...] }
```

---

## ⚠️ Hal-Hal yang Perlu Diubah

### **1. Remove Inertia.js**
- Hapus `@inertiajs/vue3` dari dependencies
- Hapus Inertia middleware
- Convert semua Inertia responses ke JSON

### **2. Convert Controllers**
**Sebelum (Inertia):**
```php
return Inertia::render('Users/Index', [
    'users' => $users
]);
```

**Sesudah (API):**
```php
return response()->json([
    'success' => true,
    'data' => $users
]);
```

### **3. Convert Vue Components**
**Sebelum (Inertia):**
```vue
<script setup>
import { usePage } from '@inertiajs/vue3'
const users = usePage().props.users
</script>
```

**Sesudah (API):**
```vue
<script setup>
import { ref, onMounted } from 'vue'
import api from '@/axios'

const users = ref([])

onMounted(async () => {
    const response = await api.get('/users')
    users.value = response.data.data
})
</script>
```

---

## 📊 Perbandingan

| Aspek | Monolith (Saat Ini) | Separated (Baru) |
|-------|---------------------|------------------|
| **Deploy** | 1 server | 2 server |
| **Scalability** | Terbatas | Lebih fleksibel |
| **Performance** | Baik | Bisa lebih baik (CDN untuk frontend) |
| **Complexity** | Sederhana | Lebih kompleks |
| **Auth** | Session | Token-based |
| **CORS** | Tidak perlu | Perlu setup |
| **Maintenance** | Mudah | Perlu maintain 2 server |

---

## ✅ Checklist Setup

### **Backend:**
- [ ] Install Laravel Sanctum
- [ ] Configure CORS
- [ ] Setup environment variables
- [ ] Convert routes ke API
- [ ] Convert controllers ke JSON response
- [ ] Setup authentication middleware
- [ ] Test API endpoints

### **Frontend:**
- [ ] Setup axios instance
- [ ] Setup Vue Router
- [ ] Setup environment variables
- [ ] Convert components dari Inertia ke API calls
- [ ] Setup authentication flow
- [ ] Build static files
- [ ] Deploy ke server terpisah

### **Infrastructure:**
- [ ] Setup backend server (Nginx)
- [ ] Setup frontend server (Nginx)
- [ ] Configure DNS
- [ ] Setup SSL certificates
- [ ] Test CORS
- [ ] Test authentication

---

## 🎯 Rekomendasi

### **Kapan Perlu Separated?**
- ✅ Traffic tinggi, perlu scale frontend dan backend terpisah
- ✅ Frontend perlu CDN untuk static assets
- ✅ Team terpisah (frontend team vs backend team)
- ✅ Frontend perlu deploy lebih sering

### **Kapan Tetap Monolith?**
- ✅ Traffic masih rendah-medium
- ✅ Team kecil, maintenance lebih mudah
- ✅ Tidak perlu scale terpisah
- ✅ Inertia.js sudah cukup untuk kebutuhan

---

## 📚 Resources

- [Laravel Sanctum Documentation](https://laravel.com/docs/sanctum)
- [CORS Configuration](https://laravel.com/docs/cors)
- [Vue Router](https://router.vuejs.org/)
- [Axios Documentation](https://axios-http.com/)

---

## ⚠️ CATATAN PENTING

1. **Inertia.js tidak bisa digunakan** jika frontend dan backend beda server
2. **Session-based auth tidak bisa digunakan** (beda domain)
3. **Harus pakai token-based auth** (Sanctum/JWT)
4. **CORS harus dikonfigurasi dengan benar**
5. **Frontend harus di-build** menjadi static files
6. **Perlu maintain 2 server** (lebih kompleks)

---

Apakah Anda ingin saya lanjutkan dengan implementasi detail untuk salah satu phase?

