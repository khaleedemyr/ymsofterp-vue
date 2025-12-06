# Purchase Requisition Form - Perbedaan Tampilan per Mode

## Overview

Form Create/Edit Purchase Requisition memiliki **tampilan yang berbeda** untuk setiap mode. Setiap mode memiliki struktur form dan field yang berbeda sesuai dengan kebutuhan bisnis masing-masing.

## Mode Selection

Di bagian atas form, user memilih mode:
- `pr_ops`: Purchase Requisition Ops
- `purchase_payment`: Payment Application
- `travel_application`: Travel Application
- `kasbon`: Kasbon

---

## 1. MODE: PR OPS (`pr_ops`)

### 1.1 Basic Information

**Fields yang ditampilkan:**
- ✅ **Title** (required)
- ✅ **Division** (required, dropdown)
- ❌ **Category** (HIDDEN - category di level item)
- ❌ **Outlet** (HIDDEN - outlet di level item)
- ✅ **Ticket** (optional, dropdown)
- ✅ **Priority** (optional, dropdown: LOW, MEDIUM, HIGH, URGENT)
- ✅ **Description** (optional, textarea)

### 1.2 Items Structure

**Struktur: Multi-Outlet → Multi-Category → Items**

```
Outlet 1 (blue card)
  ├─ Attachments (per outlet)
  └─ Category 1 (green card)
      ├─ Budget Info (per outlet-category)
      └─ Items (table)
          ├─ Item Name
          ├─ Qty
          ├─ Unit
          ├─ Unit Price
          └─ Subtotal
  └─ Category 2
      └─ Items...
  └─ [Add Category button]
Outlet 2
  └─ ...
[Add Outlet button]
Grand Total
```

**Karakteristik:**
- Setiap **Outlet** memiliki card biru terpisah
- Setiap **Category** di dalam outlet memiliki card hijau terpisah
- **Attachments** dapat diupload per outlet
- **Budget Info** ditampilkan per kombinasi outlet-category
- Items ditampilkan dalam **table** per category
- User dapat menambah multiple outlets dan categories
- Total dihitung per outlet, per category, dan grand total

### 1.3 Attachments

- **Per Outlet**: Attachments diupload per outlet
- File input muncul di setiap outlet card
- Dapat upload multiple files per outlet

### 1.4 Description

- Standard textarea untuk description
- Optional field

### 1.5 Approval Flow

- Standard approval flow
- User dapat menambah multiple approvers
- Sequential approval

---

## 2. MODE: PURCHASE PAYMENT (`purchase_payment`)

### 2.1 Basic Information

**Fields yang ditampilkan:**
- ✅ **Title** (required)
- ✅ **Division** (required, dropdown)
- ❌ **Category** (HIDDEN - category di level item)
- ❌ **Outlet** (HIDDEN - outlet di level item)
- ✅ **Ticket** (optional, dropdown)
- ✅ **Priority** (optional, dropdown)
- ✅ **Description** (optional, textarea)

### 2.2 Items Structure

**SAMA DENGAN PR OPS**: Multi-Outlet → Multi-Category → Items

- Struktur identik dengan `pr_ops`
- Multi-outlet support
- Multi-category support
- Budget info per outlet-category
- Attachments per outlet

### 2.3 Attachments

- **Per Outlet**: Sama seperti PR Ops
- File input per outlet card

### 2.4 Description

- Standard textarea
- Optional field

### 2.5 Approval Flow

- **Wajib GM Finance** sebagai approver
- Warning ditampilkan jika GM Finance belum ditambahkan
- Sequential approval

---

## 3. MODE: TRAVEL APPLICATION (`travel_application`)

### 3.1 Basic Information

**Fields yang ditampilkan:**
- ✅ **Title** (required)
- ✅ **Division** (required, dropdown)
- ✅ **Category** (AUTO-SELECTED: Transport, disabled)
  - Category otomatis dipilih: Transport
  - User tidak dapat mengubah
  - Budget info ditampilkan untuk category Transport
