# Organization Chart

Organization Chart adalah fitur untuk menampilkan struktur organisasi perusahaan dalam bentuk hierarki visual.

## Struktur Database

### Tabel `users`
- `id` - Primary key
- `nama_lengkap` - Nama lengkap karyawan
- `id_jabatan` - Foreign key ke tabel jabatan
- `id_outlet` - Foreign key ke tabel outlet
- `avatar` - Path file avatar karyawan
- `status` - Status karyawan (A = Active)

### Tabel `tbl_data_jabatan`
- `id_jabatan` - Primary key
- `nama_jabatan` - Nama jabatan
- `id_atasan` - Foreign key ke id_jabatan (atasan langsung)
- `status` - Status jabatan (A = Active)

## Cara Menggunakan

1. **Akses Organization Chart**
   - Buka URL: `/organization-chart`
   - Atau melalui menu navigasi (jika sudah ditambahkan)

2. **Fitur yang Tersedia**
   - Menampilkan struktur organisasi dalam bentuk tree
   - Klik pada kartu karyawan untuk melihat detail
   - Avatar otomatis menggunakan inisial jika tidak ada foto
   - Responsive design untuk mobile dan desktop

## Komponen yang Dibuat

### 1. OrganizationChart.vue
- Komponen utama untuk menampilkan organization chart
- Mengambil data dari API
- Menampilkan modal detail karyawan

### 2. OrganizationNode.vue
- Komponen untuk menampilkan setiap node karyawan
- Menampilkan avatar, nama, dan jabatan
- Menampilkan jumlah bawahan
- Konektor visual antar level

### 3. OrganizationChartController.php
- Controller untuk menangani API organization chart
- Query database dengan join table
- Menghitung jumlah bawahan

## API Endpoints

### GET `/api/organization-chart`
Mengambil data struktur organisasi

**Response:**
```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "nama_lengkap": "John Doe",
            "id_jabatan": 1,
            "avatar": "avatars/john.jpg",
            "nama_jabatan": "CEO",
            "id_atasan": null,
            "subordinates_count": 3,
            "atasan": null
        }
    ],
    "message": "Data organisasi berhasil dimuat"
}
```

## Styling

- Menggunakan Tailwind CSS
- Gradient background untuk setiap level
- Responsive design
- Hover effects dan animations
- Dark mode support

## Catatan Penting

1. **Hierarki**: Struktur organisasi dibangun berdasarkan relasi `id_atasan` di tabel jabatan
2. **Avatar**: Menggunakan sistem yang sama dengan Home.vue (storage path + fallback inisial)
3. **Status**: Hanya menampilkan karyawan dan jabatan dengan status 'A' (Active)
4. **Outlet Filter**: Hanya menampilkan karyawan dari outlet dengan `id_outlet = 1`
5. **Performance**: Query dioptimalkan dengan join dan indexing yang tepat

## Troubleshooting

### Data tidak muncul
- Pastikan ada data di tabel `users` dan `tbl_data_jabatan`
- Pastikan status = 'A' untuk data yang ingin ditampilkan
- Pastikan `id_outlet = 1` untuk karyawan yang ingin ditampilkan
- Periksa relasi `id_atasan` sudah benar

### Avatar tidak muncul
- Pastikan file avatar ada di folder `storage/app/public/avatars/`
- Jalankan `php artisan storage:link` jika belum
- Periksa permission folder storage

### Struktur tidak sesuai
- Periksa relasi `id_atasan` di tabel `tbl_data_jabatan`
- Pastikan tidak ada circular reference
- Root level adalah jabatan dengan `id_atasan = null`
