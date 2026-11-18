<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_plans', function (Blueprint $table) {
            $table->date('deadline_content')->nullable()->after('platform');
            $table->string('priority')->default('medium')->after('caption');
            $table->json('reference_images')->nullable()->after('reference');
        });
    }

    public function down(): void
    {
        Schema::table('content_plans', function (Blueprint $table) {
            $table->dropColumn(['deadline_content', 'priority', 'reference_images']);
        });
    }
};
