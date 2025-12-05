<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LmsQuizOption extends Model
{
    use HasFactory;

    protected $table = 'lms_quiz_options';

    protected $fillable = [
        'question_id',
        'option_text',
        'is_correct',
        'order_number',
        'image_path',
        'image_alt_text'
    ];

    protected $casts = [
        'is_correct' => 'boolean',
        'order_number' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationships
    public function question()
    {
        return $this->belongsTo(LmsQuizQuestion::class, 'question_id');
    }

    public function answers()
    {
        return $this->hasMany(LmsQuizAnswer::class, 'selected_option_id');
    }

    // Scopes
    public function scopeCorrect($query)
    {
        return $query->where('is_correct', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('order_number');
    }

    // Accessors
    public function getIsSelectedAttribute()
    {
        return $this->answers()->exists();
    }

    // Accessors
    public function getImageUrlAttribute()
    {
        if ($this->image_path) {
            return request()->getSchemeAndHttpHost() . '/storage/' . $this->image_path;
        }
        return null;
    }

    // Methods
    public function isCorrect()
    {
        return $this->is_correct;
    }
} 