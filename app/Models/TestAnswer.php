<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TestAnswer extends Model
{
    use HasFactory;

    protected $table = 'test_answers';

    protected $fillable = [
        'test_result_id',
        'soal_pertanyaan_id',
        'user_answer',
        'is_correct',
        'score',
        'max_score',
        'time_taken_seconds',
        'answered_at'
    ];

    protected $casts = [
        'is_correct' => 'boolean',
        'score' => 'decimal:2',
        'max_score' => 'decimal:2',
        'time_taken_seconds' => 'integer',
        'answered_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // Relationships
    public function testResult()
    {
        return $this->belongsTo(TestResult::class, 'test_result_id');
    }

    public function soalPertanyaan()
    {
        return $this->belongsTo(SoalPertanyaan::class, 'soal_pertanyaan_id');
    }

    // Scopes
    public function scopeCorrect($query)
    {
        return $query->where('is_correct', true);
    }

    public function scopeIncorrect($query)
    {
        return $query->where('is_correct', false);
    }

    // Accessors
    public function getPercentageAttribute()
    {
        if ($this->max_score > 0) {
            return round(($this->score / $this->max_score) * 100, 2);
        }
        return 0;
    }

    public function getFormattedTimeTakenAttribute()
    {
        $minutes = floor($this->time_taken_seconds / 60);
        $seconds = $this->time_taken_seconds % 60;

        if ($minutes > 0) {
            return sprintf('%d menit %d detik', $minutes, $seconds);
        } else {
            return sprintf('%d detik', $seconds);
        }
    }

    // Methods
    public function checkAnswer()
    {
        $pertanyaan = $this->soalPertanyaan;
        
        if (!$pertanyaan) {
            return false;
        }

        // Check answer based on question type
        switch ($pertanyaan->tipe_soal) {
            case 'pilihan_ganda':
                $isCorrect = strtolower($this->user_answer) === strtolower($pertanyaan->jawaban_benar);
                break;
            case 'yes_no':
                $isCorrect = strtolower($this->user_answer) === strtolower($pertanyaan->jawaban_benar);
                break;
            case 'essay':
                // Essay answers need manual checking, so we'll mark as pending
                $isCorrect = null;
                break;
            default:
                $isCorrect = false;
        }

        $this->update([
            'is_correct' => $isCorrect,
            'score' => $isCorrect ? $pertanyaan->skor : 0,
            'max_score' => $pertanyaan->skor
        ]);

        return $isCorrect;
    }

    public function setScore($score, $maxScore = null)
    {
        $this->update([
            'score' => $score,
            'max_score' => $maxScore ?? $this->max_score,
            'is_correct' => $score > 0
        ]);
    }
}
