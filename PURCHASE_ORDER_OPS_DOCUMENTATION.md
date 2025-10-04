# Purchase Order Ops Documentation

## Overview
Purchase Order Ops adalah sistem untuk mengelola Purchase Order (PO) untuk kebutuhan operasional (non-food items). Sistem ini mirip dengan Purchase Order Foods tetapi tanpa stock management.

## Features

### 🎯 **Key Features**
- **Multi-source Support**: Berasal dari Purchase Requisition Ops
- **Approval Workflow**: Purchasing Manager → GM Finance
- **PPN Support**: 11% PPN calculation
- **Price Tracking**: Last price, min/max price dari supplier
- **Status Management**: draft → approved → received
- **Print Support**: Mark as printed functionality

### 🔄 **Workflow**
```
Purchase Requisition Ops (APPROVED) → Create PO → PM Approval → GM Finance Approval → Received
```

## Database Structure

### **Tables:**
1. **`purchase_order_ops`** - Tabel utama PO Ops
2. **`purchase_order_ops_items`** - Item-item dalam PO Ops

### **Key Fields:**
- **PO Number**: Format POO + YYMM + 4 digit sequence
- **Source**: Link ke Purchase Requisition Ops
- **Approval Flow**: PM → GM Finance
- **PPN**: 11% PPN calculation
- **Status**: draft, approved, received, rejected

## API Endpoints

### **Web Routes:**
- `GET /po-ops` - List PO Ops
- `GET /po-ops/create` - Create PO form
- `POST /po-ops/generate` - Generate PO from PR
- `GET /po-ops/{id}` - Show PO detail
- `GET /po-ops/{id}/edit` - Edit PO form
- `PUT /po-ops/{id}` - Update PO
- `DELETE /po-ops/{id}` - Delete PO
- `POST /po-ops/{id}/approve-pm` - PM Approval
- `POST /po-ops/{id}/approve-gm` - GM Finance Approval
- `POST /po-ops/{id}/mark-printed` - Mark as printed

### **API Routes:**
- `GET /api/pr-ops/available` - Get available PR Ops

## Frontend Pages

### **1. Index (`/po-ops`)**
- List semua PO Ops dengan filter
- Search by PO number, supplier, source PR
- Filter by status, date range
- Actions: View, Edit, Delete

### **2. Create (`/po-ops/create`)**
- Select available PR Ops
- Group items by supplier
- Set price per item
- Enable/disable PPN
- Generate multiple PO per supplier

### **3. Show (`/po-ops/{id}`)**
- Detail PO information
- Items list dengan total
- Approval history
- Actions: Approve, Reject, Edit, Delete, Print

### **4. Edit (`/po-ops/{id}/edit`)**
- Edit existing items price
- Add new items
- Remove items
- Update notes dan PPN

## Business Logic

### **PO Generation:**
1. **Source**: Approved Purchase Requisition Ops
2. **Grouping**: Items dikelompokkan per supplier
3. **Pricing**: Set price per item per supplier
4. **Calculation**: Subtotal + PPN (11%)
5. **Numbering**: POO + YYMM + 4 digit sequence

### **Approval Process:**
1. **Draft**: PO dibuat dalam status draft
2. **PM Approval**: Purchasing Manager approve/reject
3. **GM Finance Approval**: GM Finance final approval
4. **Received**: PO siap untuk procurement

### **Status Flow:**
```
DRAFT → APPROVED → RECEIVED
  ↓
REJECTED
```

## Integration

### **Purchase Requisition Ops:**
- Source data dari PR Ops yang sudah approved
- Update PR status ke 'IN_PO' setelah PO dibuat
- Link ke source PR untuk tracking

### **Supplier Management:**
- Integrasi dengan supplier master data
- Price history tracking
- Supplier selection per item

### **User Management:**
- Role-based access control
- Approval permissions
- Activity logging

## Security & Compliance

### **Access Control:**
- Role-based permissions
- Approval workflow
- Audit trail dengan activity log

### **Data Validation:**
- Required field validation
- Price validation (min 0)
- Quantity validation
- Supplier selection validation

## Performance Considerations

### **Database:**
- Indexed fields untuk performance
- Foreign key constraints
- Optimized queries dengan joins

### **Frontend:**
- Lazy loading untuk large datasets
- Debounced search
- Pagination untuk list views

## Deployment Notes

### **Database Migration:**
1. Run `create_purchase_order_ops_tables.sql`
2. Run `insert_purchase_order_ops_menu.sql`
3. Update permissions untuk roles

### **Frontend:**
1. Copy Vue components ke `resources/js/Pages/PurchaseOrderOps/`
2. Update routes di `routes/web.php` dan `routes/api.php`
3. Update menu di `AppLayout.vue`

## Troubleshooting

### **Common Issues:**
1. **Permission denied**: Check user roles dan permissions
2. **PR not available**: Pastikan PR status = 'APPROVED'
3. **Price validation**: Check supplier selection dan price input
4. **Approval flow**: Verify user roles untuk approval

### **Debug Tips:**
1. Check activity logs untuk tracking
2. Verify database constraints
3. Check frontend console untuk errors
4. Validate API responses

## Future Enhancements

### **Planned Features:**
1. **Email Notifications**: Auto-notify approvers
2. **Bulk Operations**: Bulk approve/reject
3. **Advanced Reporting**: PO analytics
4. **Integration**: ERP system integration
5. **Mobile Support**: Mobile-responsive design

---

**Version**: 1.0.0  
**Last Updated**: December 2024  
**Maintainer**: Development Team
