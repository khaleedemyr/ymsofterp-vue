# Sales Outlet Dashboard - Menu Setup Guide

## 📋 Overview

Panduan lengkap untuk menambahkan menu "Sales Outlet Dashboard" ke sistem ERP dan mengatur permissions yang diperlukan.

## 🎯 Yang Akan Dibuat

### 1. **Menu di Sidebar**
- Menu "Sales Outlet Dashboard" ditambahkan di bawah "Beranda"
- Icon: `fa-solid fa-chart-line`
- Route: `/sales-outlet-dashboard`
- Code: `sales_outlet_dashboard`

### 2. **Database Records**
- **erp_menu**: Record menu baru
- **erp_permission**: 5 permissions untuk menu tersebut

## 🗄️ Database Schema

### erp_menu Table
```sql
CREATE TABLE erp_menu (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    code VARCHAR(255) NOT NULL UNIQUE,
    parent_id INT,
    route VARCHAR(255),
    icon VARCHAR(255),
    created_at DATETIME,
    updated_at DATETIME
);
```

### erp_permission Table
```sql
CREATE TABLE erp_permission (
    id INT PRIMARY KEY AUTO_INCREMENT,
    menu_id INT NOT NULL,
    action ENUM('view','create','update','delete') NOT NULL,
    code VARCHAR(255) NOT NULL UNIQUE,
    created_at DATETIME,
    updated_at DATETIME,
    FOREIGN KEY (menu_id) REFERENCES erp_menu(id)
);
```

## 🚀 Setup Instructions

### Method 1: Using PHP Script (Recommended)

1. **Jalankan script setup:**
   ```bash
   php setup_sales_outlet_dashboard_menu.php
   ```

2. **Output yang diharapkan:**
   ```
   🚀 Setting up Sales Outlet Dashboard Menu...

   📝 Inserting Sales Outlet Dashboard menu...
   ✅ Menu inserted successfully with ID: 1

   ✅ Permission 'sales_outlet_dashboard_view' inserted successfully
   ✅ Permission 'sales_outlet_dashboard_create' inserted successfully
   ✅ Permission 'sales_outlet_dashboard_update' inserted successfully
   ✅ Permission 'sales_outlet_dashboard_delete' inserted successfully
   ✅ Permission 'sales_outlet_dashboard_export' inserted successfully

   📊 Summary:
      - Menu ID: 1
      - Permissions inserted: 5
      - Permissions skipped: 0
      - Total permissions: 5

   🔍 Verifying setup...
   ✅ Menu: Sales Outlet Dashboard (Code: sales_outlet_dashboard)
   ✅ Route: /sales-outlet-dashboard
   ✅ Icon: fa-solid fa-chart-line
   ✅ Parent ID: 1
   ✅ Permissions count: 5

   🎉 Sales Outlet Dashboard menu setup completed successfully!
   ```

### Method 2: Manual SQL Execution

1. **Jalankan SQL script:**
   ```bash
   mysql -u username -p database_name < insert_sales_outlet_dashboard_menu.sql
   ```

2. **Atau copy-paste query berikut:**
   ```sql
   -- Insert Sales Outlet Dashboard Menu ke erp_menu
   INSERT INTO erp_menu (id, name, code, parent_id, route, icon, created_at, updated_at) 
   VALUES (
       1, 
       'Sales Outlet Dashboard', 
       'sales_outlet_dashboard', 
       1, 
       '/sales-outlet-dashboard', 
       'fa-solid fa-chart-line', 
       NOW(), 
       NOW()
   );

   -- Insert permissions untuk Sales Outlet Dashboard ke erp_permission
   INSERT INTO erp_permission (menu_id, action, code, created_at, updated_at) VALUES
   (1, 'view', 'sales_outlet_dashboard_view', NOW(), NOW()),
   (1, 'create', 'sales_outlet_dashboard_create', NOW(), NOW()),
   (1, 'update', 'sales_outlet_dashboard_update', NOW(), NOW()),
   (1, 'delete', 'sales_outlet_dashboard_delete', NOW(), NOW()),
   (1, 'view', 'sales_outlet_dashboard_export', NOW(), NOW());
   ```

## 📊 Data yang Akan Diinsert

### erp_menu Record
```json
{
    "id": 1,
    "name": "Sales Outlet Dashboard",
    "code": "sales_outlet_dashboard",
    "parent_id": 1,
    "route": "/sales-outlet-dashboard",
    "icon": "fa-solid fa-chart-line",
    "created_at": "2024-01-01 00:00:00",
    "updated_at": "2024-01-01 00:00:00"
}
```

