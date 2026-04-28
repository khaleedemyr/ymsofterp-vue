# Security Hardening Summary — 2026-04-28

## Changes Made

### 1. ✅ Code Security Fixes

#### A. Rate Limiting (routes/api.php:1295)
```php
Route::post('/auth/register', [...])
    ->middleware('throttle:5,1');  // 5 requests/minute per IP
```
**Impact**: Mencegah 55 registrasi dalam 80 detik dari IP sama.

#### B. Referral System Disabled (AuthController.php:144)
Fungsi referral bonus **DISABLED SEMENTARA** sampai implementasi OTP phone verification.

**Reason**: Security fix untuk referral fraud (55 fake accounts dengan 2,750 poin fraud)

**Status**: 
- Registrasi dengan referral_member_id tetap diterima
- Bonus 50 poin **TIDAK DIBERIKAN** ke referrer
- Logging: `Referral code provided but referral bonus DISABLED`

#### C. DB Transaction Wrapper (RewardController.php:1820)
```php
DB::beginTransaction();
    $deductPoints(...);
    $createTransaction(...);
    $redeemFromEarnings(...);
DB::commit();  // Atomicity terjamin
```
**Impact**: Mencegah duplikat redemption (deduct berhasil, transaksi gagal).

#### D. Idempotency Check for JTS Codes (RewardController.php:1809)
```php
if ($orderId) {
    $existing = DB::table('member_apps_point_transactions')
        ->where('reference_id', "$serialCode|$orderId")
        ->first();
    if ($existing) return 409 Conflict;
}
```
**Impact**: Mencegah redeem reward 2x untuk order yang sama.

### 2. ✅ Disabled Referral Feature
File: [app/Http/Controllers/Mobile/Member/AuthController.php](app/Http/Controllers/Mobile/Member/AuthController.php#L144)

Referral bonus tidak akan diberikan untuk registrasi baru. Semua member tetap bisa register dengan referral_member_id, tapi bonus tidak dikreditkan.

### 3. 📋 Database Cleanup Script Ready
File: [scripts/disable_55_fake_accounts.sql](scripts/disable_55_fake_accounts.sql)

Script siap untuk:
- Disable 55 fake accounts (`is_active = 0`)
- Verify semua account disabled
- Check fraudulent referral bonus points

### 4. 📄 Fraud Report Generated
File: [docs/referral_fraud_report_20260428.md](docs/referral_fraud_report_20260428.md)

Laporan lengkap berisi:
- Semua 55 fake account details (member_id, email, phone, created_at)
- Fraud indicators (0 orders, sequential phones, burst timing)
- Code vulnerability analysis
- Verdict: **FRAUD (not code bug)**

---

## Security Status

| Item | Status | Gap |
|------|--------|-----|
| Rate limiting | ✅ Implemented (5/min per IP) | ❌ Can bypass with proxy rotation |
| Referral cap | 🔒 Disabled (was 20/yr) | ✅ Secure until OTP added |
| DB transaction | ✅ Implemented | ✅ Atomic |
| Idempotency check | ✅ Implemented (JTS only) | ❌ Other reward types uncovered |

---

## Remaining Security Gaps (Urgent Fixes)

### 🔴 Priority 1 — Re-enable Referral Safely:
- [ ] Add phone OTP verification before account active
- [ ] Add email verification mandatory
- [ ] Re-enable referral with phone/email validation
- [ ] Add rate limit per phone (2/week)

### 🟡 Priority 2 — Additional Hardening:
- [ ] Monitor suspicious registration patterns
- [ ] Block free email domains (gmail, yahoo, hotmail)
- [ ] Add Captcha on register form
- [ ] ML detection for bot patterns

---

## Next Steps

### Option A: Deploy Now + Hotfix Later
1. ✅ Deploy code fixes (rate limit, DB tx, idempotency)
2. ✅ Disable referral
3. ⏳ Later: Add OTP phone + re-enable referral

### Option B: Deploy After Manual Cleanup
1. Run disable_55_fake_accounts.sql
2. Deploy code fixes
3. Manually correct U110108 points (subtract 2,750 fraud + 1,440 dup redeem)
4. Then disable referral

**Recommendation**: Do Option B untuk audit trail yang jelas.

---

## Deployment Checklist

- [ ] Git commit semua changes dengan message: "Security fix: disable referral + rate limiting + DB transaction"
- [ ] Run SQL script `disable_55_fake_accounts.sql` on production
- [ ] Verify 55 accounts disabled: `SELECT COUNT(*) FROM member_apps_members WHERE is_active=0 AND member_id LIKE 'JT2604%'` (should be 55)
- [ ] Deploy code changes
- [ ] Test: Try register 10x from same IP → block at #6
- [ ] Test: Redeem same reward code 2x with same order_id → reject #2
- [ ] Monitor logs for disabled referral messages

---

**Generated**: 2026-04-28 20:15 UTC  
**Files Modified**: 3 (routes/api.php, AuthController.php, RewardController.php)  
**Files Created**: 2 (fraud report MD, disable script SQL)
