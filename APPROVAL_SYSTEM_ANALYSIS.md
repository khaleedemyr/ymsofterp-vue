# Analisis Sistem Approval - Web vs Flutter App

## Ringkasan
Dokumen ini menganalisis semua controller approval di web system dan membandingkannya dengan implementasi Flutter app untuk memastikan konsistensi.

## 1. ContraBonController ✅

### Web System:
- **Approve:** `approve()` - menerima `approved` (default true) dan `note`/`comment`
- **Reject:** `reject()` - memanggil `approve()` dengan `approved=false` dan `reason`/`comment`

### Flutter App:
- ✅ `approveContraBon()` - mengirim `approved: true` dan `comment`
- ✅ `rejectContraBon()` - menggunakan endpoint `/reject` terpisah

**Status:** ✅ SUDAH BENAR - ContraBon reject menggunakan endpoint terpisah yang memanggil approve dengan approved=false

---

## 2. NonFoodPaymentController ⚠️

### Web System:
- **Approve:** `approve()` - perlu dicek parameter yang diperlukan
- **Reject:** `reject()` - perlu dicek parameter yang diperlukan

### Flutter App:
- ⚠️ `approveNonFoodPayment()` - hanya mengirim `comment` (perlu dicek apakah ada field required)
- ⚠️ `rejectNonFoodPayment()` - menggunakan endpoint `/reject` terpisah

**Status:** ⚠️ PERLU DICEK - Perlu melihat method approve/reject di NonFoodPaymentController

---

## 3. ApprovalController (Leave) ✅

### Web System:
- **Approve:** `approve()` - menerima `notes`/`comment`
- **Reject:** `reject()` - menerima `notes`/`comment`/`reason`

### Flutter App:
- ✅ `approveLeave()` - mengirim `comment`
- ✅ `rejectLeave()` - mengirim `comment`/`reason`

**Status:** ✅ SUDAH BENAR

---

## 4. CoachingController ✅

### Web System:
- **Approve:** `approve()` - **REQUIRED:** `approver_id` (integer), `comments` (nullable)
- **Reject:** `reject()` - **REQUIRED:** `approver_id` (integer), `comments` (required)

### Flutter App:
- ✅ `approveCoaching()` - mengirim `approver_id` dan `comment`
- ✅ `rejectCoaching()` - mengirim `approver_id` dan `comment`

**Status:** ✅ SUDAH BENAR

---

## 5. EmployeeMovementController ✅

### Web System:
- **Approve:** `approve()` - menerima `approval_flow_id` (nullable), `status: 'approved'`, `notes`
- **Reject:** Route menggunakan closure yang merge `status: 'rejected'` lalu panggil `approve()`

### Flutter App:
- ✅ `approveMovement()` - mengirim `approval_flow_id`, `status: 'approved'`, `notes`
- ✅ `rejectMovement()` - mengirim `approval_flow_id`, `status: 'rejected'`, `notes`

**Status:** ✅ SUDAH BENAR

---

## 6. OutletFoodInventoryAdjustmentController ✅

### Web System:
- **Approve:** `approve()` - menerima `approval_flow_id` (nullable), `note`/`comment`/`notes`
- **Reject:** `reject()` - menerima `approval_flow_id` (nullable), `rejection_reason`/`reason`/`comment` (required)

### Flutter App:
- ✅ `approveStockAdjustment()` - mengirim `approval_flow_id`, `comment`
- ✅ `rejectStockAdjustment()` - mengirim `approval_flow_id`, `reason`

**Status:** ✅ SUDAH BENAR

---

## 7. OutletInternalUseWasteController ✅

### Web System:
- **Approve:** `approve()` - menerima `approval_flow_id` (nullable), `note`/`comment`/`notes`
- **Reject:** `reject()` - menerima `approval_flow_id` (nullable), `rejection_reason`/`reason`/`comment` (required)

