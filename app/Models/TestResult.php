<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TestResult extends Model
{
    use HasFactory;

    protected $table = 'test_results';

    protected $fillable = [
        'enroll_test_id',
        'attempt_number',
        'started_at',
        'completed_at',
        'total_score',
        'max_score',
        'percentage',
        'gpa_score',
        'grade_description',
        'time_taken_seconds',
        'status',
        'question_order',
        'current_question_index',
        'answers'
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'total_score' => 'decimal:2',
        'max_score' => 'decimal:2',
        'percentage' => 'decimal:2',
        'gpa_score' => 'decimal:2',
        'grade_description' => 'string',
        'time_taken_seconds' => 'integer',
        'question_order' => 'array',
        'current_question_index' => 'integer',
        'answers' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // Relationships
    public function enrollTest()
    {
        return $this->belongsTo(EnrollTest::class, 'enroll_test_id');
    }

    public function testAnswers()
    {
        return $this->hasMany(TestAnswer::class, 'test_result_id');
    }

    // Scopes
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }

    // Accessors
    public function getStatusTextAttribute()
    {
        $statuses = [
            'in_progress' => 'Sedang Test',
            'completed' => 'Selesai',
            'timeout' => 'Waktu Habis',
            'cancelled' => 'Dibatalkan'
        ];

        return $statuses[$this->status] ?? 'Unknown';
    }

    public function getStatusBadgeClassAttribute()
    {
        $classes = [
            'in_progress' => 'bg-yellow-100 text-yellow-800',
            'completed' => 'bg-green-100 text-green-800',
            'timeout' => 'bg-red-100 text-red-800',
            'cancelled' => 'bg-gray-100 text-gray-800'
        ];

        return $classes[$this->status] ?? 'bg-gray-100 text-gray-800';
    }

    public function getIsPassedAttribute()
    {
        return $this->percentage >= 70; // 70% sebagai standar kelulusan
    }

    public function getFormattedTimeTakenAttribute()
    {
        $hours = floor($this->time_taken_seconds / 3600);
        $minutes = floor(($this->time_taken_seconds % 3600) / 60);
        $seconds = $this->time_taken_seconds % 60;

        if ($hours > 0) {
            return sprintf('%d jam %d menit %d detik', $hours, $minutes, $seconds);
        } elseif ($minutes > 0) {
            return sprintf('%d menit %d detik', $minutes, $seconds);
        } else {
            return sprintf('%d detik', $seconds);
        }
    }

    public function getGradeAttribute()
    {
        if ($this->percentage >= 90) return 'A';
        if ($this->percentage >= 80) return 'B';
        if ($this->percentage >= 70) return 'C';
        if ($this->percentage >= 60) return 'D';
        return 'E';
    }

    // Methods
    public function completeTest($answers = [])
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
            'answers' => $answers
        ]);
    }

    public function cancelTest()
    {
        $this->update([
            'status' => 'cancelled'
        ]);
    }

    public function timeoutTest()
    {
        $this->update([
            'status' => 'timeout',
            'completed_at' => now()
        ]);
    }
}
