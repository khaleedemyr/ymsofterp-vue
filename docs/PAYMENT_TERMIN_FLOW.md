# Tutorial Flow Pembayaran Termin (Payment Terms)

Dokumen ini menjelaskan alur lengkap penggunaan fitur pembayaran termin di sistem, mulai dari Purchase Requisition Ops hingga Non Food Payment.

## 📋 Daftar Isi
1. [Overview](#overview)
2. [Flow Diagram](#flow-diagram)
3. [Step-by-Step Tutorial](#step-by-step-tutorial)
4. [Perbedaan Lunas vs Termin](#perbedaan-lunas-vs-termin)
5. [FAQ](#faq)

---

## 🎯 Overview

Sistem mendukung dua jenis metode pembayaran:
- **Lunas**: Pembayaran penuh sekaligus
- **Termin**: Pembayaran bertahap (partial payment)

Fitur ini memungkinkan admin untuk:
- Melihat progress pembayaran termin
- Melacak berapa yang sudah dibayar dan sisa yang harus dibayar
- Melihat riwayat semua pembayaran untuk satu PO

---

## 🔄 Flow Diagram

```
┌─────────────────────────────────────────────────────────────┐
│ 1. Purchase Requisition Ops (PR Ops)                        │
│    - User membuat PR untuk kebutuhan pembelian             │
│    - PR di-approve oleh approver                            │
└────────────────────┬────────────────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────────────┐
│ 2. Purchase Order Ops (PO Ops) - CREATE                     │
│    - User memilih PR yang sudah approved                   │
│    - User memilih metode pembayaran:                        │
│      • Lunas: Bayar penuh sekaligus                        │
│      • Termin: Bayar bertahap                              │
│    - Jika Termin, user input detail termin                 │
│      (contoh: "50% di muka, 50% setelah barang diterima")  │
│    - PO dibuat dan di-approve                              │
└────────────────────┬────────────────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────────────┐
│ 3. Non Food Payment - CREATE                                │
│    - User memilih PO yang sudah approved                   │
│    - Sistem menampilkan:                                   │
│      • Metode pembayaran (Lunas/Termin)                    │
│      • Detail termin (jika Termin)                         │
│      • Total PO                                             │
│      • Sudah dibayar (untuk Termin)                        │
│      • Sisa pembayaran (untuk Termin)                     │
│    - User input amount pembayaran                          │
│      • Lunas: Amount = Total PO                            │
│      • Termin: Amount ≤ Sisa pembayaran                    │
│    - Payment dibuat dan di-approve                        │
└────────────────────┬────────────────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────────────┐
│ 4. Non Food Payment - SHOW                                  │
│    - Admin melihat detail payment                          │
│    - Untuk Termin, tampil:                                 │
│      • Progress bar pembayaran                             │
│      • Total paid, Remaining                               │
│      • Riwayat semua pembayaran                            │
│    - Admin bisa approve/reject payment                     │
└─────────────────────────────────────────────────────────────┘
```

---

## 📝 Step-by-Step Tutorial

### Step 1: Membuat Purchase Requisition Ops (PR Ops)

1. Buka menu **Purchase Requisition Ops**
2. Klik **Create** untuk membuat PR baru
3. Isi semua field yang diperlukan:
   - PR Number (auto-generated)
   - Date
   - Title
   - Description
   - Items
   - dll
4. Submit dan tunggu approval
5. Setelah PR di-approve, lanjut ke Step 2

**Catatan**: Di step ini belum ada pilihan payment type karena payment type ditentukan saat membuat PO.

---

### Step 2: Membuat Purchase Order Ops (PO Ops) dengan Payment Type

1. Buka menu **Purchase Order Ops**
2. Klik **Create** untuk membuat PO baru
3. Pilih PR yang sudah approved dari list
4. Isi data PO (supplier, items, dll)

5. **PENTING: Pilih Metode Pembayaran**
   
   Di form PO, ada section **"Metode Pembayaran"** dengan 2 opsi:
   
   #### Opsi A: Bayar Lunas
   - Pilih radio button **"Bayar Lunas"**
   - PO akan dibuat dengan `payment_type = 'lunas'`
   - Nantinya di Non Food Payment, hanya bisa 1x pembayaran penuh
   
   #### Opsi B: Termin Bayar
   - Pilih radio button **"Termin Bayar"**
   - Akan muncul textarea **"Detail Termin Pembayaran"**
   - Input detail termin, contoh:
     ```
     50% di muka, 50% setelah barang diterima
     ```
     atau
     ```
     30% di muka, 40% saat pengiriman, 30% setelah barang diterima
     ```
   - PO akan dibuat dengan `payment_type = 'termin'`
   - Nantinya di Non Food Payment, bisa multiple payments

6. Submit PO dan tunggu approval
7. Setelah PO di-approve, lanjut ke Step 3

**Screenshot Area:**
```
┌─────────────────────────────────────────────┐
│ Metode Pembayaran                           │
├─────────────────────────────────────────────┤
│ ○ Bayar Lunas                               │
│ ● Termin Bayar                              │
│                                             │
│ Detail Termin Pembayaran:                   │
│ ┌─────────────────────────────────────────┐ │
│ │ 50% di muka, 50% setelah barang        │ │
│ │ diterima                                │ │
│ └─────────────────────────────────────────┘ │
└─────────────────────────────────────────────┘
```

---

### Step 3: Membuat Non Food Payment

1. Buka menu **Non Food Payment**
2. Klik **Create** untuk membuat payment baru
3. Pilih PO yang sudah approved dari list

4. **Sistem akan menampilkan informasi PO:**
   
   #### Jika PO dengan Payment Type = Lunas:
   ```
   ┌─────────────────────────────────────────┐
   │ Purchase Order Information              │
   ├─────────────────────────────────────────┤
   │ PO Number: PO-2024-001                  │
   │ Metode Pembayaran: [Bayar Lunas]        │
   │ Grand Total: Rp 10.000.000              │
   └─────────────────────────────────────────┘
   ```
   - Amount otomatis terisi dengan Grand Total PO
   - Hanya bisa 1x pembayaran
   - Jika sudah ada payment untuk PO ini, tidak bisa buat payment lagi
   
   #### Jika PO dengan Payment Type = Termin:
   ```
   ┌─────────────────────────────────────────┐
   │ Informasi Pembayaran Termin             │
   ├─────────────────────────────────────────┤
   │ Total PO:        Rp 10.000.000          │
   │ Sudah Dibayar:   Rp 0                   │
   │                  (0 pembayaran)        │
   │ Sisa Pembayaran: Rp 10.000.000          │
   │                                         │
   │ [Progress Bar: 0%]                      │
   │                                         │
   │ Detail Termin:                          │
   │ 50% di muka, 50% setelah barang diterima│
   └─────────────────────────────────────────┘
   ```
   - Amount otomatis terisi dengan **Sisa Pembayaran**
   - Bisa diubah sesuai kebutuhan (maksimal = Sisa Pembayaran)
   - Bisa multiple payments sampai lunas

5. **Input Data Payment:**
   - Amount: 
     - Lunas: Harus = Total PO
     - Termin: Bisa ≤ Sisa Pembayaran
   - Payment Method: Cash/Transfer/Check
   - Payment Date
   - Due Date (optional)
   - Description
   - Reference Number (optional)
   - Notes (optional)

6. Submit payment
7. Payment akan berstatus **Pending**, tunggu approval

**Validasi:**
- Untuk Termin: Amount tidak boleh melebihi Sisa Pembayaran
- Untuk Termin: Amount harus > 0
- Untuk Lunas: Jika PO sudah punya payment aktif, tidak bisa buat payment baru

---

### Step 4: Melihat Progress Pembayaran Termin

1. Buka menu **Non Food Payment**
2. Klik pada payment yang ingin dilihat detailnya
3. Di halaman **Show**, akan tampil:

   #### Informasi PO:
   ```
   ┌─────────────────────────────────────────┐
   │ Purchase Order Information              │
   ├─────────────────────────────────────────┤
   │ PO Number: PO-2024-001                  │
   │ Metode Pembayaran: [Termin Bayar]       │
   │ Detail Termin: 50% di muka, 50% ...    │
   │ Grand Total: Rp 10.000.000              │
   └─────────────────────────────────────────┘
   ```

   #### Progress Pembayaran Termin:
   ```
   ┌─────────────────────────────────────────┐
   │ Progress Pembayaran Termin              │
   ├─────────────────────────────────────────┤
   │ Total PO:        Rp 10.000.000          │
   │ Sudah Dibayar:   Rp 5.000.000          │
   │                  (1 pembayaran)        │
   │ Sisa Pembayaran: Rp 5.000.000           │
   │                                         │
   │ [████████████░░░░░░░░] 50%              │
   │                                         │
   │ Riwayat Pembayaran:                     │
   │ ┌─────────────────────────────────────┐ │
   │ │ #1 NFP-20240101-0001                │ │
   │ │     2024-01-15                      │ │
   │ │     Rp 5.000.000  [approved]        │ │
   │ └─────────────────────────────────────┘ │
   │ ┌─────────────────────────────────────┐ │
   │ │ #2 NFP-20240120-0001  ← Current    │ │
   │ │     2024-01-20                      │ │
   │ │     Rp 3.000.000  [pending]        │ │
   │ └─────────────────────────────────────┘ │
   └─────────────────────────────────────────┘
   ```

4. **Fitur yang tersedia:**
   - Lihat total yang sudah dibayar
   - Lihat sisa yang harus dibayar
   - Lihat progress bar visual
   - Lihat riwayat semua pembayaran untuk PO ini
   - Payment saat ini akan di-highlight (border biru)

---

### Step 5: Membuat Pembayaran Termin Berikutnya

Jika PO dengan payment type = Termin dan masih ada sisa pembayaran:

1. Buka menu **Non Food Payment** → **Create**
2. Pilih PO yang sama (PO dengan termin yang belum lunas)
3. Sistem akan menampilkan:
   ```
   Total PO:        Rp 10.000.000
   Sudah Dibayar:   Rp 5.000.000  (1 pembayaran)
   Sisa Pembayaran: Rp 5.000.000
   ```
4. Input amount untuk pembayaran kedua (maksimal Rp 5.000.000)
5. Submit payment
6. Di Show page, akan tampil 2 pembayaran dalam riwayat

**Catatan:**
- Bisa membuat multiple payments sampai total paid = Grand Total PO
- Setelah lunas (total paid = grand total), tidak bisa buat payment lagi
- Setiap payment punya sequence number (#1, #2, #3, dst)

---

## 🔍 Perbedaan Lunas vs Termin

| Aspek | Lunas | Termin |
|-------|-------|--------|
| **Jumlah Pembayaran** | 1x pembayaran penuh | Multiple payments |
| **Amount** | Harus = Grand Total PO | Bisa partial, total ≤ Grand Total |
| **Validasi** | Jika sudah ada payment, tidak bisa buat lagi | Bisa buat payment baru selama belum lunas |
| **Use Case** | Pembayaran langsung, barang sudah diterima | Pembayaran bertahap sesuai kesepakatan |
| **Tracking** | Simple, hanya 1 payment | Ada progress tracking dan history |

---

## ❓ FAQ

### Q1: Apakah bisa mengubah payment type setelah PO dibuat?
**A:** Tidak, payment type ditentukan saat membuat PO dan tidak bisa diubah. Jika perlu mengubah, harus buat PO baru.

### Q2: Apakah bisa membuat payment untuk PO termin yang sudah lunas?
**A:** Tidak, sistem akan mencegah pembayaran jika total paid sudah = grand total.

### Q3: Bagaimana jika salah input amount untuk termin?
**A:** Sistem akan validasi di frontend dan backend. Jika amount > remaining, akan muncul error.

### Q4: Apakah payment termin harus sesuai dengan detail termin yang diinput?
**A:** Detail termin hanya sebagai catatan/referensi. Sistem tidak memvalidasi apakah pembayaran sesuai dengan detail termin. Admin yang bertanggung jawab untuk memastikan pembayaran sesuai kesepakatan.

### Q5: Bagaimana cara melihat semua payment untuk satu PO?
**A:** Buka salah satu payment untuk PO tersebut, di Show page akan tampil semua payment dalam riwayat.

### Q6: Apakah bisa cancel payment termin?
**A:** Ya, payment bisa di-cancel. Jika di-cancel, amount-nya tidak akan dihitung dalam total paid.

### Q7: Bagaimana jika PO dengan termin sudah lunas, tapi ternyata ada kesalahan?
**A:** Admin bisa cancel payment yang salah, kemudian buat payment baru dengan amount yang benar.

---

## 🎬 Contoh Skenario Lengkap

### Skenario: PO dengan Termin 50% + 50%

1. **Create PO:**
   - PO Number: PO-2024-001
   - Grand Total: Rp 10.000.000
   - Payment Type: Termin
   - Detail Termin: "50% di muka, 50% setelah barang diterima"

2. **Payment Pertama (50% di muka):**
   - Payment Number: NFP-20240115-0001
   - Amount: Rp 5.000.000
   - Status: Approved → Paid
   - Progress: 50% (Rp 5.000.000 / Rp 10.000.000)

3. **Payment Kedua (50% setelah barang diterima):**
   - Payment Number: NFP-20240120-0001
   - Amount: Rp 5.000.000
   - Status: Approved → Paid
   - Progress: 100% (Rp 10.000.000 / Rp 10.000.000)
   - Status PO: **LUNAS** ✅

4. **Di Show Page Payment Kedua:**
   - Total PO: Rp 10.000.000
   - Sudah Dibayar: Rp 10.000.000 (2 pembayaran)
   - Sisa Pembayaran: Rp 0
   - Progress Bar: 100%
   - Badge: "PO sudah lunas!"

---

## 📞 Support

Jika ada pertanyaan atau masalah terkait fitur ini, silakan hubungi tim development.

---

**Last Updated:** 2024-01-20
**Version:** 1.0

