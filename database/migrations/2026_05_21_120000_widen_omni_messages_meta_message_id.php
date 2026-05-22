<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('omni_messages') || ! Schema::hasColumn('omni_messages', 'meta_message_id')) {
            return;
        }

        DB::statement('ALTER TABLE `omni_messages` MODIFY `meta_message_id` VARCHAR(512) NULL');
    }

    public function down(): void
    {
        if (! Schema::hasTable('omni_messages') || ! Schema::hasColumn('omni_messages', 'meta_message_id')) {
            return;
        }

        DB::statement('ALTER TABLE `omni_messages` MODIFY `meta_message_id` VARCHAR(128) NULL');
    }
};
