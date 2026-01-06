# Asset Management System - Feature Documentation

## Overview
Sistem Asset Management untuk restaurant dengan fokus pada Kitchen Equipment dan Furniture. Sistem ini memungkinkan tracking, maintenance, dan management asset di seluruh outlet.

---

## Phase 1: Core Features

### 1. Master Data Asset

#### 1.1 Asset Categories
- **Kategori Asset:**
  - Kitchen Equipment (oven, mixer, refrigerator, dll)
  - Furniture (meja, kursi, cabinet, dll)
  - Dapat ditambah kategori lain sesuai kebutuhan
- **Fitur:**
  - CRUD kategori asset
  - Kode kategori (auto-generate atau manual)
  - Deskripsi kategori

#### 1.2 Asset Master Data
**Field yang diperlukan:**
- Asset Code (auto-generate atau manual, unique)
- Asset Name
- Category (relasi ke asset_categories)
- Brand
- Model
- Serial Number
- Purchase Date
- Purchase Price
- Supplier/Vendor
- Current Location/Outlet (relasi ke data_outlet)
- Status (Active, Maintenance, Disposed, Lost, Transfer)
- Photo (multiple photos - JSON array)
- Description/Notes
- QR Code/Barcode (generate otomatis, stored as string)
- Created By (relasi ke users)
- Created At
- Updated At

**Fitur:**
- CRUD asset
- Upload multiple photos
- Generate QR Code otomatis
- Search & filter asset
- View asset detail dengan semua informasi

---

### 2. Asset Transfer (Tracking Perpindahan)

#### 2.1 Transfer Asset Antar Outlet
**Field yang diperlukan:**
- Asset ID (relasi ke assets)
- From Outlet (relasi ke data_outlet)
- To Outlet (relasi ke data_outlet)
- Transfer Date
- Transfer Reason/Notes
- Status (Pending, Approved, Completed, Rejected)
- Requested By (relasi ke users)
- Approved By (relasi ke users, nullable)
- Approved At (nullable)
- Completed At (nullable)
- Created At
- Updated At

**Fitur:**
- Request transfer asset
- Approval workflow (jika diperlukan)
- History transfer per asset
- History transfer per outlet
- Filter by status, date range, outlet
- Notification untuk approval

**Workflow:**
1. User request transfer → Status: Pending
2. Approver approve/reject → Status: Approved/Rejected
3. Asset dipindahkan → Status: Completed
4. Asset location di-update otomatis

---

### 3. Maintenance Management

#### 3.1 Maintenance Schedule (Preventive Maintenance)
**Field yang diperlukan:**
- Asset ID (relasi ke assets)
- Maintenance Type (Cleaning, Service, Repair, Inspection)
- Frequency (Daily, Weekly, Monthly, Quarterly, Yearly)
- Next Maintenance Date (auto-calculate)
- Last Maintenance Date (nullable)
- Notes
- Is Active (boolean)
- Created At
- Updated At

#### 3.2 Maintenance History
**Field yang diperlukan:**
- Asset ID (relasi ke assets)
- Maintenance Schedule ID (relasi ke maintenance_schedules, nullable)
- Maintenance Date
- Maintenance Type (Cleaning, Service, Repair, Inspection)
- Cost (decimal)
- Vendor/Service Provider (string)
- Notes/Description
- Status (Scheduled, In Progress, Completed, Cancelled)
- Performed By (relasi ke users, nullable)
- Created At
- Updated At

**Fitur:**
- Create maintenance schedule
- Record maintenance history
- Auto-update next maintenance date setelah maintenance selesai
- Maintenance alerts/reminders:
  - Maintenance due (X hari sebelum due date)
  - Overdue maintenance
- Filter maintenance by:
  - Asset
  - Outlet
  - Maintenance type
  - Date range
  - Status
- Maintenance cost report

---

### 4. Asset Status & Lifecycle

#### 4.1 Asset Status
**Status yang tersedia:**
- **Active**: Asset aktif digunakan
- **Maintenance**: Asset sedang dalam maintenance
- **Disposed**: Asset sudah dibuang/dispose
- **Lost**: Asset hilang
- **Transfer**: Asset sedang dalam proses transfer

#### 4.2 Asset Lifecycle
**Flow:**
1. **Procurement** → Asset baru dibuat
2. **Active** → Asset aktif digunakan
3. **Maintenance** → Asset dalam maintenance (temporary)
4. **Active** → Kembali aktif setelah maintenance
5. **Disposal** → Asset di-dispose (end of lifecycle)

#### 4.3 Disposal Management
**Field yang diperlukan:**
- Asset ID (relasi ke assets)
- Disposal Date
- Disposal Method (Sold, Broken, Donated, Scrapped)
- Disposal Value (decimal, nullable)
- Reason/Notes
- Status (Pending, Approved, Completed, Rejected)
- Requested By (relasi ke users)
- Approved By (relasi ke users, nullable)
- Approved At (nullable)
- Created At
- Updated At

