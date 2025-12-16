<?php

/**
 * Test Script untuk Birthday Points
 * 
 * Script ini untuk test apakah birthday points masuk ke table member_apps_point_earnings
 * 
 * Cara menjalankan:
 * php test_birthday_points.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\MemberAppsMember;
use App\Models\MemberAppsPointTransaction;
use App\Models\MemberAppsPointEarning;
use App\Services\PointEarningService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

echo "========================================\n";
echo "TEST BIRTHDAY POINTS\n";
echo "========================================\n\n";

try {
    // 1. Cari member yang aktif dan punya tanggal lahir
    echo "1. Mencari member untuk test...\n";
    $member = MemberAppsMember::where('is_active', 1)
        ->whereNotNull('tanggal_lahir')
        ->first();
    
    if (!$member) {
        echo "   ❌ Tidak ada member yang ditemukan!\n";
        exit(1);
    }
    
    echo "   ✓ Member ditemukan:\n";
    echo "      - ID: {$member->id}\n";
    echo "      - Member ID: {$member->member_id}\n";
    echo "      - Nama: {$member->nama_lengkap}\n";
    echo "      - Tanggal Lahir: {$member->tanggal_lahir}\n";
    echo "      - Points saat ini: " . ($member->just_points ?? 0) . "\n\n";
    
    // 2. Cek apakah sudah ada birthday points tahun ini
    echo "2. Cek birthday points tahun ini...\n";
    $yearStart = now()->startOfYear();
    $yearEnd = now()->endOfYear();
    
    $existingBonus = MemberAppsPointTransaction::where('member_id', $member->id)
        ->where('transaction_type', 'bonus')
        ->where('channel', 'birthday')
        ->whereBetween('transaction_date', [$yearStart->format('Y-m-d'), $yearEnd->format('Y-m-d')])
        ->first();
    
    if ($existingBonus) {
        echo "   ⚠️  Member sudah menerima birthday points tahun ini!\n";
        echo "      Transaction ID: {$existingBonus->id}\n";
        echo "      Point Amount: {$existingBonus->point_amount}\n";
        echo "      Transaction Date: {$existingBonus->transaction_date}\n\n";
        
        // Cek apakah ada di point_earnings
        $pointEarning = MemberAppsPointEarning::where('member_id', $member->id)
            ->where('point_transaction_id', $existingBonus->id)
            ->first();
        
        if ($pointEarning) {
            echo "   ✓ Point earning DITEMUKAN di member_apps_point_earnings:\n";
            echo "      - Earning ID: {$pointEarning->id}\n";
            echo "      - Point Amount: {$pointEarning->point_amount}\n";
            echo "      - Remaining Points: {$pointEarning->remaining_points}\n";
            echo "      - Earned At: {$pointEarning->earned_at}\n";
            echo "      - Expires At: {$pointEarning->expires_at}\n";
            echo "      - Is Expired: " . ($pointEarning->is_expired ? 'Yes' : 'No') . "\n";
            echo "      - Is Fully Redeemed: " . ($pointEarning->is_fully_redeemed ? 'Yes' : 'No') . "\n\n";
        } else {
            echo "   ❌ Point earning TIDAK DITEMUKAN di member_apps_point_earnings!\n";
            echo "      Transaction ID: {$existingBonus->id}\n\n";
            
            echo "   🔍 Mencari semua point earnings untuk member ini...\n";
            $allEarnings = MemberAppsPointEarning::where('member_id', $member->id)->get();
            echo "      Total point earnings: {$allEarnings->count()}\n";
            if ($allEarnings->count() > 0) {
                echo "      Point Earnings:\n";
                foreach ($allEarnings as $earning) {
                    echo "        - ID: {$earning->id}, Transaction ID: {$earning->point_transaction_id}, Points: {$earning->point_amount}\n";
                }
            }
            echo "\n";
        }
        
        echo "   💡 Untuk test ulang, hapus transaction dan earning yang sudah ada:\n";
        echo "      DELETE FROM member_apps_point_earnings WHERE point_transaction_id = {$existingBonus->id};\n";
        echo "      DELETE FROM member_apps_point_transactions WHERE id = {$existingBonus->id};\n";
        echo "      UPDATE member_apps_members SET just_points = just_points - {$existingBonus->point_amount} WHERE id = {$member->id};\n\n";
        
        exit(0);
    }
    
    echo "   ✓ Belum ada birthday points tahun ini, lanjut test...\n\n";
    
    // 3. Test award birthday points
    echo "3. Test award birthday points...\n";
    $pointService = app(PointEarningService::class);
    $pointsBefore = $member->just_points ?? 0;
    
    echo "   Points sebelum: {$pointsBefore}\n";
    
    $result = $pointService->earnBonusPoints($member->id, 'birthday');
    
    if (!$result) {
        echo "   ❌ Gagal award birthday points!\n";
        exit(1);
    }
    
    echo "   ✓ Birthday points berhasil di-award!\n";
    echo "      Points earned: {$result['points_earned']}\n";
    echo "      Total points: {$result['total_points']}\n";
    echo "      Expires at: {$result['expires_at']}\n";
    echo "      Transaction ID: {$result['transaction']->id}\n\n";
    
    // 4. Verifikasi di database
    echo "4. Verifikasi di database...\n";
    
    // Refresh member
    $member->refresh();
    $pointsAfter = $member->just_points ?? 0;
    echo "   Points setelah: {$pointsAfter}\n";
    echo "   Selisih: " . ($pointsAfter - $pointsBefore) . "\n\n";
    
    // Cek point transaction
    $transaction = MemberAppsPointTransaction::find($result['transaction']->id);
    if ($transaction) {
        echo "   ✓ Point transaction ditemukan:\n";
        echo "      - ID: {$transaction->id}\n";
        echo "      - Type: {$transaction->transaction_type}\n";
        echo "      - Channel: {$transaction->channel}\n";
        echo "      - Point Amount: {$transaction->point_amount}\n";
        echo "      - Transaction Date: {$transaction->transaction_date}\n\n";
    } else {
        echo "   ❌ Point transaction TIDAK ditemukan!\n\n";
    }
    
    // Cek point earning
    $pointEarning = MemberAppsPointEarning::where('member_id', $member->id)
        ->where('point_transaction_id', $result['transaction']->id)
        ->first();
    
    if ($pointEarning) {
        echo "   ✓ Point earning DITEMUKAN di member_apps_point_earnings:\n";
        echo "      - Earning ID: {$pointEarning->id}\n";
        echo "      - Member ID: {$pointEarning->member_id}\n";
        echo "      - Point Transaction ID: {$pointEarning->point_transaction_id}\n";
        echo "      - Point Amount: {$pointEarning->point_amount}\n";
        echo "      - Remaining Points: {$pointEarning->remaining_points}\n";
        echo "      - Earned At: {$pointEarning->earned_at}\n";
        echo "      - Expires At: {$pointEarning->expires_at}\n";
        echo "      - Is Expired: " . ($pointEarning->is_expired ? 'Yes' : 'No') . "\n";
        echo "      - Is Fully Redeemed: " . ($pointEarning->is_fully_redeemed ? 'Yes' : 'No') . "\n\n";
        
        echo "   ✅ TEST BERHASIL! Birthday points masuk ke member_apps_point_earnings.\n\n";
    } else {
        echo "   ❌ Point earning TIDAK DITEMUKAN di member_apps_point_earnings!\n";
        echo "      Transaction ID yang dicari: {$result['transaction']->id}\n\n";
        
        echo "   🔍 Mencari semua point earnings untuk member ini...\n";
        $allEarnings = MemberAppsPointEarning::where('member_id', $member->id)->get();
        echo "      Total point earnings: {$allEarnings->count()}\n";
        if ($allEarnings->count() > 0) {
            echo "      Point Earnings:\n";
            foreach ($allEarnings as $earning) {
                echo "        - ID: {$earning->id}, Transaction ID: {$earning->point_transaction_id}, Points: {$earning->point_amount}\n";
            }
        }
        echo "\n";
        
        echo "   ❌ TEST GAGAL! Birthday points TIDAK masuk ke member_apps_point_earnings.\n\n";
        
        // Cek apakah ada error di log
        echo "   🔍 Cek log untuk error...\n";
        $logFile = storage_path('logs/laravel.log');
        if (file_exists($logFile)) {
            $logContent = file_get_contents($logFile);
            if (strpos($logContent, 'Error earning bonus points') !== false) {
                echo "      ⚠️  Ada error di log file!\n";
                echo "      Cek: storage/logs/laravel.log\n";
            }
        }
    }
    
    // 5. Cleanup (optional)
    echo "5. Cleanup (optional)...\n";
    echo "   Untuk menghapus test data, jalankan:\n";
    echo "   DELETE FROM member_apps_point_earnings WHERE point_transaction_id = {$result['transaction']->id};\n";
    echo "   DELETE FROM member_apps_point_transactions WHERE id = {$result['transaction']->id};\n";
    echo "   UPDATE member_apps_members SET just_points = just_points - {$result['points_earned']} WHERE id = {$member->id};\n\n";
    
    echo "========================================\n";
    echo "TEST SELESAI\n";
    echo "========================================\n";
    
} catch (\Exception $e) {
    echo "\n❌ ERROR: {$e->getMessage()}\n";
    echo "Stack trace:\n{$e->getTraceAsString()}\n";
    exit(1);
}

