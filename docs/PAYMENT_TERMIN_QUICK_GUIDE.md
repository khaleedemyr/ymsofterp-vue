# Quick Guide: Payment Type (Lunas vs Termin)

## 🚀 Quick Start

### 1️⃣ Di Menu Purchase Order Ops (Saat Create PO)

**Lokasi:** Purchase Order Ops → Create → Section "Metode Pembayaran"

```
┌─────────────────────────────────────────────────────┐
│ Metode Pembayaran                                  │
├─────────────────────────────────────────────────────┤
│                                                     │
│  ○ Bayar Lunas                                     │
│     → Pembayaran penuh sekaligus                   │
│     → Hanya 1x payment di Non Food Payment         │
│                                                     │
│  ● Termin Bayar                                    │
│     → Pembayaran bertahap                          │
│     → Bisa multiple payments                       │
│                                                     │
│  Detail Termin Pembayaran:                         │
│  ┌───────────────────────────────────────────────┐ │
│  │ 50% di muka, 50% setelah barang diterima    │ │
│  └───────────────────────────────────────────────┘ │
│                                                     │
└─────────────────────────────────────────────────────┘
```

**Pilih salah satu:**
- **Lunas**: Untuk pembayaran langsung penuh
- **Termin**: Untuk pembayaran bertahap (isi detail termin)

---

### 2️⃣ Di Menu Non Food Payment (Saat Create Payment)

#### A. Jika PO dengan Payment Type = **LUNAS**

```
┌─────────────────────────────────────────────────────┐
│ Purchase Order Information                          │
├─────────────────────────────────────────────────────┤
│ PO Number: PO-2024-001                             │
│ Metode Pembayaran: [Bayar Lunas] 🟢                │
│ Grand Total: Rp 10.000.000                         │
│                                                     │
│ Amount: [Rp 10.000.000] ← Auto-filled, harus = PO  │
└─────────────────────────────────────────────────────┘
```

**Aturan:**
- ✅ Amount harus = Grand Total PO
- ✅ Hanya bisa 1x pembayaran
- ❌ Jika sudah ada payment, tidak bisa buat payment baru

---

#### B. Jika PO dengan Payment Type = **TERMIN**

```
┌─────────────────────────────────────────────────────┐
│ Informasi Pembayaran Termin                         │
├─────────────────────────────────────────────────────┤
│ Total PO:        Rp 10.000.000                      │
│ Sudah Dibayar:   Rp 0                               │
│                  (0 pembayaran)                     │
│ Sisa Pembayaran: Rp 10.000.000                       │
│                                                     │
│ [░░░░░░░░░░░░░░░░░░░░] 0%                           │
│                                                     │
│ Detail Termin:                                      │
│ 50% di muka, 50% setelah barang diterima          │
├─────────────────────────────────────────────────────┤
│ Amount: [Rp 10.000.000] ← Auto-filled dengan sisa  │
│         (Bisa diubah, maks = Sisa Pembayaran)      │
└─────────────────────────────────────────────────────┘
```

