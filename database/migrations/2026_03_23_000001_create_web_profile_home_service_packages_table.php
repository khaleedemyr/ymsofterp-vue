<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('web_profile_home_service_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('web_profile_brand_id')
                ->constrained('web_profile_brands')
                ->cascadeOnDelete();
            $table->string('title');
            $table->string('price_label')->nullable();
            $table->longText('body_html')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('web_profile_home_service_packages');
    }
};