### erp_permission Records
```json
[
    {
        "menu_id": 1,
        "action": "view",
        "code": "sales_outlet_dashboard_view"
    },
    {
        "menu_id": 1,
        "action": "create",
        "code": "sales_outlet_dashboard_create"
    },
    {
        "menu_id": 1,
        "action": "update",
        "code": "sales_outlet_dashboard_update"
    },
    {
        "menu_id": 1,
        "action": "delete",
        "code": "sales_outlet_dashboard_delete"
    },
    {
        "menu_id": 1,
        "action": "view",
        "code": "sales_outlet_dashboard_export"
    }
]
```

## 🔧 Frontend Changes

### AppLayout.vue Update
Menu telah ditambahkan ke sidebar di grup "Main":

```javascript
{
    title: () => t('sidebar.main'),
    icon: 'fa-solid fa-bars',
    menus: [
        { name: () => t('sidebar.dashboard'), icon: 'fa-solid fa-home', route: '/home', code: 'dashboard' },
        { name: () => 'Sales Outlet Dashboard', icon: 'fa-solid fa-chart-line', route: '/sales-outlet-dashboard', code: 'sales_outlet_dashboard' },
        { name: () => 'My Attendance', icon: 'fa-solid fa-user-clock', route: '/attendance', code: 'my_attendance' },
        // ... other menus
    ],
}
```

## 🔐 Permission Management

### Available Permissions
1. **sales_outlet_dashboard_view** - View dashboard
2. **sales_outlet_dashboard_create** - Create new records (if applicable)
3. **sales_outlet_dashboard_update** - Update existing records (if applicable)
4. **sales_outlet_dashboard_delete** - Delete records (if applicable)
5. **sales_outlet_dashboard_export** - Export data to CSV

### Assigning Permissions to Roles
```sql
-- Example: Assign all permissions to admin role
INSERT INTO role_permissions (role_id, permission_id) 
SELECT 1, id FROM erp_permission WHERE code LIKE 'sales_outlet_dashboard_%';
```

## 🧪 Testing

### 1. **Menu Visibility Test**
- Login ke sistem
- Cek apakah menu "Sales Outlet Dashboard" muncul di sidebar
- Menu harus berada di bawah "Beranda"

### 2. **Access Test**
- Klik menu "Sales Outlet Dashboard"
- Pastikan halaman dashboard terbuka tanpa error
- Cek apakah semua charts dan data loading dengan benar

### 3. **Permission Test**
- Test dengan user yang memiliki permission
- Test dengan user yang tidak memiliki permission
- Pastikan access control bekerja dengan benar

## 🔍 Troubleshooting

### Common Issues

#### 1. **Menu Tidak Muncul di Sidebar**
```sql
-- Check if menu exists
SELECT * FROM erp_menu WHERE code = 'sales_outlet_dashboard';

-- Check if user has permission
SELECT p.* FROM erp_permission p
JOIN role_permissions rp ON p.id = rp.permission_id
JOIN user_roles ur ON rp.role_id = ur.role_id
WHERE p.code = 'sales_outlet_dashboard_view' AND ur.user_id = [USER_ID];
```

#### 2. **Permission Denied Error**
```sql
-- Check user permissions
SELECT p.code, p.action FROM erp_permission p
JOIN role_permissions rp ON p.id = rp.permission_id
JOIN user_roles ur ON rp.role_id = ur.role_id
WHERE ur.user_id = [USER_ID] AND p.code LIKE 'sales_outlet_dashboard_%';
```

#### 3. **Route Not Found Error**
- Pastikan route sudah ditambahkan di `routes/web.php`
- Cek apakah controller dan method sudah ada
- Restart web server jika diperlukan

## 📝 Maintenance

### Updating Menu
```sql
-- Update menu name
UPDATE erp_menu SET name = 'New Name' WHERE code = 'sales_outlet_dashboard';

-- Update menu icon
UPDATE erp_menu SET icon = 'fa-solid fa-new-icon' WHERE code = 'sales_outlet_dashboard';

-- Update menu route
UPDATE erp_menu SET route = '/new-route' WHERE code = 'sales_outlet_dashboard';
```

### Adding New Permissions
```sql
-- Add new permission
INSERT INTO erp_permission (menu_id, action, code, created_at, updated_at) 
VALUES (1, 'view', 'sales_outlet_dashboard_new_feature', NOW(), NOW());
```

### Removing Menu
```sql
-- Delete permissions first
DELETE FROM erp_permission WHERE menu_id = (SELECT id FROM erp_menu WHERE code = 'sales_outlet_dashboard');

-- Delete menu
DELETE FROM erp_menu WHERE code = 'sales_outlet_dashboard';
```

## 🎉 Success Criteria

Setup berhasil jika:
- ✅ Menu muncul di sidebar di bawah "Beranda"
- ✅ Menu dapat diklik dan membuka dashboard
- ✅ Dashboard menampilkan data dengan benar
- ✅ Export functionality bekerja
- ✅ Permission system berfungsi
- ✅ No console errors

---

**Setup selesai! Sales Outlet Dashboard siap digunakan! 🚀**
