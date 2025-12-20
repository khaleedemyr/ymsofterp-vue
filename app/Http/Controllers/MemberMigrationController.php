<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\MemberAppsMember;
use App\Models\MemberAppsOccupation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class MemberMigrationController extends Controller
{
    /**
     * Helper function to map pekerjaan from costumers to member_apps_occupations
     */
    private function mapPekerjaanId($pekerjaan)
    {
        if (empty($pekerjaan)) {
            return null;
        }
        
        // Clean pekerjaan string (remove trailing dots, spaces, convert to lowercase)
        $pekerjaanClean = trim(strtolower(rtrim($pekerjaan, '.')));
        
        // Try exact match first
        $occupation = MemberAppsOccupation::where('is_active', true)
            ->where('name', $pekerjaan)
            ->first();
        
        if ($occupation) {
            return $occupation->id;
        }
        
        // Try case-insensitive match with cleaned string
        $occupation = MemberAppsOccupation::where('is_active', true)
            ->whereRaw('LOWER(TRIM(TRIM(TRAILING \'.\' FROM name))) = ?', [$pekerjaanClean])
            ->first();
        
        if ($occupation) {
            return $occupation->id;
        }
        
        // Try partial match (contains) - check if cleaned pekerjaan contains or is contained in occupation name
        $occupations = MemberAppsOccupation::where('is_active', true)->get();
        foreach ($occupations as $occ) {
            $occNameClean = trim(strtolower(rtrim($occ->name, '.')));
            if (strpos($occNameClean, $pekerjaanClean) !== false || strpos($pekerjaanClean, $occNameClean) !== false) {
                return $occ->id;
            }
        }
        
        return null;
    }
    
    /**
     * Display a listing of members that can be migrated
     */
    public function index(Request $request)
    {
        $search = $request->get('search', '');
        
        // Get all customers from database second that are active
        $query = Customer::where('status_aktif', '1');
        
        // Apply search filter
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('costumers_id', 'like', "%{$search}%")
                  ->orWhere('telepon', 'like', "%{$search}%");
            });
        }
        
        // Get customers
        $customers = $query->orderBy('created_at', 'desc')->paginate(20)->withQueryString();
        
        // Get existing emails as a Set for faster lookup (only emails from current page customers)
        $currentPageEmails = $customers->getCollection()->pluck('email')->filter()->toArray();
        $existingEmailsSet = MemberAppsMember::whereIn('email', $currentPageEmails)
            ->pluck('email')
            ->flip(); // Use flip() to create a hash map for O(1) lookup
        
        // Get all occupations for mapping (cache this)
        $occupations = MemberAppsOccupation::where('is_active', true)->get()->keyBy('name');
        
        // Transform customers to include migration status
        $customers->getCollection()->transform(function ($customer) use ($existingEmailsSet, $occupations) {
            $customer->can_migrate = !empty($customer->email) && !isset($existingEmailsSet[$customer->email]);
            $customer->is_migrated = !$customer->can_migrate && !empty($customer->email);
            $customer->migration_status = $customer->can_migrate ? 'ready' : ($customer->is_migrated ? 'migrated' : 'no_email');
            
            // Map pekerjaan to pekerjaan_id using helper function
            $customer->pekerjaan_id_mapped = $this->mapPekerjaanId($customer->pekerjaan);
            
            // Map jenis kelamin text manually (handle null, empty, '1', '2', etc)
            $jenisKelaminValue = $customer->jenis_kelamin;
            if ($jenisKelaminValue == '1' || $jenisKelaminValue === 1) {
                $customer->jenis_kelamin_text = 'Laki-laki';
            } elseif ($jenisKelaminValue == '2' || $jenisKelaminValue === 2) {
                $customer->jenis_kelamin_text = 'Perempuan';
            } else {
                $customer->jenis_kelamin_text = '-';
            }
            
            return $customer;
        });
        
        // Calculate stats efficiently using database queries
        $totalCount = Customer::where('status_aktif', '1')->count();
        
        // Get existing emails from default connection first
        $existingEmailsForStats = MemberAppsMember::whereNotNull('email')
            ->where('email', '!=', '')
            ->pluck('email')
            ->toArray();
        
        // Use raw query for better performance on large datasets
        // Count ready customers (have email and not in existing emails)
        $readyCount = DB::connection('mysql_second')
            ->table('costumers')
            ->where('status_aktif', '1')
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->whereNotIn('email', $existingEmailsForStats)
            ->count();
            
        // Count migrated customers (have email and in existing emails)
        $migratedCount = DB::connection('mysql_second')
            ->table('costumers')
            ->where('status_aktif', '1')
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->whereIn('email', $existingEmailsForStats)
            ->count();
            
        $noEmailCount = Customer::where('status_aktif', '1')
            ->where(function($q) {
                $q->whereNull('email')
                  ->orWhere('email', '=', '');
            })
            ->count();
        
        return Inertia::render('MemberMigration/Index', [
            'customers' => $customers,
            'filters' => [
                'search' => $search
            ],
            'stats' => [
                'total' => $totalCount,
                'ready' => $readyCount,
                'migrated' => $migratedCount,
                'no_email' => $noEmailCount,
            ]
        ]);
    }
    
    /**
     * Get all ready customer IDs (for migrate all functionality)
     * Optimized to use database query instead of loading all data into memory
     */
    public function getReadyCustomers()
    {
        // Get existing emails from default connection first
        $existingEmails = MemberAppsMember::whereNotNull('email')
            ->where('email', '!=', '')
            ->pluck('email')
            ->toArray();
        
        // Use database query to get only IDs of ready customers
        // This avoids loading all customer data into memory
        $readyCustomerIds = Customer::where('status_aktif', '1')
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->whereNotIn('email', $existingEmails)
            ->pluck('id')
            ->toArray();
        
        return response()->json([
            'success' => true,
            'customer_ids' => $readyCustomerIds,
            'count' => count($readyCustomerIds)
        ]);
    }
    
    /**
     * Migrate a single customer to member_apps_members
     */
    public function migrate(Request $request, $customerId)
    {
        try {
            DB::beginTransaction();
            
            // Get customer from database second
            $customer = Customer::findOrFail($customerId);
            
            // Validate customer can be migrated
            if ($customer->status_aktif !== '1') {
                return response()->json([
                    'success' => false,
                    'message' => 'Customer tidak aktif, tidak dapat di-migrasi'
                ], 400);
            }
            
            if (empty($customer->email)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Customer tidak memiliki email, tidak dapat di-migrasi'
                ], 400);
            }
            
            // Check if email already exists
            $existingMember = MemberAppsMember::where('email', $customer->email)->first();
            if ($existingMember) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email sudah terdaftar di member_apps_members'
                ], 400);
            }
            
            // Map jenis kelamin: 1 -> 'L', 2 -> 'P'
            $jenisKelamin = null;
            if ($customer->jenis_kelamin == '1' || $customer->jenis_kelamin === 1) {
                $jenisKelamin = 'L';
            } elseif ($customer->jenis_kelamin == '2' || $customer->jenis_kelamin === 2) {
                $jenisKelamin = 'P';
            }
            
            // Map is_exclusive_member: 'Y' -> 1, 'N' -> 0
            $isExclusiveMember = ($customer->exclusive_member === 'Y') ? 1 : 0;
            
            // Map pekerjaan_id using helper function
            $pekerjaanId = $this->mapPekerjaanId($customer->pekerjaan);
            
            // Use android_password (required)
            $password = $customer->android_password;
            
            // Encrypt PIN if not already encrypted (check if it's a bcrypt hash)
            $pin = $customer->pin;
            if (!empty($pin) && !preg_match('/^\$2[ayb]\$.{56}$/', $pin)) {
                // PIN is plain text, encrypt it
                $pin = bcrypt($pin);
            }
            
            // Create member in member_apps_members
            $member = MemberAppsMember::create([
                'member_id' => $customer->costumers_id,
                'photo' => null,
                'email' => $customer->email,
                'nama_lengkap' => $customer->name,
                'mobile_phone' => $customer->telepon,
                'tanggal_lahir' => $customer->tanggal_lahir,
                'jenis_kelamin' => $jenisKelamin,
                'pekerjaan_id' => $pekerjaanId,
                'pin' => $pin,
                'password' => $password ? bcrypt($password) : bcrypt('default123'), // Default password if empty
                'is_exclusive_member' => $isExclusiveMember,
                'member_level' => 'silver',
                'total_spending' => 0,
                'just_points' => 0,
                'point_remainder' => 0,
                'is_active' => 1,
                'allow_notification' => 1,
                'email_verified_at' => now(),
                'mobile_verified_at' => null,
                'last_login_at' => $customer->last_logged,
            ]);
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Member berhasil di-migrasi',
                'member' => $member
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            \Log::error('Member migration failed', [
                'customer_id' => $customerId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal migrasi member: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Migrate multiple customers
     * Optimized to process in chunks to avoid memory issues
     * For large migrations, use artisan command: php artisan members:migrate --all
     */
    public function migrateMultiple(Request $request)
    {
        $request->validate([
            'customer_ids' => 'required|array|min:1',
            'customer_ids.*' => 'required|integer'
        ]);
        
        $customerIds = $request->customer_ids;
        
        // Limit to 500 customers per request to avoid timeout
        if (count($customerIds) > 500) {
            return response()->json([
                'success' => false,
                'message' => 'Terlalu banyak customer. Maksimal 500 per request. Gunakan command: php artisan members:migrate --all untuk migrasi besar.',
                'suggestion' => 'Untuk migrasi besar, jalankan: php artisan members:migrate --all --chunk=50'
            ], 400);
        }
        
        $successCount = 0;
        $failedCount = 0;
        $errors = [];
        
        // Process in smaller chunks of 50 to avoid timeout
        $chunks = array_chunk($customerIds, 50);
        
        foreach ($chunks as $chunk) {
            // Get all customers in this chunk at once
            $customers = Customer::whereIn('id', $chunk)->get()->keyBy('id');
            
            // Get existing emails for this chunk
            $chunkEmails = $customers->pluck('email')->filter()->toArray();
            $existingEmails = MemberAppsMember::whereIn('email', $chunkEmails)
                ->pluck('email')
                ->flip();
            
            foreach ($chunk as $customerId) {
                try {
                    if (!isset($customers[$customerId])) {
                        $failedCount++;
                        $errors[] = "Customer ID {$customerId}: Tidak ditemukan";
                        continue;
                    }
                    
                    $customer = $customers[$customerId];
                    
                    DB::beginTransaction();
                    
                    // Validate customer can be migrated
                    if ($customer->status_aktif !== '1') {
                        $failedCount++;
                        $errors[] = "Customer ID {$customerId}: Tidak aktif";
                        DB::rollBack();
                        continue;
                    }
                    
                    if (empty($customer->email)) {
                        $failedCount++;
                        $errors[] = "Customer ID {$customerId}: Tidak memiliki email";
                        DB::rollBack();
                        continue;
                    }
                    
                    // Check if email already exists (using pre-fetched set)
                    if (isset($existingEmails[$customer->email])) {
                        $failedCount++;
                        $errors[] = "Customer ID {$customerId}: Email sudah terdaftar";
                        DB::rollBack();
                        continue;
                    }
                    
                    // Map jenis kelamin: 1 -> 'L', 2 -> 'P'
                    $jenisKelamin = null;
                    if ($customer->jenis_kelamin == '1' || $customer->jenis_kelamin === 1) {
                        $jenisKelamin = 'L';
                    } elseif ($customer->jenis_kelamin == '2' || $customer->jenis_kelamin === 2) {
                        $jenisKelamin = 'P';
                    }
                    
                    // Map is_exclusive_member: 'Y' -> 1, 'N' -> 0
                    $isExclusiveMember = ($customer->exclusive_member === 'Y') ? 1 : 0;
                    
                    // Map pekerjaan_id using helper function
                    $pekerjaanId = $this->mapPekerjaanId($customer->pekerjaan);
                    
                    // Use android_password (required)
                    $password = $customer->android_password;
                    
                    // Encrypt PIN if not already encrypted (check if it's a bcrypt hash)
                    $pin = $customer->pin;
                    if (!empty($pin) && !preg_match('/^\$2[ayb]\$.{56}$/', $pin)) {
                        // PIN is plain text, encrypt it
                        $pin = bcrypt($pin);
                    }
                    
                    // Create member in member_apps_members
                    MemberAppsMember::create([
                        'member_id' => $customer->costumers_id,
                        'photo' => null,
                        'email' => $customer->email,
                        'nama_lengkap' => $customer->name,
                        'mobile_phone' => $customer->telepon,
                        'tanggal_lahir' => $customer->tanggal_lahir,
                        'jenis_kelamin' => $jenisKelamin,
                        'pekerjaan_id' => $pekerjaanId,
                        'pin' => $pin,
                        'password' => $password ? bcrypt($password) : bcrypt('default123'), // Default password if empty
                        'is_exclusive_member' => $isExclusiveMember,
                        'member_level' => 'silver',
                        'total_spending' => 0,
                        'just_points' => 0,
                        'point_remainder' => 0,
                        'is_active' => 1,
                        'allow_notification' => 1,
                        'email_verified_at' => now(),
                        'mobile_verified_at' => null,
                        'last_login_at' => $customer->last_logged,
                    ]);
                    
                    DB::commit();
                    $successCount++;
                    
                    // Add to existing emails set to avoid duplicate checks in same batch
                    $existingEmails[$customer->email] = true;
                    
                } catch (\Exception $e) {
                    DB::rollBack();
                    $failedCount++;
                    $errors[] = "Customer ID {$customerId}: " . $e->getMessage();
                    
                    \Log::error('Member migration failed', [
                        'customer_id' => $customerId,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            
            // Clear memory after each chunk
            unset($customers, $chunkEmails, $existingEmails);
        }
        
        return response()->json([
            'success' => true,
            'message' => "Migrasi selesai: {$successCount} berhasil, {$failedCount} gagal",
            'success_count' => $successCount,
            'failed_count' => $failedCount,
            'errors' => array_slice($errors, 0, 100) // Limit errors to first 100 to avoid large response
        ]);
    }
}