- ❌ **Outlet** (HIDDEN - outlet di level travel destinations)
- ✅ **Ticket** (optional, dropdown)
- ✅ **Priority** (optional, dropdown)
- ✅ **Agenda Kerja** (required, textarea besar - 8 rows)
  - Label: "Agenda Kerja *"
  - Placeholder: "Masukkan agenda kerja perjalanan dinas (bisa sangat panjang)..."
  - **Catatan Penting**: Wajib mencantumkan tanggal keberangkatan dan tanggal pulang
- ✅ **Notes** (optional, textarea - 4 rows)
  - Label: "Notes"
  - Placeholder: "Masukkan catatan tambahan jika diperlukan..."

### 3.2 Items Structure

**Struktur: Travel Destinations → Travel Items**

```
Outlet Tujuan Perjalanan Dinas (purple card)
  ├─ Outlet 1 (dropdown)
  ├─ Outlet 2 (dropdown)
  └─ [Add Outlet button]

Items Perjalanan (purple card)
  ├─ Item 1 (white card)
  │   ├─ Tipe Item * (dropdown: transport, allowance, others)
  │   ├─ [Jika allowance]
  │   │   ├─ Nama Penerima Allowance *
  │   │   └─ No. Rekening *
  │   ├─ [Jika others]
  │   │   └─ Notes Others *
  │   ├─ Qty *
  │   ├─ Unit *
  │   ├─ Unit Price *
  │   └─ Subtotal
  └─ Item 2
  └─ [Add Item button]
Total
```

**Karakteristik:**
- **Travel Destinations**: Multiple outlets tujuan perjalanan
- **Travel Items**: Items dengan tipe khusus:
  - **Transport**: Standard item (item_name, qty, unit, unit_price)
  - **Allowance**: 
    - Nama Penerima Allowance (required)
    - No. Rekening (required)
    - Standard fields (qty, unit, unit_price)
  - **Others**:
    - Notes Others (required, textarea)
    - Standard fields (qty, unit, unit_price)
- Items ditampilkan dalam **card layout** (bukan table)
- Total dihitung dari semua travel items

### 3.3 Attachments

- **Standard attachments** (bukan per outlet)
- Single file input di bagian bawah form
- Dapat upload multiple files

### 3.4 Description vs Agenda Kerja

- **Agenda Kerja** (required, 8 rows):
  - Wajib mencantumkan tanggal keberangkatan dan tanggal pulang
  - Textarea besar untuk detail agenda
- **Notes** (optional, 4 rows):
  - Catatan tambahan

### 3.5 Approval Flow

- **Wajib GA Supervisor** sebagai **approver pertama** (level 1)
  - GA Supervisor otomatis ditambahkan dan tidak dapat dihapus
  - Badge hijau: "Required (GA Supervisor)"
- **Wajib GM Finance** sebagai approver
  - Warning jika belum ditambahkan
  - Badge hijau: "Required (GM Finance)"
- Sequential approval

---

## 4. MODE: KASBON (`kasbon`)

### 4.1 Basic Information

**Fields yang ditampilkan:**
- ✅ **Title** (required)
- ✅ **Division** (AUTO-SELECTED: Division user yang login, disabled)
  - Division otomatis dipilih sesuai division user
  - User tidak dapat mengubah
- ✅ **Category** (AUTO-SELECTED: Kasbon, disabled)
  - Category otomatis dipilih: Kasbon
  - User tidak dapat mengubah
- ✅ **Outlet** (required, dropdown)
  - User memilih outlet
- ✅ **Ticket** (optional, dropdown)
- ✅ **Priority** (optional, dropdown)
- ❌ **Description** (HIDDEN - menggunakan kasbon_reason)

### 4.2 Items Structure

**TIDAK ADA ITEMS TABLE**

Sebagai gantinya, ada **Kasbon Section**:

