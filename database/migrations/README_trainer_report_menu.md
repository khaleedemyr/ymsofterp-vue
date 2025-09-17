# Trainer Report Menu & Permissions Setup

## Overview
File-file SQL ini digunakan untuk menambahkan menu "Trainer Report" ke dalam sistem ERP dengan permission yang sesuai.

## Files
1. `insert_trainer_report_menu.sql` - Query insert sederhana untuk eksekusi langsung
2. `add_lms_trainer_report_menu_permissions.sql` - Query lengkap dengan komentar dan verification
3. `add_trainer_report_menu.sql` - Query basic insert

## Database Tables Affected

### erp_menu
- **ID**: 128
- **Name**: Trainer Report
- **Code**: lms-trainer-report
- **Parent ID**: 127 (LMS group)
- **Route**: /lms/trainer-report-page
- **Icon**: fa-solid fa-chart-line

### erp_permission
- **Menu ID**: 128
- **Actions**: view, create, update, delete
- **Codes**: 
  - lms-trainer-report-view
  - lms-trainer-report-create
  - lms-trainer-report-update
  - lms-trainer-report-delete

## How to Execute

### Option 1: Direct SQL Execution
```sql
-- Copy and paste the content from insert_trainer_report_menu.sql
-- Execute in your MySQL/PostgreSQL client
```

### Option 2: Laravel Migration
```bash
# Create a new migration file
php artisan make:migration add_trainer_report_menu

# Copy the SQL content to the migration file
# Run the migration
php artisan migrate
```

### Option 3: Database Seeder
```bash
# Add to existing seeder or create new one
php artisan make:seeder TrainerReportMenuSeeder
```

## Verification
After execution, you can verify the data:

```sql
-- Check menu
SELECT * FROM erp_menu WHERE id = 128;

-- Check permissions
SELECT * FROM erp_permission WHERE menu_id = 128;

-- Check menu hierarchy
SELECT m.id, m.name, m.code, m.parent_id, p.name as parent_name
FROM erp_menu m
LEFT JOIN erp_menu p ON m.parent_id = p.id
WHERE m.id = 128;
```

## Rollback
If you need to remove the data:

```sql
-- Delete permissions first (foreign key constraint)
DELETE FROM erp_permission WHERE menu_id = 128;

-- Delete menu
DELETE FROM erp_menu WHERE id = 128;
```

## Notes
- Make sure the parent menu (ID: 127) exists in erp_menu table
- The IDs (128, 129-132) should be unique and not conflict with existing records
- Update the IDs if they conflict with existing data in your database
