<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class TrainingInvitation extends Model
{
    use HasFactory;

    protected $table = 'training_invitations';

    protected $fillable = [
        'schedule_id',
        'user_id',
        'status',
        'qr_code',
        'check_in_time',
        'check_out_time',
        'certificate_issued',
        'certificate_issued_date'
    ];

    protected $casts = [
        'check_in_time' => 'datetime',
        'check_out_time' => 'datetime',
        'certificate_issued' => 'boolean',
        'certificate_issued_date' => 'datetime',
    ];

    // Relationships
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(TrainingSchedule::class, 'schedule_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopeInvited($query)
    {
        return $query->where('status', 'invited');
    }

    public function scopeAttended($query)
    {
        return $query->where('status', 'attended');
    }

    public function scopeAbsent($query)
    {
        return $query->where('status', 'absent');
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    // Accessors
    public function getStatusTextAttribute()
    {
        $statuses = [
            'invited' => 'Diundang',
            'confirmed' => 'Konfirmasi',
            'attended' => 'Hadir',
            'absent' => 'Tidak Hadir',
            'cancelled' => 'Dibatalkan'
        ];
        
        return $statuses[$this->status] ?? $this->status;
    }

    public function getStatusColorAttribute()
    {
        $colors = [
            'invited' => 'blue',
            'confirmed' => 'yellow',
            'attended' => 'green',
            'absent' => 'red',
            'cancelled' => 'gray'
        ];
        
        return $colors[$this->status] ?? 'gray';
    }

    public function getIsCheckedInAttribute()
    {
        return !is_null($this->check_in_time);
    }

    public function getIsCheckedOutAttribute()
    {
        return !is_null($this->check_out_time);
    }

    public function getCheckInTimeFormattedAttribute()
    {
        return $this->check_in_time ? $this->check_in_time->format('H:i') : '-';
    }

    public function getCheckOutTimeFormattedAttribute()
    {
        return $this->check_out_time ? $this->check_out_time->format('H:i') : '-';
    }

    // Methods
    public static function generateQRCode(int $invitationId, int $scheduleId): string
    {
        $data = [
            'invitation_id' => $invitationId,
            'schedule_id' => $scheduleId,
            'timestamp' => time(),
            'hash' => hash('sha256', $invitationId . $scheduleId . time())
        ];
        
        return base64_encode(json_encode($data));
    }

    public function getQrCodeUrlAttribute()
    {
        // Generate unique QR code for this invitation
        $qrData = $this->qr_code ?: self::generateQRCode($this->id, $this->schedule_id);
        
        // Generate QR code using Google Charts API
        return "https://chart.googleapis.com/chart?chs=300x300&chld=L|0&cht=qr&chl=" . urlencode($qrData);
    }

    public function generateQRCodeForInvitation(): void
    {
        if (empty($this->qr_code)) {
            $this->update([
                'qr_code' => self::generateQRCode($this->id, $this->schedule_id)
            ]);
        }
    }

    public function checkIn(): bool
    {
        if ($this->status === 'invited' && !$this->is_checked_in) {
            $this->update([
                'status' => 'attended',
                'check_in_time' => now()
            ]);
            return true;
        }
        
        return false;
    }

    public function checkOut(): bool
    {
        if ($this->status === 'attended' && $this->is_checked_in && !$this->is_checked_out) {
            $this->update([
                'check_out_time' => now()
            ]);
            return true;
        }
        
        return false;
    }

    public function markAsAbsent(): void
    {
        if ($this->status === 'invited') {
            $this->update(['status' => 'absent']);
        }
    }

    public function issueCertificate(): void
    {
        if ($this->status === 'attended' && !$this->certificate_issued) {
            $this->update([
                'certificate_issued' => true,
                'certificate_issued_date' => now()
            ]);
        }
    }

    public function canCheckIn(): bool
    {
        return $this->status === 'invited' && 
               $this->schedule->status === 'ongoing' &&
               $this->schedule->is_today;
    }

    public function canCheckOut(): bool
    {
        return $this->status === 'attended' && 
               $this->is_checked_in && 
               !$this->is_checked_out;
    }

    public function canReceiveCertificate(): bool
    {
        return $this->status === 'attended' && 
               !$this->certificate_issued &&
               $this->schedule->status === 'completed';
    }

    // Static methods for QR code validation
    public static function validateQRCode(string $qrCode): ?self
    {
        try {
            $data = json_decode(base64_decode($qrCode), true);
            
            if (!$data) {
                return null;
            }
            
            // Check if it's an invitation QR code
            if (isset($data['invitation_id'], $data['schedule_id'], $data['hash'])) {
                // Validate hash
                $expectedHash = hash('sha256', $data['invitation_id'] . $data['schedule_id'] . $data['timestamp']);
                if ($data['hash'] !== $expectedHash) {
                    return null;
                }
                
                // Check if invitation exists and is valid
                $invitation = self::with('schedule')
                    ->where('id', $data['invitation_id'])
                    ->where('schedule_id', $data['schedule_id'])
                    ->where('qr_code', $qrCode)
                    ->first();
                
                return $invitation;
            }
            
            // Check if it's a training schedule QR code
            if (isset($data['schedule_id'], $data['course_id'], $data['hash'])) {
                // Validate hash
                $expectedHash = hash('sha256', $data['schedule_id'] . $data['course_id'] . $data['timestamp']);
                if ($data['hash'] !== $expectedHash) {
                    return null;
                }
                
                // Find invitation for this schedule and current user
                $invitation = self::with('schedule')
                    ->where('schedule_id', $data['schedule_id'])
                    ->where('user_id', auth()->id())
                    ->first();
                
                return $invitation;
            }
            
            return null;
            
        } catch (\Exception $e) {
            return null;
        }
    }
}
