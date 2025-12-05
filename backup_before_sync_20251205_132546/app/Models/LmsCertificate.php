<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LmsCertificate extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'lms_certificates';

    protected $fillable = [
        'course_id',
        'enrollment_id',
        'user_id',
        'certificate_number',
        'issued_at',
        'expires_at',
        'template_id',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'issued_at' => 'datetime',
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // Relationships
    public function course()
    {
        return $this->belongsTo(LmsCourse::class, 'course_id');
    }

    public function enrollment()
    {
        return $this->belongsTo(LmsEnrollment::class, 'enrollment_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function template()
    {
        return $this->belongsTo(CertificateTemplate::class, 'template_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<', now());
    }

    public function scopeValid($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
              ->orWhere('expires_at', '>', now());
        });
    }

    // Accessors
    public function getIsExpiredAttribute()
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function getIsValidAttribute()
    {
        return !$this->isExpired && $this->status === 'active';
    }

    public function getDaysUntilExpiryAttribute()
    {
        if (!$this->expires_at) {
            return null;
        }

        return now()->diffInDays($this->expires_at, false);
    }

    public function getCertificateNumberAttribute($value)
    {
        if (!$value) {
            $value = 'CERT-' . strtoupper(substr(md5($this->id . time()), 0, 8));
        }
        return $value;
    }

    // Methods
    public function generateCertificateNumber()
    {
        $prefix = 'CERT';
        $year = date('Y');
        $month = date('m');
        $sequence = static::whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->count() + 1;

        return sprintf('%s-%s%s-%04d', $prefix, $year, $month, $sequence);
    }

    public function markAsIssued()
    {
        $this->status = 'active';
        $this->issued_at = now();
        if (!$this->certificate_number) {
            $this->certificate_number = $this->generateCertificateNumber();
        }
        $this->save();
    }

    public function revoke()
    {
        $this->status = 'revoked';
        $this->save();
    }

    // Boot method
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($certificate) {
            if (!$certificate->created_by) {
                $certificate->created_by = auth()->id();
            }
            if (!$certificate->updated_by) {
                $certificate->updated_by = auth()->id();
            }
            if (!$certificate->certificate_number) {
                $certificate->certificate_number = $certificate->generateCertificateNumber();
            }
        });

        static::updating(function ($certificate) {
            $certificate->updated_by = auth()->id();
        });
    }
} 