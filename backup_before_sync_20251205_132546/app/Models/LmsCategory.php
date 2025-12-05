<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LmsCategory extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'lms_categories';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'color',
        'icon',
        'status',
        'sort_order',
        'meta_title',
        'meta_description',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // Relationships
    public function courses()
    {
        return $this->hasMany(LmsCourse::class, 'category_id');
    }

    public function activeCourses()
    {
        return $this->hasMany(LmsCourse::class, 'category_id')->where('status', 'published');
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
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('description', 'like', "%{$search}%");
        });
    }

    // Accessors
    public function getSlugAttribute($value)
    {
        if (!$value) {
            $value = \Str::slug($this->name);
        }
        return $value;
    }

    public function getCoursesCountAttribute()
    {
        return $this->courses()->count();
    }

    public function getActiveCoursesCountAttribute()
    {
        return $this->activeCourses()->count();
    }

    public function getTotalEnrollmentsAttribute()
    {
        return $this->courses()->withCount('enrollments')->get()->sum('enrollments_count');
    }

    public function getColorClassAttribute()
    {
        $colors = [
            'blue' => 'bg-blue-500',
            'green' => 'bg-green-500',
            'yellow' => 'bg-yellow-500',
            'red' => 'bg-red-500',
            'purple' => 'bg-purple-500',
            'pink' => 'bg-pink-500',
            'indigo' => 'bg-indigo-500',
            'gray' => 'bg-gray-500',
        ];
        
        return $colors[$this->color] ?? 'bg-blue-500';
    }

    public function getIconClassAttribute()
    {
        $icons = [
            'book' => 'fas fa-book',
            'graduation-cap' => 'fas fa-graduation-cap',
            'laptop' => 'fas fa-laptop',
            'users' => 'fas fa-users',
            'chart-line' => 'fas fa-chart-line',
            'cog' => 'fas fa-cog',
            'heart' => 'fas fa-heart',
            'star' => 'fas fa-star',
            'trophy' => 'fas fa-trophy',
            'certificate' => 'fas fa-certificate',
            'folder' => 'fas fa-folder',
            'tag' => 'fas fa-tag',
        ];
        
        return $icons[$this->icon] ?? 'fas fa-folder';
    }

    // Mutators
    public function setSlugAttribute($value)
    {
        if (!$value) {
            $value = \Str::slug($this->name);
        }
        $this->attributes['slug'] = $value;
    }

    // Boot method
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($category) {
            if (!$category->created_by) {
                $category->created_by = auth()->id();
            }
            if (!$category->updated_by) {
                $category->updated_by = auth()->id();
            }
            if (!$category->sort_order) {
                $category->sort_order = static::max('sort_order') + 1;
            }
        });

        static::updating(function ($category) {
            $category->updated_by = auth()->id();
        });
    }
} 