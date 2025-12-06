# Purchase Requisition Ops - Dokumentasi Lengkap

## 1. Overview

**Purchase Requisition Ops (PR Ops)** adalah salah satu mode dari sistem Purchase Requisition yang digunakan untuk pengajuan pembelian operasional. Mode ini berbeda dari mode lain (`purchase_payment`, `travel_application`, `kasbon`) karena memiliki struktur data yang lebih kompleks dengan dukungan multi-outlet dan multi-category di level item.

## 2. Karakteristik Utama

### 2.1 Mode Identifier
- **Mode**: `pr_ops`
- **Status yang valid**: `DRAFT`, `SUBMITTED`, `APPROVED`, `REJECTED`, `PROCESSED`, `COMPLETED`, `PAID`
- **Default mode**: Jika tidak ditentukan, default adalah `pr_ops`

### 2.2 Struktur Data Khusus

#### Perbedaan dengan Mode Lain:
1. **Multi-Outlet di Level Item**: Setiap item bisa memiliki outlet berbeda
2. **Multi-Category di Level Item**: Setiap item bisa memiliki category berbeda
3. **Budget Check per Outlet-Category**: Validasi budget dilakukan per kombinasi outlet + category
4. **Konversi ke PO Ops**: PR Ops dapat dikonversi menjadi Purchase Order Ops (PO Ops)

#### Struktur Database:
```php
// Purchase Requisition (Header)
- id
- pr_number (format: PR-YYYYMM-####)
- mode: 'pr_ops'
- division_id (required)
- category_id (nullable - untuk backward compatibility)
- outlet_id (nullable - untuk backward compatibility)
- status
- amount (total dari semua items)
- created_by
- is_held (boolean)
- held_by
- hold_reason

// Purchase Requisition Items
- id
- purchase_requisition_id
- outlet_id (required untuk pr_ops)
- category_id (required untuk pr_ops)
- item_name
- qty
- unit
- unit_price
- subtotal
```

## 3. Flow Proses

### 3.1 Lifecycle Status

```
DRAFT → SUBMITTED → APPROVED → PROCESSED → COMPLETED
         ↓
      REJECTED
```

### 3.2 Tahapan Detail

#### A. DRAFT
- PR dibuat oleh user
- Dapat diedit dan dihapus
- Belum dikirim untuk approval

#### B. SUBMITTED
- PR dikirim untuk approval
- Approval flow dimulai
- Tidak dapat diedit (kecuali oleh superadmin)
- Status approval flow: `PENDING` untuk approver pertama

#### C. APPROVED
- Semua approver telah approve
- PR siap untuk dibuat menjadi PO Ops
- Dapat di-hold jika diperlukan
- Status approval flow: `APPROVED` untuk semua level

#### D. PROCESSED
- **KONDISI**: Semua items dari PR sudah dibuat menjadi PO Ops
- Status diubah manual oleh user
- PR tidak dapat diubah lagi

#### E. COMPLETED
- Semua proses selesai
- Status final

#### F. REJECTED
- Ditolak oleh salah satu approver
- PR tidak dapat diproses lebih lanjut
- Creator akan mendapat notifikasi

## 4. Approval Flow

### 4.1 Struktur Approval
- **Sequential Approval**: Approval dilakukan secara berurutan berdasarkan `approval_level`
- **Multi-Level**: Bisa memiliki beberapa level approver
- **Status per Level**: `PENDING`, `APPROVED`, `REJECTED`

### 4.2 Proses Approval

```php
// Approval Flow Table
- purchase_requisition_id
- approver_id
- approval_level (1 = terendah, semakin tinggi semakin tinggi levelnya)
- status: 'PENDING' | 'APPROVED' | 'REJECTED'
- approved_at
- rejected_at
- comments
```

### 4.3 Logic Approval

1. **Submit PR**: 
   - Approval flow dibuat untuk setiap approver
   - Status semua flow: `PENDING`
   - Notifikasi dikirim ke approver level 1

2. **Approve Level 1**:
   - Flow level 1: `PENDING` → `APPROVED`
   - Jika ada level 2, notifikasi dikirim ke approver level 2
   - Jika tidak ada level 2, PR status: `SUBMITTED` → `APPROVED`

3. **Approve Level N**:
   - Flow level N: `PENDING` → `APPROVED`
   - Jika ada level N+1, notifikasi dikirim ke approver level N+1
   - Jika tidak ada level N+1, PR status: `SUBMITTED` → `APPROVED`

