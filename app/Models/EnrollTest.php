<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EnrollTest extends Model
{
    use HasFactory;

    protected $table = 'enroll_tests';

    protected $fillable = [
        'master_soal_id',
        'user_id',
        'outlet_id',
        'status',
        'enrolled_at',
        'started_at',
        'completed_at',
        'expired_at',
        'max_attempts',
        'current_attempt',
        'notes',
        'created_by',
        'updated_by'
    ];

    protected $casts = [
        'enrolled_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'expired_at' => 'date',
        'max_attempts' => 'integer',
        'current_attempt' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // Relationships
    public function masterSoal()
    {
        return $this->belongsTo(MasterSoal::class, 'master_soal_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function outlet()
    {
        return $this->belongsTo(\App\Models\Outlet::class, 'outlet_id', 'id_outlet');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function testResults()
    {
        return $this->hasMany(TestResult::class, 'enroll_test_id');
    }

    public function latestTestResult()
    {
        return $this->hasOne(TestResult::class, 'enroll_test_id')->latest();
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->whereIn('status', ['enrolled', 'in_progress']);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeExpired($query)
    {
        return $query->where('status', 'expired');
    }

    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByMasterSoal($query, $masterSoalId)
    {
        return $query->where('master_soal_id', $masterSoalId);
    }

    // Accessors
    public function getStatusTextAttribute()
    {
        $statuses = [
            'enrolled' => 'Terdaftar',
            'in_progress' => 'Sedang Test',
            'completed' => 'Selesai',
            'expired' => 'Kedaluwarsa',
            'cancelled' => 'Dibatalkan'
        ];

        return $statuses[$this->status] ?? 'Unknown';
    }

    public function getStatusBadgeClassAttribute()
    {
        $classes = [
            'enrolled' => 'bg-blue-100 text-blue-800',
            'in_progress' => 'bg-yellow-100 text-yellow-800',
            'completed' => 'bg-green-100 text-green-800',
            'expired' => 'bg-red-100 text-red-800',
            'cancelled' => 'bg-gray-100 text-gray-800'
        ];

        return $classes[$this->status] ?? 'bg-gray-100 text-gray-800';
    }

    public function getIsActiveAttribute()
    {
        return in_array($this->status, ['enrolled', 'in_progress']);
    }

    public function getCanStartTestAttribute()
    {
        return $this->status === 'enrolled' && 
               $this->current_attempt < $this->max_attempts &&
               (!$this->expired_at || $this->expired_at > now());
    }

    public function getIsExpiredAttribute()
    {
        return $this->expired_at && $this->expired_at < now();
    }

    public function getRemainingAttemptsAttribute()
    {
        return $this->max_attempts - $this->current_attempt;
    }

    // Methods
    public function startTest()
    {
        if (!$this->can_start_test) {
            return false;
        }

        $this->update([
            'status' => 'in_progress',
            'started_at' => now(),
            'current_attempt' => $this->current_attempt + 1
        ]);

        return true;
    }

    public function completeTest($score, $maxScore, $timeTaken)
    {
        $percentage = $maxScore > 0 ? ($score / $maxScore) * 100 : 0;

        $this->update([
            'status' => 'completed',
            'completed_at' => now()
        ]);

        // Create test result
        return $this->testResults()->create([
            'attempt_number' => $this->current_attempt,
            'started_at' => $this->started_at,
            'completed_at' => now(),
            'total_score' => $score,
            'max_score' => $maxScore,
            'percentage' => $percentage,
            'time_taken_seconds' => $timeTaken,
            'status' => 'completed'
        ]);
    }

    public function cancelTest()
    {
        $this->update([
            'status' => 'cancelled'
        ]);
    }

    public function expireTest()
    {
        $this->update([
            'status' => 'expired'
        ]);
    }

    // Boot method
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($enrollTest) {
            if (!$enrollTest->created_by) {
                $enrollTest->created_by = auth()->id();
            }
            if (!$enrollTest->updated_by) {
                $enrollTest->updated_by = auth()->id();
            }
        });

        static::updating(function ($enrollTest) {
            $enrollTest->updated_by = auth()->id();
        });
    }
}
