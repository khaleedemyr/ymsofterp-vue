<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Competency extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'category',
        'level',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the courses that have this competency
     */
    public function courses()
    {
        return $this->belongsToMany(LmsCourse::class, 'course_competencies', 'competency_id', 'course_id')
                    ->withPivot('proficiency_level', 'notes')
                    ->withTimestamps();
    }

    /**
     * Scope to get only active competencies
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter by category
     */
    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope to filter by level
     */
    public function scopeByLevel($query, $level)
    {
        return $query->where('level', $level);
    }

    /**
     * Get all available categories
     */
    public static function getCategories()
    {
        return [
            'Technical' => 'Technical Skills',
            'Soft Skills' => 'Soft Skills',
            'Leadership' => 'Leadership',
            'Communication' => 'Communication',
            'Problem Solving' => 'Problem Solving',
            'Analytical' => 'Analytical Skills',
            'Creative' => 'Creative Skills',
            'Management' => 'Management Skills',
        ];
    }

    /**
     * Get all available levels
     */
    public static function getLevels()
    {
        return [
            'beginner' => 'Beginner',
            'intermediate' => 'Intermediate',
            'advanced' => 'Advanced',
        ];
    }

    /**
     * Get all available proficiency levels
     */
    public static function getProficiencyLevels()
    {
        return [
            'basic' => 'Basic',
            'intermediate' => 'Intermediate',
            'advanced' => 'Advanced',
            'expert' => 'Expert',
        ];
    }
}
