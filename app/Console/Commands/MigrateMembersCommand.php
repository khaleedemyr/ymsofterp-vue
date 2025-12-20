<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\MemberAppsMember;
use App\Models\MemberAppsOccupation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateMembersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'members:migrate 
                            {--ids=* : Specific customer IDs to migrate}
                            {--all : Migrate all ready customers}
                            {--chunk=100 : Number of customers to process per chunk}
                            {--skip-existing : Skip customers that already exist}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate customers from costumers table to member_apps_members';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting member migration...');
        
        $customerIds = [];
        
        if ($this->option('all')) {
            // Get all ready customer IDs
            $existingEmails = MemberAppsMember::whereNotNull('email')
                ->where('email', '!=', '')
                ->pluck('email')
                ->toArray();
            
            $customerIds = Customer::where('status_aktif', '1')
                ->whereNotNull('email')
                ->where('email', '!=', '')
                ->whereNotIn('email', $existingEmails)
                ->pluck('id')
                ->toArray();
            
            $this->info("Found " . count($customerIds) . " customers ready to migrate.");
        } elseif ($this->option('ids')) {
            $customerIds = $this->option('ids');
            $this->info("Migrating " . count($customerIds) . " specific customers.");
        } else {
            $this->error('Please specify --all or --ids option');
            return 1;
        }
        
        if (empty($customerIds)) {
            $this->warn('No customers to migrate.');
            return 0;
        }
        
        $chunkSize = (int) $this->option('chunk');
        $chunks = array_chunk($customerIds, $chunkSize);
        $totalChunks = count($chunks);
        
        $this->info("Processing in {$totalChunks} chunks of {$chunkSize} customers each...");
        
        $successCount = 0;
        $failedCount = 0;
        $skippedCount = 0;
        $errors = [];
        
        $bar = $this->output->createProgressBar(count($customerIds));
        $bar->start();
        
        foreach ($chunks as $chunkIndex => $chunk) {
            $this->newLine();
            $this->info("Processing chunk " . ($chunkIndex + 1) . " of {$totalChunks}...");
            
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
                        $bar->advance();
                        continue;
                    }
                    
                    $customer = $customers[$customerId];
                    
                    // Skip if already exists and --skip-existing flag is set
                    if ($this->option('skip-existing') && isset($existingEmails[$customer->email])) {
                        $skippedCount++;
                        $bar->advance();
                        continue;
                    }
                    
                    DB::beginTransaction();
                    
                    // Validate customer can be migrated
                    if ($customer->status_aktif !== '1') {
                        $failedCount++;
                        $errors[] = "Customer ID {$customerId}: Tidak aktif";
                        DB::rollBack();
                        $bar->advance();
                        continue;
                    }
                    
                    if (empty($customer->email)) {
                        $failedCount++;
                        $errors[] = "Customer ID {$customerId}: Tidak memiliki email";
                        DB::rollBack();
                        $bar->advance();
                        continue;
                    }
                    
                    // Check if email already exists
                    if (isset($existingEmails[$customer->email])) {
                        $skippedCount++;
                        DB::rollBack();
                        $bar->advance();
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
                    
                    // Map pekerjaan_id
                    $pekerjaanId = $this->mapPekerjaanId($customer->pekerjaan);
                    
                    // Use android_password (required)
                    $password = $customer->android_password;
                    
                    // Encrypt PIN if not already encrypted
                    $pin = $customer->pin;
                    if (!empty($pin) && !preg_match('/^\$2[ayb]\$.{56}$/', $pin)) {
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
                        'password' => $password ? bcrypt($password) : bcrypt('default123'),
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
                
                $bar->advance();
            }
            
            // Clear memory after each chunk
            unset($customers, $chunkEmails, $existingEmails);
            
            // Small delay to prevent overwhelming the system
            if ($chunkIndex < $totalChunks - 1) {
                usleep(100000); // 0.1 second delay
            }
        }
        
        $bar->finish();
        $this->newLine(2);
        
        // Display summary
        $this->info("Migration completed!");
        $this->table(
            ['Status', 'Count'],
            [
                ['Success', $successCount],
                ['Failed', $failedCount],
                ['Skipped', $skippedCount],
                ['Total', count($customerIds)],
            ]
        );
        
        // Display errors if any
        if (!empty($errors) && count($errors) <= 20) {
            $this->warn('Errors:');
            foreach (array_slice($errors, 0, 20) as $error) {
                $this->error($error);
            }
            if (count($errors) > 20) {
                $this->warn('... and ' . (count($errors) - 20) . ' more errors');
            }
        }
        
        return 0;
    }
    
    /**
     * Helper function to map pekerjaan from costumers to member_apps_occupations
     */
    private function mapPekerjaanId($pekerjaan)
    {
        if (empty($pekerjaan)) {
            return null;
        }
        
        // Clean pekerjaan string
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
        
        // Try partial match
        $occupations = MemberAppsOccupation::where('is_active', true)->get();
        foreach ($occupations as $occ) {
            $occNameClean = trim(strtolower(rtrim($occ->name, '.')));
            if (strpos($occNameClean, $pekerjaanClean) !== false || strpos($pekerjaanClean, $occNameClean) !== false) {
                return $occ->id;
            }
        }
        
        return null;
    }
}

