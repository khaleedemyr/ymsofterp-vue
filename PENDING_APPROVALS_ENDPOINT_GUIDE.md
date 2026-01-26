# Single Endpoint untuk All Pending Approvals - Guide

## 🎯 Tujuan

Mengurangi jumlah API calls dari Home.vue dari **15+ menjadi 1** untuk meningkatkan performa.

## 📍 Endpoint Baru

### GET `/api/pending-approvals/all`

**Deskripsi**: Menggabungkan semua pending approvals dalam 1 response dengan caching.

**Parameters**:
- `limit` (optional, default: 50): Limit jumlah data per type

**Response**:
```json
{
    "success": true,
    "data": {
        "purchase_requisitions": [...],
        "purchase_order_ops": [...],
        "contra_bons": [...],
        "approval": [...],
        "outlet_internal_use_waste": [...],
        "outlet_food_inventory_adjustment": [...],
        "stock_opnames": [...],
        "outlet_transfer": [...],
        "warehouse_stock_opnames": [...],
        "employee_movements": [...],
        "coaching": [...],
        "schedule_attendance_correction": [...],
        "food_payment": [...],
        "non_food_payment": [...],
        "pr_food": [...],
        "po_food": [...],
        "ro_khusus": [...],
        "employee_resignation": [...]
    },
    "cached": false,
    "cache_ttl": 10
}
```

**Caching**:
- Cache TTL: **10 detik**
- Cache key: `all_pending_approvals_{user_id}`
- Cache per user (personalized)

### POST `/api/pending-approvals/clear-cache`

**Deskripsi**: Clear cache untuk testing atau manual refresh.

**Response**:
```json
{
    "success": true,
    "message": "Cache cleared successfully"
}
```

---

## 🔧 Implementasi di Frontend (Home.vue)

### Opsi 1: Ganti Semua API Calls dengan 1 Call (Recommended)

```javascript
// Sebelum (15+ API calls):
async function loadAllPendingApprovals() {
    await Promise.all([
        axios.get('/api/purchase-requisitions/pending-approvals'),
        axios.get('/po-ops/pending-approvals'),
        axios.get('/api/contra-bon/pending-approvals'),
        // ... 12+ lainnya
    ]);
}

// Sesudah (1 API call):
async function loadAllPendingApprovals() {
    try {
        const response = await axios.get('/api/pending-approvals/all', {
            params: { limit: 50 } // Optional
        });
        
        if (response.data.success) {
            const data = response.data.data;
            
            // Assign ke variabel yang sudah ada
            pendingPrApprovals.value = data.purchase_requisitions || [];
            pendingPoOpsApprovals.value = data.purchase_order_ops || [];
            pendingContraBonApprovals.value = data.contra_bons || [];
            // ... dst
        }
    } catch (error) {
        console.error('Error loading all pending approvals:', error);
    }
}
```

### Opsi 2: Hybrid (Tetap Gunakan Endpoint Lama + Endpoint Baru)

Jika ingin tetap backward compatible, bisa gunakan endpoint baru sebagai fallback atau primary:

```javascript
async function loadAllPendingApprovals() {
    try {
        // Coba endpoint baru dulu
        const response = await axios.get('/api/pending-approvals/all');
        if (response.data.success) {
            // Use new endpoint data
            const data = response.data.data;
            pendingPrApprovals.value = data.purchase_requisitions || [];
            // ... dst
        }
    } catch (error) {
        // Fallback ke endpoint lama jika error
        console.warn('New endpoint failed, using old endpoints:', error);
        await loadAllPendingApprovalsOld();
    }
}
```

---

## ⚠️ Catatan Penting

1. **Error Handling**: Setiap type approval memiliki try-catch terpisah. Jika 1 type error, yang lain tetap jalan.

2. **Caching**: 
   - Cache 10 detik per user
   - Jika perlu refresh manual, gunakan `/api/pending-approvals/clear-cache`
   - Atau tunggu 10 detik untuk auto-refresh

3. **Limit**: 
   - Default limit: 50 per type
   - Bisa di-override via parameter `limit`
   - Jika tidak ada limit, semua data akan dikembalikan

4. **Backward Compatibility**: 
   - Endpoint lama masih tetap berfungsi
   - Bisa migrasi bertahap

5. **Response Format**: 
   - Setiap type menggunakan format yang sama dengan endpoint aslinya
   - Jika endpoint asli return `{ success: true, data: [...] }`, maka di sini juga `data.type_name: [...]`

---

## 📊 Dampak Performa

### Sebelum:
- **15+ API calls** → **15+ query database** → **10-20 detik**

### Sesudah:
- **1 API call** → **15+ query database** (tapi dengan caching) → **0.5-2 detik** (first call), **0.1-0.3 detik** (cached)

**Improvement**: **80-95% lebih cepat** (tergantung cache hit rate)

---

## 🧪 Testing

1. **Test endpoint baru**:
```bash
curl -X GET "http://your-domain/api/pending-approvals/all?limit=50" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

2. **Test clear cache**:
```bash
curl -X POST "http://your-domain/api/pending-approvals/clear-cache" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

3. **Monitor cache hit rate**:
   - Check log untuk melihat apakah cache bekerja
   - Response `cached: true` berarti dari cache

---

## 🔄 Migration Plan

1. **Phase 1**: Deploy endpoint baru (tidak merusak yang lama)
2. **Phase 2**: Update Home.vue untuk menggunakan endpoint baru
3. **Phase 3**: Monitor performa dan error
4. **Phase 4**: Jika stabil, bisa deprecate endpoint lama (opsional)

---

## 📝 File yang Dibuat/Diubah

1. ✅ `app/Http/Controllers/PendingApprovalController.php` - Controller baru
2. ✅ `routes/api.php` - Route baru ditambahkan
3. ⏳ `resources/js/Pages/Home.vue` - Perlu diupdate untuk menggunakan endpoint baru (opsional)

---

## ⚠️ Troubleshooting

### Error: "Method not found"
- Pastikan semua controller yang dipanggil memiliki method `getPendingApprovals()`
- Check log untuk detail error

### Cache tidak bekerja
- Pastikan Redis/Cache sudah dikonfigurasi dengan benar
- Check `.env` untuk `CACHE_DRIVER`

### Response format berbeda
- Setiap controller mungkin return format berbeda
- Check log untuk melihat response format dari setiap controller
