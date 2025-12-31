# Banner Upload Guide

## Lokasi Menu
Admin Panel → Web Profile → Banner Management
URL: `/web-profile/banners`

## Persyaratan Upload Banner

### 1. Background Image (Wajib)
**Dimensi:**
- **Minimum:** 1920 x 1080 pixels
- **Rasio:** 16:9 (landscape)
- **Recommended:** 1920 x 1080 pixels untuk kualitas terbaik
- **Maximum:** Tidak ada batas maksimum, tapi disarankan tidak lebih dari 3840 x 2160 (4K)

**Ukuran File:**
- **Maximum:** 5 MB
- **Recommended:** < 2 MB untuk loading cepat

**Format File:**
- JPG/JPEG (Recommended untuk foto)
- PNG (Recommended untuk gambar dengan transparansi)
- WEBP (Recommended untuk ukuran file lebih kecil)

**Tips:**
- Gunakan format JPG untuk foto dengan banyak detail
- Gunakan PNG jika memerlukan transparansi
- Kompres gambar sebelum upload untuk mengurangi ukuran file
- Pastikan gambar tidak blur atau pixelated

### 2. Content Image (Opsional)
**Dimensi:**
- **Minimum:** 800 x 600 pixels
- **Rasio:** 4:3 atau sesuai kebutuhan
- **Recommended:** 1200 x 800 pixels untuk kualitas terbaik

**Ukuran File:**
- **Maximum:** 5 MB
- **Recommended:** < 1.5 MB

**Format File:**
- JPG/JPEG
- PNG
- WEBP

**Tips:**
- Content image biasanya gambar produk, makanan, atau konten utama
- Pastikan objek utama berada di tengah frame
- Gunakan background yang kontras dengan teks overlay

## Cara Upload Banner

1. **Login ke Admin Panel** (`ymsofterp`)
2. **Navigasi ke:** Web Profile → Banner Management
3. **Klik "Create New Banner"**
4. **Isi Form:**
   - **Title:** Judul banner (wajib)
   - **Subtitle:** Subtitle banner (opsional, contoh: "SINCE 2005")
   - **Description:** Deskripsi banner (opsional)
   - **Background Image:** Upload gambar background (wajib)
   - **Content Image:** Upload gambar konten (opsional)
   - **Order:** Urutan tampil (0 = pertama)
   - **Active:** Centang untuk mengaktifkan banner
5. **Klik "Create Banner"**

## Tips Upload

### Optimasi Gambar
1. **Kompres sebelum upload:**
   - Gunakan tools seperti TinyPNG, ImageOptim, atau Squoosh
   - Target: < 2MB untuk background, < 1.5MB untuk content

2. **Resize jika perlu:**
   - Background: Resize ke 1920x1080 jika lebih besar
   - Content: Resize ke 1200x800 jika lebih besar

3. **Format yang tepat:**
   - Foto: JPG dengan quality 80-90%
   - Logo/Graphic: PNG dengan optimasi
   - Modern: WEBP untuk ukuran lebih kecil

### Best Practices
- **Maksimal 5 banner aktif** akan ditampilkan di homepage
- **Urutkan banner** dengan order (0 = pertama)
- **Test di berbagai device** setelah upload
- **Hapus banner lama** jika tidak digunakan untuk menghemat storage

## Troubleshooting

### Error: "The background image must be at least 1920x1080 pixels"
**Solusi:** Resize gambar ke minimum 1920x1080 pixels

### Error: "The background image may not be greater than 5120 kilobytes"
**Solusi:** Kompres gambar hingga < 5MB

### Error: "The background image must be a file of type: jpeg, jpg, png, webp"
**Solusi:** Convert gambar ke format JPG, PNG, atau WEBP

### Gambar terlihat blur
**Solusi:** 
- Pastikan dimensi sesuai minimum requirement
- Gunakan gambar dengan resolusi tinggi
- Jangan stretch gambar (jaga rasio aspek)

## Lokasi File di Server

Banner images disimpan di:
```
storage/app/public/web-profile/banners/
```

File akan accessible via:
```
/storage/web-profile/banners/{filename}
```

## API Endpoint

Banner dapat diakses via API:
```
GET /api/web-profile/banners
```

Response akan mengembalikan maksimal 5 banner aktif, diurutkan berdasarkan `order`.

