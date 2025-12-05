# Budget Management Frontend Documentation

## 📋 Overview

Frontend untuk mengelola sistem budget Purchase Requisition dengan dukungan untuk:
- **GLOBAL Budget**: Budget dihitung total semua outlet
- **PER_OUTLET Budget**: Budget dihitung per outlet dengan alokasi terpisah

## 🎯 Fitur Utama

### 1. Dashboard Budget Management (`/budget-management`)
- **Summary Cards**: Total categories, global budgets, per-outlet budgets, total outlets
- **Categories by Division**: Tabel yang menampilkan semua kategori dikelompokkan per divisi
- **Budget Type Indicators**: Badge untuk menunjukkan tipe budget (Global/Per-Outlet)
- **Quick Actions**: Refresh data, view summary, export data

### 2. Edit Category Budget (`/budget-management/category/{id}/edit`)
- **Category Information**: Display info kategori (name, division, subcategory, description)
- **Budget Type Selection**: Radio button untuk pilih GLOBAL atau PER_OUTLET
- **Budget Limit Input**: Input untuk set budget limit
- **Warning System**: Alert jika mengubah budget type
- **Current Allocations**: Tabel outlet budgets yang sudah ada (jika PER_OUTLET)

### 3. Manage Outlet Budgets (`/budget-management/category/{id}/outlet-budgets`)
- **Category Summary**: Cards untuk total budget, allocated, used, remaining
- **Bulk Actions**: Create all outlet budgets, export data
- **Add New Budget**: Form untuk tambah outlet budget baru
- **Outlet Budgets Table**: Tabel dengan usage percentage, status, actions
- **Modals**: Bulk create dan edit budget

## 🎨 UI Components

### Summary Cards
```vue
<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
  <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
    <div class="p-6">
      <div class="flex items-center">
        <div class="flex-shrink-0">
          <i class="fa fa-chart-pie text-blue-600 text-2xl"></i>
        </div>
        <div class="ml-4">
          <p class="text-sm font-medium text-gray-500">Total Categories</p>
          <p class="text-2xl font-semibold text-gray-900">{{ categories.length }}</p>
        </div>
      </div>
    </div>
  </div>
</div>
```

### Budget Type Badges
```vue
<span :class="getBudgetTypeBadgeClass(category.budget_type)" class="inline-flex px-2 py-1 text-xs font-semibold rounded-full">
  {{ getBudgetTypeLabel(category.budget_type) }}
</span>
```

### Usage Progress Bars
```vue
<div class="w-16 bg-gray-200 rounded-full h-2 mr-2">
  <div
    class="h-2 rounded-full"
    :class="getUsageBarClass(budget)"
    :style="{ width: getUsagePercentage(budget) + '%' }"
  ></div>
</div>
```

## 🔧 JavaScript Functions

### Budget Type Helpers
```javascript
const getBudgetTypeLabel = (budgetType) => {
  return budgetType === 'GLOBAL' ? 'Global' : 'Per Outlet'
}

const getBudgetTypeBadgeClass = (budgetType) => {
  return budgetType === 'GLOBAL' 
    ? 'bg-blue-100 text-blue-800' 
    : 'bg-orange-100 text-orange-800'
}
```

### Usage Calculations
```javascript
const getUsagePercentage = (budget) => {
  if (budget.allocated_budget <= 0) return 0
  return (budget.used_budget / budget.allocated_budget) * 100
}

const getStatusLabel = (budget) => {
  const percentage = getUsagePercentage(budget)
  if (percentage >= 100) return 'Exceeded'
  if (percentage >= 90) return 'Critical'
  if (percentage >= 75) return 'Warning'
  return 'Safe'
}
```

### Currency Formatting
```javascript
const formatCurrency = (amount) => {
  if (!amount) return 'Rp 0'
  return 'Rp ' + new Intl.NumberFormat('id-ID').format(amount)
}
```

## 📊 Data Flow

### 1. Index Page
```
Controller → Index.vue
├── categories (with outletBudgets relationship)
├── categoriesByDivision (grouped by division)
└── outlets (for reference)
```

### 2. Edit Category Page
```
Controller → EditCategory.vue
├── category (with outletBudgets relationship)
└── outlets (for reference)
```

### 3. Manage Outlet Budgets Page
```
Controller → ManageOutletBudgets.vue
├── category
├── outletBudgets (with outlet relationship)
├── outlets (all outlets)
└── unallocatedOutlets (outlets without budget)
```

## 🎯 User Workflows

### Workflow 1: Setup Per-Outlet Budget
1. Go to Budget Management dashboard
2. Click "Edit" on a category
3. Select "Per-Outlet Budget" radio button
4. Set budget limit
5. Save changes
6. Click "Manage" to configure outlet budgets
7. Add individual outlet budget allocations

### Workflow 2: Bulk Create Outlet Budgets
1. Go to Manage Outlet Budgets page
2. Click "Create All Outlet Budgets"
3. Set default budget amount
4. Click "Create All"
5. System creates budgets for all unallocated outlets

### Workflow 3: Monitor Budget Usage
1. View outlet budgets table
2. Check usage percentage bars
3. Review status badges (Safe/Warning/Critical/Exceeded)
4. Edit allocations if needed

## 🎨 Color Scheme

### Budget Type Colors
- **GLOBAL**: Blue (`bg-blue-100 text-blue-800`)
- **PER_OUTLET**: Orange (`bg-orange-100 text-orange-800`)

### Status Colors
- **Safe**: Green (`bg-green-100 text-green-800`)
- **Warning**: Yellow (`bg-yellow-100 text-yellow-800`)
- **Critical**: Red (`bg-red-100 text-red-800`)
- **Exceeded**: Red (`bg-red-100 text-red-800`)

### Usage Bar Colors
- **0-74%**: Green (`bg-green-500`)
- **75-89%**: Yellow (`bg-yellow-500`)
- **90-99%**: Red (`bg-red-400`)
- **100%+**: Red (`bg-red-500`)

## 🔗 Navigation

### Breadcrumb Navigation
```
Budget Management → Edit Category → Manage Outlet Budgets
```

### Quick Links
- Back to Budget Management (from all sub-pages)
- Edit Category (from dashboard)
- Manage Outlet Budgets (from dashboard, for PER_OUTLET categories)

## 📱 Responsive Design

- **Mobile**: Single column layout, stacked cards
- **Tablet**: 2-column grid for summary cards
- **Desktop**: 4-column grid for summary cards, full table width

## 🚀 Future Enhancements

1. **Export Functionality**: CSV/Excel export for budget data
2. **Budget History**: Track budget changes over time
3. **Budget Transfers**: Move budget between outlets
4. **Budget Alerts**: Email notifications for budget warnings
5. **Budget Reports**: Detailed usage reports and analytics
