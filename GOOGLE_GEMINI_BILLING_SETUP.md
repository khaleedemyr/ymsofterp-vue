# 💳 Setup Billing untuk Google Gemini API

## 📊 Free Tier vs Paid Tier

### **Free Tier (Saat Ini)**
- ✅ **15 requests per minute (RPM)**
- ✅ **1,500 requests per day (RPD)**
- ✅ **32,000 tokens per minute (TPM)**
- ✅ **1,500,000 tokens per day (TPD)**
- ✅ **GRATIS** - Tidak perlu kartu kredit

**Cukup untuk:**
- Testing & development
- Penggunaan ringan (10-50 analisa/hari)
- Proof of concept

### **Paid Tier (Setelah Setup Billing)**
- ✅ **Tidak ada limit RPM/RPD** (hanya limit TPM/TPD)
- ✅ **Lebih tinggi limit tokens**
- ✅ **Biaya**: $0.50 per 1M input tokens, $1.50 per 1M output tokens
- ✅ **Perlu kartu kredit** untuk setup

**Diperlukan untuk:**
- Production dengan traffic tinggi
- Penggunaan berat (200+ analisa/hari)
- Enterprise usage

---

## 🎯 Apakah Perlu Setup Billing?

### **TIDAK PERLU** jika:
- ✅ Hanya untuk testing
- ✅ Penggunaan ringan (< 50 analisa/hari)
- ✅ Free tier limit sudah cukup

### **PERLU** jika:
- ⚠️ Error "Quota exceeded" atau "Rate limit exceeded"
- ⚠️ Penggunaan berat (> 100 analisa/hari)
- ⚠️ Production environment dengan banyak user

---

## 🚀 Cara Setup Billing (Jika Diperlukan)

### **STEP 1: Buka Google AI Studio**
1. Buka: https://makersuite.google.com/app/apikey
2. Login dengan Google account yang sama dengan API key

### **STEP 2: Klik "Set up billing"**
- Di halaman API keys, klik link **"Set up billing"** (biru)
- Atau buka: https://console.cloud.google.com/billing

### **STEP 3: Pilih atau Buat Billing Account**
1. **Jika sudah punya billing account:**
   - Pilih billing account yang ada
   - Klik "Set account"

2. **Jika belum punya billing account:**
   - Klik "Create billing account"
   - Isi informasi:
     - **Account name**: Nama untuk billing account (contoh: "YMSoft ERP Billing")
     - **Country**: Pilih negara (Indonesia)
     - **Currency**: IDR (Rupiah)
   - Klik "Continue"

### **STEP 4: Tambahkan Payment Method**
1. **Pilih payment method:**
   - Credit card / Debit card
   - Bank account (jika tersedia)

2. **Isi informasi kartu:**
   - Card number
   - Expiry date
   - CVV
   - Name on card
   - Billing address

3. **Klik "Submit and enable billing"**

### **STEP 5: Link Billing ke Project**
1. Setelah billing account dibuat, kembali ke: https://console.cloud.google.com/billing
2. Pilih project: **"Default Gemini Project"** atau project yang digunakan
3. Klik "Link billing account"
4. Pilih billing account yang baru dibuat
5. Klik "Set account"

### **STEP 6: Verifikasi**
1. Kembali ke: https://makersuite.google.com/app/apikey
2. Cek quota tier, seharusnya berubah dari "Free tier" ke "Paid tier
2. Atau cek di: https://console.cloud.google.com/apis/api/generativelanguage.googleapis.com/quotas

---

## 💰 Estimasi Biaya Setelah Setup Billing

### **Penggunaan Ringan (10 analisa/hari = 300/bulan)**
- Input: 300 × 3,000 tokens = 900,000 tokens
- Output: 300 × 500 tokens = 150,000 tokens
- **Biaya**: 
  - Input: (900,000 / 1,000,000) × $0.50 = **$0.45**
  - Output: (150,000 / 1,000,000) × $1.50 = **$0.23**
  - **Total: $0.68/bulan = Rp 10,500/bulan** ✅

### **Penggunaan Sedang (50 analisa/hari = 1,500/bulan)**
- Input: 1,500 × 3,000 tokens = 4,500,000 tokens
- Output: 1,500 × 500 tokens = 750,000 tokens
- **Biaya**: 
  - Input: (4,500,000 / 1,000,000) × $0.50 = **$2.25**
  - Output: (750,000 / 1,000,000) × $1.50 = **$1.13**
  - **Total: $3.38/bulan = Rp 52,000/bulan** ✅

### **Penggunaan Berat (200 analisa/hari = 6,000/bulan)**
- Input: 6,000 × 3,000 tokens = 18,000,000 tokens
- Output: 6,000 × 500 tokens = 3,000,000 tokens
- **Biaya**: 
  - Input: (18,000,000 / 1,000,000) × $0.50 = **$9**
  - Output: (3,000,000 / 1,000,000) × $1.50 = **$4.50**
  - **Total: $13.50/bulan = Rp 209,000/bulan** ✅

---

## ⚠️ Troubleshooting

### **Error: "Quota exceeded"**
**Solusi**: 
1. Tunggu beberapa menit (limit per minute)
2. Atau setup billing untuk menghilangkan limit

### **Error: "Rate limit exceeded"**
**Solusi**: 
1. Tambahkan delay antar request
2. Atau setup billing untuk limit lebih tinggi

### **Error: "Billing not enabled"**
**Solusi**: 
1. Setup billing account
2. Link billing account ke project
3. Tunggu beberapa menit untuk aktivasi

### **Error: "Invalid API key"**
**Solusi**: 
1. Cek API key di `.env` atau `config/ai.php`
2. Pastikan API key masih aktif
3. Generate API key baru jika perlu

---

## 📋 Checklist Setup Billing

- [ ] Buka Google AI Studio
- [ ] Klik "Set up billing"
- [ ] Buat atau pilih billing account
- [ ] Tambahkan payment method (kartu kredit)
- [ ] Link billing account ke project
- [ ] Verifikasi quota tier sudah berubah
- [ ] Test API lagi

---

## 🎯 Kesimpulan

### **Untuk Testing:**
- ✅ **Free tier sudah cukup** - Tidak perlu setup billing
- ✅ Cukup untuk 1,500 requests/hari
- ✅ Cukup untuk testing & development

### **Untuk Production:**
- ⚠️ **Setup billing jika:**
  - Traffic tinggi (> 100 analisa/hari)
  - Error "Quota exceeded"
  - Butuh limit lebih tinggi

### **Biaya Setelah Setup:**
- **Ringan**: Rp 10,500/bulan
- **Sedang**: Rp 52,000/bulan
- **Berat**: Rp 209,000/bulan

**Sangat terjangkau!** 💰

---

## 🔗 Link Penting

- **API Keys**: https://makersuite.google.com/app/apikey
- **Billing Console**: https://console.cloud.google.com/billing
- **Quotas**: https://console.cloud.google.com/apis/api/generativelanguage.googleapis.com/quotas
- **Usage**: https://console.cloud.google.com/apis/api/generativelanguage.googleapis.com/metrics

---

**Catatan**: Free tier biasanya cukup untuk testing. Setup billing hanya diperlukan jika ada error quota atau untuk production dengan traffic tinggi.