4. **Reject**:
   - Flow current level: `PENDING` → `REJECTED`
   - PR status: `SUBMITTED` → `REJECTED`
   - Semua flow level di atasnya tetap `PENDING` (tidak perlu approve)
   - Notifikasi dikirim ke creator

### 4.4 Superadmin Privilege
- Superadmin (`id_role = '5af56935b011a'` dan `status = 'A'`) dapat:
  - Melihat semua pending approvals
  - Approve/reject level manapun tanpa harus menunggu level sebelumnya

## 5. Budget Management

### 5.1 Budget Check untuk PR Ops

Budget check dilakukan **per kombinasi outlet + category** di level item:

```php
// Group items by outlet_id + category_id
foreach ($items as $item) {
    $key = $item['outlet_id'] . '_' . $item['category_id'];
    $budgetChecks[$key]['amount'] += $item['subtotal'];
}

// Validate each outlet/category combination
foreach ($budgetChecks as $check) {
    validateBudgetLimit($check['category_id'], $check['outlet_id'], $check['amount']);
}
```

### 5.2 Budget Types

1. **Global Budget**: Budget berlaku untuk semua outlet
2. **Outlet-Specific Budget**: Budget per outlet

### 5.3 Budget Calculation

- Budget dihitung per bulan
- Hanya PR dengan status `SUBMITTED`, `APPROVED`, `PROCESSED`, `COMPLETED` yang dihitung
- PR yang `is_held = true` tidak dihitung dalam budget
- Untuk PR Ops, budget dihitung berdasarkan items (bukan header)

## 6. Konversi ke Purchase Order Ops

### 6.1 Kondisi untuk Konversi
- PR status harus `APPROVED`
- PR mode harus `pr_ops`
- Items yang belum dikonversi dapat dipilih untuk dibuat PO

### 6.2 Proses Konversi

1. **Get Available PRs**:
   ```php
   // Endpoint: GET /api/pr-ops/available
   // Controller: PurchaseOrderOpsController::getAvailablePR()
   ```
   - Mengambil PR dengan status `APPROVED` dan mode `pr_ops`
   - Exclude items yang sudah ada di PO
   - Exclude PR yang semua items-nya sudah di PO

2. **Create PO from PR**:
   - User memilih items dari PR yang akan dibuat PO
   - PO dibuat dengan `source_type = 'purchase_requisition_ops'`
   - PO items memiliki `pr_ops_item_id` yang merujuk ke PR item

3. **Tracking**:
   - PR dapat memiliki multiple PO
   - Setiap item PR dapat dikonversi ke PO yang berbeda
   - Status PR menjadi `PROCESSED` hanya jika semua items sudah di PO

### 6.3 Relasi Database

```php
// Purchase Order Ops
- source_type: 'purchase_requisition_ops'
- source_id: purchase_requisition_id
- source_pr: pr_number

// Purchase Order Ops Items
- pr_ops_item_id: purchase_requisition_item_id
- source_type: 'purchase_requisition_ops'
```

## 7. Hold/Release Mechanism

### 7.1 Hold PR
- **Kondisi**: PR dengan status `APPROVED` atau `PROCESSED`
- **Tujuan**: Menahan PR sementara (misalnya menunggu informasi tambahan)
- **Efek**: 
  - PR tidak dihitung dalam budget
  - PR tidak muncul di available PRs untuk PO creation
  - PR tetap dapat dilihat dan dikomentari

### 7.2 Release PR
- **Kondisi**: PR dengan `is_held = true`
- **Efek**: 
  - PR kembali dihitung dalam budget
  - PR muncul kembali di available PRs

## 8. Comments & Attachments

### 8.1 Comments
- **Struktur**: 
  - `user_id`: User yang membuat komentar
  - `comment`: Isi komentar
  - `is_internal`: Boolean (komentar internal atau tidak)
  - `attachment_path`: Optional file attachment
- **Notifikasi**: 
  - Creator dan semua approver mendapat notifikasi
  - Exclude user yang membuat komentar

### 8.2 Attachments
- **Struktur**:
  - `purchase_requisition_id`
  - `outlet_id`: Optional (untuk PR Ops, attachment bisa per outlet)
  - `file_name`, `file_path`, `file_size`, `mime_type`
  - `uploaded_by`
