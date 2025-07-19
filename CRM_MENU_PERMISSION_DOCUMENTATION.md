# CRM Menu & Permission Documentation

## Overview
Dokumentasi ini menjelaskan struktur menu CRM yang telah ditambahkan ke sistem, termasuk permission yang diperlukan untuk mengakses setiap fitur.

## Struktur Menu CRM

### Parent Menu: CRM
- **Name**: CRM
- **Code**: `crm`
- **Route**: `#` (parent menu, tidak memiliki route langsung)
- **Icon**: `fa-solid fa-handshake`
- **Parent ID**: `NULL` (menu utama)

### Sub-Menu CRM

#### 1. Data Member
- **Name**: Data Member
- **Code**: `crm_members`
- **Route**: `/members`
- **Icon**: `fa-solid fa-users`
- **Parent**: CRM
- **Description**: Menu untuk mengelola data member/customer

#### 2. Dashboard CRM
- **Name**: Dashboard CRM
- **Code**: `crm_dashboard`
- **Route**: `/crm/dashboard`
- **Icon**: `fa-solid fa-chart-line`
- **Parent**: CRM
- **Description**: Dashboard untuk melihat statistik CRM

#### 3. Customer Analytics
- **Name**: Customer Analytics
- **Code**: `crm_analytics`
- **Route**: `/crm/analytics`
- **Icon**: `fa-solid fa-chart-pie`
- **Parent**: CRM
- **Description**: Analisis data customer/member

#### 4. Member Reports
- **Name**: Member Reports
- **Code**: `crm_reports`
- **Route**: `/crm/reports`
- **Icon**: `fa-solid fa-file-lines`
- **Parent**: CRM
- **Description**: Laporan-laporan terkait member

## Struktur Permission

### Permission untuk Data Member (`crm_members`)
| Action | Code | Description |
|--------|------|-------------|
| view | `crm_members_view` | Melihat data member |
| create | `crm_members_create` | Menambah member baru |
| update | `crm_members_update` | Mengubah data member |
| delete | `crm_members_delete` | Menghapus member |

### Permission untuk Dashboard CRM (`crm_dashboard`)
| Action | Code | Description |
|--------|------|-------------|
| view | `crm_dashboard_view` | Melihat dashboard CRM |

### Permission untuk Customer Analytics (`crm_analytics`)
| Action | Code | Description |
|--------|------|-------------|
| view | `crm_analytics_view` | Melihat analisis customer |

### Permission untuk Member Reports (`crm_reports`)
| Action | Code | Description |
|--------|------|-------------|
| view | `crm_reports_view` | Melihat laporan member |
| create | `crm_reports_create` | Membuat laporan baru |

## Database Schema

### Tabel: `erp_menu`
```sql
CREATE TABLE erp_menu (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    code VARCHAR(100) UNIQUE NOT NULL,
    parent_id INT NULL,
    route VARCHAR(255) NULL,
    icon VARCHAR(100) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES erp_menu(id) ON DELETE CASCADE
);
```

### Tabel: `erp_permission`
```sql
CREATE TABLE erp_permission (
    id INT PRIMARY KEY AUTO_INCREMENT,
    menu_id INT NOT NULL,
    action ENUM('view', 'create', 'update', 'delete') NOT NULL,
    code VARCHAR(100) UNIQUE NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (menu_id) REFERENCES erp_menu(id) ON DELETE CASCADE
);
```

## Cara Menjalankan SQL

### 1. Menggunakan MySQL Command Line
```bash
mysql -u username -p database_name < database/sql/insert_crm_menu_permissions.sql
```

### 2. Menggunakan phpMyAdmin
1. Buka phpMyAdmin
2. Pilih database yang sesuai
3. Klik tab "SQL"
4. Copy dan paste isi file `insert_crm_menu_permissions.sql`
5. Klik "Go" untuk menjalankan

### 3. Menggunakan Laravel Artisan
```bash
# Buat seeder untuk menu CRM
php artisan make:seeder CrmMenuSeeder

# Jalankan seeder
php artisan db:seed --class=CrmMenuSeeder
```

## Verifikasi Data

