<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Divisi extends Model {
    use HasFactory;

    protected $table = 'tbl_data_divisi';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $guarded = [];

    protected $fillable = [
        'nama_divisi',
        'status'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Scope to get active records
    public function scopeActive($query)
    {
        return $query->where('status', 'A');
    }

    // Relationships
    public function subDivisis()
    {
        return $this->hasMany(SubDivisi::class, 'id_divisi', 'id');
    }
} 