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
        Schema::create('course_competencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('lms_courses')->onDelete('cascade');
            $table->foreignId('competency_id')->constrained('competencies')->onDelete('cascade');
            $table->enum('proficiency_level', ['basic', 'intermediate', 'advanced', 'expert'])->default('basic');
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->unique(['course_id', 'competency_id']);
            $table->index(['course_id', 'proficiency_level']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course_competencies');
    }
};
