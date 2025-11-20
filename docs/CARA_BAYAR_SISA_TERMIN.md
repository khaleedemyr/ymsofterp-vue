# Cara Membayar Sisa Termin

Panduan lengkap untuk membuat pembayaran termin berikutnya (pembayaran kedua, ketiga, dst) untuk PO yang belum lunas.

---

## 📋 Prasyarat

Sebelum membuat pembayaran sisa termin, pastikan:
- ✅ PO sudah di-approve
- ✅ PO memiliki payment_type = 'termin'
- ✅ Masih ada sisa pembayaran (belum lunas)
- ✅ Payment sebelumnya sudah di-approve (opsional, bisa juga masih pending)

---

## 🚀 Langkah-Langkah Membayar Sisa Termin

### Step 1: Buka Menu Non Food Payment

1. Login ke sistem
2. Buka menu **Non Food Payment**
3. Klik tombol **Create** (atau ikon +) untuk membuat payment baru

---

### Step 2: Pilih PO yang Sama

1. Di form Create Payment, akan muncul list **Available Purchase Orders**
2. **Pilih PO yang sama** yang sudah pernah dibayar sebelumnya
   - PO ini akan memiliki payment_type = 'termin'
   - PO ini sudah memiliki payment sebelumnya

**Catatan:** 
- PO dengan termin yang belum lunas akan tetap muncul di list
- PO dengan termin yang sudah lunas (sisa = 0) tidak akan muncul di list

---

### Step 3: Lihat Informasi Pembayaran Termin

Setelah memilih PO, sistem akan menampilkan informasi pembayaran termin:

```
┌─────────────────────────────────────────────────────┐
│ Informasi Pembayaran Termin                         │
├─────────────────────────────────────────────────────┤
│ Total PO:        Rp 10.000.000                      │
│ Sudah Dibayar:   Rp 5.000.000                       │
│                  (1 pembayaran)                     │
│ Sisa Pembayaran: Rp 5.000.000                       │
│                                                     │
│ [████████████░░░░░░░░] 50%                          │
│ Progress: 50%                                       │
│                                                     │
│ Detail Termin:                                      │
│ 50% di muka, 50% setelah barang diterima            │
└─────────────────────────────────────────────────────┘
```

**Informasi yang ditampilkan:**
- **Total PO**: Grand total dari PO
- **Sudah Dibayar**: Total semua payment yang sudah dibuat (approved/paid)
- **Sisa Pembayaran**: Total PO - Sudah Dibayar
- **Progress Bar**: Visual progress pembayaran
- **Detail Termin**: Catatan termin yang diinput saat create PO

---

### Step 4: Input Amount Pembayaran

1. Di field **Amount**, sistem akan otomatis mengisi dengan **Sisa Pembayaran**
   ```
   Amount: [Rp 5.000.000] ← Auto-filled dengan sisa
   ```

2. **Pilih salah satu:**
   
   #### Opsi A: Bayar Sisa Penuh (Lunas)
   - Biarkan amount = Sisa Pembayaran
   - Atau klik tombol **Reset** untuk set ke sisa
   - Contoh: Sisa Rp 5.000.000 → Input Rp 5.000.000
   - Setelah payment ini, PO akan **LUNAS** ✅

   #### Opsi B: Bayar Sebagian (Partial)
   - Ubah amount menjadi lebih kecil dari sisa
   - Contoh: Sisa Rp 5.000.000 → Input Rp 3.000.000
   - Setelah payment ini, masih ada sisa Rp 2.000.000
   - Bisa buat payment lagi nanti

**Validasi:**
- ✅ Amount bisa = Sisa Pembayaran (lunas)
- ✅ Amount bisa < Sisa Pembayaran (partial)
- ❌ Amount tidak boleh > Sisa Pembayaran
- ❌ Amount harus > 0

---

### Step 5: Lengkapi Data Payment Lainnya

Isi field lainnya:
- **Payment Method**: Cash / Transfer / Check
- **Payment Date**: Tanggal pembayaran
- **Due Date**: (Optional) Tanggal jatuh tempo
- **Description**: Keterangan pembayaran
- **Reference Number**: (Optional) Nomor referensi (contoh: no. transfer)
- **Notes**: (Optional) Catatan tambahan

