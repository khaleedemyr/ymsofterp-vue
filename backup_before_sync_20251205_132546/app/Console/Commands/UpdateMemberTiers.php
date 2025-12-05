<?php

namespace App\Console\Commands;

use App\Models\MemberAppsMember;
use App\Services\MemberTierService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class UpdateMemberTiers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'members:update-tiers {--member-id= : Update specific member only}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update member tiers based on rolling 12-month spending';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting tier update process...');

        $memberId = $this->option('member-id');

        if ($memberId) {
            // Update specific member
            $member = MemberAppsMember::find($memberId);
            if (!$member) {
                $this->error("Member with ID {$memberId} not found");
                return 1;
            }

            $result = MemberTierService::updateMemberTier($memberId);
            if ($result && $result['updated']) {
                $this->info("Member {$memberId}: {$result['old_tier']} → {$result['new_tier']} (Rolling 12M: " . number_format($result['rolling_spending']) . ")");
            } else {
                $this->info("Member {$memberId}: No change (Current: {$result['current_tier']}, Rolling 12M: " . number_format($result['rolling_spending']) . ")");
            }
        } else {
            // Update all active members
            $members = MemberAppsMember::where('is_active', true)->get();
            $total = $members->count();
            $updated = 0;
            $noChange = 0;

            $this->info("Processing {$total} members...");

            $bar = $this->output->createProgressBar($total);
            $bar->start();

            foreach ($members as $member) {
                $result = MemberTierService::updateMemberTier($member->id);
                if ($result && $result['updated']) {
                    $updated++;
                    Log::info("Tier updated via command", [
                        'member_id' => $member->id,
                        'old_tier' => $result['old_tier'],
                        'new_tier' => $result['new_tier'],
                    ]);
                } else {
                    $noChange++;
                }
                $bar->advance();
            }

            $bar->finish();
            $this->newLine();
            $this->info("Completed: {$updated} updated, {$noChange} no change");
        }

        $this->info('Tier update process completed.');
        return 0;
    }
}

