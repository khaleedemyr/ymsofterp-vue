<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_team_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('category_id');
            $table->string('name')->nullable();
            $table->char('status', 1)->default('A');
            $table->timestamps();

            $table->index(['category_id', 'status']);
        });

        Schema::create('ticket_team_setting_regions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ticket_team_setting_id');
            $table->unsignedBigInteger('region_id');
            $table->timestamps();

            $table->unique(['ticket_team_setting_id', 'region_id'], 'tts_region_unique');
            $table->index('region_id');
        });

        Schema::create('ticket_team_setting_outlets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ticket_team_setting_id');
            $table->unsignedBigInteger('outlet_id');
            $table->timestamps();

            $table->unique(['ticket_team_setting_id', 'outlet_id'], 'tts_outlet_unique');
            $table->index('outlet_id');
        });

        Schema::create('ticket_team_setting_users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ticket_team_setting_id');
            $table->unsignedBigInteger('user_id');
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->unique(['ticket_team_setting_id', 'user_id'], 'tts_user_unique');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_team_setting_users');
        Schema::dropIfExists('ticket_team_setting_outlets');
        Schema::dropIfExists('ticket_team_setting_regions');
        Schema::dropIfExists('ticket_team_settings');
    }
};