- **Upload**: 
  - Dapat diupload saat create/edit PR
  - Dapat diupload bersama comment

## 9. API Endpoints

### 9.1 CRUD Operations

```
GET    /purchase-requisitions              # List PRs (dengan filter)
GET    /purchase-requisitions/create         # Form create
POST   /purchase-requisitions                # Create PR
GET    /purchase-requisitions/{id}           # Show PR detail
GET    /purchase-requisitions/{id}/edit      # Form edit
PUT    /purchase-requisitions/{id}           # Update PR
DELETE /purchase-requisitions/{id}           # Delete PR (hanya DRAFT)
```

### 9.2 Actions

```
POST   /purchase-requisitions/{id}/submit    # Submit untuk approval
POST   /purchase-requisitions/{id}/approve    # Approve PR
POST   /purchase-requisitions/{id}/reject    # Reject PR
POST   /purchase-requisitions/{id}/process   # Mark as PROCESSED
POST   /purchase-requisitions/{id}/complete   # Mark as COMPLETED
POST   /purchase-requisitions/{id}/hold       # Hold PR
POST   /purchase-requisitions/{id}/release    # Release PR
```

### 9.3 API Endpoints (JSON)

```
GET    /api/purchase-requisitions/pending-approvals     # Get pending approvals untuk current user
GET    /api/purchase-requisitions/{id}/approval-details # Get approval details untuk modal
POST   /api/purchase-requisitions/{id}/approve          # Approve (API)
POST   /api/purchase-requisitions/{id}/reject           # Reject (API)
GET    /api/purchase-requisitions/categories            # Get categories
GET    /api/purchase-requisitions/tickets               # Get tickets
GET    /api/purchase-requisitions/budget-info          # Get budget info
GET    /api/purchase-requisitions/approvers            # Get approvers (search)
GET    /api/pr-ops/available                           # Get available PRs untuk PO creation
```

### 9.4 Comments & Attachments

```
GET    /purchase-requisitions/{id}/comments            # Get comments
POST   /purchase-requisitions/{id}/comments             # Add comment
PUT    /purchase-requisitions/{id}/comments/{commentId} # Update comment
DELETE /purchase-requisitions/{id}/comments/{commentId} # Delete comment
POST   /purchase-requisitions/{id}/attachments          # Upload attachment
DELETE /purchase-requisitions/attachments/{id}         # Delete attachment
GET    /purchase-requisitions/attachments/{id}/view     # View attachment
```

## 10. Validasi & Business Rules

### 10.1 Create/Update Validation

```php
- title: required|string|max:255
- division_id: required|exists:tbl_data_divisi,id
- category_id: nullable (untuk backward compatibility)
- outlet_id: nullable (untuk backward compatibility)
- items: required|array|min:1
- items.*.item_name: required|string|max:255
- items.*.qty: required|numeric|min:0.01
- items.*.unit: required|string|max:50
- items.*.unit_price: required|numeric|min:0
- items.*.subtotal: required|numeric|min:0
- items.*.outlet_id: nullable|exists:tbl_data_outlet,id_outlet (required untuk pr_ops)
- items.*.category_id: nullable|exists:purchase_requisition_categories,id (required untuk pr_ops)
- approvers: nullable|array
- approvers.*: required|exists:users,id
- mode: required|in:pr_ops,purchase_payment,travel_application,kasbon
```

### 10.2 Business Rules

1. **Edit Rules**:
   - Hanya PR dengan status `DRAFT` atau `SUBMITTED` yang dapat diedit
   - Superadmin dapat edit PR dengan status apapun

2. **Delete Rules**:
   - Hanya PR dengan status `DRAFT` yang dapat dihapus

3. **Submit Rules**:
   - Hanya PR dengan status `DRAFT` yang dapat di-submit
   - Minimal harus ada 1 approver

4. **Approve/Reject Rules**:
   - Hanya PR dengan status `SUBMITTED` yang dapat di-approve/reject
   - User harus menjadi approver di level yang sedang pending

5. **Process Rules**:
   - Hanya PR dengan status `APPROVED` yang dapat di-process
   - **Khusus PR Ops**: Semua items harus sudah dibuat menjadi PO

6. **Complete Rules**:
   - Hanya PR dengan status `PROCESSED` yang dapat di-complete

7. **Hold Rules**:
   - Hanya PR dengan status `APPROVED` atau `PROCESSED` yang dapat di-hold

