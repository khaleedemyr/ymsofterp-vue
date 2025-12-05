<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Database\Seeders\OrganizationStructureSeeder;

class SeedOrganizationStructure extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'seed:organization-structure';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seed organization structure data for testing';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Seeding organization structure...');
        
        $seeder = new OrganizationStructureSeeder();
        $seeder->run();
        
        $this->info('Organization structure seeded successfully!');
        
        return 0;
    }
}