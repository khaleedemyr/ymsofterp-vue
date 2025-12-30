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
        // Default filter_not_migrated = true (hanya tampilkan yang belum migrasi)
        $filterNotMigrated = $request->has('filter_not_migrated') 
            ? ($request->get('filter_not_migrated') === '1' || $request->get('filter_not_migrated') === true) 
            : true;
        
        // Get existing emails from default connection first (untuk filter)
        $existingEmailsForFilter = [];
        if ($filterNotMigrated) {
            $existingEmailsForFilter = MemberAppsMember::whereNotNull('email')
                ->where('email', '!=', '')
                ->pluck('email')
                ->toArray();
        }
        
        // Get all customers from database second that are active
        $query = Customer::where('status_aktif', '1');
        
        // Filter hanya yang belum migrasi jika filter_not_migrated = true
        if ($filterNotMigrated) {
            // Get existing emails from member_apps_members
            $existingEmailsForFilter = MemberAppsMember::whereNotNull('email')
                ->where('email', '!=', '')
                ->pluck('email')
                ->toArray();
            
            if (count($existingEmailsForFilter) > 0) {
                $query->where(function($q) use ($existingEmailsForFilter) {
                    $q->whereNotNull('email')
                      ->where('email', '!=', '')
                      ->whereNotIn('email', $existingEmailsForFilter);
                });
            } else {
                // Jika belum ada yang migrasi, tampilkan semua yang punya email
                $query->whereNotNull('email')
                      ->where('email', '!=', '');
            }
        }
        
        // Apply search filter
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('costumers_id', 'like', "%{$search}%")
                  ->orWhere('telepon', 'like', "%{$search}%");
            });
        }
        
        // Get customers - 500 per page for better migration efficiency
        $customers = $query->orderBy('created_at', 'desc')->paginate(500)->withQueryString();
        
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
                'search' => $search,
                'filter_not_migrated' => $filterNotMigrated
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
    
    /**
     * Export ready customers to CSV for direct import to Navicat
     * Optimized with streaming and chunking to handle large datasets
     */
    public function exportCsv(Request $request)
    {
        try {
            // Set execution time limit
            set_time_limit(600); // 10 minutes
            
            // Get all occupations for mapping (cache once)
            $occupations = MemberAppsOccupation::where('is_active', true)->get()->keyBy('name');
            
            // Load existing emails dan phones dengan chunking untuk menghindari memory issue
            // Gunakan connection default (mysql) untuk member_apps_members
            $existingEmails = [];
            $existingPhones = [];
            
            // Load emails dengan chunking
            MemberAppsMember::whereNotNull('email')
                ->where('email', '!=', '')
                ->chunk(1000, function($members) use (&$existingEmails) {
                    foreach ($members as $member) {
                        $existingEmails[$member->email] = true;
                    }
                });
            
            // Load phones dengan chunking
            MemberAppsMember::whereNotNull('mobile_phone')
                ->where('mobile_phone', '!=', '')
                ->chunk(1000, function($members) use (&$existingPhones) {
                    foreach ($members as $member) {
                        $existingPhones[$member->mobile_phone] = true;
                    }
                });
            
            // Generate filename
            $filename = 'member_migration_' . date('Y-m-d_His') . '.csv';
            
            // Use streaming response untuk handle large data
            return response()->stream(function() use ($occupations, $existingEmails, $existingPhones) {
                try {
                    $output = fopen('php://output', 'w');
                    
                    if (!$output) {
                        throw new \Exception('Failed to open output stream');
                    }
            
            // Add BOM for UTF-8 (untuk Excel/Navicat)
            fprintf($output, "\xEF\xBB\xBF");
            
            // CSV Header (sesuai dengan kolom table member_apps_members)
            // TIDAK include 'id' karena AUTO_INCREMENT
            // Urutan harus sesuai dengan urutan kolom di table
            $headers = [
                'member_id',
                'photo',
                'email',
                'nama_lengkap',
                'mobile_phone',
                'tanggal_lahir',
                'jenis_kelamin',
                'pekerjaan_id',
                'pin',
                'password',
                'is_exclusive_member',
                'member_level',
                'total_spending',
                'just_points',
                'point_remainder',
                'is_active',
                'allow_notification',
                'email_verified_at',
                'mobile_verified_at',
                'last_login_at',
                'created_at',
                'updated_at'
            ];
            
            fputcsv($output, $headers, ',', '"');
            
            // Process customers in chunks to avoid memory issues
            $chunkSize = 500;
            $offset = 0;
            
            // Pre-generate default password hash (untuk performa, gunakan 1 hash untuk semua)
            // Atau bisa skip hash dan biar user set manual di Navicat
            $defaultPasswordHash = bcrypt('default123'); // Generate sekali saja
            
            // Track processed emails dan mobile_phones untuk cek duplicate dalam batch
            $processedEmails = [];
            $processedPhones = [];
            
            do {
                // Get customers in chunks
                // Query tanpa filter whereNotIn untuk menghindari "too many placeholders"
                // Filter akan dilakukan di PHP level menggunakan array lookup (O(1))
                try {
                    $query = Customer::where('status_aktif', '1')
                        ->whereNotNull('email')
                        ->where('email', '!=', '')
                        ->whereNotNull('telepon')
                        ->where('telepon', '!=', '');
                    
                    $customers = $query->orderBy('created_at', 'desc')
                        ->offset($offset)
                        ->limit($chunkSize)
                        ->get();
                } catch (\Exception $queryError) {
                    \Log::error('Query Error in Export CSV: ' . $queryError->getMessage(), [
                        'trace' => $queryError->getTraceAsString()
                    ]);
                    break; // Break loop jika query error
                }
                
                if ($customers->isEmpty()) {
                    break;
                }
                
                foreach ($customers as $customer) {
                    // Filter di PHP level: skip jika email atau telepon sudah ada di existingEmails/existingPhones
                    // Array lookup adalah O(1), lebih efisien daripada query dengan banyak whereNotIn
                    if (isset($existingEmails[$customer->email]) || isset($existingPhones[$customer->telepon])) {
                        continue; // Skip jika sudah ada di member_apps_members
                    }
                    
                    // Skip jika email atau telepon duplicate dalam batch ini
                    if (isset($processedEmails[$customer->email]) || isset($processedPhones[$customer->telepon])) {
                        continue; // Skip duplicate dalam batch
                    }
                    
                    // Track processed
                    $processedEmails[$customer->email] = true;
                    $processedPhones[$customer->telepon] = true;
                    // Map jenis kelamin: 1 -> 'L', 2 -> 'P'
                    $jenisKelamin = null;
                    if ($customer->jenis_kelamin == '1' || $customer->jenis_kelamin === 1) {
                        $jenisKelamin = 'L';
                    } elseif ($customer->jenis_kelamin == '2' || $customer->jenis_kelamin === 2) {
                        $jenisKelamin = 'P';
                    }
                    
                    // Default jika kosong
                    if (empty($jenisKelamin)) {
                        $jenisKelamin = 'L';
                    }
                    
                    // Map is_exclusive_member: 'Y' -> 1, 'N' -> 0
                    $isExclusiveMember = ($customer->exclusive_member === 'Y') ? 1 : 0;
                    
                    // Map pekerjaan_id (cached occupations)
                    $pekerjaanId = $this->mapPekerjaanId($customer->pekerjaan);
                    
                    // Validasi pekerjaan_id - jika tidak valid atau tidak ada, set NULL
                    // Foreign key constraint akan error jika pekerjaan_id tidak ada di member_apps_occupations
                    if ($pekerjaanId) {
                        // Cek apakah pekerjaan_id valid (ada di table dan aktif)
                        $occupationExists = MemberAppsOccupation::where('id', $pekerjaanId)->where('is_active', true)->exists();
                        if (!$occupationExists) {
                            $pekerjaanId = null; // Set NULL jika pekerjaan tidak valid (akan jadi empty string di CSV)
                        }
                    }
                    
                    // Untuk export CSV, gunakan default password hash (lebih cepat)
                    $pin = $defaultPasswordHash; // Default PIN hash
                    $password = $defaultPasswordHash; // Default password hash
                    
                    // Format dates
                    // Tanggal lahir: pastikan tidak ada 0000-00-00 (invalid date untuk MySQL)
                    $tanggalLahir = '1970-01-01'; // Default date
                    if (!empty($customer->tanggal_lahir)) {
                        // Cek jika tanggal_lahir berisi 0000-00-00
                        $rawTanggalLahir = is_string($customer->tanggal_lahir) ? trim($customer->tanggal_lahir) : (string)$customer->tanggal_lahir;
                        if ($rawTanggalLahir && 
                            $rawTanggalLahir !== '0000-00-00' && 
                            strpos($rawTanggalLahir, '0000-00-00') === false &&
                            strpos($rawTanggalLahir, '0000') === false) {
                            // Parse tanggal
                            $timestamp = @strtotime($rawTanggalLahir);
                            if ($timestamp !== false && $timestamp > 0) {
                                $parsedDate = date('Y-m-d', $timestamp);
                                // Validasi hasil parsing
                                if ($parsedDate && 
                                    $parsedDate !== '1970-01-01' && 
                                    $parsedDate !== '0000-00-00' && 
                                    strpos($parsedDate, '0000') === false &&
                                    preg_match('/^\d{4}-\d{2}-\d{2}$/', $parsedDate)) {
                                    $tanggalLahir = $parsedDate;
                                }
                            }
                        }
                    }
                    
                    // Last login: nullable, pastikan tidak ada 0000-00-00
                    $lastLogin = null;
                    if (!empty($customer->last_logged)) {
                        $rawLastLogin = is_string($customer->last_logged) ? trim($customer->last_logged) : (string)$customer->last_logged;
                        if ($rawLastLogin && 
                            $rawLastLogin !== '0000-00-00 00:00:00' && 
                            strpos($rawLastLogin, '0000-00-00') === false &&
                            strpos($rawLastLogin, '0000') === false) {
                            $timestamp = @strtotime($rawLastLogin);
                            if ($timestamp !== false && $timestamp > 0) {
                                $parsedLastLogin = date('Y-m-d H:i:s', $timestamp);
                                if ($parsedLastLogin && 
                                    strpos($parsedLastLogin, '1970-01-01') === false && 
                                    strpos($parsedLastLogin, '0000-00-00') === false &&
                                    preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $parsedLastLogin)) {
                                    $lastLogin = $parsedLastLogin;
                                }
                            }
                        }
                    }
                    
                    $now = date('Y-m-d H:i:s');
                    
                    // Format data untuk Navicat
                    // Untuk foreign key (pekerjaan_id), jika NULL harus benar-benar empty (tidak bisa 0 atau empty string yang tidak valid)
                    $memberId = $customer->costumers_id ?: '';
                    // pekerjaan_id: hanya set jika valid dan > 0, jika tidak set empty string
                    // Pastikan pekerjaan_id valid atau NULL (empty string akan jadi NULL di Navicat)
                    $pekerjaanIdValue = ($pekerjaanId !== null && $pekerjaanId !== '' && $pekerjaanId > 0) ? (int)$pekerjaanId : '';
                    
                    $row = [
                        $memberId, // member_id (nullable) - empty string jika NULL
                        '', // photo (nullable) - empty string
                        $customer->email, // email (NOT NULL, UNIQUE)
                        $customer->name ?: 'Member', // nama_lengkap (NOT NULL)
                        $customer->telepon, // mobile_phone (NOT NULL, UNIQUE)
                        $tanggalLahir, // tanggal_lahir (NOT NULL)
                        $jenisKelamin, // jenis_kelamin (NOT NULL, enum('L', 'P'))
                        $pekerjaanIdValue, // pekerjaan_id (nullable) - empty string jika NULL (akan jadi NULL di Navicat)
                        $pin, // pin (NOT NULL) - default hash
                        $password, // password (NOT NULL) - default hash
                        $isExclusiveMember, // is_exclusive_member (default 0)
                        'Silver', // member_level (NOT NULL, enum) - harus 'Silver' (case sensitive)
                        '0.00', // total_spending (default 0.00)
                        '0', // just_points (default 0)
                        '0.00', // point_remainder (default 0.00)
                        '1', // is_active (default 1)
                        '1', // allow_notification (default 1)
                        $now, // email_verified_at (nullable) - set timestamp
                        '', // mobile_verified_at (nullable) - empty string untuk NULL
                        $lastLogin ?: '', // last_login_at (nullable) - empty string jika NULL
                        $now, // created_at (nullable) - set timestamp
                        $now  // updated_at (nullable) - set timestamp
                    ];
                    
                    // Use fputcsv untuk format yang benar
                    // Navicat akan convert empty string ke NULL jika setting "Replace NULL with" dikosongkan
                    fputcsv($output, $row, ',', '"');
                }
                
                $offset += $chunkSize;
                
                // Flush output buffer untuk streaming
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
                
                // Clear memory
                unset($customers);
                
                // Reset processed arrays setiap beberapa chunk untuk menghindari memory issue
                if ($offset % 5000 == 0) {
                    $processedEmails = [];
                    $processedPhones = [];
                }
                
            } while (true);
            
            fclose($output);
                } catch (\Exception $e) {
                    // Log error jika ada
                    \Log::error('Export CSV Error: ' . $e->getMessage(), [
                        'trace' => $e->getTraceAsString()
                    ]);
                    // Output error message jika output stream masih terbuka
                    if (isset($output) && is_resource($output)) {
                        fwrite($output, "Error: " . $e->getMessage());
                        fclose($output);
                    }
                }
            }, 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                'Content-Transfer-Encoding' => 'binary',
            ]);
        } catch (\Exception $e) {
            \Log::error('Export CSV Initialization Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'error' => 'Failed to export CSV: ' . $e->getMessage()
            ], 500);
        }
    }
}

