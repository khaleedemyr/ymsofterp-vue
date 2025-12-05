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
        Schema::create('shared_documents', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('filename');
            $table->string('file_path');
            $table->string('file_type'); // xlsx, docx, pptx
            $table->string('file_size');
            $table->text('description')->nullable();
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->string('document_key')->unique(); // OnlyOffice document key
            $table->boolean('is_public')->default(false);
            $table->timestamps();
        });

        Schema::create('document_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('shared_documents')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->enum('permission', ['view', 'edit', 'admin'])->default('view');
            $table->timestamps();
            
            $table->unique(['document_id', 'user_id']);
        });

        Schema::create('document_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('shared_documents')->onDelete('cascade');
            $table->string('version_number');
            $table->string('file_path');
            $table->text('change_description')->nullable();
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_versions');
        Schema::dropIfExists('document_permissions');
        Schema::dropIfExists('shared_documents');
    }
}; 