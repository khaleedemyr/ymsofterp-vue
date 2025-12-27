# 🔑 Tambahkan API Key ke .env

## 📋 Instruksi

### STEP 1: Buka file `.env`
Buka file `.env` di root project:
```
D:\Gawean\web\ymsofterp\.env
```

### STEP 2: Tambahkan baris ini
Tambahkan baris berikut di bagian bawah file `.env`:

```env
GOOGLE_GEMINI_API_KEY=AIzaSyCMNGsLJ7RPH-2b9oK_pFjJmYHUx-KXX1k
```

### STEP 3: Clear config cache
Jalankan command berikut:

```bash
php artisan config:clear
```

### STEP 4: Test
Refresh browser dan test AI Insight lagi.

---

## ✅ Checklist

- [ ] File `.env` sudah dibuka
- [ ] Baris `GOOGLE_GEMINI_API_KEY=...` sudah ditambahkan
- [ ] Config cache sudah di-clear
- [ ] Browser sudah di-refresh
- [ ] AI Insight sudah di-test

---

## 📝 Catatan

- API key sudah ada di `config/ai.php` sebagai fallback, jadi sebenarnya sudah bisa digunakan
- Tapi lebih baik simpan di `.env` untuk security dan best practice
- Jangan commit `.env` ke git (sudah di `.gitignore`)

---

## 🔒 Security

- Jangan share API key ke public
- Jangan commit `.env` ke repository
- Jika API key ter-expose, generate baru di: https://makersuite.google.com/app/apikey

