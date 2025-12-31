# Web Profile Setup di ymsofterp

## 1. Jalankan Migration

```bash
php artisan migrate
```

Ini akan membuat tables:
- `web_profile_pages` - Halaman website
- `web_profile_page_sections` - Sections untuk setiap page
- `web_profile_menu_items` - Menu items
- `web_profile_galleries` - Gallery
- `web_profile_settings` - Settings
- `web_profile_contacts` - Contact submissions

## 2. Akses Admin Panel

Setelah login, akses:
- **List Pages**: `/web-profile`
- **Create Page**: `/web-profile/create`
- **Edit Page**: `/web-profile/{id}/edit`

## 3. API Endpoints

API endpoints tersedia di `/api/web-profile/*`:

### Public Endpoints (tanpa auth):
- `GET /api/web-profile/pages` - List published pages
- `GET /api/web-profile/pages/{slug}` - Get page by slug
- `GET /api/web-profile/pages/{id}/sections` - Get page sections
- `GET /api/web-profile/menu` - Get menu items
- `GET /api/web-profile/gallery` - Get gallery
- `GET /api/web-profile/settings` - Get settings
- `POST /api/web-profile/contact` - Submit contact form

## 4. CORS Configuration

Pastikan CORS dikonfigurasi di `config/cors.php` untuk allow request dari domain website frontend.

## 5. Models

Models tersedia di:
- `App\Models\WebProfilePage`
- `App\Models\WebProfilePageSection`
- `App\Models\WebProfileMenuItem`
- `App\Models\WebProfileGallery`
- `App\Models\WebProfileSetting`
- `App\Models\WebProfileContact`

## 6. Controller

Controller: `App\Http\Controllers\WebProfileController`

- Admin methods: `index`, `create`, `store`, `edit`, `update`, `destroy`
- API methods: `apiPages`, `apiPage`, `apiPageSections`, `apiMenu`, `apiGallery`, `apiSettings`, `apiContact`

