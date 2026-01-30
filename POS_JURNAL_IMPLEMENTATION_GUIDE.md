# POS Jurnal Implementation Guide

## 📋 Daftar Isi
1. [Rekomendasi COA](#rekomendasi-coa)
2. [Struktur Jurnal](#struktur-jurnal)
3. [Mapping Payment Type ke COA](#mapping-payment-type-ke-coa)
4. [Contoh Jurnal](#contoh-jurnal)
5. [Implementasi Teknis](#implementasi-teknis)
6. [Void Order & Rollback](#void-order--rollback)

---

## 🏦 Rekomendasi COA

### COA yang Perlu Dibuat di Chart of Accounts

#### 1. **Kas/Tunai** (Asset)
- **Code**: `1.1.1.01` (contoh, sesuaikan dengan struktur COA Anda)
- **Name**: Kas Tunai Outlet
- **Type**: Asset
- **Description**: Untuk mencatat pembayaran tunai/cash dari customer
- **Parent**: Bisa di bawah "Kas & Setara Kas" atau "Aktiva Lancar"

#### 2. **Perjamuan** (Asset atau Expense - tergantung kebijakan)
- **Code**: `1.1.1.02` (jika Asset) atau `5.1.1.01` (jika Expense)
- **Name**: Perjamuan / Entertainment
- **Type**: Asset (jika prepaid) atau Expense (jika langsung dibebankan)
- **Description**: Untuk mencatat pembayaran via perjamuan
- **Catatan**: Tentukan apakah ini prepaid (Asset) atau langsung expense

#### 3. **Guest Satisfaction** (Asset atau Expense - tergantung kebijakan)
- **Code**: `1.1.1.03` (jika Asset) atau `5.1.1.02` (jika Expense)
- **Name**: Guest Satisfaction / Complimentary
- **Type**: Asset (jika prepaid) atau Expense (jika langsung dibebankan)
- **Description**: Untuk mencatat pembayaran via guest satisfaction/complimentary

#### 4. **Piutang Officer Check** (Asset)
- **Code**: `1.1.2.01`
- **Name**: Piutang Officer Check - Head Office
- **Type**: Asset
- **Description**: Untuk mencatat hutang dari officer check yang akan dibayar oleh Head Office
- **Catatan**: Ini adalah piutang karena outlet berhak menerima pembayaran dari HO

#### 5. **Pendapatan Penjualan** (Revenue)
- **Code**: `4.1.1.01` (contoh, sesuaikan dengan struktur COA Anda)
- **Name**: Pendapatan Penjualan POS
- **Type**: Revenue
- **Description**: Untuk mencatat pendapatan dari penjualan POS (grand_total)
- **Catatan**: Service charge dan PB1 sudah termasuk di grand_total, jadi tidak perlu breakdown

---

## 📊 Struktur Jurnal

### Rekomendasi: **1 Jurnal dengan Multiple Debit Lines**

**Alasan:**
1. ✅ **Audit Trail Lebih Baik**: Semua payment dalam 1 jurnal entry, mudah ditelusuri
2. ✅ **Balance Check**: Total debit = total kredit dalam 1 jurnal (double entry bookkeeping)
3. ✅ **Referensi**: 1 `no_jurnal` untuk 1 order, mudah di-track
4. ✅ **Rollback Mudah**: Saat void, cukup delete/rollback 1 jurnal entry

**Struktur:**
```
1 Order = 1 Jurnal Entry dengan:
- Multiple Debit Lines (sesuai jumlah payment)
- 1 Kredit Line (Revenue = grand_total)
```

**Contoh:**
```
Jurnal Entry #1:
- Debit: Kas Tunai        Rp 50.000
- Debit: Bank BCA         Rp 30.000
- Debit: Bank Mandiri     Rp 20.000
- Kredit: Pendapatan      Rp 100.000
```

---

## 🔄 Mapping Payment Type ke COA

### Logic Mapping:

```php
// Pseudocode
foreach ($order->payments as $payment) {
    $coaDebit = null;
    
    switch ($payment->payment_code) {
        case 'CASH':
        case 'TUNAI':
            $coaDebit = COA_KAS_TUNAI; // COA Kas/Tunai
            break;
            
        case 'BANK_BCA':
        case 'BANK_MANDIRI':
        case 'EDC_BCA':
        case 'EDC_MANDIRI':
        case // ... semua bank payment codes
            if ($payment->bank_id) {
                $bankAccount = BankAccount::find($payment->bank_id);
                $coaDebit = $bankAccount->coa_id; // Ambil dari bank_accounts.coa_id
            }
            break;
            
        case 'PERJAMUAN':
            $coaDebit = COA_PERJAMUAN; // COA Perjamuan
            break;
            
        case 'GUEST_SATISFACTION':
        case 'COMPLIMENTARY':
            $coaDebit = COA_GUEST_SATISFACTION; // COA Guest Satisfaction
            break;
            
        case 'OFFICER_CHECK':
            $coaDebit = COA_PIUTANG_OFFICER_CHECK; // COA Piutang Officer Check
            break;
            
        default:
            // Fallback ke Kas jika tidak diketahui
            $coaDebit = COA_KAS_TUNAI;
    }
    
    // Create jurnal debit line
    Jurnal::create([
        'coa_debit_id' => $coaDebit,
        'coa_kredit_id' => COA_PENDAPATAN_PENJUALAN,
        'jumlah_debit' => $payment->amount,
        'jumlah_kredit' => $payment->amount, // Balance dengan debit
        // ... fields lainnya
    ]);
}
```

---

## 💡 Contoh Jurnal

### Contoh 1: Single Payment (Cash)
```
Order: ORD-20260130-0001
Grand Total: Rp 100.000
Payment: Cash Rp 100.000

Jurnal Entry:
┌─────────────────────────────────────────┐
│ No Jurnal: JRN-20260130-0001           │
│ Tanggal: 2026-01-30                     │
│ Keterangan: Order ORD-20260130-0001     │
├─────────────────────────────────────────┤
│ Debit:  Kas Tunai        Rp 100.000    │
│ Kredit: Pendapatan       Rp 100.000    │
└─────────────────────────────────────────┘
```

### Contoh 2: Split Payment (Cash + Bank)
```
Order: ORD-20260130-0002
Grand Total: Rp 150.000
Payment 1: Cash Rp 50.000
Payment 2: Bank BCA Rp 100.000

Jurnal Entry (1 entry dengan 2 debit lines):
┌─────────────────────────────────────────┐
│ No Jurnal: JRN-20260130-0002           │
│ Tanggal: 2026-01-30                     │
│ Keterangan: Order ORD-20260130-0002     │
├─────────────────────────────────────────┤
│ Debit:  Kas Tunai        Rp  50.000    │
│ Debit:  Bank BCA         Rp 100.000    │
│ Kredit: Pendapatan       Rp 150.000    │
└─────────────────────────────────────────┘
```

### Contoh 3: Multiple Payments (Cash + 2 Banks)
```
Order: ORD-20260130-0003
Grand Total: Rp 200.000
Payment 1: Cash Rp 50.000
Payment 2: Bank BCA Rp 100.000
Payment 3: Bank Mandiri Rp 50.000

Jurnal Entry (1 entry dengan 3 debit lines):
┌─────────────────────────────────────────┐
│ No Jurnal: JRN-20260130-0003           │
│ Tanggal: 2026-01-30                     │
│ Keterangan: Order ORD-20260130-0003     │
├─────────────────────────────────────────┤
│ Debit:  Kas Tunai        Rp  50.000    │
│ Debit:  Bank BCA         Rp 100.000    │
│ Debit:  Bank Mandiri     Rp  50.000    │
│ Kredit: Pendapatan       Rp 200.000    │
└─────────────────────────────────────────┘
```

### Contoh 4: Officer Check (Hutang)
```
Order: ORD-20260130-0004
Grand Total: Rp 75.000
Payment: Officer Check Rp 75.000

Jurnal Entry:
┌─────────────────────────────────────────┐
│ No Jurnal: JRN-20260130-0004           │
│ Tanggal: 2026-01-30                     │
│ Keterangan: Order ORD-20260130-0004     │
├─────────────────────────────────────────┤
│ Debit:  Piutang Officer Check Rp 75.000│
│ Kredit: Pendapatan       Rp 75.000      │
└─────────────────────────────────────────┘

Catatan: Saat Head Office bayar, buat jurnal:
- Debit: Bank/Cash (sesuai pembayaran HO)
- Kredit: Piutang Officer Check
```

---

## ⚙️ Implementasi Teknis

### 1. Service/Helper Class: `JurnalService`

Buat service class untuk handle jurnal creation:

```php
namespace App\Services;

use App\Models\Jurnal;
use App\Models\JurnalGlobal;
use App\Models\ChartOfAccount;
use App\Models\BankAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class JurnalService
{
    // COA Constants (akan diambil dari config atau database)
    private const COA_KAS_TUNAI = 'COA_ID_KAS'; // Ambil dari config
    private const COA_PERJAMUAN = 'COA_ID_PERJAMUAN';
    private const COA_GUEST_SATISFACTION = 'COA_ID_GUEST_SATISFACTION';
    private const COA_PIUTANG_OFFICER_CHECK = 'COA_ID_PIUTANG_OFFICER';
    private const COA_PENDAPATAN_PENJUALAN = 'COA_ID_PENDAPATAN';
    
    /**
     * Create jurnal from POS order
     */
    public function createFromPosOrder($order, $payments, $outletId)
    {
        // Validasi: Total payment harus = grand_total
        $totalPayment = collect($payments)->sum('amount');
        if (abs($totalPayment - $order->grand_total) > 0.01) {
            throw new \Exception("Total payment tidak sama dengan grand_total");
        }
        
        // Generate no jurnal
        $noJurnal = Jurnal::generateNoJurnal();
        $tanggal = $order->created_at ?? now();
        $keterangan = "Order POS: {$order->nomor}";
        
        // Get COA Pendapatan (kredit)
        $coaPendapatan = $this->getCoaPendapatan();
        
        // Create multiple debit entries (1 per payment)
        $jurnalEntries = [];
        foreach ($payments as $payment) {
            $coaDebit = $this->getCoaDebitFromPayment($payment);
            
            // Create jurnal entry
            $jurnal = Jurnal::create([
                'no_jurnal' => $noJurnal,
                'tanggal' => $tanggal,
                'keterangan' => $keterangan,
                'coa_debit_id' => $coaDebit,
                'coa_kredit_id' => $coaPendapatan,
                'jumlah_debit' => $payment->amount,
                'jumlah_kredit' => $payment->amount, // Balance
                'outlet_id' => $outletId,
                'reference_type' => 'pos_order',
                'reference_id' => $order->id,
                'status' => 'posted', // Auto-post karena sudah paid
                'created_by' => auth()->id() ?? 1,
            ]);
            
            $jurnalEntries[] = $jurnal;
            
            // Insert ke jurnal_global juga
            JurnalGlobal::create([
                'no_jurnal' => $noJurnal,
                'tanggal' => $tanggal,
                'keterangan' => $keterangan,
                'coa_debit_id' => $coaDebit,
                'coa_kredit_id' => $coaPendapatan,
                'jumlah_debit' => $payment->amount,
                'jumlah_kredit' => $payment->amount,
                'outlet_id' => $outletId,
                'source_module' => 'pos_order',
                'source_id' => $order->id,
                'reference_type' => 'pos_order',
                'reference_id' => $order->id,
                'status' => 'posted',
                'posted_at' => now(),
                'posted_by' => auth()->id() ?? 1,
                'created_by' => auth()->id() ?? 1,
            ]);
        }
        
        return $jurnalEntries;
    }
    
    /**
     * Get COA Debit berdasarkan payment type
     */
    private function getCoaDebitFromPayment($payment)
    {
        // 1. Check payment_code untuk determine type
        $paymentCode = strtoupper($payment->payment_code ?? '');
        
        // 2. Cash/Tunai
        if (in_array($paymentCode, ['CASH', 'TUNAI', 'KAS'])) {
            return $this->getCoaKasTunai();
        }
        
        // 3. Bank (jika ada bank_id, ambil dari bank_accounts.coa_id)
        if ($payment->bank_id) {
            $bankAccount = BankAccount::find($payment->bank_id);
            if ($bankAccount && $bankAccount->coa_id) {
                return $bankAccount->coa_id;
            }
        }
        
        // 4. Perjamuan
        if (in_array($paymentCode, ['PERJAMUAN', 'ENTERTAINMENT'])) {
            return $this->getCoaPerjamuan();
        }
        
        // 5. Guest Satisfaction
        if (in_array($paymentCode, ['GUEST_SATISFACTION', 'COMPLIMENTARY', 'COMP'])) {
            return $this->getCoaGuestSatisfaction();
        }
        
        // 6. Officer Check
        if (in_array($paymentCode, ['OFFICER_CHECK', 'OFFICER', 'OC'])) {
            return $this->getCoaPiutangOfficerCheck();
        }
        
        // 7. Fallback: Kas Tunai
        Log::warning('Payment code tidak dikenali, menggunakan COA Kas Tunai', [
            'payment_code' => $paymentCode,
            'payment_id' => $payment->id ?? null
        ]);
        return $this->getCoaKasTunai();
    }
    
    /**
     * Get COA IDs (akan diambil dari config atau database)
     */
    private function getCoaKasTunai()
    {
        // Option 1: Dari config
        // return config('jurnal.coa.kas_tunai');
        
        // Option 2: Dari database (cari by code atau name)
        return ChartOfAccount::where('code', '1.1.1.01')
            ->orWhere('name', 'LIKE', '%Kas Tunai%')
            ->where('is_active', 1)
            ->first()?->id;
    }
    
    private function getCoaPerjamuan()
    {
        return ChartOfAccount::where('code', '1.1.1.02')
            ->orWhere('name', 'LIKE', '%Perjamuan%')
            ->where('is_active', 1)
            ->first()?->id;
    }
    
    private function getCoaGuestSatisfaction()
    {
        return ChartOfAccount::where('code', '1.1.1.03')
            ->orWhere('name', 'LIKE', '%Guest Satisfaction%')
            ->where('is_active', 1)
            ->first()?->id;
    }
    
    private function getCoaPiutangOfficerCheck()
    {
        return ChartOfAccount::where('code', '1.1.2.01')
            ->orWhere('name', 'LIKE', '%Piutang Officer%')
            ->where('is_active', 1)
            ->first()?->id;
    }
    
    private function getCoaPendapatan()
    {
        return ChartOfAccount::where('code', '4.1.1.01')
            ->orWhere('name', 'LIKE', '%Pendapatan Penjualan%')
            ->where('is_active', 1)
            ->first()?->id;
    }
    
    /**
     * Rollback jurnal saat void order
     */
    public function rollbackFromPosOrder($orderId)
    {
        // Find all jurnal entries dengan reference ke order ini
        $jurnals = Jurnal::where('reference_type', 'pos_order')
            ->where('reference_id', $orderId)
            ->get();
        
        if ($jurnals->isEmpty()) {
            return false; // Tidak ada jurnal untuk di-rollback
        }
        
        DB::beginTransaction();
        try {
            // Delete dari jurnal_global juga
            JurnalGlobal::where('reference_type', 'pos_order')
                ->where('reference_id', $orderId)
                ->delete();
            
            // Delete dari jurnal
            Jurnal::where('reference_type', 'pos_order')
                ->where('reference_id', $orderId)
                ->delete();
            
            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error rolling back jurnal from POS order', [
                'order_id' => $orderId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}
```

### 2. Integration di PosOrderController

Tambahkan di method `syncOrder()` setelah insert order_payment:

```php
// Setelah insert order_payment (line ~315)
// 6. Create jurnal entries
if ($orderData['status'] === 'paid') {
    try {
        $jurnalService = new \App\Services\JurnalService();
        
        // Get outlet_id
        $outletId = null;
        if (!empty($kodeOutlet)) {
            $outlet = DB::table('tbl_data_outlet')
                ->where('qr_code', $kodeOutlet)
                ->first();
            $outletId = $outlet->id_outlet ?? null;
        }
        
        // Get payments
        $payments = DB::table('order_payment')
            ->where('order_id', $orderData['id'])
            ->get();
        
        if ($payments->isNotEmpty()) {
            $jurnalService->createFromPosOrder(
                (object)$orderData,
                $payments,
                $outletId
            );
            
            Log::info('Jurnal created for POS order', [
                'order_id' => $orderData['id'],
                'payments_count' => $payments->count()
            ]);
        }
    } catch (\Exception $e) {
        // Log error but don't fail the order sync
        Log::error('Failed to create jurnal for POS order', [
            'order_id' => $orderData['id'],
            'error' => $e->getMessage()
        ]);
    }
}
```

### 3. Rollback saat Void Order

Tambahkan di method `voidOrder()` sebelum delete order:

```php
// Sebelum delete order (line ~1302)
// Rollback jurnal
try {
    $jurnalService = new \App\Services\JurnalService();
    $jurnalService->rollbackFromPosOrder($orderId);
    
    Log::info('Jurnal rolled back for void order', [
        'order_id' => $orderId
    ]);
} catch (\Exception $e) {
    Log::error('Error rolling back jurnal in void order', [
        'order_id' => $orderId,
        'error' => $e->getMessage()
    ]);
    // Continue with void even if jurnal rollback fails
}
```

---

## 🔄 Void Order & Rollback

### Strategi Rollback

**Option 1: Delete Jurnal Entries** (Recommended)
- Simple dan langsung
- Delete semua jurnal entries dengan `reference_type = 'pos_order'` dan `reference_id = order_id`
- Delete dari `jurnal` dan `jurnal_global`

**Option 2: Create Reversal Entry**
- Buat jurnal reversal (debit jadi kredit, kredit jadi debit)
- Lebih kompleks tapi audit trail lebih lengkap
- Tidak disarankan untuk awal implementasi

**Implementasi:**
- Gunakan method `rollbackFromPosOrder()` di `JurnalService`
- Panggil saat void order, sebelum delete order data

---

## 📝 Checklist Implementasi

### Phase 1: Setup COA
- [ ] Buat COA Kas Tunai
- [ ] Buat COA Perjamuan
- [ ] Buat COA Guest Satisfaction
- [ ] Buat COA Piutang Officer Check
- [ ] Buat COA Pendapatan Penjualan POS
- [ ] Pastikan semua COA `is_active = 1`

### Phase 2: Create Service
- [ ] Buat `JurnalService` class
- [ ] Implement `createFromPosOrder()` method
- [ ] Implement `getCoaDebitFromPayment()` method
- [ ] Implement helper methods untuk get COA IDs
- [ ] Implement `rollbackFromPosOrder()` method

### Phase 3: Integration
- [ ] Integrate di `PosOrderController::syncOrder()`
- [ ] Integrate di `PosOrderController::voidOrder()`
- [ ] Test dengan single payment (Cash)
- [ ] Test dengan split payment (Cash + Bank)
- [ ] Test dengan multiple banks
- [ ] Test dengan Officer Check
- [ ] Test void order rollback

### Phase 4: Testing & Validation
- [ ] Validasi total debit = total kredit
- [ ] Validasi total payment = grand_total
- [ ] Test edge cases (payment code tidak dikenal)
- [ ] Test dengan bank yang tidak punya coa_id
- [ ] Test rollback untuk order yang sudah di-jurnal

---

## ⚠️ Catatan Penting

1. **Service Charge & PB1**: Tidak perlu breakdown, sudah termasuk di `grand_total`
2. **Multiple Payments**: 1 jurnal dengan multiple debit lines (bukan multiple jurnal entries)
3. **Bank COA**: Ambil dari `bank_accounts.coa_id`, pastikan semua bank account sudah punya `coa_id`
4. **Officer Check**: Ini adalah piutang, bukan expense. Saat HO bayar, buat jurnal terpisah
5. **No Jurnal**: Gunakan `Jurnal::generateNoJurnal()` untuk generate nomor jurnal
6. **Jurnal Global**: Setiap insert ke `jurnal` juga insert ke `jurnal_global`
7. **Error Handling**: Jangan fail order sync jika jurnal creation gagal (log saja)

---

## 🔍 Troubleshooting

### Issue: COA tidak ditemukan
**Solusi**: Pastikan COA sudah dibuat dan `is_active = 1`. Gunakan fallback ke Kas Tunai jika tidak ditemukan.

### Issue: Bank tidak punya coa_id
**Solusi**: Update semua `bank_accounts` untuk set `coa_id` yang sesuai.

### Issue: Total debit != total kredit
**Solusi**: Pastikan `jumlah_debit = jumlah_kredit` untuk setiap jurnal entry.

### Issue: Jurnal tidak ter-create
**Solusi**: Check log untuk error, pastikan order status = 'paid', pastikan ada payments.

---

## 📚 Referensi

- Model: `App\Models\Jurnal`
- Model: `App\Models\JurnalGlobal`
- Model: `App\Models\ChartOfAccount`
- Model: `App\Models\BankAccount`
- Controller: `App\Http\Controllers\Api\PosOrderController`
- Service: `App\Services\JurnalService` (akan dibuat)

---

**Last Updated**: 2026-01-30
**Version**: 1.0