**Contoh:**
```
Payment Method: Transfer
Payment Date: 2024-01-20
Due Date: 2024-01-25
Description: Pembayaran termin kedua (50% setelah barang diterima)
Reference Number: TRF-20240120-001
Notes: Transfer via BCA
```

---

### Step 6: Submit Payment

1. Klik tombol **Submit** atau **Create Payment**
2. Sistem akan validasi:
   - ✅ Amount tidak melebihi sisa
   - ✅ PO masih memiliki sisa pembayaran
   - ✅ Data lengkap

3. Jika berhasil, payment akan dibuat dengan status **Pending**

---

### Step 7: Approve Payment

1. Payment yang baru dibuat akan berstatus **Pending**
2. Approver perlu approve payment ini
3. Setelah di-approve, payment akan masuk ke riwayat pembayaran
4. Progress pembayaran akan update otomatis

---

## 📊 Contoh Skenario Lengkap

### Skenario: PO Termin 50% + 50%

**PO:**
- PO Number: PO-2024-001
- Grand Total: Rp 10.000.000
- Payment Type: Termin
- Detail Termin: "50% di muka, 50% setelah barang diterima"

---

#### Payment Pertama (50% di muka)

1. **Create Payment:**
   - Pilih PO-2024-001
   - Info: Total Rp 10.000.000, Sudah Dibayar Rp 0, Sisa Rp 10.000.000
   - Amount: Rp 5.000.000
   - Submit

2. **Approve Payment:**
   - Payment #1: NFP-20240115-0001
   - Status: Approved → Paid
   - Progress: 50%

---

#### Payment Kedua (50% setelah barang diterima) - **INI CARA BAYAR SISA TERMIN**

1. **Buka Menu Non Food Payment → Create**

2. **Pilih PO yang Sama:**
   - Pilih PO-2024-001 (PO yang sama dengan payment pertama)

3. **Lihat Info Pembayaran:**
   ```
   Total PO:        Rp 10.000.000
   Sudah Dibayar:   Rp 5.000.000  (1 pembayaran)
   Sisa Pembayaran: Rp 5.000.000
   
   [████████████░░░░░░░░] 50%
   ```

4. **Input Amount:**
   - Amount: Rp 5.000.000 (sisa penuh, untuk lunas)
   - Atau: Rp 3.000.000 (partial, masih ada sisa Rp 2.000.000)

5. **Lengkapi Data:**
   - Payment Method: Transfer
   - Payment Date: 2024-01-20
   - Description: Pembayaran termin kedua (50% setelah barang diterima)

6. **Submit Payment**

7. **Approve Payment:**
   - Payment #2: NFP-20240120-0001
   - Status: Approved → Paid
   - Progress: 100% ✅ **LUNAS**

---

## 🎯 Tips Penting

### 1. Cek Sisa Pembayaran Sebelum Input Amount

Selalu cek **Sisa Pembayaran** di info box sebelum input amount:
- Jika ingin lunas → Amount = Sisa Pembayaran
- Jika partial → Amount < Sisa Pembayaran

### 2. Gunakan Description untuk Tracking

Gunakan field **Description** untuk mencatat:
- "Pembayaran termin pertama (50% di muka)"
- "Pembayaran termin kedua (50% setelah barang diterima)"
- "Pembayaran termin ketiga (sisa 30%)"

Ini membantu tracking dan audit trail.

### 3. Cek Progress di Show Page

Setelah membuat payment, buka Show page untuk melihat:
- Progress bar terbaru
- Total paid yang sudah update
- Sisa pembayaran yang sudah berkurang
- Riwayat semua payment

### 4. Validasi Otomatis

Sistem akan otomatis:
- ✅ Mencegah amount > sisa pembayaran
- ✅ Update progress setelah payment di-approve
- ✅ Menghitung total paid dari semua payment (approved/paid)
- ✅ Menghitung sisa pembayaran otomatis

---

## ⚠️ Troubleshooting

### Problem: "Jumlah pembayaran melebihi sisa yang harus dibayar"

**Penyebab:**
- Input amount > Sisa Pembayaran

**Solusi:**
1. Cek **Sisa Pembayaran** di info box
2. Kurangi amount sesuai sisa
3. Atau klik **Reset** untuk set ke sisa penuh

---

### Problem: PO tidak muncul di list Available PO

