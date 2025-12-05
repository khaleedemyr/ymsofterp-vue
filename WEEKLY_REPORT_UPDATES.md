# Weekly Outlet FB Revenue Report - Updates

## Perubahan yang Telah Dibuat

### 1. Pengelompokan Mingguan Baru
- **Sebelum**: Minggu diakhiri pada hari Minggu
- **Sesudah**: Minggu dikelompokkan per 7 hari (dimulai dari tanggal 1)

### 2. Logika Pengelompokan
- **Week 1**: Hari 1-7
- **Week 2**: Hari 8-14  
- **Week 3**: Hari 15-21
- **Week 4**: Hari 22-31 (bisa lebih dari 7 hari)

### 3. Merge Row Week
- Kolom WEEK sekarang menggunakan `rowspan` untuk merge
- Setiap minggu akan menampilkan nomor minggu hanya sekali di baris pertama

### 4. Struktur Tabel Baru
```html
<table>
  <thead>
    <tr>
      <th rowspan="2">WEEK</th>  <!-- Merge 2 baris -->
      <th>DATE</th>
      <th>DAY</th>
      <th>FB REVENUE</th>
      <th>COVER</th>
      <th>A/C</th>
    </tr>
  </thead>
  <tbody>
    <template v-for="(weekGroup, weekNum) in groupByWeek(weekData)">
      <tr v-for="(day, index) in weekGroup">
        <td v-if="index === 0" :rowspan="weekGroup.length">{{ day.week }}</td>
        <td>{{ formatDate(day.date) }}</td>
        <td>{{ day.day }}</td>
        <td>{{ formatNumber(day.revenue) }}</td>
        <td>{{ day.cover }}</td>
        <td>{{ formatNumber(day.avg_check) }}</td>
      </tr>
    </template>
  </tbody>
</table>
```

## File yang Diperbarui

### 1. Controller
- `app/Http/Controllers/ReportWeeklyOutletFbRevenueController.php`
  - Logika pengelompokan mingguan diperbarui
  - Menambahkan logging untuk debugging

### 2. Vue Component  
- `resources/js/Pages/Report/ReportWeeklyOutletFbRevenue.vue`
  - Template table diperbarui dengan rowspan
  - Menambahkan fungsi `groupByWeek()`
  - Merge row untuk kolom WEEK

## Contoh Hasil

### Untuk Bulan 31 Hari (Agustus):
- **Week 1**: 7 hari (1-7 Agustus)
- **Week 2**: 7 hari (8-14 Agustus)  
- **Week 3**: 7 hari (15-21 Agustus)
- **Week 4**: 10 hari (22-31 Agustus)

### Untuk Bulan 30 Hari (April):
- **Week 1**: 7 hari (1-7 April)
- **Week 2**: 7 hari (8-14 April)
- **Week 3**: 7 hari (15-21 April)  
- **Week 4**: 9 hari (22-30 April)

### Untuk Bulan 28 Hari (Februari):
- **Week 1**: 7 hari (1-7 Februari)
- **Week 2**: 7 hari (8-14 Februari)
- **Week 3**: 7 hari (15-21 Februari)
- **Week 4**: 7 hari (22-28 Februari)

## Fitur Tetap Berfungsi
- ✅ Highlight weekend (orange)
- ✅ Highlight hari libur (merah)
- ✅ Keterangan hari libur (dari field keterangan tbl_kalender_perusahaan)
- ✅ Weekly summary
- ✅ MTD summary
- ✅ Budget input dan display
- ✅ MTD performance calculation

## Perbaikan Terbaru

### 1. Logika Pengelompokan Mingguan
- **Diperbaiki**: Logika pengelompokan yang salah menyebabkan Week 4 berisi data Week 3
- **Sekarang**: Week 1-3 masing-masing 7 hari, Week 4 berisi SEMUA hari yang tersisa dengan nomor week yang benar
- **Contoh**: Bulan 31 hari = Week 1 (7) + Week 2 (7) + Week 3 (7) + Week 4 (10) = 31 hari
- **Perbaikan**: Update nomor week untuk hari-hari yang masuk ke Week 4

### 5. Perbaikan Frontend Template
- **Diperbaiki**: Template Vue yang salah menggunakan `groupByWeek()` pada data yang sudah dikelompokkan
- **Sekarang**: Template langsung menggunakan `weekData` tanpa pengelompokan tambahan
- **Hasil**: Week 4 sekarang menampilkan nomor "4" di kolom WEEK dengan benar

### 2. Keterangan Hari Libur
- **Ditambahkan**: Keterangan hari libur dari field `keterangan` di tabel `tbl_kalender_perusahaan`
- **Tampilan**: Keterangan muncul di bawah nama hari dengan warna merah dan ukuran font kecil

### 3. Contoh Hasil yang Benar
- **Bulan 31 hari**: Week 1 (7) + Week 2 (7) + Week 3 (7) + Week 4 (10) = 31 hari
- **Bulan 30 hari**: Week 1 (7) + Week 2 (7) + Week 3 (7) + Week 4 (9) = 30 hari  
- **Bulan 28 hari**: Week 1 (7) + Week 2 (7) + Week 3 (7) + Week 4 (7) = 28 hari
- **Tidak ada Week 5**: Semua hari yang tersisa masuk ke Week 4

### 4. Perbaikan Error
- **Error**: `Cannot use object of type stdClass as array`
- **Penyebab**: Menggunakan `->toArray()` pada Collection yang sudah di-keyBy
- **Solusi**: Menggunakan Collection methods (`->has()`, `->get()`) dan object notation (`->keterangan`) 