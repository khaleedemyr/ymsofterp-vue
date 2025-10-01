# Quality Assurance Group Implementation

## Overview
Telah dibuat group menu baru "Quality Assurance" di AppLayout dengan menu QA Categories dan insert untuk tabel erp_menu dan erp_permission.

## Changes Made

### 1. AppLayout.vue Updates

#### Added Quality Assurance Group
```javascript
{
    title: () => 'Quality Assurance',
    icon: 'fa-solid fa-shield-halved',
    collapsible: true,
    open: ref(false),
    menus: [
        { name: () => 'QA Categories', icon: 'fa-solid fa-clipboard-list', route: '/qa-categories', code: 'qa_categories' },
    ],
},
```

#### Removed QA Categories from Master Data
- QA Categories dipindahkan dari Master Data section ke Quality Assurance group
- Master Data section tetap bersih dengan menu-menu yang relevan

### 2. Database Inserts

#### erp_menu Table Inserts
```sql
-- Quality Assurance Group (Parent)
INSERT INTO `erp_menu` (`name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`) VALUES
('Quality Assurance', 'quality_assurance', NULL, NULL, 'fa-solid fa-shield-halved', NOW(), NOW());

-- QA Categories Menu (Child)
INSERT INTO `erp_menu` (`name`, `code`, `parent_id`, `route`, `icon`, `created_at`, `updated_at`) VALUES
('QA Categories', 'qa_categories', @quality_assurance_id, '/qa-categories', 'fa-solid fa-clipboard-list', NOW(), NOW());
```

#### erp_permission Table Inserts
```sql
-- Permissions for QA Categories
INSERT INTO `erp_permission` (`menu_id`, `action`, `code`, `created_at`, `updated_at`) VALUES
(@qa_categories_menu_id, 'view', 'qa_categories_view', NOW(), NOW()),
(@qa_categories_menu_id, 'create', 'qa_categories_create', NOW(), NOW()),
(@qa_categories_menu_id, 'update', 'qa_categories_update', NOW(), NOW()),
(@qa_categories_menu_id, 'delete', 'qa_categories_delete', NOW(), NOW());
```

## Menu Structure

### Quality Assurance Group
- **Icon**: `fa-solid fa-shield-halved`
- **Position**: Setelah Master Data, sebelum Ops Management
- **Collapsible**: Yes
- **Default State**: Closed

### QA Categories Menu
- **Name**: QA Categories
- **Code**: `qa_categories`
- **Route**: `/qa-categories`
- **Icon**: `fa-solid fa-clipboard-list`
- **Parent**: Quality Assurance

## Permissions Structure

### QA Categories Permissions
1. **View Permission**
   - Code: `qa_categories_view`
   - Action: `view`
   - Description: Can view QA Categories list and details

2. **Create Permission**
   - Code: `qa_categories_create`
   - Action: `create`
   - Description: Can create new QA Categories

3. **Update Permission**
   - Code: `qa_categories_update`
   - Action: `update`
   - Description: Can edit existing QA Categories

4. **Delete Permission**
   - Code: `qa_categories_delete`
   - Action: `delete`
   - Description: Can delete/nonaktifkan QA Categories

## Database Schema

### erp_menu Table Structure
```sql
CREATE TABLE `erp_menu` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `code` varchar(255) NOT NULL,
  `parent_id` int DEFAULT NULL,
  `route` varchar(255) DEFAULT NULL,
  `icon` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_parent_id` (`parent_id`),
  KEY `idx_code` (`code`)
);
```

### erp_permission Table Structure
```sql
CREATE TABLE `erp_permission` (
  `id` int NOT NULL AUTO_INCREMENT,
  `menu_id` int NOT NULL,
  `action` enum('view','create','update','delete') NOT NULL,
  `code` varchar(255) NOT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_menu_id` (`menu_id`),
  KEY `idx_action` (`action`),
  KEY `idx_code` (`code`)
);
```

## Implementation Steps

### 1. Run Database Inserts
```bash
mysql -u root -p your_database < insert_qa_categories_menu_permissions.sql
```

### 2. Verify Menu Structure
- Check that Quality Assurance group appears in sidebar
- Verify QA Categories is under Quality Assurance group
- Confirm QA Categories is removed from Master Data section

### 3. Test Permissions
- Assign permissions to roles/users as needed
- Test access control for QA Categories functionality

## File Structure
```
resources/js/Layouts/AppLayout.vue (updated)
insert_qa_categories_menu_permissions.sql (new)
QUALITY_ASSURANCE_GROUP_IMPLEMENTATION.md (new)
```

## Menu Hierarchy
```
Quality Assurance (Group)
└── QA Categories (Menu)
    ├── View Permission
    ├── Create Permission
    ├── Update Permission
    └── Delete Permission
```

## Benefits

### 1. Organized Structure
- Quality Assurance menu terpisah dari Master Data
- Logical grouping untuk fitur QA
- Easy to extend dengan menu QA lainnya

### 2. Permission Management
- Granular permissions untuk setiap action
- Easy role-based access control
- Scalable permission system

### 3. Future Extensibility
- Easy to add more QA-related menus
- Consistent permission structure
- Maintainable code organization

## Next Steps

1. **Run SQL Script**: Execute `insert_qa_categories_menu_permissions.sql`
2. **Test Menu**: Verify Quality Assurance group appears in sidebar
3. **Assign Permissions**: Assign permissions to appropriate roles
4. **Test Access**: Verify permission-based access control works
5. **Add More QA Menus**: Extend with additional QA-related functionality

## Future QA Menu Suggestions
- QA Checklists
- QA Reports
- QA Standards
- QA Audits
- QA Training
- QA Documentation