**Penyebab:**
- PO sudah lunas (sisa = 0)
- PO belum di-approve
- PO sudah memiliki payment yang belum di-cancel

**Solusi:**
1. Cek di Show page payment sebelumnya
2. Pastikan masih ada sisa pembayaran
3. Jika sudah lunas, tidak perlu buat payment lagi

---

### Problem: Progress tidak update setelah approve

**Penyebab:**
- Payment belum di-approve
- Payment di-cancel

**Solusi:**
1. Pastikan payment sudah di-approve
2. Refresh halaman Show
3. Progress akan update otomatis setelah approve

---

### Problem: Ingin bayar lebih dari sisa (overpayment)

**Catatan:**
- Sistem tidak mengizinkan overpayment
- Amount maksimal = Sisa Pembayaran
- Jika ada kelebihan, bisa dibuat payment terpisah atau dicatat di notes

---

## 📝 Checklist Membayar Sisa Termin

- [ ] Buka menu Non Food Payment → Create
- [ ] Pilih PO yang sama (yang sudah punya payment sebelumnya)
- [ ] Cek info: Total PO, Sudah Dibayar, Sisa Pembayaran
- [ ] Input amount (≤ Sisa Pembayaran)
- [ ] Lengkapi data payment (method, date, description, dll)
- [ ] Submit payment
- [ ] Approve payment
- [ ] Cek progress di Show page (harus update)

---

## 🔄 Flow Diagram

```
┌─────────────────────────────────────────┐
│ PO dengan Termin (Belum Lunas)         │
│ Total: Rp 10.000.000                    │
│ Sudah Dibayar: Rp 5.000.000            │
│ Sisa: Rp 5.000.000                      │
└──────────────┬──────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────┐
│ Non Food Payment → Create                │
│ Pilih PO yang sama                       │
└──────────────┬──────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────┐
│ Lihat Info Pembayaran Termin            │
│ - Total PO                              │
│ - Sudah Dibayar                         │
│ - Sisa Pembayaran                       │
│ - Progress Bar                          │
└──────────────┬──────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────┐
│ Input Amount                            │
│ - Auto-filled dengan Sisa               │
│ - Bisa diubah (≤ Sisa)                  │
│ - Bisa bayar penuh atau partial         │
└──────────────┬──────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────┐
│ Lengkapi Data Payment                   │
│ - Payment Method                        │
│ - Payment Date                          │
│ - Description                           │
│ - dll                                   │
└──────────────┬──────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────┐
│ Submit Payment                          │
│ Status: Pending                         │
└──────────────┬──────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────┐
│ Approve Payment                         │
│ Status: Approved → Paid                 │
│ Progress Update: 50% → 100% (jika lunas)│
└─────────────────────────────────────────┘
```

---

## 💡 Contoh Real Case

### Case 1: Bayar Sisa Penuh (Lunas)

**Situasi:**
- PO: Rp 10.000.000 (Termin)
- Payment #1: Rp 5.000.000 (approved)
- Sisa: Rp 5.000.000

**Langkah:**
1. Create Payment baru
2. Pilih PO yang sama
3. Amount: Rp 5.000.000 (sisa penuh)
4. Submit & Approve
5. ✅ PO LUNAS (Progress 100%)

---

### Case 2: Bayar Sebagian (Masih Ada Sisa)

**Situasi:**
- PO: Rp 10.000.000 (Termin)
- Payment #1: Rp 3.000.000 (approved)
- Sisa: Rp 7.000.000

**Langkah:**
1. Create Payment baru
2. Pilih PO yang sama
3. Amount: Rp 4.000.000 (partial)
4. Submit & Approve
5. Sisa baru: Rp 3.000.000
6. Bisa buat payment lagi nanti

---

### Case 3: Multiple Payments (3x)

**Situasi:**
- PO: Rp 12.000.000 (Termin: "30% + 40% + 30%")
- Payment #1: Rp 3.600.000 (30%)
- Payment #2: Rp 4.800.000 (40%)
- Sisa: Rp 3.600.000 (30% terakhir)

**Langkah:**
1. Create Payment #3
2. Pilih PO yang sama
3. Amount: Rp 3.600.000 (sisa penuh)
4. Submit & Approve
5. ✅ PO LUNAS (Progress 100%)

---

**Last Updated:** 2024-01-20

