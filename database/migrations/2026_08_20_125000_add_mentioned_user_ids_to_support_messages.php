<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('support_messages') || Schema::hasColumn('support_messages', 'mentioned_user_ids')) {
            return;
        }

        Schema::table('support_messages', function (Blueprint $table) {
            $table->json('mentioned_user_ids')->nullable();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('support_messages') || ! Schema::hasColumn('support_messages', 'mentioned_user_ids')) {
            return;
        }

        Schema::table('support_messages', function (Blueprint $table) {
            $table->dropColumn('mentioned_user_ids');
        });
    }
};
