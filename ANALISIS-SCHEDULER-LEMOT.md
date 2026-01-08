# Analisis Scheduler - Tasks yang Berpotensi Membuat Server Lemot

## 🔴 TASKS YANG MENcurigakan (Priority Tinggi)

### 1. **SendIncompleteChallengeNotification** - ⚠️ SANGAT MENcurigakan!
**Schedule:** Setiap jam (`->hourly()`)

**Masalah:**
- **Line 46-65**: Query dengan **nested `whereHas`** (2x whereHas) - **SANGAT BERAT!**
  ```php
  ->whereHas('challenge', function ($query) { ... })
  ->whereHas('member', function ($query) { ... })
  ```
- **Line 64**: `->with(['member', 'challenge'])` - Eager loading (OK)
- **Line 68-99**: Filter di **memory** dengan logic kompleks (bisa lambat jika banyak data)
- **Line 122, 131, 147**: Multiple `refresh()` calls untuk **setiap progress** - **SANGAT BERAT!**
  - Setiap `refresh()` = 1 query ke database
  - Jika ada 100 progresses = 300+ queries!

**Impact:**
- Jika ada banyak challenge progresses, query ini bisa sangat lambat
- Multiple refresh() calls = banyak queries ke database
- Berjalan setiap jam = bisa overlap dengan traffic peak

**Rekomendasi:**
1. **Optimize query** - gunakan join instead of whereHas
2. **Remove unnecessary refresh()** - sudah di-filter di query
3. **Add index** pada `started_at`, `is_completed` di `member_apps_challenge_progresses`
4. **Consider reduce frequency** - dari hourly ke every 2-3 hours

---

### 2. **UpdateMemberTiers** - ⚠️ Berpotensi Berat
**Schedule:** Setiap tanggal 1 setiap bulan (`->monthlyOn(1, '00:00')`)

**Masalah:**
- **Line 51**: `MemberAppsMember::where('is_active', true)->get()` - **Load semua members ke memory!**
- **Line 61-74**: Loop semua members dan call `MemberTierService::updateMemberTier()` untuk setiap member
- Jika ada **ribuan members**, ini bisa sangat berat
- Setiap update tier = query ke database untuk calculate rolling 12-month spending

**Impact:**
- Bisa consume banyak memory jika banyak members
- Banyak queries ke database (1 per member)
- Bisa lama jika ada ribuan members

**Rekomendasi:**
1. **Process in chunks** - gunakan `chunk()` instead of `get()`
2. **Add index** pada transaction date untuk calculate rolling spending
3. **Consider run di off-peak hours** (sudah di 00:00, OK)
4. **Add progress bar** untuk monitoring (sudah ada)

---

### 3. **ExpirePoints** - ⚠️ Berpotensi Berat
**Schedule:** Setiap hari jam 00:00 (`->dailyAt('00:00')`)

**Masalah:**
- **Line 41-46**: Query untuk expired transactions
- **Line 80-155**: Loop dengan **transaction** untuk setiap expired point
- Multiple database operations per transaction:
  - Update member points
  - Update transaction
  - Create expired transaction record

**Impact:**
- Jika ada banyak expired points, bisa banyak transactions
- Bisa lock database tables jika banyak concurrent operations

**Rekomendasi:**
1. **Process in chunks** - jangan load semua expired transactions sekaligus
2. **Batch updates** - group updates untuk reduce transactions
3. **Add index** pada `expires_at`, `is_expired` di `member_apps_point_transactions`

---

## 🟡 TASKS YANG RELATIF RINGAN (Tapi Perlu Monitor)

### 4. **SendIncompleteProfileNotification** - OK
**Schedule:** Setiap jam (`->hourly()`)

**Status:** Relatif ringan
- Query sederhana dengan `whereBetween`
- Loop dan send notification
- Tidak ada nested queries atau refresh()

**Rekomendasi:**
- Monitor jika ada banyak members yang register setiap hari
- Add index pada `created_at`, `photo` di `member_apps_members`

---

## ✅ REKOMENDASI PRIORITAS

### Immediate (Lakukan Sekarang):

1. **Optimize SendIncompleteChallengeNotification** ⚠️ **PRIORITAS TINGGI**
   - Remove unnecessary `refresh()` calls
   - Optimize query dengan join instead of whereHas
   - Add database indexes

2. **Check apakah tasks overlap**
   - Monitor log untuk melihat berapa lama setiap task berjalan
   - Pastikan `withoutOverlapping()` bekerja dengan benar

3. **Add monitoring**
   - Log execution time untuk setiap scheduled task
   - Alert jika task berjalan > 5 menit

### Short Term (1-2 hari):

1. **Optimize UpdateMemberTiers**
   - Process in chunks
   - Add indexes

2. **Optimize ExpirePoints**
   - Process in chunks
   - Batch updates

### Long Term (1 minggu):

1. **Review semua scheduled tasks**
   - Check apakah semua masih diperlukan
   - Consider reduce frequency jika memungkinkan

---

## 📊 CHECKLIST

- [ ] Optimize SendIncompleteChallengeNotification (remove refresh, optimize query)
- [ ] Add database indexes untuk scheduled tasks
- [ ] Monitor execution time untuk setiap task
- [ ] Check apakah tasks overlap dengan traffic peak
- [ ] Optimize UpdateMemberTiers (chunk processing)
- [ ] Optimize ExpirePoints (chunk processing)

---

## 🔍 CARA MONITOR

### Check Log Files:
```bash
# Check execution time untuk setiap task
tail -f storage/logs/incomplete-challenge-notifications.log
tail -f storage/logs/incomplete-profile-notifications.log
tail -f storage/logs/member-tiers-update.log
tail -f storage/logs/points-expiry.log
```

### Check Scheduled Tasks:
```bash
# List semua scheduled tasks
php artisan schedule:list

# Run task manual untuk test
php artisan member:notify-incomplete-challenge
php artisan member:notify-incomplete-profile
```

### Check Database Queries:
```bash
# Enable query logging (sudah di AppServiceProvider)
# Check slow queries
grep "Slow query" storage/logs/laravel.log | grep -i "challenge\|member\|point"
```
