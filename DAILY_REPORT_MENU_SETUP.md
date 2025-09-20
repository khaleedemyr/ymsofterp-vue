# Daily Report Menu Setup Guide

## Overview
Dokumentasi untuk setup menu dan permission Daily Report di sistem ERP.

## Database Tables

### 1. erp_menu
```sql
CREATE TABLE `erp_menu` (
    `id` int NOT NULL AUTO_INCREMENT,
    `name` varchar(255) NOT NULL,
    `code` varchar(100) NOT NULL UNIQUE,
    `parent_id` int NULL,
    `route` varchar(255) NULL,
    `icon` varchar(100) NULL,
    `created_at` datetime NULL,
    `updated_at` datetime NULL,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`parent_id`) REFERENCES `erp_menu`(`id`) ON DELETE CASCADE
);
```

### 2. erp_permission
```sql
CREATE TABLE `erp_permission` (
    `id` int NOT NULL AUTO_INCREMENT,
    `menu_id` int NOT NULL,
    `action` enum('view','create','update','delete') NOT NULL,
    `code` varchar(100) NOT NULL UNIQUE,
    `created_at` datetime NULL,
    `updated_at` datetime NULL,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`menu_id`) REFERENCES `erp_menu`(`id`) ON DELETE CASCADE
);
```

## Menu Structure

### Ops Management Group
```
Ops Management (Group)
├── Master Daily Report (Menu)
│   ├── view permission
│   ├── create permission
│   ├── update permission
│   └── delete permission
└── Daily Report (Menu)
    ├── view permission
    ├── create permission
    ├── update permission
    └── delete permission
```

## SQL Insert Queries

### Option 1: Insert Daily Report Only
```sql
-- File: database/sql/insert_daily_report_menu.sql
-- Insert menu Daily Report ke erp_menu
INSERT INTO `erp_menu` (`id`, `name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`) VALUES
(2, 'Daily Report', 'daily_report', 1, '/daily-report', 'fa-solid fa-clipboard-list', NOW(), NOW());

-- Insert permissions untuk Daily Report
INSERT INTO `erp_permission` (`id`, `menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
(5, 2, 'view', 'daily_report_view', NOW(), NOW()),
(6, 2, 'create', 'daily_report_create', NOW(), NOW()),
(7, 2, 'update', 'daily_report_update', NOW(), NOW()),
(8, 2, 'delete', 'daily_report_delete', NOW(), NOW());
```

### Option 2: Complete Ops Management Setup
```sql
-- File: database/sql/insert_ops_management_complete.sql
-- Insert complete Ops Management group dengan semua menu dan permissions

-- 1. Insert group "Ops Management"
INSERT IGNORE INTO `erp_menu` (`id`, `name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`) VALUES
(1, 'Ops Management', 'ops_management', NULL, NULL, 'fa-solid fa-cogs', NOW(), NOW());

-- 2. Insert menu "Master Daily Report"
INSERT IGNORE INTO `erp_menu` (`id`, `name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`) VALUES
(2, 'Master Daily Report', 'master_report', 1, '/master-report', 'fa-solid fa-chart-line', NOW(), NOW());

-- 3. Insert menu "Daily Report"
INSERT IGNORE INTO `erp_menu` (`id`, `name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`) VALUES
(3, 'Daily Report', 'daily_report', 1, '/daily-report', 'fa-solid fa-clipboard-list', NOW(), NOW());

