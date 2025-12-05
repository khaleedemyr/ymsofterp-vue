# Packing List Summary Modal

## Deskripsi
Fitur modal summary untuk packing list yang memungkinkan user melakukan double check sebelum submit packing list.

## Fitur yang Ditambahkan

### 1. Modal Summary
- **Trigger**: Klik tombol "Submit Packing List"
- **Validasi**: 
  - Minimal satu item harus dipilih
  - Semua item yang dipilih harus memiliki quantity yang valid (> 0)

### 2. Informasi yang Ditampilkan dalam Modal

#### Detail Request Order (RO)
- Outlet
- Nomor RO
- Tanggal
- Warehouse Division

#### Items Summary
- Total items yang akan di-packing
- Total quantity
- Tabel detail items dengan kolom:
  - No
  - Nama Item
  - Qty Order (quantity dari RO)
  - Qty Packing (quantity yang akan di-packing)
  - Unit

### 3. Validasi
- **Peringatan jika tidak ada item yang dipilih**
- **Peringatan jika ada item dengan quantity tidak valid**
- **Konfirmasi sebelum submit**

### 4. Styling
- Modal dengan lebar 600px
- Scrollable table untuk items (max-height: 160px)
- Custom scrollbar styling
- Responsive design

## File yang Dimodifikasi

### `resources/js/Pages/PackingList/Form.vue`

#### Fungsi Baru:
1. **`summaryData`** - Computed property untuk data summary
2. **`showSummaryModal`** - Function untuk menampilkan modal summary
3. **`submitPackingList`** - Function untuk submit packing list (dipindah dari `onSubmit`)

#### Perubahan:
- Tombol submit sekarang memanggil `showSummaryModal` instead of `onSubmit`
- Menghapus fungsi `onSubmit` yang lama
- Menambahkan CSS styling untuk modal

## Cara Kerja

1. User memilih RO dan warehouse division
2. User memilih items dan mengisi quantity
3. User klik "Submit Packing List"
4. Sistem menampilkan modal summary dengan:
   - Detail RO
   - List items yang akan di-packing
   - Total quantity
5. User dapat:
   - Klik "Ya, Buat Packing List" untuk melanjutkan
   - Klik "Batal" untuk kembali ke form
6. Jika user konfirmasi, sistem akan submit packing list

## Keuntungan

1. **Double Check**: User dapat memastikan data yang benar sebelum submit
2. **Validasi**: Mencegah submit data yang tidak valid
3. **UX yang Lebih Baik**: Modal yang informatif dan mudah dibaca
4. **Prevent Error**: Mengurangi kemungkinan kesalahan input

## Screenshot Modal

Modal akan menampilkan:
```
┌─────────────────────────────────────────────────────────┐
│                Konfirmasi Packing List                  │
├─────────────────────────────────────────────────────────┤
│ Summary Packing List                                    │
│                                                         │
│ ┌─ Detail Request Order ──────────────────────────────┐ │
│ │ Outlet: [Nama Outlet]    Nomor RO: [RO-2025...]    │ │
│ │ Tanggal: [dd/mm/yyyy]    Warehouse Division: [Name]│ │
│ └─────────────────────────────────────────────────────┘ │
│                                                         │
│ ┌─ Items yang akan di-packing ───────────────────────┐ │
│ │ Total Items: X item(s)                             │ │
│ │ Total Quantity: XXX                                │ │
│ │                                                     │ │
│ │ ┌─ Tabel Items ──────────────────────────────────┐ │ │
│ │ │ No │ Item │ Qty Order │ Qty Packing │ Unit    │ │ │
│ │ │ 1  │ ...  │     ...   │     ...     │ ...     │ │ │
│ │ └─────────────────────────────────────────────────┘ │ │
│ └─────────────────────────────────────────────────────┘ │
│                                                         │
│ [Ya, Buat Packing List]           [Batal]              │
└─────────────────────────────────────────────────────────┘
```
