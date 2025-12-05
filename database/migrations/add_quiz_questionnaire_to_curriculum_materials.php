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
        Schema::table('lms_curriculum_materials', function (Blueprint $table) {
            // Add quiz_id and questionnaire_id columns
            $table->unsignedBigInteger('quiz_id')->nullable()->after('file_type');
            $table->unsignedBigInteger('questionnaire_id')->nullable()->after('quiz_id');
            
            // Add foreign key constraints
            $table->foreign('quiz_id')->references('id')->on('lms_quizzes')->onDelete('set null');
            $table->foreign('questionnaire_id')->references('id')->on('lms_questionnaires')->onDelete('set null');
            
            // Add indexes for better performance
            $table->index('quiz_id');
            $table->index('questionnaire_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lms_curriculum_materials', function (Blueprint $table) {
            // Drop foreign key constraints
            $table->dropForeign(['quiz_id']);
            $table->dropForeign(['questionnaire_id']);
            
            // Drop indexes
            $table->dropIndex(['quiz_id']);
            $table->dropIndex(['questionnaire_id']);
            
            // Drop columns
            $table->dropColumn(['quiz_id', 'questionnaire_id']);
        });
    }
};