### Query untuk mengecek menu yang diinsert:
```sql
SELECT id, name, code, parent_id, route, icon 
FROM erp_menu 
WHERE code LIKE 'crm%' 
ORDER BY parent_id, id;
```

### Query untuk mengecek permission yang diinsert:
```sql
SELECT p.id, m.name as menu_name, p.action, p.code 
FROM erp_permission p 
JOIN erp_menu m ON p.menu_id = m.id 
WHERE m.code LIKE 'crm%' 
ORDER BY m.name, p.action;
```

## Integrasi dengan Role Management

### Menambahkan Permission ke Role
```sql
-- Contoh: Menambahkan semua permission CRM ke role 'admin'
INSERT INTO role_permissions (role_id, permission_id) 
SELECT r.id, p.id 
FROM roles r, erp_permission p 
WHERE r.name = 'admin' 
AND p.code LIKE 'crm%';
```

### Menambahkan Permission ke User
```sql
-- Contoh: Menambahkan permission view member ke user tertentu
INSERT INTO user_permissions (user_id, permission_id) 
SELECT u.id, p.id 
FROM users u, erp_permission p 
WHERE u.email = 'user@example.com' 
AND p.code = 'crm_members_view';
```

## Frontend Integration

### AppLayout.vue
Menu CRM telah ditambahkan ke `resources/js/Layouts/AppLayout.vue`:

```javascript
{
    title: () => 'CRM',
    icon: 'fa-solid fa-handshake',
    collapsible: true,
    open: ref(false),
    menus: [
        { name: () => 'Data Member', icon: 'fa-solid fa-users', route: '/members', code: 'crm_members' },
        { name: () => 'Dashboard CRM', icon: 'fa-solid fa-chart-line', route: '/crm/dashboard', code: 'crm_dashboard' },
        { name: () => 'Customer Analytics', icon: 'fa-solid fa-chart-pie', route: '/crm/analytics', code: 'crm_analytics' },
        { name: () => 'Member Reports', icon: 'fa-solid fa-file-lines', route: '/crm/reports', code: 'crm_reports' },
    ],
}
```

### Permission Check di Controller
```php
// Contoh penggunaan permission di controller
public function index()
{
    // Check if user has permission to view members
    if (!auth()->user()->hasPermission('crm_members_view')) {
        abort(403, 'Unauthorized action.');
    }
    
    // Your code here
}
```

## Troubleshooting

### Common Issues

1. **Menu tidak muncul di sidebar**
   - Pastikan user memiliki permission yang sesuai
   - Check apakah menu code sudah benar di `allowedMenus`

2. **Permission tidak berfungsi**
   - Pastikan tabel `erp_permission` sudah terisi
   - Check foreign key constraint

3. **Duplicate entry error**
   - Pastikan menu code bersifat unique
   - Check apakah data sudah ada sebelumnya

### Debug Commands
```sql
-- Check menu structure
SELECT m1.name as parent, m2.name as child, m2.code, m2.route
FROM erp_menu m1
RIGHT JOIN erp_menu m2 ON m1.id = m2.parent_id
WHERE m2.code LIKE 'crm%'
ORDER BY m1.name, m2.name;

-- Check permissions for specific user
SELECT p.code, p.action
FROM user_permissions up
JOIN erp_permission p ON up.permission_id = p.id
JOIN users u ON up.user_id = u.id
WHERE u.email = 'user@example.com'
AND p.code LIKE 'crm%';
```

## Future Enhancements

### Planned Features
1. **Advanced Analytics**: Dashboard dengan chart dan grafik
2. **Member Segmentation**: Kategorisasi member berdasarkan kriteria
3. **Communication Tools**: Email/SMS integration
4. **Lead Management**: Tracking potential customers
5. **Sales Pipeline**: Visualisasi proses penjualan

### Technical Improvements
1. **Caching**: Cache menu dan permission untuk performa
2. **API Endpoints**: REST API untuk mobile app
3. **Audit Trail**: Log semua akses ke menu CRM
4. **Export/Import**: Bulk operations untuk data member

## Support

### Contact
- **Developer**: AI Assistant
- **Documentation**: File ini
- **Issues**: GitHub Issues (jika ada)

### Resources
- Laravel Permission Documentation
- Vue.js Router Documentation
- Font Awesome Icons 