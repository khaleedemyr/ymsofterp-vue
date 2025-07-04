<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Jabatan extends Model {
    use HasFactory;

    protected $table = 'tbl_data_jabatan';
    protected $primaryKey = 'id_jabatan';
    protected $guarded = [];

    protected $fillable = [
        'nama_jabatan',
        'id_atasan',
        'id_divisi',
        'id_sub_divisi',
        'id_level',
        'status'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'status' => 'string'
    ];

    public $timestamps = false;

    // Accessor to get status text
    public function getStatusTextAttribute()
    {
        return $this->status === 'A' ? 'Active' : 'Inactive';
    }

    // Relationships
    public function atasan()
    {
        return $this->belongsTo(Jabatan::class, 'id_atasan', 'id_jabatan');
    }

    public function divisi()
    {
        return $this->belongsTo(Divisi::class, 'id_divisi', 'id');
    }

    public function subDivisi()
    {
        return $this->belongsTo(SubDivisi::class, 'id_sub_divisi', 'id');
    }

    public function level()
    {
        return $this->belongsTo(DataLevel::class, 'id_level', 'id');
    }

    // Scope to get active records
    public function scopeActive($query)
    {
        return $query->where('status', 'A');
    }
} 