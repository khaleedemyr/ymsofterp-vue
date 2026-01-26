# Analisis Performa - Home Page & Member App

## 🚨 Masalah Kritis yang Ditemukan

### 1. **Home Page (Home.vue)** - ⚠️ SANGAT KRITIS

**Masalah**:
- **15+ API calls sekaligus** saat page load untuk pending approvals
- Setiap API call = query database terpisah
- Semua dipanggil secara **sequential atau parallel** → beban database sangat tinggi

**API Calls yang Dipanggil**:
1. `/api/purchase-requisitions/pending-approvals` (limit 500)
2. `/po-ops/pending-approvals` (limit 200)
3. `/api/contra-bon/pending-approvals` (limit 500)
4. `/outlet-internal-use-waste/approvals/pending`
5. `/api/outlet-food-inventory-adjustment/pending-approvals`
6. `/api/stock-opnames/pending-approvals`
7. `/api/outlet-transfer/pending-approvals`
8. `/api/warehouse-stock-opnames/pending-approvals`
9. `/api/approval/pending`
10. `/employee-movements/pending-approvals` (limit 100)
11. Dan masih banyak lagi...

**Dampak**:
- **15+ query database sekaligus** saat user buka home page
- Response time bisa > 10 detik
- Database connection pool habis
- CPU 100% karena banyak query concurrent

**Solusi**:
1. **Buat single endpoint** untuk semua pending approvals
2. **Batch query** semua pending approvals dalam 1 request
3. **Cache hasil** untuk beberapa detik (5-10 detik)
4. **Lazy load** - hanya load saat user scroll/klik

---

### 2. **PurchaseRequisitionController.getPendingApprovals()** - ⚠️ KRITIS

**Lokasi**: `app/Http/Controllers/PurchaseRequisitionController.php` line 2621-2719

**Masalah**:
- **N+1 Query** untuk unread comments count (line 2692-2710)
- Untuk setiap PR, ada 2 query tambahan:
  1. Query last view time
  2. Query unread comments count

**Dampak**:
- Jika ada 100 PR pending → **200 query tambahan**!
- Response time bisa > 5 detik

**Solusi**:
```php
// Batch load last view time untuk semua PR
$allPrIds = $pendingApprovals->pluck('id')->toArray();
$allLastViews = DB::table('purchase_requisition_history')
    ->whereIn('purchase_requisition_id', $allPrIds)
    ->where('user_id', $userId)
    ->where('action', 'viewed')
    ->select('purchase_requisition_id as pr_id', 'created_at')
    ->orderBy('created_at', 'desc')
    ->get()
    ->groupBy('pr_id')
    ->map(function($views) {
        return $views->first()->created_at;
    });

// Batch load unread comments count
$allComments = DB::table('purchase_requisition_comments')
    ->whereIn('purchase_requisition_id', $allPrIds)
    ->where('user_id', '!=', $userId)
    ->select('purchase_requisition_id as pr_id', 'created_at')
    ->get()
    ->groupBy('pr_id');

// Calculate unread count per PR
$allUnreadComments = $allComments->map(function($comments, $prId) use ($allLastViews) {
    $lastViewTime = $allLastViews->get($prId);
    if ($lastViewTime) {
        return $comments->filter(function($comment) use ($lastViewTime) {
            return $comment->created_at > $lastViewTime;
        })->count();
    } else {
        return $comments->count();
    }
});

// Map di PHP instead of query per item
$pendingApprovals = $pendingApprovals->map(function($pr) use ($allUnreadComments) {
    $pr->unread_comments_count = $allUnreadComments->get($pr->id, 0);
    return $pr;
});
```

**Prioritas**: 🔴 **SANGAT TINGGI**

---

### 3. **ContraBonController.getPendingApprovals()** - ⚠️ KRITIS

**Lokasi**: `app/Http/Controllers/ContraBonController.php` line 1821-1950

**Masalah**:
- **Query di dalam loop** untuk get approver name (line 1861, 1864, 1909)
- Query `User::where('id_jabatan', 160)->first()` dipanggil untuk setiap CB

**Dampak**:
- Jika ada 50 CB pending → **50+ query tambahan**!
- Response time bisa > 3 detik