```
Kasbon Section
  ├─ Periode Kasbon Info (blue box)
  │   ├─ Periode Aktif: [tanggal]
  │   └─ Info: Periode kasbon: Tanggal 20 bulan berjalan hingga tanggal 10 bulan selanjutnya
  │   └─ Warning: Per periode hanya diijinkan 1 user saja per outlet
  ├─ Nilai Kasbon * (number input)
  │   └─ Total: [formatted currency]
  └─ Reason / Alasan Kasbon * (textarea, 6 rows)
      └─ Placeholder: "Masukkan alasan atau tujuan penggunaan kasbon..."
      └─ Info: Jelaskan secara detail alasan dan tujuan penggunaan kasbon, termasuk rencana pelunasan
```

**Karakteristik:**
- **Tidak ada items table**
- **Nilai Kasbon**: Number input untuk jumlah kasbon
- **Reason**: Textarea besar untuk alasan kasbon
- **Periode Kasbon**: Info box menampilkan periode aktif
- **Warning**: Per periode hanya 1 user per outlet

### 4.3 Attachments

- **Standard attachments**
- Single file input
- Dapat upload multiple files

### 4.4 Description

- **TIDAK ADA** - menggunakan `kasbon_reason` sebagai gantinya

### 4.5 Approval Flow

- **Wajib GM Finance** sebagai approver
- Warning ditampilkan jika GM Finance belum ditambahkan
- Sequential approval

---

## Perbandingan Ringkas

| Feature | PR Ops | Purchase Payment | Travel Application | Kasbon |
|---------|--------|-----------------|-------------------|--------|
| **Division** | Dropdown | Dropdown | Dropdown | Auto (disabled) |
| **Category** | Hidden | Hidden | Auto: Transport | Auto: Kasbon |
| **Outlet** | Hidden (di item) | Hidden (di item) | Hidden (di destination) | Dropdown |
| **Items Structure** | Multi-Outlet → Multi-Category → Items (table) | Multi-Outlet → Multi-Category → Items (table) | Destinations → Travel Items (cards) | Tidak ada (kasbon_amount + reason) |
| **Attachments** | Per Outlet | Per Outlet | Standard | Standard |
| **Description** | Standard textarea | Standard textarea | Agenda Kerja (8 rows) + Notes | Tidak ada (reason) |
| **Budget Info** | Per Outlet-Category | Per Outlet-Category | Per Category (Transport) | Tidak ditampilkan |
| **Approval Requirements** | Standard | GM Finance wajib | GA Supervisor (first) + GM Finance | GM Finance wajib |
| **Special Fields** | - | - | Item Type (transport/allowance/others), Allowance fields, Others notes | Kasbon Amount, Kasbon Reason, Periode Info |

---

## Detail Field per Mode

### PR OPS & PURCHASE PAYMENT

**Items Table Structure:**
```
| Item Name | Qty | Unit | Unit Price | Subtotal | Actions |
|-----------|-----|------|------------|----------|---------|
| [input]   | [input] | [input] | [input] | [auto] | [delete] |
```

**Budget Info Display:**
- Budget Type (Global/Per Outlet)
- Category Budget / Outlet Budget
- Used This Month
- Current Input
- Total After Input
- Budget Breakdown Detail:
  - PR Unpaid
  - PO Unpaid
  - NFP Submitted
  - NFP Approved
  - NFP Paid
  - Retail Non Food

### TRAVEL APPLICATION

**Travel Items Card Structure:**
```
┌─────────────────────────────────────┐
│ Tipe Item * [dropdown]              │
│                                       │
│ [Jika allowance]                     │
│ ┌─────────────────────────────────┐ │
│ │ Nama Penerima * [input]         │ │
│ │ No. Rekening * [input]          │ │
│ └─────────────────────────────────┘ │
│                                       │
│ [Jika others]                        │
│ ┌─────────────────────────────────┐ │
│ │ Notes Others * [textarea]        │ │
│ └─────────────────────────────────┘ │
│                                       │
│ Qty | Unit | Unit Price | Subtotal   │
│ [input] [input] [input] [auto]       │
│                                       │
│ [Remove Item button]                 │
└─────────────────────────────────────┘
```

### KASBON

