# PANDUAN LAPORAN JURNAL (JURNAL REPORTS GUIDE)

**Created:** 2026-01-30  
**Description:** Analisis lengkap berbagai jenis laporan yang bisa dibuat dengan tabel `jurnal` dan `jurnal_global`

---

## 📋 DAFTAR ISI

1. [Struktur Data](#struktur-data)
2. [Jenis-Jenis Laporan](#jenis-jenis-laporan)
3. [Detail Implementasi Setiap Laporan](#detail-implementasi-setiap-laporan)
4. [Query Examples](#query-examples)
5. [Prioritas Pengembangan](#prioritas-pengembangan)

---

## 📊 STRUKTUR DATA

### Tabel `jurnal`
- **Primary Key:** `id`
- **Indexes:** `no_jurnal`, `tanggal`, `coa_debit_id`, `coa_kredit_id`, `outlet_id`, `reference_type`, `reference_id`, `status`
- **Foreign Keys:** 
  - `coa_debit_id` → `chart_of_accounts.id`
  - `coa_kredit_id` → `chart_of_accounts.id`
  - `outlet_id` → `tbl_data_outlet.id_outlet`
  - `created_by`, `updated_by` → `users.id`
- **Fields Penting:**
  - `no_jurnal`: Nomor jurnal (Format: JRN-YYYYMM####)
  - `tanggal`: Tanggal transaksi
  - `coa_debit_id`, `coa_kredit_id`: COA untuk debit dan kredit
  - `jumlah_debit`, `jumlah_kredit`: Jumlah transaksi
  - `outlet_id`: ID Outlet (bisa NULL untuk transaksi pusat)
  - `reference_type`, `reference_id`: Tracking sumber transaksi (pos_order, outlet_payment, dll)
  - `status`: draft, posted, cancelled

### Tabel `jurnal_global`
- **Primary Key:** `id`
- **Indexes:** Sama seperti `jurnal` + `source_module`, `source_id`, `posted_at`, `posted_by`
- **Fields Tambahan:**
  - `source_module`: Module sumber (jurnal, pos_order, outlet_payment, dll)
  - `source_id`: ID dari source module
  - `posted_at`: Waktu jurnal di-post
  - `posted_by`: User yang mem-post jurnal

### Relasi dengan Tabel Lain
- `chart_of_accounts`: COA (code, name, type: Asset/Liability/Equity/Revenue/Expense)
- `tbl_data_outlet`: Data outlet (nama_outlet, kode_outlet, dll)
- `users`: User yang membuat/mengupdate/mem-post jurnal

---

## 📈 JENIS-JENIS LAPORAN

### A. LAPORAN AKUNTANSI STANDAR

#### 1. **Buku Besar (General Ledger)**
**Deskripsi:** Laporan detail semua transaksi per COA dalam periode tertentu.

**Fitur:**
- Filter: Tanggal, Outlet, COA, Status
- Grouping: Per COA, Per Tanggal
- Menampilkan: Saldo awal, Debit, Kredit, Saldo akhir
- Sorting: Tanggal, No Jurnal, COA

**Use Case:**
- Audit transaksi per akun
- Tracking perubahan saldo akun
- Verifikasi double entry

**Data Source:** `jurnal` atau `jurnal_global`

---

#### 2. **Neraca Saldo (Trial Balance)**
**Deskripsi:** Ringkasan saldo semua COA pada tanggal tertentu.

**Fitur:**
- Filter: Tanggal, Outlet
- Menampilkan: Nama COA, Code COA, Total Debit, Total Kredit, Saldo
- Grouping: Per COA Type (Asset, Liability, Equity, Revenue, Expense)
- Validasi: Total Debit harus = Total Kredit

**Use Case:**
- Validasi keseimbangan buku besar
- Persiapan laporan keuangan
- Quick check saldo akun

**Data Source:** `jurnal_global` (hanya status = 'posted')

---

#### 3. **Laporan Laba Rugi (Profit & Loss / Income Statement)**
**Deskripsi:** Laporan pendapatan dan beban dalam periode tertentu.

**Fitur:**
- Filter: Tanggal (periode), Outlet
- Menampilkan:
  - **Pendapatan:** Total Revenue (COA type = 'Revenue')
  - **Beban:** Total Expense (COA type = 'Expense')
  - **Laba/Rugi:** Pendapatan - Beban
- Breakdown: Per COA, Per Outlet
- Comparison: Bulan ini vs Bulan lalu, Tahun ini vs Tahun lalu

**Use Case:**
- Evaluasi profitabilitas outlet
- Analisis tren pendapatan dan beban
- Decision making untuk strategi bisnis

**Data Source:** `jurnal_global` (COA type = Revenue/Expense, status = 'posted')

---

#### 4. **Laporan Posisi Keuangan (Balance Sheet)**
**Deskripsi:** Laporan aset, kewajiban, dan ekuitas pada tanggal tertentu.

**Fitur:**
- Filter: Tanggal (tanggal tertentu), Outlet
- Menampilkan:
  - **Aset:** Total Asset (COA type = 'Asset')
  - **Kewajiban:** Total Liability (COA type = 'Liability')
  - **Ekuitas:** Total Equity (COA type = 'Equity')
  - **Validasi:** Aset = Kewajiban + Ekuitas
- Breakdown: Per kategori COA (Kas, Bank, Piutang, Hutang, dll)

**Use Case:**
- Evaluasi kesehatan keuangan
- Analisis struktur modal
- Compliance reporting

**Data Source:** `jurnal_global` (COA type = Asset/Liability/Equity, status = 'posted')

---

#### 5. **Laporan Arus Kas (Cash Flow Statement)**
**Deskripsi:** Laporan arus kas masuk dan keluar dalam periode tertentu.

**Fitur:**
- Filter: Tanggal (periode), Outlet
- Kategori:
  - **Aktivitas Operasi:** Kas dari operasi bisnis (Revenue, Expense operasional)
  - **Aktivitas Investasi:** Kas dari investasi (jika ada)
  - **Aktivitas Pendanaan:** Kas dari pendanaan (jika ada)
- Menampilkan: Saldo awal, Kas masuk, Kas keluar, Saldo akhir

**Use Case:**
- Analisis likuiditas
- Perencanaan cash flow
- Evaluasi kemampuan bayar

**Data Source:** `jurnal_global` (COA terkait Kas/Bank, status = 'posted')

---

### B. LAPORAN OPERASIONAL

#### 6. **Jurnal Harian (Daily Journal)**
**Deskripsi:** Laporan semua transaksi jurnal per hari.

**Fitur:**
- Filter: Tanggal, Outlet, Status
- Menampilkan: No Jurnal, Tanggal, Keterangan, COA Debit, COA Kredit, Jumlah, Reference
- Sorting: No Jurnal, Tanggal, Waktu dibuat
- Export: PDF, Excel

**Use Case:**
- Review transaksi harian
- Audit trail harian
- Dokumentasi transaksi

**Data Source:** `jurnal` atau `jurnal_global`

---

#### 7. **Jurnal per Outlet**
**Deskripsi:** Laporan jurnal dikelompokkan per outlet.

**Fitur:**
- Filter: Tanggal (periode), Outlet (multiple selection)
- Menampilkan: Per outlet: Total Debit, Total Kredit, Saldo
- Comparison: Perbandingan antar outlet
- Breakdown: Per COA per outlet

**Use Case:**
- Evaluasi performa outlet
- Analisis perbandingan outlet
- Alokasi biaya per outlet

**Data Source:** `jurnal_global` (group by `outlet_id`)

---

#### 8. **Jurnal per COA**
**Deskripsi:** Laporan jurnal dikelompokkan per Chart of Account.

**Fitur:**
- Filter: Tanggal (periode), COA (multiple selection), COA Type
- Menampilkan: Per COA: Total Debit, Total Kredit, Saldo, Jumlah Transaksi
- Breakdown: Per tanggal, Per outlet
- Trend: Grafik per bulan

**Use Case:**
- Analisis penggunaan akun
- Tracking perubahan saldo akun
- Identifikasi akun yang aktif

**Data Source:** `jurnal_global` (group by `coa_debit_id` atau `coa_kredit_id`)

---

#### 9. **Jurnal per Source Module**
**Deskripsi:** Laporan jurnal dikelompokkan berdasarkan sumber transaksi.

**Fitur:**
- Filter: Tanggal (periode), Source Module (pos_order, outlet_payment, dll)
- Menampilkan: Per source: Total Debit, Total Kredit, Jumlah Transaksi
- Breakdown: Per tanggal, Per outlet
- Detail: Link ke source transaction

**Use Case:**
- Tracking transaksi dari berbagai modul
- Verifikasi integrasi modul dengan jurnal
- Audit trail per modul

**Data Source:** `jurnal_global` (group by `source_module`)

---

### C. LAPORAN PIUTANG & HUTANG

#### 10. **Laporan Piutang (Receivables Report)**
**Deskripsi:** Laporan semua piutang yang belum dibayar.

**Fitur:**
- Filter: Tanggal, Outlet, COA Piutang (Officer Check, City Ledger, Investor, dll)
- Menampilkan:
  - **Piutang Officer Check:** Total, Jumlah transaksi, Rata-rata
  - **Piutang City Ledger:** Total, Jumlah transaksi, Rata-rata
  - **Piutang Investor:** Total, Jumlah transaksi, Rata-rata
  - **Piutang Lainnya:** Total semua piutang
- Breakdown: Per outlet, Per tanggal
- Aging: 0-30 hari, 31-60 hari, 61-90 hari, >90 hari

**Use Case:**
- Monitoring piutang
- Analisis aging piutang
- Perencanaan penagihan

**Data Source:** `jurnal_global` (COA type = 'Asset', name LIKE '%Piutang%', status = 'posted')

---

#### 11. **Laporan Hutang (Payables Report)**
**Deskripsi:** Laporan semua hutang yang belum dibayar.

**Fitur:**
- Filter: Tanggal, Outlet, COA Hutang
- Menampilkan: Total hutang per COA, Jumlah transaksi, Rata-rata
- Breakdown: Per outlet, Per tanggal
- Aging: 0-30 hari, 31-60 hari, 61-90 hari, >90 hari

**Use Case:**
- Monitoring hutang
- Analisis aging hutang
- Perencanaan pembayaran

**Data Source:** `jurnal_global` (COA type = 'Liability', name LIKE '%Hutang%', status = 'posted')

---

### D. LAPORAN KAS & BANK

#### 12. **Laporan Kas & Bank (Cash & Bank Report)**
**Deskripsi:** Laporan pergerakan kas dan bank.

**Fitur:**
- Filter: Tanggal (periode), Outlet, Bank Account
- Menampilkan:
  - **Kas Tunai:** Saldo awal, Masuk, Keluar, Saldo akhir
  - **Bank:** Per bank account: Saldo awal, Masuk, Keluar, Saldo akhir
- Breakdown: Per tanggal, Per outlet
- Comparison: Saldo di jurnal vs saldo di bank_books

**Use Case:**
- Monitoring kas dan bank
- Rekonsiliasi bank
- Analisis cash flow

**Data Source:** `jurnal_global` (COA terkait Kas/Bank, status = 'posted') + `bank_books`

---

#### 13. **Laporan Rekonsiliasi Bank**
**Deskripsi:** Laporan untuk mencocokkan saldo jurnal dengan saldo bank.

**Fitur:**
- Filter: Tanggal, Bank Account
- Menampilkan:
  - Saldo di Jurnal (dari `jurnal_global`)
  - Saldo di Bank Books (dari `bank_books`)
  - Selisih (jika ada)
- Detail: List transaksi yang berbeda
- Export: Excel untuk rekonsiliasi manual

**Use Case:**
- Rekonsiliasi bank bulanan
- Identifikasi transaksi yang belum match
- Audit bank reconciliation

**Data Source:** `jurnal_global` + `bank_books` (join by COA)

---

### E. LAPORAN POS-SPECIFIC

#### 14. **Laporan Pendapatan POS per Outlet**
**Deskripsi:** Laporan pendapatan dari POS per outlet.

**Fitur:**
- Filter: Tanggal (periode), Outlet
- Menampilkan:
  - Total Pendapatan (COA Pendapatan Penjualan)
  - Breakdown per Payment Type:
    - Cash/Tunai
    - Bank (per bank account)
    - Perjamuan
    - Guest Satisfaction
    - Officer Check
    - Investor
    - City Ledger
- Comparison: Per outlet, Per bulan
- Trend: Grafik pendapatan per hari/bulan

**Use Case:**
- Evaluasi performa penjualan outlet
- Analisis metode pembayaran
- Perencanaan target penjualan

**Data Source:** `jurnal_global` (source_module = 'pos_order', COA kredit = Pendapatan, status = 'posted')

---

#### 15. **Laporan Payment Type Analysis**
**Deskripsi:** Analisis penggunaan metode pembayaran di POS.

**Fitur:**
- Filter: Tanggal (periode), Outlet
- Menampilkan:
  - Total per Payment Type
  - Persentase per Payment Type
  - Rata-rata transaksi per Payment Type
  - Jumlah transaksi per Payment Type
- Visualization: Pie chart, Bar chart
- Comparison: Per outlet, Per periode

**Use Case:**
- Analisis preferensi pembayaran customer
- Evaluasi efektivitas payment methods
- Perencanaan strategi payment

**Data Source:** `jurnal_global` (source_module = 'pos_order', group by COA debit)

---

### F. LAPORAN AUDIT & COMPLIANCE

#### 16. **Audit Trail Report**
**Deskripsi:** Laporan tracking semua perubahan jurnal.

**Fitur:**
- Filter: Tanggal, User, Action (create, update, post, cancel)
- Menampilkan:
  - User yang membuat jurnal (`created_by`)
  - User yang mengupdate jurnal (`updated_by`)
  - User yang mem-post jurnal (`posted_by`)
  - Waktu: `created_at`, `updated_at`, `posted_at`
  - Status perubahan
- Detail: Before/After values (jika ada)

**Use Case:**
- Audit compliance
- Tracking user activity
- Investigasi transaksi

**Data Source:** `jurnal_global` (semua field audit: created_by, updated_by, posted_by, posted_at)

---

#### 17. **Laporan Jurnal yang Belum di-Post**
**Deskripsi:** Laporan jurnal dengan status draft.

**Fitur:**
- Filter: Tanggal, Outlet, User
- Menampilkan: List jurnal dengan status = 'draft'
- Detail: No Jurnal, Tanggal, Keterangan, Jumlah, User yang membuat
- Action: Batch post, Cancel

**Use Case:**
- Monitoring jurnal draft
- Workflow approval
- Prevent posting error

**Data Source:** `jurnal` atau `jurnal_global` (status = 'draft')

---

#### 18. **Laporan Jurnal yang Dibatalkan (Cancelled)**
**Deskripsi:** Laporan jurnal dengan status cancelled.

**Fitur:**
- Filter: Tanggal, Outlet, User, Reason
- Menampilkan: List jurnal dengan status = 'cancelled'
- Detail: No Jurnal, Tanggal, Keterangan, Jumlah, User yang membatalkan, Alasan

**Use Case:**
- Audit cancelled transactions
- Analisis alasan pembatalan
- Compliance reporting

**Data Source:** `jurnal` atau `jurnal_global` (status = 'cancelled')

---

### G. LAPORAN ANALITIK

#### 19. **Laporan Trend Pendapatan**
**Deskripsi:** Analisis tren pendapatan dalam periode tertentu.

**Fitur:**
- Filter: Tanggal (periode panjang: bulan/tahun), Outlet
- Menampilkan:
  - Grafik trend pendapatan (line chart)
  - Growth rate (bulanan, tahunan)
  - Seasonal analysis
  - Forecast (jika ada)
- Breakdown: Per outlet, Per COA pendapatan

**Use Case:**
- Analisis tren bisnis
- Perencanaan strategi
- Budgeting

**Data Source:** `jurnal_global` (COA type = 'Revenue', status = 'posted', group by bulan/tahun)

---

#### 20. **Laporan Perbandingan Outlet**
**Deskripsi:** Perbandingan performa antar outlet.

**Fitur:**
- Filter: Tanggal (periode), Multiple Outlets
- Menampilkan:
  - Total Pendapatan per Outlet
  - Total Beban per Outlet
  - Laba/Rugi per Outlet
  - ROI per Outlet (jika ada)
- Visualization: Bar chart, Comparison table
- Ranking: Top performing outlets

**Use Case:**
- Evaluasi performa outlet
- Identifikasi outlet yang perlu improvement
- Alokasi resources

**Data Source:** `jurnal_global` (group by `outlet_id`, COA type = Revenue/Expense)

---

#### 21. **Laporan COA Usage Analysis**
**Deskripsi:** Analisis penggunaan Chart of Accounts.

**Fitur:**
- Filter: Tanggal (periode)
- Menampilkan:
  - COA yang paling sering digunakan
  - COA yang tidak pernah digunakan
  - Total transaksi per COA
  - Total nilai per COA
- Recommendation: COA yang bisa dihapus/merge

**Use Case:**
- Optimasi COA structure
- Cleanup unused COA
- COA maintenance

**Data Source:** `jurnal_global` (group by `coa_debit_id`, `coa_kredit_id`)

---

## 🔍 QUERY EXAMPLES

### Example 1: Buku Besar per COA
```sql
SELECT 
    coa.code,
    coa.name,
    coa.type,
    SUM(CASE WHEN j.coa_debit_id = coa.id THEN j.jumlah_debit ELSE 0 END) as total_debit,
    SUM(CASE WHEN j.coa_kredit_id = coa.id THEN j.jumlah_kredit ELSE 0 END) as total_kredit,
    (SUM(CASE WHEN j.coa_debit_id = coa.id THEN j.jumlah_debit ELSE 0 END) - 
     SUM(CASE WHEN j.coa_kredit_id = coa.id THEN j.jumlah_kredit ELSE 0 END)) as saldo
FROM jurnal_global j
INNER JOIN chart_of_accounts coa ON (coa.id = j.coa_debit_id OR coa.id = j.coa_kredit_id)
WHERE j.tanggal BETWEEN '2026-01-01' AND '2026-01-31'
    AND j.status = 'posted'
GROUP BY coa.id, coa.code, coa.name, coa.type
ORDER BY coa.code;
```

### Example 2: Neraca Saldo
```sql
SELECT 
    coa.type,
    coa.code,
    coa.name,
    SUM(CASE WHEN j.coa_debit_id = coa.id THEN j.jumlah_debit ELSE 0 END) as total_debit,
    SUM(CASE WHEN j.coa_kredit_id = coa.id THEN j.jumlah_kredit ELSE 0 END) as total_kredit
FROM jurnal_global j
INNER JOIN chart_of_accounts coa ON (coa.id = j.coa_debit_id OR coa.id = j.coa_kredit_id)
WHERE j.tanggal <= '2026-01-31'
    AND j.status = 'posted'
GROUP BY coa.id, coa.type, coa.code, coa.name
ORDER BY coa.type, coa.code;
```

### Example 3: Laporan Laba Rugi
```sql
SELECT 
    coa.code,
    coa.name,
    SUM(CASE WHEN j.coa_kredit_id = coa.id THEN j.jumlah_kredit ELSE 0 END) as pendapatan,
    SUM(CASE WHEN j.coa_debit_id = coa.id THEN j.jumlah_debit ELSE 0 END) as beban
FROM jurnal_global j
INNER JOIN chart_of_accounts coa ON (coa.id = j.coa_debit_id OR coa.id = j.coa_kredit_id)
WHERE j.tanggal BETWEEN '2026-01-01' AND '2026-01-31'
    AND j.status = 'posted'
    AND coa.type IN ('Revenue', 'Expense')
GROUP BY coa.id, coa.code, coa.name, coa.type
ORDER BY coa.type, coa.code;
```

### Example 4: Laporan Piutang
```sql
SELECT 
    coa.code,
    coa.name,
    o.nama_outlet,
    SUM(j.jumlah_debit) as total_piutang,
    COUNT(DISTINCT j.reference_id) as jumlah_transaksi,
    MIN(j.tanggal) as tanggal_terlama,
    MAX(j.tanggal) as tanggal_terbaru
FROM jurnal_global j
INNER JOIN chart_of_accounts coa ON coa.id = j.coa_debit_id
LEFT JOIN tbl_data_outlet o ON o.id_outlet = j.outlet_id
WHERE j.tanggal BETWEEN '2026-01-01' AND '2026-01-31'
    AND j.status = 'posted'
    AND coa.type = 'Asset'
    AND (coa.name LIKE '%Piutang%' OR coa.name LIKE '%Receivable%')
GROUP BY coa.id, coa.code, coa.name, o.nama_outlet
ORDER BY total_piutang DESC;
```

### Example 5: Laporan Pendapatan POS per Payment Type
```sql
SELECT 
    coa_debit.code as payment_coa_code,
    coa_debit.name as payment_type,
    o.nama_outlet,
    SUM(j.jumlah_debit) as total_pendapatan,
    COUNT(DISTINCT j.reference_id) as jumlah_transaksi
FROM jurnal_global j
INNER JOIN chart_of_accounts coa_debit ON coa_debit.id = j.coa_debit_id
INNER JOIN chart_of_accounts coa_kredit ON coa_kredit.id = j.coa_kredit_id
LEFT JOIN tbl_data_outlet o ON o.id_outlet = j.outlet_id
WHERE j.tanggal BETWEEN '2026-01-01' AND '2026-01-31'
    AND j.status = 'posted'
    AND j.source_module = 'pos_order'
    AND coa_kredit.code = '4001' -- COA Pendapatan Penjualan
GROUP BY coa_debit.id, coa_debit.code, coa_debit.name, o.nama_outlet
ORDER BY total_pendapatan DESC;
```

---

## 🎯 PRIORITAS PENGEMBANGAN

### **PRIORITAS TINGGI (High Priority)**
1. ✅ **Buku Besar (General Ledger)** - Fundamental untuk audit
2. ✅ **Neraca Saldo (Trial Balance)** - Validasi keseimbangan
3. ✅ **Laporan Laba Rugi (P&L)** - Evaluasi profitabilitas
4. ✅ **Jurnal Harian (Daily Journal)** - Review transaksi harian
5. ✅ **Laporan Pendapatan POS per Outlet** - Evaluasi performa outlet

### **PRIORITAS SEDANG (Medium Priority)**
6. ✅ **Laporan Piutang (Receivables)** - Monitoring piutang
7. ✅ **Laporan Kas & Bank** - Monitoring cash flow
8. ✅ **Laporan Payment Type Analysis** - Analisis metode pembayaran
9. ✅ **Jurnal per Outlet** - Perbandingan outlet
10. ✅ **Audit Trail Report** - Compliance

### **PRIORITAS RENDAH (Low Priority)**
11. ✅ **Laporan Posisi Keuangan (Balance Sheet)** - Laporan keuangan lengkap
12. ✅ **Laporan Arus Kas (Cash Flow)** - Analisis cash flow detail
13. ✅ **Laporan Trend Pendapatan** - Analisis tren
14. ✅ **Laporan Perbandingan Outlet** - Analisis komparatif
15. ✅ **Laporan COA Usage Analysis** - Optimasi COA

---

## 📝 CATATAN PENTING

### 1. **Perbedaan `jurnal` vs `jurnal_global`**
- **`jurnal`**: Jurnal outlet-specific, untuk tracking per outlet
- **`jurnal_global`**: Jurnal global dengan tracking posting, untuk laporan konsolidasi

### 2. **Status Jurnal**
- **`draft`**: Jurnal belum di-post (belum masuk ke laporan keuangan)
- **`posted`**: Jurnal sudah di-post (masuk ke laporan keuangan)
- **`cancelled`**: Jurnal dibatalkan (tidak masuk ke laporan keuangan)

### 3. **Filter yang Umum Digunakan**
- **Tanggal:** `tanggal BETWEEN 'start_date' AND 'end_date'`
- **Outlet:** `outlet_id = ?` atau `outlet_id IN (?)`
- **Status:** `status = 'posted'` (untuk laporan keuangan)
- **COA Type:** Join dengan `chart_of_accounts` dan filter `type`

### 4. **Performance Considerations**
- Gunakan index yang sudah ada: `tanggal`, `outlet_id`, `coa_debit_id`, `coa_kredit_id`, `status`
- Untuk laporan besar, pertimbangkan pagination atau summary table
- Cache hasil query untuk laporan yang sering diakses

### 5. **Export Format**
- **PDF:** Untuk laporan formal (Buku Besar, Neraca Saldo, P&L)
- **Excel:** Untuk analisis lebih lanjut (Jurnal Harian, Detail Report)
- **CSV:** Untuk import ke sistem lain

---

## 🔗 REFERENSI

- **POS Jurnal Implementation Guide:** `POS_JURNAL_IMPLEMENTATION_GUIDE.md`
- **Create Jurnal Tables:** `database/create_jurnal_tables.sql`
- **JurnalService:** `app/Services/JurnalService.php`

---

**Last Updated:** 2026-01-30  
**Author:** AI Assistant  
**Version:** 1.0
