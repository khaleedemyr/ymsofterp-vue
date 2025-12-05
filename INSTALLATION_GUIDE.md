# Coaching Module Installation Guide

## 1. Database Setup

Run the following SQL queries in your database:

```sql
-- Create coaching table
-- (Run the content from database/coaching_table.sql)

-- Insert menu and permissions
-- (Run the content from database/coaching_menu_permissions.sql)
```

## 2. Install Vue Multiselect

Install the vue-multiselect package for better autocomplete functionality:

```bash
npm install vue-multiselect
```

## 3. Features Implemented

### ✅ Coaching Module Features:
- **Employee Search**: Advanced autocomplete with vue-multiselect
- **Auto-fill**: Position, division, unit, and start date automatically populate
- **Signature Integration**: Supervisor and employee signatures from users table
- **Disciplinary Actions**: Table with checkboxes and date fields
- **Comments & Signatures**: Supervisor and employee sections
- **CRUD Operations**: Full create, read, update, delete functionality
- **Filtering & Pagination**: Advanced search and pagination in index view

### ✅ Database Structure:
- `coachings` table with all required fields
- Proper foreign key relationships
- JSON support for disciplinary actions
- Signature path integration

### ✅ Navigation:
- Added to Human Resource menu group
- Menu permissions configured
- Route protection implemented

## 4. Usage

1. Navigate to Human Resource > Coaching
2. Click "Tambah Coaching" to create new coaching record
3. Use the employee search to find and select employees
4. Fill in violation details and disciplinary actions
5. Add supervisor and employee comments
6. Save the coaching record

The autocomplete now uses vue-multiselect which provides:
- Better search functionality
- Loading states
- Custom templates for display
- No results and empty state messages
- Clear functionality