## 11. Notifications

### 11.1 Notification Types

1. **purchase_requisition_approval**: 
   - Dikirim ke approver berikutnya saat PR di-submit atau di-approve level sebelumnya

2. **purchase_requisition_approved**:
   - Dikirim ke creator saat semua approver telah approve

3. **purchase_requisition_rejected**:
   - Dikirim ke creator saat PR di-reject

4. **purchase_requisition_comment**:
   - Dikirim ke creator dan semua approver saat ada komentar baru

### 11.2 Notification Structure

```php
- user_id: Target user
- task_id: purchase_requisition_id
- type: notification type
- message: Notification message
- url: URL ke detail PR
- is_read: 0/1
```

## 12. Search & Filter

### 12.1 Search Fields

Untuk PR Ops, search dilakukan di:
- PR number, title, description, notes, amount
- Division name
- **Outlet**: Search di items.outlet (bukan header outlet)
- **Category**: Search di items.category (bukan header category)
- Creator name, email
- Ticket number, title

### 12.2 Filter Options

- **Status**: all, DRAFT, SUBMITTED, APPROVED, REJECTED, PROCESSED, COMPLETED
- **Division**: all atau division_id tertentu
- **Category**: all atau category_id tertentu (filter di items untuk PR Ops)
- **Outlet**: all atau outlet_id tertentu (filter di items untuk PR Ops)
- **Is Held**: all, held, not_held
- **Date Range**: date_from, date_to
- **Per Page**: 15 (default)

## 13. Statistics

Statistics dihitung berdasarkan filter yang sama dengan list:
- **Total**: Total PRs
- **Draft**: PRs dengan status DRAFT
- **Submitted**: PRs dengan status SUBMITTED
- **Approved**: PRs dengan status APPROVED

## 14. Payment Tracking

### 14.1 Payment Tracker

Endpoint: `GET /api/purchase-requisitions/payment-tracker`

- Menampilkan PR yang telah di-approve oleh current user
- Filter by date range dan search
- Menampilkan detail approval (approver, approval level, comments, dll)

### 14.2 Payment Status

PR dapat memiliki relasi dengan:
- **Purchase Order Ops**: PR items dikonversi ke PO
- **Non Food Payments**: Payment dibuat dari PO atau langsung dari PR

## 15. Print & Export

### 15.1 Print Preview

Endpoint: `GET /purchase-requisitions/print-preview`

- Generate PDF/HTML untuk print PR
- Include semua detail: header, items, attachments, approval flows, comments

## 16. Integration dengan PO Ops

### 16.1 Available PRs untuk PO

Endpoint: `GET /api/pr-ops/available`

**Kondisi PR yang muncul**:
- Status: `APPROVED`
- Mode: `pr_ops`
- `is_held = false`
- Memiliki items yang belum dikonversi ke PO

**Struktur Response**:
```json
{
  "id": 1,
  "number": "PR-202512-0001",
  "date": "01/12/2025",
  "division_name": "Operations",
  "title": "PR Title",
  "description": "PR Description",
  "amount": 1000000,
  "mode": "pr_ops",
  "status": "APPROVED",
  "is_held": false,
  "outlet": {...}, // PR outlet (fallback)
  "category": {...}, // PR category (fallback)
  "attachments": [...],
  "items": [
    {
      "id": 1,
      "item_name": "Item 1",
      "qty": 10,
      "unit": "pcs",
      "unit_price": 10000,
      "subtotal": 100000,
      "outlet_id": 1,
      "category_id": 1,
      "outlet": {...},
      "category": {...},
      "pr_id": 1
    }
  ]
}
```

### 16.2 PO Creation dari PR

Saat membuat PO dari PR:
1. User memilih items dari PR
2. PO dibuat dengan `source_type = 'purchase_requisition_ops'`
3. PO items memiliki `pr_ops_item_id` yang merujuk ke PR item
4. PR tetap dapat digunakan untuk membuat PO lain (jika masih ada items yang belum dikonversi)

### 16.3 Status Update

Saat semua items PR sudah dikonversi ke PO:
- PR dapat diubah status menjadi `PROCESSED`
- PR tidak muncul lagi di available PRs

## 17. Special Features

### 17.1 Multi-Outlet Support