-- 4. Insert permissions untuk Master Daily Report
INSERT IGNORE INTO `erp_permission` (`id`, `menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
(5, 2, 'view', 'master_report_view', NOW(), NOW()),
(6, 2, 'create', 'master_report_create', NOW(), NOW()),
(7, 2, 'update', 'master_report_update', NOW(), NOW()),
(8, 2, 'delete', 'master_report_delete', NOW(), NOW());

-- 5. Insert permissions untuk Daily Report
INSERT IGNORE INTO `erp_permission` (`id`, `menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
(9, 3, 'view', 'daily_report_view', NOW(), NOW()),
(10, 3, 'create', 'daily_report_create', NOW(), NOW()),
(11, 3, 'update', 'daily_report_update', NOW(), NOW()),
(12, 3, 'delete', 'daily_report_delete', NOW(), NOW());
```

## Menu Codes

### Daily Report Permissions
- `daily_report_view` - View daily reports
- `daily_report_create` - Create new daily report
- `daily_report_update` - Update existing daily report
- `daily_report_delete` - Delete daily report

### Master Daily Report Permissions
- `master_report_view` - View master data (departments, areas)
- `master_report_create` - Create master data
- `master_report_update` - Update master data
- `master_report_delete` - Delete master data

## Frontend Integration

### AppLayout.vue
Menu sudah terintegrasi di sidebar:
```javascript
{
  title: () => 'Ops Management',
  icon: 'fa-solid fa-cogs',
  collapsible: true,
  open: ref(false),
  menus: [
    { name: () => 'Master Daily Report', icon: 'fa-solid fa-chart-line', route: '/master-report', code: 'master_report' },
    { name: () => 'Daily Report', icon: 'fa-solid fa-clipboard-list', route: '/daily-report', code: 'daily_report' },
  ],
}
```

## User Role Assignment

### Assign Permissions to User Role
```sql
-- Contoh: Assign semua permissions Daily Report ke role "Manager"
INSERT INTO `user_role_permissions` (`user_role_id`, `permission_code`) VALUES
(1, 'daily_report_view'),
(1, 'daily_report_create'),
(1, 'daily_report_update'),
(1, 'daily_report_delete'),
(1, 'master_report_view'),
(1, 'master_report_create'),
(1, 'master_report_update'),
(1, 'master_report_delete');
```

## Verification Queries

### Check Menu Structure
```sql
SELECT 
    m.id,
    m.name,
    m.code,
    m.parent_id,
    m.route,
    m.icon,
    CASE 
        WHEN m.parent_id IS NULL THEN 'Group'
        ELSE 'Menu'
    END as type
FROM `erp_menu` m
WHERE m.code IN ('ops_management', 'master_report', 'daily_report')
ORDER BY m.parent_id, m.id;
```

### Check Permissions
```sql
SELECT 
    p.id,
    p.menu_id,
    p.action,
    p.code,
    m.name as menu_name,
    m.code as menu_code
FROM `erp_permission` p
JOIN `erp_menu` m ON p.menu_id = m.id
WHERE m.code IN ('master_report', 'daily_report')
ORDER BY m.code, p.action;
```

## Notes

1. **ID Management**: Pastikan ID yang digunakan tidak conflict dengan data yang sudah ada
2. **INSERT IGNORE**: Menggunakan `INSERT IGNORE` untuk mencegah error jika data sudah ada
3. **Parent ID**: Sesuaikan `parent_id` dengan ID group "Ops Management" yang sebenarnya
4. **Permission Codes**: Kode permission harus unique di seluruh sistem
5. **Route**: Pastikan route sesuai dengan yang didefinisikan di `routes/web.php`

## Troubleshooting

### Common Issues
1. **Menu tidak muncul**: Check permission user dan `allowedMenus` di frontend
2. **Permission denied**: Pastikan user memiliki permission yang sesuai
3. **Route not found**: Check route di `routes/web.php` dan controller
4. **ID conflict**: Gunakan `INSERT IGNORE` atau update ID yang digunakan

### Debug Steps
1. Check menu di database: `SELECT * FROM erp_menu WHERE code = 'daily_report'`
2. Check permissions: `SELECT * FROM erp_permission WHERE menu_id = [menu_id]`
3. Check user permissions: `SELECT * FROM user_role_permissions WHERE permission_code LIKE 'daily_report%'`
4. Check frontend: Console log `allowedMenus` di browser