### Flutter App:
- ✅ `approveCategoryCost()` - mengirim `approval_flow_id`, `comment`
- ✅ `rejectCategoryCost()` - mengirim `approval_flow_id`, `reason`

**Status:** ✅ SUDAH BENAR

---

## 8. EmployeeResignationController ✅

### Web System:
- **Approve:** `approve()` - menerima `approval_flow_id` (nullable), `note`/`comment`/`comments`
- **Reject:** `reject()` - menerima `approval_flow_id` (nullable), `note`/`reason`/`comment` (**REQUIRED**)

### Flutter App:
- ✅ `approveEmployeeResignation()` - mengirim `approval_flow_id`, `comment`
- ✅ `rejectEmployeeResignation()` - mengirim `approval_flow_id`, `reason` (perlu validasi required)

**Status:** ✅ SUDAH BENAR - Validasi required sudah ada di Flutter screen

---

## 9. FoodPaymentController ✅

### Web System:
- **Approve:** `approve()` - **REQUIRED:** `approved` (boolean), `note` (nullable)
- **Reject:** Menggunakan `approve()` dengan `approved=false`

### Flutter App:
- ✅ `approveFoodPayment()` - mengirim `approved: true` dan `note`
- ✅ `rejectFoodPayment()` - menggunakan endpoint `/approve` dengan `approved: false`

**Status:** ✅ SUDAH BENAR - Sudah diperbaiki

---

## 10. PrFoodController ✅

### Web System:
- **Approve:** Multiple levels:
  - `approveAssistantSsdManager()` - menerima `note`/`comment`/`notes`, `approved` (boolean)
  - `approveSsdManager()` - menerima `note`/`comment`/`notes`, `approved` (boolean)
  - `approveViceCoo()` - menerima `note`/`comment`/`notes`, `approved` (boolean)

### Flutter App:
- ✅ `approvePrFood()` - mengirim `approved: true` dan `note` berdasarkan `approvalLevel`
- ✅ `rejectPrFood()` - mengirim `approved: false` dan `note` berdasarkan `approvalLevel`

**Status:** ✅ SUDAH BENAR

---

## 11. PurchaseOrderFoodsController ✅

### Web System:
- **Approve:** Multiple levels:
  - `approvePurchasingManager()` - menerima `note`/`comment`/`notes`, `approved` (boolean, default true)
  - `approveGMFinance()` - menerima `note`/`comment`/`notes`, `approved` (boolean, default true)

### Flutter App:
- ✅ `approvePoFood()` - mengirim `approved: true` dan `note` berdasarkan `approvalLevel`
- ✅ `rejectPoFood()` - mengirim `approved: false` dan `note` berdasarkan `approvalLevel`

**Status:** ✅ SUDAH BENAR

---

## 12. FoodFloorOrderController (RO Khusus) ✅

### Web System:
- **Approve:** `approve()` - menerima `approved` (boolean) atau `reject` (boolean), `note`/`comment`/`notes`/`reason`
- **Reject:** Menggunakan `approve()` dengan `approved=false`

### Flutter App:
- ✅ `approveROKhusus()` - mengirim `approved: true` dan `comment`
- ✅ `rejectROKhusus()` - mengirim `approved: false` dan `reason`

**Status:** ✅ SUDAH BENAR

---

## Kesimpulan

### ✅ Yang Sudah Benar:
1. ContraBon ✅
2. Leave (ApprovalController) ✅
3. Coaching ✅
4. EmployeeMovement ✅
5. Stock Adjustment ✅
6. Category Cost ✅
7. Employee Resignation ✅
8. Food Payment ✅ (sudah diperbaiki)
9. PR Food ✅
10. PO Food ✅
11. RO Khusus ✅

### ⚠️ Yang Perlu Dicek:
1. **NonFoodPayment** - Perlu cek method approve/reject di controller

### 📋 Action Items:
1. Cek NonFoodPaymentController approve/reject methods
2. Pastikan semua parameter sesuai dengan web system
3. Test semua approval flows

