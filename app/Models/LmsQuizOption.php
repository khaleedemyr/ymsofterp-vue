<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LmsQuizOption extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'lms_quiz_options';

    protected $fillable = [
        'question_id',
        'option_text',
        'is_correct',
        'order_number',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_correct' => 'boolean',
        'order_number' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // Relationships
    public function question()
    {
        return $this->belongsTo(LmsQuizQuestion::class, 'question_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
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

    public function scopeOrdered($query)
    {
        return $query->orderBy('order_number');
    }

    // Accessors
    public function getIsCorrectTextAttribute()
    {
        return $this->is_correct ? 'Benar' : 'Salah';
    }

    // Boot method
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($option) {
            if (!$option->created_by) {
                $option->created_by = auth()->id();
            }
            if (!$option->updated_by) {
                $option->updated_by = auth()->id();
            }
            if (!$option->order_number) {
                $option->order_number = static::where('question_id', $option->question_id)->max('order_number') + 1;
            }
        });

        static::updating(function ($option) {
            $option->updated_by = auth()->id();
        });
    }
} 