**Solusi**:
```php
// Batch load approvers sekali
$financeManagers = \App\Models\User::where('id_jabatan', 160)
    ->where('status', 'A')
    ->get()
    ->keyBy('id_jabatan');
    
$gmFinances = \App\Models\User::whereIn('id_jabatan', [152, 381])
    ->where('status', 'A')
    ->get()
    ->keyBy('id_jabatan');

// Gunakan di loop
foreach ($financeManagerApprovals as $cb) {
    $approverName = $financeManagers->first()?->nama_lengkap ?? 'Finance Manager';
    // ...
}
```

**Prioritas**: 🔴 **TINGGI**

---

### 4. **Member App - RewardController** - 🟡 SEDANG

**Lokasi**: `app/Http/Controllers/Mobile/Member/RewardController.php` line 31-200

**Masalah**:
- Query di dalam loop untuk challenge rewards (line 121-134)
- Query `with('challenge')` untuk setiap progress

**Dampak**:
- Jika ada 20 challenges → **20 query tambahan**!
- Response time bisa > 2 detik

**Solusi**:
```php
// Batch load challenges
$challengeIds = $allCompletedChallenges->pluck('challenge_id')->unique();
$challenges = MemberAppsChallenge::whereIn('id', $challengeIds)
    ->get()
    ->keyBy('id');

// Map di PHP
foreach ($allCompletedChallenges as $progress) {
    $challenge = $challenges->get($progress->challenge_id);
    // ...
}
```

**Prioritas**: 🟡 **SEDANG**

---

### 5. **Member App - VoucherController** - ✅ SUDAH CUKUP BAIK

**Status**: Sudah menggunakan eager loading (`with(['voucher.outlets'])`)
**Prioritas**: ✅ Tidak perlu diubah

---

## 📊 Summary Masalah

### Prioritas 1 (Kritis - Lakukan Segera):
1. ⚠️ **Home Page - Multiple API Calls** - Buat single endpoint atau cache
2. ⚠️ **PurchaseRequisitionController.getPendingApprovals()** - Fix N+1 query
3. ⚠️ **ContraBonController.getPendingApprovals()** - Fix query di loop

### Prioritas 2 (Penting):
1. 🟡 **RewardController** - Fix query di loop untuk challenges

---

## 🔧 Solusi yang Disarankan

### 1. Buat Single Endpoint untuk All Pending Approvals

**Buat Controller baru**: `PendingApprovalController.php`

```php
public function getAllPendingApprovals(Request $request)
{
    $user = auth()->user();
    $limit = $request->input('limit', 50); // Limit per type
    
    // OPTIMASI: Cache hasil untuk 10 detik
    $cacheKey = 'pending_approvals_' . $user->id;
    return Cache::remember($cacheKey, 10, function() use ($user, $limit) {
        return [
            'purchase_requisitions' => $this->getPendingPRs($user, $limit),
            'purchase_order_ops' => $this->getPendingPOs($user, $limit),
            'contra_bons' => $this->getPendingContraBons($user, $limit),
            // ... lainnya
        ];
    });
}
```

**Keuntungan**:
- Hanya 1 API call dari frontend
- Bisa di-cache
- Lebih cepat

### 2. Optimasi getPendingApprovals Methods

Fix N+1 query di semua method `getPendingApprovals()` dengan batch loading.

---

## 📋 Checklist Optimasi

### Home Page:
- [ ] Buat single endpoint untuk all pending approvals
- [ ] Implementasi caching (5-10 detik)
- [ ] Lazy load approvals (hanya load saat dibutuhkan)

### getPendingApprovals Methods:
- [ ] Fix N+1 query di PurchaseRequisitionController
- [ ] Fix query di loop di ContraBonController
- [ ] Cek dan fix method lain yang serupa

### Member App:
- [ ] Fix query di loop di RewardController
- [ ] Cek controller lain yang digunakan member app

---

## 🎯 Dampak Setelah Optimasi

**Sebelum**:
- Home page load: **15+ API calls** → **15+ query database**
- Response time: **10-20 detik**

**Sesudah**:
- Home page load: **1 API call** (atau cached)
- Response time: **0.5-2 detik** (dengan cache)

**Improvement**: **80-90% lebih cepat**
