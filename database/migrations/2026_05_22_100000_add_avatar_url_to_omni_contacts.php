<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('omni_contacts')) {
            return;
        }

        if (! Schema::hasColumn('omni_contacts', 'avatar_url')) {
            Schema::table('omni_contacts', function (Blueprint $table) {
                $table->string('avatar_url', 1024)->nullable()->after('display_name');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('omni_contacts') && Schema::hasColumn('omni_contacts', 'avatar_url')) {
            Schema::table('omni_contacts', function (Blueprint $table) {
                $table->dropColumn('avatar_url');
            });
        }
    }
};
