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

    // Relationship untuk bawahan (jabatan yang memiliki atasan = id_jabatan ini)
    public function bawahan()
    {
        return $this->hasMany(Jabatan::class, 'id_atasan', 'id_jabatan');
    }

    // Relationship untuk users yang memiliki jabatan ini
    public function users()
    {
        return $this->hasMany(User::class, 'id_jabatan', 'id_jabatan');
    }

    // Method untuk mendapatkan struktur organisasi per outlet
    public static function getOrganizationStructure($outletId = null)
    {
        $query = self::active()
            ->where('status', 'A')
            ->with(['atasan', 'bawahan', 'users' => function($q) use ($outletId) {
                $q->where('status', 'A');
                if ($outletId) {
                    $q->where('id_outlet', $outletId);
                }
            }])
            ->with(['level', 'divisi', 'subDivisi']);

        return $query->get();
    }

    // Method untuk build tree structure
    public static function buildOrganizationTree($outletId = null)
    {
        $jabatans = self::getOrganizationStructure($outletId);
        
        // Group by id_atasan to build tree
        $tree = [];
        $jabatanMap = [];
        
        // Create map for quick lookup
        foreach ($jabatans as $jabatan) {
            $jabatanMap[$jabatan->id_jabatan] = $jabatan;
            $jabatan->children = collect();
        }
        
        // Build tree structure
        foreach ($jabatans as $jabatan) {
            if ($jabatan->id_atasan && isset($jabatanMap[$jabatan->id_atasan])) {
                $jabatanMap[$jabatan->id_atasan]->children->push($jabatan);
            } else {
                // This is a root level jabatan
                $tree[] = $jabatan;
            }
        }
        
        return collect($tree);
    }

    // Method untuk mendapatkan root jabatan (yang tidak memiliki atasan)
    public static function getRootJabatans($outletId = null)
    {
        $query = self::active()
            ->whereNull('id_atasan')
            ->where('status', 'A')
            ->with(['users' => function($q) use ($outletId) {
                $q->where('status', 'A');
                if ($outletId) {
                    $q->where('id_outlet', $outletId);
                }
            }])
            ->with(['level', 'divisi', 'subDivisi']);

        return $query->get();
    }
} 