**Aturan:**
- ✅ Amount bisa ≤ Sisa Pembayaran
- ✅ Bisa multiple payments
- ✅ Setiap payment punya sequence (#1, #2, #3, dst)
- ❌ Tidak bisa melebihi Sisa Pembayaran
- ❌ Tidak bisa buat payment jika sudah lunas

---

### 3️⃣ Di Menu Non Food Payment (Show Page)

#### Progress Pembayaran Termin

```
┌─────────────────────────────────────────────────────┐
│ Progress Pembayaran Termin                          │
├─────────────────────────────────────────────────────┤
│ Total PO:        Rp 10.000.000                      │
│ Sudah Dibayar:   Rp 5.000.000                       │
│                  (1 pembayaran)                     │
│ Sisa Pembayaran: Rp 5.000.000                       │
│                                                     │
│ [████████████░░░░░░░░] 50%                          │
│                                                     │
│ Riwayat Pembayaran:                                 │
│ ┌─────────────────────────────────────────────────┐ │
│ │ #1  NFP-20240115-0001                          │ │
│ │      2024-01-15                                │ │
│ │      Rp 5.000.000  [approved]                │ │
│ └─────────────────────────────────────────────────┘ │
│ ┌─────────────────────────────────────────────────┐ │
│ │ #2  NFP-20240120-0001  ← Current Payment       │ │
│ │      2024-01-20                                │ │
│ │      Rp 3.000.000  [pending]                 │ │
│ └─────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────┘
```

**Fitur:**
- 📊 Progress bar visual
- 💰 Total paid & remaining
- 📝 Riwayat semua pembayaran
- 🎯 Payment saat ini di-highlight

---

## 📋 Checklist Flow

### Flow Lunas:
- [ ] Create PO → Pilih "Bayar Lunas"
- [ ] Approve PO
- [ ] Create Payment → Amount = Grand Total
- [ ] Approve Payment
- [ ] ✅ Selesai (hanya 1x payment)

### Flow Termin:
- [ ] Create PO → Pilih "Termin Bayar" + Input detail termin
- [ ] Approve PO
- [ ] Create Payment #1 → Amount ≤ Grand Total
- [ ] Approve Payment #1
- [ ] Create Payment #2 → Amount ≤ Sisa Pembayaran
- [ ] Approve Payment #2
- [ ] ... (ulangi sampai lunas)
- [ ] ✅ Selesai (multiple payments)

---

## ⚠️ Validasi Penting

### Saat Create Payment untuk Termin:

1. **Amount tidak boleh melebihi Sisa Pembayaran**
   ```
   ❌ Sisa: Rp 5.000.000, Input: Rp 6.000.000
   ✅ Sisa: Rp 5.000.000, Input: Rp 5.000.000
   ✅ Sisa: Rp 5.000.000, Input: Rp 3.000.000
   ```

2. **Amount harus > 0**
   ```
   ❌ Amount: 0
   ❌ Amount: -1000
   ✅ Amount: 1000000
   ```

3. **Tidak bisa buat payment jika sudah lunas**
   ```
   Total PO: Rp 10.000.000
   Sudah Dibayar: Rp 10.000.000
   Sisa: Rp 0
   
   ❌ Tidak bisa buat payment baru
   ```

---

## 💡 Tips & Best Practices

1. **Detail Termin:**
   - Gunakan format jelas: "50% di muka, 50% setelah barang diterima"
   - Bisa lebih dari 2 termin: "30% di muka, 40% saat pengiriman, 30% setelah diterima"

2. **Tracking:**
   - Selalu cek Show page untuk melihat progress
   - Gunakan riwayat pembayaran untuk audit trail

3. **Approval:**
   - Pastikan amount sesuai dengan kesepakatan termin
   - Verifikasi sisa pembayaran sebelum approve

4. **Error Handling:**
   - Jika amount > remaining, sistem akan tolak
   - Jika PO sudah lunas, tidak bisa buat payment baru

---

## 🔄 Contoh Skenario Real

### Skenario 1: PO Lunas (Simple)

```
PO-001: Rp 10.000.000 (Lunas)
  ↓
Payment #1: Rp 10.000.000
  ↓
✅ LUNAS (1 payment)
```

### Skenario 2: PO Termin 50% + 50%

```
PO-002: Rp 10.000.000 (Termin: "50% di muka, 50% setelah barang diterima")
  ↓
Payment #1: Rp 5.000.000 (50% di muka)
  ↓ Progress: 50%
Payment #2: Rp 5.000.000 (50% setelah barang diterima)
  ↓ Progress: 100%
✅ LUNAS (2 payments)
```

### Skenario 3: PO Termin 3x Pembayaran

```
PO-003: Rp 12.000.000 (Termin: "30% di muka, 40% saat pengiriman, 30% setelah diterima")
  ↓
Payment #1: Rp 3.600.000 (30% di muka)
  ↓ Progress: 30%
Payment #2: Rp 4.800.000 (40% saat pengiriman)
  ↓ Progress: 70%
Payment #3: Rp 3.600.000 (30% setelah diterima)
  ↓ Progress: 100%
✅ LUNAS (3 payments)
```

---

## ❓ Troubleshooting

### Problem: "Jumlah pembayaran melebihi sisa yang harus dibayar"

**Solusi:**
- Cek Sisa Pembayaran di info box
- Kurangi amount sesuai sisa
- Atau buat payment berikutnya dengan sisa yang tersedia

### Problem: "Purchase Order ini sudah memiliki payment yang aktif" (untuk Lunas)

**Solusi:**
- Untuk PO Lunas, hanya bisa 1x payment
- Jika perlu ubah, cancel payment lama dulu
- Atau buat PO baru dengan payment type Termin

### Problem: Tidak bisa buat payment baru untuk Termin

**Cek:**
- Apakah PO sudah lunas? (Sisa = 0)
- Apakah ada payment yang pending/rejected?
- Cek di Show page untuk melihat status

---

**Last Updated:** 2024-01-20

