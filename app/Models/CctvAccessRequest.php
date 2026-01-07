<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class CctvAccessRequest extends Model
{
    use HasFactory;

    protected $table = 'cctv_access_requests';

    protected $fillable = [
        'user_id',
        'access_type',
        'reason',
        'outlet_ids',
        'email',
        'area',
        'date_from',
        'date_to',
        'time_from',
        'time_to',
        'incident_description',
        'playback_file_path',
        'playback_uploaded_at',
        'playback_uploaded_by',
        'valid_until',
        'status',
        'it_manager_id',
        'approval_notes',
        'approved_at',
        'rejected_at',
        'revoked_at',
        'revoked_by',
        'revocation_reason'
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'revoked_at' => 'datetime',
        'playback_uploaded_at' => 'datetime',
        'valid_until' => 'date',
        'outlet_ids' => 'array',
        'playback_file_path' => 'array' // JSON array of file paths
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function itManager()
    {
        return $this->belongsTo(User::class, 'it_manager_id');
    }

    public function revokedBy()
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    public function playbackUploadedBy()
    {
        return $this->belongsTo(User::class, 'playback_uploaded_by');
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }


    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeLiveView($query)
    {
        return $query->where('access_type', 'live_view');
    }

    public function scopePlayback($query)
    {
        return $query->where('access_type', 'playback');
    }

    // Accessors
    public function getStatusTextAttribute()
    {
        $statuses = [
            'pending' => 'Menunggu Approval',
            'approved' => 'Disetujui',
            'rejected' => 'Ditolak',
            'revoked' => 'Dicabut'
        ];

        return $statuses[$this->status] ?? 'Unknown';
    }

    public function getStatusColorAttribute()
    {
        $colors = [
            'pending' => 'yellow',
            'approved' => 'green',
            'rejected' => 'red',
            'revoked' => 'orange'
        ];

        return $colors[$this->status] ?? 'gray';
    }

    public function getAccessTypeTextAttribute()
    {
        return $this->access_type === 'live_view' ? 'Live View' : 'Playback';
    }

    public function getIsActiveAttribute()
    {
        return $this->status === 'approved';
    }

    // Methods
    public function approve($itManagerId, $notes = null)
    {
        $this->update([
            'status' => 'approved',
            'it_manager_id' => $itManagerId,
            'approval_notes' => $notes,
            'approved_at' => now()
        ]);

        return $this;
    }

    public function reject($itManagerId, $notes = null)
    {
        $this->update([
            'status' => 'rejected',
            'it_manager_id' => $itManagerId,
            'approval_notes' => $notes,
            'rejected_at' => now()
        ]);

        return $this;
    }

    public function revoke($revokedBy, $reason = null)
    {
        $this->update([
            'status' => 'revoked',
            'revoked_by' => $revokedBy,
            'revocation_reason' => $reason,
            'revoked_at' => now()
        ]);

        return $this;
    }

    public function isPending()
    {
        return $this->status === 'pending';
    }

    public function isApproved()
    {
        return $this->status === 'approved' && !$this->is_expired;
    }

    public function isRejected()
    {
        return $this->status === 'rejected';
    }


    public function isRevoked()
    {
        return $this->status === 'revoked';
    }

    /**
     * Get playback files as array
     */
    public function getPlaybackFiles()
    {
        if (!$this->playback_file_path) {
            return [];
        }

        // If it's already an array, return it
        if (is_array($this->playback_file_path)) {
            return $this->playback_file_path;
        }

        // If it's a string (old format), convert to array
        if (is_string($this->playback_file_path)) {
            return [$this->playback_file_path];
        }

        return [];
    }

    /**
     * Check if playback access is still valid (not expired)
     */
    public function isPlaybackValid()
    {
        if ($this->access_type !== 'playback') {
            return false;
        }

        $files = $this->getPlaybackFiles();
        if (empty($files)) {
            return false; // No file uploaded yet
        }

        if (!$this->valid_until) {
            return true; // No expiration date means always valid
        }

        return Carbon::today()->lte(Carbon::parse($this->valid_until));
    }

    /**
     * Check if playback access is expired
     */
    public function isPlaybackExpired()
    {
        return !$this->isPlaybackValid();
    }

    // Static method to check if user can request playback (must be IT team)
    public static function canRequestPlayback($userId)
    {
        // Check if user is in IT division
        $user = User::find($userId);
        if (!$user || $user->status !== 'A') {
            return false;
        }

        // Check by division_id = 21 (IT Team)
        if ($user->division_id == 21) {
            return true;
        }

        // Fallback: Check by jabatan name containing "IT"
        if ($user->jabatan) {
            $jabatanName = strtolower($user->jabatan->nama_jabatan ?? '');
            if (str_contains($jabatanName, 'it')) {
                return true;
            }
        }

        // Fallback: Check by divisi name containing "IT"
        if ($user->divisi) {
            $divisiName = strtolower($user->divisi->nama_divisi ?? '');
            if (str_contains($divisiName, 'it')) {
                return true;
            }
        }

        return false;
    }
}

