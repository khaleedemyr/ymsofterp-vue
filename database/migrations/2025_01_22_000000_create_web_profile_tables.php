<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Pages table
        Schema::create('web_profile_pages', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('content')->nullable();
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->boolean('is_published')->default(false);
            $table->integer('order')->default(0);
            $table->timestamps();
        });

        // Page sections table (untuk flexible content)
        Schema::create('web_profile_page_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->constrained('web_profile_pages')->onDelete('cascade');
            $table->string('type'); // hero, content, gallery, testimonial, etc
            $table->string('title')->nullable();
            $table->text('content')->nullable();
            $table->json('data')->nullable(); // untuk data tambahan (images, links, etc)
            $table->integer('order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Menu items table
        Schema::create('web_profile_menu_items', function (Blueprint $table) {
            $table->id();
            $table->string('label');
            $table->string('url')->nullable();
            $table->foreignId('page_id')->nullable()->constrained('web_profile_pages')->onDelete('set null');
            $table->integer('parent_id')->nullable();
            $table->integer('order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Gallery table
        Schema::create('web_profile_galleries', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('image_path');
            $table->string('category')->nullable();
            $table->integer('order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Settings table
        Schema::create('web_profile_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('type')->default('text'); // text, image, json
            $table->timestamps();
        });

        // Contact submissions table
        Schema::create('web_profile_contacts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('subject')->nullable();
            $table->text('message');
            $table->boolean('is_read')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('web_profile_contacts');
        Schema::dropIfExists('web_profile_settings');
        Schema::dropIfExists('web_profile_galleries');
        Schema::dropIfExists('web_profile_menu_items');
        Schema::dropIfExists('web_profile_page_sections');
        Schema::dropIfExists('web_profile_pages');
    }
};