- Setiap item dapat memiliki outlet berbeda
- Budget check dilakukan per outlet
- Attachment dapat di-assign ke outlet tertentu
- Search dan filter mendukung multi-outlet

### 17.2 Multi-Category Support

- Setiap item dapat memiliki category berbeda
- Budget check dilakukan per category
- Search dan filter mendukung multi-category

### 17.3 Backward Compatibility

- PR lama (tanpa mode) tetap didukung
- Items tanpa outlet_id/category_id menggunakan fallback dari PR header
- Category/outlet di PR header digunakan sebagai fallback untuk items

## 18. Database Tables

### 18.1 Main Tables

- `purchase_requisitions`: Header PR
- `purchase_requisition_items`: Items PR
- `purchase_requisition_approval_flows`: Approval flows
- `purchase_requisition_comments`: Comments
- `purchase_requisition_attachments`: Attachments
- `purchase_requisition_history`: History/logs

### 18.2 Related Tables

- `purchase_order_ops`: PO yang dibuat dari PR
- `purchase_order_ops_items`: PO items dengan relasi ke PR items
- `non_food_payments`: Payments yang dibuat dari PO atau PR
- `purchase_requisition_categories`: Categories
- `purchase_requisition_outlet_budgets`: Budget per outlet-category

## 19. Key Methods di Controller

### 19.1 PurchaseRequisitionController

- `index()`: List PRs dengan filter
- `create()`: Form create
- `store()`: Create PR (dengan budget validation)
- `show()`: Detail PR
- `edit()`: Form edit
- `update()`: Update PR (dengan budget validation)
- `destroy()`: Delete PR
- `submit()`: Submit untuk approval
- `approve()`: Approve PR
- `reject()`: Reject PR
- `process()`: Mark as PROCESSED
- `complete()`: Mark as COMPLETED
- `hold()`: Hold PR
- `release()`: Release PR
- `getPendingApprovals()`: Get pending approvals untuk current user
- `getApprovalDetails()`: Get approval details untuk modal
- `getBudgetInfo()`: Get budget information
- `getApprovers()`: Get approvers (search)
- `addComment()`: Add comment
- `getComments()`: Get comments
- `uploadAttachment()`: Upload attachment

### 19.2 PurchaseOrderOpsController

- `getAvailablePR()`: Get available PRs untuk PO creation

## 20. Frontend Components (Vue/Inertia)

- `PurchaseRequisition/Index.vue`: List PRs
- `PurchaseRequisition/Create.vue`: Form create
- `PurchaseRequisition/Edit.vue`: Form edit
- `PurchaseRequisition/Show.vue`: Detail PR
- `PurchaseRequisitionCommentSection.vue`: Comment section

## 21. Testing Considerations

1. **Budget Validation**: Test dengan berbagai kombinasi outlet + category
2. **Approval Flow**: Test dengan multiple approvers dan sequential approval
3. **PO Conversion**: Test konversi items ke PO (partial dan full)
4. **Hold/Release**: Test hold dan release mechanism
5. **Multi-Outlet**: Test dengan items dari outlet berbeda
6. **Backward Compatibility**: Test dengan data lama (tanpa mode)

## 22. Common Issues & Solutions

### 22.1 Budget Exceeded

**Issue**: Budget limit exceeded saat create/update PR
**Solution**: 
- Check budget limit per outlet-category
- Pastikan PR yang di-hold tidak dihitung dalam budget
- Pastikan hanya PR dengan status tertentu yang dihitung

### 22.2 Items Not Showing in PO Creation

**Issue**: PR tidak muncul di available PRs
**Solution**:
- Check PR status harus `APPROVED`
- Check PR mode harus `pr_ops`
- Check `is_held` harus `false`
- Check items harus belum dikonversi ke PO

### 22.3 Cannot Process PR

**Issue**: Tidak dapat mengubah status ke PROCESSED
**Solution**:
- Pastikan semua items sudah dikonversi ke PO
- Check relasi `purchase_order_ops_items.pr_ops_item_id`

## 23. Future Enhancements

1. **Bulk Operations**: Approve/reject multiple PRs sekaligus
2. **Advanced Filtering**: Filter berdasarkan budget usage, PO status, dll
3. **Export**: Export PRs ke Excel/PDF
4. **Templates**: Template untuk PR yang sering dibuat
5. **Workflow Customization**: Custom approval workflow per division/category

---

**Last Updated**: December 2025
**Version**: 1.0

