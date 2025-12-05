<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CertificateTemplate extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'background_image',
        'text_positions',
        'style_settings',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'text_positions' => 'array',
        'style_settings' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // Relationships
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function certificates()
    {
        return $this->hasMany(LmsCertificate::class, 'template_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    // Accessors
    public function getBackgroundImageUrlAttribute()
    {
        if ($this->background_image) {
            return asset('storage/' . $this->background_image);
        }
        return null;
    }

    // Default text positions
    public function getDefaultTextPositions()
    {
        return [
            'participant_name' => ['x' => 400, 'y' => 300, 'font_size' => 32, 'font_weight' => 'bold'],
            'course_title' => ['x' => 400, 'y' => 350, 'font_size' => 24, 'font_weight' => 'normal'],
            'completion_date' => ['x' => 400, 'y' => 400, 'font_size' => 18, 'font_weight' => 'normal'],
            'certificate_number' => ['x' => 100, 'y' => 500, 'font_size' => 12, 'font_weight' => 'normal'],
            'instructor_name' => ['x' => 600, 'y' => 500, 'font_size' => 16, 'font_weight' => 'normal'],
        ];
    }

    // Boot method
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($template) {
            if (!$template->created_by) {
                $template->created_by = auth()->id();
            }
            if (!$template->updated_by) {
                $template->updated_by = auth()->id();
            }
            if (!$template->text_positions) {
                $template->text_positions = $template->getDefaultTextPositions();
            }
        });

        static::updating(function ($template) {
            $template->updated_by = auth()->id();
        });
    }
}