**Kasbon Section Structure:**
```
┌─────────────────────────────────────┐
│ Periode Kasbon                      │
│ Periode Aktif: [tanggal range]      │
│ Info: [periode rules]               │
│ Warning: [1 user per outlet]       │
└─────────────────────────────────────┘

Nilai Kasbon *
[number input]
Total: [formatted]

Reason / Alasan Kasbon *
[textarea - 6 rows]
Info: [detail explanation]
```

---

## Conditional Rendering Logic

### Vue Template Conditions

```vue
<!-- Division -->
<div v-if="form.mode === 'kasbon'">
  <!-- Auto-selected, disabled -->
</div>
<div v-else>
  <!-- Dropdown -->
</div>

<!-- Category -->
<div v-if="form.mode !== 'pr_ops' && form.mode !== 'purchase_payment'">
  <div v-if="form.mode === 'travel_application'">
    <!-- Auto: Transport -->
  </div>
  <div v-else-if="form.mode === 'kasbon'">
    <!-- Auto: Kasbon -->
  </div>
  <div v-else>
    <!-- Dropdown -->
  </div>
</div>

<!-- Items -->
<div v-if="form.mode === 'pr_ops' || form.mode === 'purchase_payment'">
  <!-- Multi-Outlet → Multi-Category → Items -->
</div>
<div v-else-if="form.mode === 'travel_application'">
  <!-- Travel Destinations → Travel Items -->
</div>
<div v-else>
  <!-- Simple Items Table (untuk mode lain/legacy) -->
</div>

<!-- Description -->
<div v-if="form.mode === 'travel_application'">
  <!-- Agenda Kerja (8 rows) -->
</div>
<div v-else>
  <!-- Standard Description -->
</div>

<!-- Kasbon Section -->
<div v-if="form.mode === 'kasbon'">
  <!-- Kasbon Amount + Reason -->
</div>

<!-- Attachments -->
<div v-if="form.mode !== 'pr_ops' && form.mode !== 'purchase_payment'">
  <!-- Standard attachments -->
</div>
<!-- Untuk pr_ops & purchase_payment: attachments per outlet -->
```

---

## Validation Rules per Mode

### PR OPS
- Title: required
- Division: required
- Items: min 1 item
- Items.*.outlet_id: required (per item)
- Items.*.category_id: required (per item)
- Items.*.item_name: required
- Items.*.qty: required, min 0.01
- Items.*.unit: required
- Items.*.unit_price: required, min 0

### PURCHASE PAYMENT
- Sama dengan PR Ops
- **Tambahan**: GM Finance wajib di approvers

### TRAVEL APPLICATION
- Title: required
- Division: required
- Category: auto (Transport)
- Travel Outlets: min 1 outlet
- Travel Items: min 1 item
- Travel Items.*.item_type: required
- Travel Items.*.qty: required, min 0.01
- Travel Items.*.unit: required
- Travel Items.*.unit_price: required, min 0
- Travel Items.*.allowance_recipient_name: required (jika item_type = allowance)
- Travel Items.*.allowance_account_number: required (jika item_type = allowance)
- Travel Items.*.others_notes: required (jika item_type = others)
- Travel Agenda: required
- **Tambahan**: GA Supervisor wajib sebagai approver pertama, GM Finance wajib

### KASBON
- Title: required
- Division: auto (user division)
- Category: auto (Kasbon)
- Outlet: required
- Kasbon Amount: required, min 0
- Kasbon Reason: required
- **Tambahan**: GM Finance wajib di approvers
- **Tambahan**: Validasi periode kasbon (disabled saat ini)

---

## Summary

Setiap mode memiliki **tampilan form yang sangat berbeda**:

1. **PR Ops & Purchase Payment**: Struktur kompleks dengan multi-outlet dan multi-category
2. **Travel Application**: Struktur khusus untuk perjalanan dinas dengan item types khusus
3. **Kasbon**: Struktur sederhana tanpa items table, hanya amount + reason

Perbedaan ini mencerminkan kebutuhan bisnis yang berbeda untuk setiap jenis pengajuan.

---

**Last Updated**: December 2025
**Version**: 1.0