**Fitur:**
- Request disposal asset
- Approval workflow untuk disposal
- Disposal history report
- Filter by disposal method, date range, status

---

### 5. Depreciation & Valuation

#### 5.1 Depreciation Calculation
**Field yang diperlukan:**
- Asset ID (relasi ke assets)
- Purchase Price
- Useful Life (tahun)
- Depreciation Method (Straight-Line)
- Depreciation Rate (auto-calculate: 1 / Useful Life)
- Current Value (auto-calculate)
- Last Calculated Date
- Created At
- Updated At

**Formula:**
- **Annual Depreciation** = Purchase Price / Useful Life
- **Monthly Depreciation** = Annual Depreciation / 12
- **Current Value** = Purchase Price - (Annual Depreciation × Years Used)

#### 5.2 Depreciation Records
**Field yang diperlukan:**
- Asset ID (relasi ke assets)
- Calculation Date
- Purchase Price (snapshot)
- Useful Life (snapshot)
- Depreciation Amount
- Accumulated Depreciation
- Current Value
- Created At

**Fitur:**
- Auto-calculate depreciation (monthly atau yearly)
- Depreciation report
- Asset value report
- Filter by date range, category, outlet

---

### 6. Reporting & Dashboard

#### 6.1 Dashboard
**Key Metrics:**
- Total Assets (by status)
- Total Assets by Category
- Total Assets by Outlet
- Total Asset Value
- Maintenance Due (count)
- Overdue Maintenance (count)
- Recent Transfers
- Recent Maintenances
- Assets by Status (chart)
- Asset Value by Category (chart)
- Asset Distribution by Outlet (chart)

#### 6.2 Reports
**Available Reports:**
1. **Asset Register**
   - List semua asset dengan detail lengkap
   - Filter by category, outlet, status, date range
   - Export to Excel/PDF

2. **Asset by Outlet**
   - List asset per outlet
   - Total asset value per outlet
   - Filter by category, status

3. **Asset by Category**
   - List asset per kategori
   - Total asset value per kategori
   - Filter by outlet, status

4. **Asset by Status**
   - List asset berdasarkan status
   - Filter by category, outlet

5. **Maintenance History Report**
   - History maintenance per asset
   - Total maintenance cost
   - Filter by asset, outlet, date range, maintenance type

6. **Transfer History Report**
   - History transfer asset
   - Filter by asset, outlet, date range, status

7. **Depreciation Report**
   - Depreciation per asset
   - Total depreciation
   - Current value per asset
   - Filter by category, outlet, date range

8. **Asset Value Report**
   - Total asset value
   - Asset value by category
   - Asset value by outlet
   - Filter by date range

---

### 7. Document Management

#### 7.1 Asset Documents
**Field yang diperlukan:**
- Asset ID (relasi ke assets)
- Document Type (Invoice, Warranty, Manual, Maintenance Record, Other)
- Document Name
- File Path
- File Size
- Uploaded By (relasi ke users)
- Uploaded At
- Description/Notes (nullable)

**Fitur:**
- Upload multiple documents per asset
- Document preview (jika supported)
- Download document
- Delete document
- Filter by document type
- Document list per asset

**Supported File Types:**
- PDF
- Images (JPG, PNG)
- Documents (DOC, DOCX, XLS, XLSX)

---

### 8. QR Code/Barcode

#### 8.1 QR Code Generation
**Fitur:**
- Auto-generate QR code untuk setiap asset
- QR code berisi: Asset Code, Asset Name, Asset ID
- Store QR code sebagai image file
- Display QR code di asset detail page
- Print QR code labels

**QR Code Format:**
```
{
  "asset_id": 123,
  "asset_code": "AST-001",
  "asset_name": "Oven Industrial"
}
```

**Fitur Print:**
- Print single QR code
- Print multiple QR codes (batch)
- Custom label size
- Include asset info di label

---

### 9. Notifications & Alerts

#### 9.1 Maintenance Alerts
- **Maintenance Due Alert:**
  - Notifikasi X hari sebelum maintenance due date
  - Default: 7 hari sebelum due date
  - Configurable per asset atau global

- **Overdue Maintenance Alert:**
  - Notifikasi untuk maintenance yang sudah lewat due date
  - Daily reminder sampai maintenance dilakukan

#### 9.2 Warranty Alerts
- **Warranty Expiry Alert:**
  - Notifikasi X hari sebelum warranty expired
  - Default: 30 hari sebelum expired
  - Configurable

#### 9.3 Transfer Alerts
- **Transfer Approval Notification:**
  - Notifikasi ke approver saat ada transfer request
  - Notifikasi ke requester saat transfer approved/rejected
  - Notifikasi saat transfer completed

#### 9.4 Disposal Alerts
- **Disposal Approval Notification:**
  - Notifikasi ke approver saat ada disposal request
  - Notifikasi ke requester saat disposal approved/rejected

**Notification Methods:**
- In-app notification
- Email notification (optional)
- Dashboard alerts

---

### 10. Search & Filter

