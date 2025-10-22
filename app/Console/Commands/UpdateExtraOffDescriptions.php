<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ExtraOffService;

class UpdateExtraOffDescriptions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'extra-off:update-descriptions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update existing Extra Off transaction descriptions to include work time details';

    protected $extraOffService;

    public function __construct(ExtraOffService $extraOffService)
    {
        parent::__construct();
        $this->extraOffService = $extraOffService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting update of Extra Off transaction descriptions...');
        
        try {
            $results = $this->extraOffService->updateExistingTransactionDescriptions();
            
            $this->info("Updated {$results['updated']} transactions");
            
            if (!empty($results['errors'])) {
                $this->error('Errors encountered:');
                foreach ($results['errors'] as $error) {
                    $this->error('- ' . (is_array($error) ? json_encode($error) : $error));
                }
            }
            
            $this->info('Update completed successfully!');
            
        } catch (\Exception $e) {
            $this->error('Error updating transaction descriptions: ' . $e->getMessage());
            return 1;
        }
        
        return 0;
    }
}
