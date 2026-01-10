# Build Instructions untuk Server

## Masalah yang Ditemukan:
1. **CSS Error**: `@import` harus di awal file sebelum `@tailwind`
2. **Memory Error**: JavaScript heap out of memory saat build

## Solusi yang Sudah Diterapkan:

### 1. CSS Fix
- File `resources/css/app.css` sudah diperbaiki
- `@import './shared-documents.css'` dipindah ke atas sebelum `@tailwind`

### 2. Memory Limit Fix
- File `package.json` sudah diupdate dengan `NODE_OPTIONS=--max-old-space-size=4096`
- File `vite.config.js` sudah dioptimasi dengan code splitting

## Cara Build di Server:

### Opsi 1: Menggunakan script yang sudah diupdate
```bash
npm run build
```

### Opsi 2: Manual dengan memory limit lebih besar (jika masih error)
```bash
NODE_OPTIONS=--max-old-space-size=6144 npm run build
```

### Opsi 3: Build dengan optimasi tambahan
```bash
NODE_OPTIONS=--max-old-space-size=4096 NODE_ENV=production npm run build
```

## Catatan:
- Memory limit default Node.js adalah ~1.5GB
- Dengan `--max-old-space-size=4096` akan menggunakan 4GB
- Jika masih error, coba tingkatkan ke 6144 (6GB) atau 8192 (8GB)
- Pastikan server memiliki RAM yang cukup

## Troubleshooting:

Jika masih error memory:
1. Cek RAM server: `free -h`
2. Tutup aplikasi lain yang menggunakan banyak memory
3. Tingkatkan memory limit di package.json
4. Pertimbangkan build di local machine lalu upload hasil build ke server