#### 10.1 Search
**Search Fields:**
- Asset Code
- Asset Name
- Brand
- Model
- Serial Number
- Description/Notes

**Search Features:**
- Real-time search
- Search across multiple fields
- Highlight search results

#### 10.2 Filter
**Filter Options:**
- Category
- Outlet
- Status
- Purchase Date Range
- Maintenance Date Range
- Transfer Date Range
- Price Range
- Brand
- Supplier/Vendor

**Filter Features:**
- Multiple filter combination
- Save filter presets (optional)
- Clear all filters
- Filter count indicator

---

## Phase 2: Advanced Features (Future)

### 11. Mobile App (QR Code Scanning)
- Scan QR code untuk view asset detail
- Scan QR code untuk update asset status
- Scan QR code untuk record maintenance
- Scan QR code untuk transfer asset
- Offline capability
- Sync data saat online

### 12. Real-time Location Tracking
- GPS tracking untuk asset (jika menggunakan IoT)
- Location history
- Geofencing alerts

### 13. Advanced Analytics
- Asset utilization rate
- Maintenance cost trend
- Asset lifecycle analysis
- ROI analysis
- Predictive maintenance

### 14. Integration
- Integration dengan Accounting System (untuk depresiasi)
- Integration dengan Purchase System
- Integration dengan HR System (untuk assignment)

---

## Database Schema Overview

### Tables:
1. **`asset_categories`** - Kategori asset
2. **`assets`** - Master data asset
3. **`asset_transfers`** - History perpindahan asset
4. **`asset_maintenance_schedules`** - Jadwal maintenance
5. **`asset_maintenances`** - History maintenance
6. **`asset_disposals`** - Disposal records
7. **`asset_documents`** - Documents terkait asset
8. **`asset_depreciations`** - Depreciation records
9. **`asset_depreciation_history`** - History perhitungan depresiasi

### Relationships:
- `assets` → `asset_categories` (category_id)
- `assets` → `data_outlet` (current_outlet_id)
- `assets` → `users` (created_by)
- `asset_transfers` → `assets` (asset_id)
- `asset_transfers` → `data_outlet` (from_outlet_id, to_outlet_id)
- `asset_transfers` → `users` (requested_by, approved_by)
- `asset_maintenance_schedules` → `assets` (asset_id)
- `asset_maintenances` → `assets` (asset_id)
- `asset_maintenances` → `asset_maintenance_schedules` (schedule_id, nullable)
- `asset_maintenances` → `users` (performed_by)
- `asset_disposals` → `assets` (asset_id)
- `asset_disposals` → `users` (requested_by, approved_by)
- `asset_documents` → `assets` (asset_id)
- `asset_documents` → `users` (uploaded_by)
- `asset_depreciations` → `assets` (asset_id)
- `asset_depreciation_history` → `assets` (asset_id)

---

## User Roles & Permissions

### Roles (akan ditentukan via role management):
- **Super Admin**: Full access
- **Asset Manager**: Manage assets, transfers, maintenance, disposal
- **Outlet Manager**: View assets di outlet mereka, request transfer
- **Maintenance Staff**: View assets, record maintenance
- **Viewer**: View only

### Permissions:
- Create Asset
- Edit Asset
- Delete Asset
- View Asset
- Transfer Asset
- Approve Transfer
- Create Maintenance
- Edit Maintenance
- Delete Maintenance
- Create Disposal
- Approve Disposal
- View Reports
- Export Reports
- Manage Categories
- Upload Documents
- Delete Documents

---

## Technical Requirements

### Backend:
- Laravel Framework
- MySQL Database
- File Storage untuk photos & documents
- QR Code generation library (PHP)

### Frontend:
- Vue.js 3
- Inertia.js
- Tailwind CSS
- Chart library (ApexCharts atau Chart.js)
- QR Code display library

### Features:
- Image upload & resize
- PDF generation untuk reports
- Excel export
- Email notifications (optional)
- Real-time notifications (optional)

---

## Implementation Priority

### Sprint 1:
1. Master Data Asset (CRUD)
2. Asset Categories (CRUD)
3. Basic Search & Filter

### Sprint 2:
4. Asset Transfer
5. Asset Status Management
6. Basic Dashboard

### Sprint 3:
7. Maintenance Management
8. Maintenance Alerts
9. Maintenance Reports

### Sprint 4:
10. Depreciation Calculation
11. Depreciation Reports
12. Asset Value Reports

### Sprint 5:
13. Document Management
14. QR Code Generation
15. Print QR Code Labels

### Sprint 6:
16. Advanced Reports
17. Notifications & Alerts
18. Final Testing & Bug Fixes

---

## Notes

- Semua tanggal menggunakan timezone Asia/Jakarta
- Currency menggunakan Rupiah (IDR)
- Asset Code format: `AST-YYYY-XXXX` (contoh: AST-2026-0001)
- QR Code disimpan sebagai image file di storage
- Photos disimpan di storage dengan resize otomatis
- Documents disimpan di storage dengan original size
- Semua reports dapat di-export ke Excel/PDF

