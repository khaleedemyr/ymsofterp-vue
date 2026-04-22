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
        Schema::create('document_folders', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('parent_id')->nullable()->constrained('document_folders')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->boolean('is_public')->default(false);
            $table->timestamps();

            $table->index('parent_id');
            $table->index('created_by');
            $table->unique(['parent_id', 'name', 'created_by'], 'document_folders_parent_name_creator_unique');
        });

        Schema::table('shared_documents', function (Blueprint $table) {
            $table->foreignId('folder_id')->nullable()->after('id')->constrained('document_folders')->nullOnDelete();
            $table->index('folder_id');
        });

        Schema::create('document_access_scopes', function (Blueprint $table) {
            $table->id();
            $table->enum('resource_type', ['document', 'folder']);
            $table->unsignedBigInteger('resource_id');
            $table->enum('scope_type', ['user', 'jabatan', 'divisi', 'outlet']);
            $table->unsignedBigInteger('scope_id');
            $table->enum('permission', ['view', 'edit', 'admin'])->default('view');
            $table->timestamps();

            $table->index(['resource_type', 'resource_id'], 'document_access_scopes_resource_index');
            $table->index(['scope_type', 'scope_id'], 'document_access_scopes_scope_index');
            $table->unique(
                ['resource_type', 'resource_id', 'scope_type', 'scope_id'],
                'document_access_scopes_unique_scope_per_resource'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_access_scopes');

        Schema::table('shared_documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('folder_id');
        });

        Schema::dropIfExists('document_folders');
    }
};
