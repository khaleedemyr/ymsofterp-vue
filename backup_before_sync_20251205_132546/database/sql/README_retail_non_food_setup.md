# Retail Non Food Module - Complete Setup Guide

## Overview
Panduan lengkap untuk setup modul Retail Non Food di YM Soft ERP.

## Prerequisites
- Database MySQL sudah running
- Tabel `erp_menus` dan `erp_permission` sudah ada
- Tabel `tbl_data_outlet`, `warehouse_outlets`, `users` sudah ada

## Setup Steps

### Step 1: Create Database Tables
Jalankan query untuk membuat tabel retail non food:

```sql
-- File: database/sql/retail_non_food_setup.sql
```

Atau copy-paste query berikut:

```sql
-- Create retail_non_food table
CREATE TABLE `retail_non_food` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `retail_number` varchar(255) NOT NULL,
  `outlet_id` bigint(20) UNSIGNED NOT NULL,
  `warehouse_outlet_id` bigint(20) UNSIGNED NULL,
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `transaction_date` date NOT NULL,
  `total_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `notes` text NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'approved',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `retail_non_food_retail_number_unique` (`retail_number`),
  KEY `retail_non_food_outlet_id_foreign` (`outlet_id`),
  KEY `retail_non_food_warehouse_outlet_id_foreign` (`warehouse_outlet_id`),
  KEY `retail_non_food_created_by_foreign` (`created_by`),
  CONSTRAINT `retail_non_food_outlet_id_foreign` FOREIGN KEY (`outlet_id`) REFERENCES `tbl_data_outlet` (`id_outlet`) ON DELETE CASCADE,
  CONSTRAINT `retail_non_food_warehouse_outlet_id_foreign` FOREIGN KEY (`warehouse_outlet_id`) REFERENCES `warehouse_outlets` (`id`) ON DELETE SET NULL,
  CONSTRAINT `retail_non_food_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create retail_non_food_items table
CREATE TABLE `retail_non_food_items` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `retail_non_food_id` bigint(20) UNSIGNED NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `qty` decimal(10,2) NOT NULL,
  `unit` varchar(255) NOT NULL,
  `price` decimal(15,2) NOT NULL,
  `subtotal` decimal(15,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `retail_non_food_items_retail_non_food_id_foreign` (`retail_non_food_id`),
  CONSTRAINT `retail_non_food_items_retail_non_food_id_foreign` FOREIGN KEY (`retail_non_food_id`) REFERENCES `retail_non_food` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Step 2: Insert Menu and Permissions
Jalankan query untuk menambahkan menu dan permissions:

```sql
-- File: database/sql/insert_retail_non_food_menu_simple.sql
```

Atau copy-paste query berikut:

```sql
-- Insert menu into erp_menus table
INSERT INTO `erp_menus` (`name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`) VALUES
('Retail Non Food', 'view-retail-non-food', 4, '/retail-non-food', 'fa-solid fa-shopping-bag', NOW(), NOW())
ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `parent_id` = VALUES(`parent_id`),
    `route` = VALUES(`route`),
    `icon` = VALUES(`icon`),
    `updated_at` = NOW();

-- Get the menu_id
SET @menu_id = (SELECT id FROM erp_menus WHERE code = 'view-retail-non-food' LIMIT 1);

-- Delete existing permissions for this menu (if any)
DELETE FROM `erp_permission` WHERE `menu_id` = @menu_id;

-- Insert permissions for Retail Non Food menu
INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
(@menu_id, 'view', 'view-retail-non-food', NOW(), NOW()),
(@menu_id, 'create', 'create-retail-non-food', NOW(), NOW()),
(@menu_id, 'edit', 'edit-retail-non-food', NOW(), NOW()),
(@menu_id, 'delete', 'delete-retail-non-food', NOW(), NOW());
```

### Step 3: Assign Permissions to Roles
Setelah menu dan permissions dibuat, Anda perlu assign permissions ke role yang sesuai:

```sql
-- Contoh: Assign ke role admin (sesuaikan dengan role_id yang ada)
INSERT INTO `role_permissions` (`role_id`, `permission_id`, `created_at`, `updated_at`)
SELECT 
    1 as role_id,  -- Ganti dengan role_id admin
    p.id as permission_id,
    NOW() as created_at,
    NOW() as updated_at
FROM `erp_permission` p
WHERE p.menu_id = (SELECT id FROM erp_menus WHERE code = 'view-retail-non-food')
ON DUPLICATE KEY UPDATE updated_at = NOW();
```

## File Structure Created

### Database Files
- `database/sql/retail_non_food_setup.sql` - Create tables
- `database/sql/insert_retail_non_food_menu_simple.sql` - Insert menu & permissions
- `database/sql/insert_retail_non_food_menu_complete.sql` - Complete setup with checks
- `database/sql/create_retail_non_food_tables.sql` - Detailed table creation

### Application Files
- `app/Models/RetailNonFood.php` - Main model
- `app/Models/RetailNonFoodItem.php` - Item model
- `app/Http/Controllers/RetailNonFoodController.php` - Controller
- `resources/js/Pages/RetailNonFood/Index.vue` - List page
- `resources/js/Pages/RetailNonFood/Form.vue` - Create/Edit form
- `resources/js/Pages/RetailNonFood/Detail.vue` - Detail page
- `resources/js/Layouts/AppLayout.vue` - Updated with menu

### Routes
Routes sudah ditambahkan di `routes/web.php`:
```php
Route::resource('retail-non-food', \App\Http\Controllers\RetailNonFoodController::class);
Route::get('retail-non-food/daily-total', [\App\Http\Controllers\RetailNonFoodController::class, 'dailyTotal']);
```

## Menu Location
Menu "Retail Non Food" akan muncul di:
- **Group**: Outlet Management
- **Position**: Setelah "Retail Food"
- **Icon**: Shopping bag (hijau)
- **Route**: `/retail-non-food`

## Permissions Created
- `view-retail-non-food` - Melihat list transaksi
- `create-retail-non-food` - Membuat transaksi baru
- `edit-retail-non-food` - Edit transaksi
- `delete-retail-non-food` - Hapus transaksi

## Testing
1. Login dengan user yang memiliki permission `view-retail-non-food`
2. Cek menu "Retail Non Food" muncul di sidebar
3. Coba akses halaman list
4. Coba buat transaksi baru
5. Test fitur lainnya

## Troubleshooting

### Menu tidak muncul
- Cek apakah permission sudah di-assign ke role user
- Cek apakah menu sudah di-insert ke `erp_menus`
- Cek apakah `parent_id = 4` sudah benar

### Error foreign key
- Pastikan tabel `tbl_data_outlet`, `warehouse_outlets`, `users` sudah ada
- Pastikan data outlet dan warehouse outlet sudah ada

### Error route
- Pastikan routes sudah terdaftar di `routes/web.php`
- Clear route cache: `php artisan route:clear`

### Permission denied
- Assign permission `view-retail-non-food` ke role user
- Cek apakah user memiliki role yang sesuai 