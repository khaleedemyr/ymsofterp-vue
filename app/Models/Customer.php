<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    /**
     * Menentukan connection database yang digunakan
     * 
     * @var string
     */
    protected $connection = 'mysql_second';

    /**
     * Nama tabel di database kedua
     * 
     * @var string
     */
    protected $table = 'costumers';

    /**
     * Primary key
     * 
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * Mass assignment protection
     * 
     * @var array
     */
    protected $fillable = [
        'costumers_id',
        'nik',
        'name',
        'nama_panggilan',
        'email',
        'alamat',
        'telepon',
        'tanggal_lahir',
        'jenis_kelamin',
        'pekerjaan',
        'valid_until',
        'status_aktif',
        'password2',
        'android_password',
        'hint',
        'barcode',
        'pin',
        'tanggal_aktif',
        'status_block',
        'last_logged',
        'tanggal_register',
        'device',
        'exclusive_member'
    ];

    /**
     * Cast attributes
     * 
     * @var array
     */
    protected $casts = [
        'tanggal_lahir' => 'date',
        'valid_until' => 'date',
        'tanggal_aktif' => 'date',
        'tanggal_register' => 'date',
        'last_logged' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Scope untuk member aktif
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('status_aktif', '1');
    }

    /**
     * Scope untuk member tidak diblokir
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeNotBlocked($query)
    {
        return $query->where('status_block', 'N');
    }

    /**
     * Scope untuk pencarian berdasarkan nama, NIK, atau email
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $search
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeSearch($query, $search)
    {
        return $query->where(function($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('nik', 'like', "%{$search}%")
              ->orWhere('email', 'like', "%{$search}%")
              ->orWhere('telepon', 'like', "%{$search}%")
              ->orWhere('costumers_id', 'like', "%{$search}%");
        });
    }

    /**
     * Scope untuk filter berdasarkan status aktif
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $status
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByStatus($query, $status)
    {
        if ($status === 'active') {
            return $query->where('status_aktif', '1');
        } elseif ($status === 'inactive') {
            return $query->where('status_aktif', '0');
        }
        return $query;
    }



    /**
     * Scope untuk member eksklusif
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeExclusive($query)
    {
        return $query->where('exclusive_member', 'Y');
    }

    /**
     * Accessor untuk status aktif yang readable
     * 
     * @return string
     */
    public function getStatusAktifTextAttribute()
    {
        return $this->status_aktif === '1' ? 'Aktif' : 'Tidak Aktif';
    }

    /**
     * Accessor untuk status block yang readable
     * 
     * @return string
     */
    public function getStatusBlockTextAttribute()
    {
        return $this->status_block === 'Y' ? 'Diblokir' : 'Tidak Diblokir';
    }

    /**
     * Accessor untuk jenis kelamin yang readable
     * 
     * @return string
     */
    public function getJenisKelaminTextAttribute()
    {
        if ($this->jenis_kelamin === '1') {
            return 'Laki-laki';
        } elseif ($this->jenis_kelamin === '2') {
            return 'Perempuan';
        }
        return '-';
    }

    /**
     * Accessor untuk exclusive member yang readable
     * 
     * @return string
     */
    public function getExclusiveMemberTextAttribute()
    {
        return $this->exclusive_member === 'Y' ? 'Ya' : 'Tidak';
    }

    /**
     * Accessor untuk usia
     * 
     * @return int|null
     */
    public function getUsiaAttribute()
    {
        if (!$this->tanggal_lahir) {
            return null;
        }
        return $this->tanggal_lahir->age;
    }

    /**
     * Accessor untuk status member yang lengkap
     * 
     * @return string
     */
    public function getStatusLengkapAttribute()
    {
        if ($this->status_block === 'Y') {
            return 'Diblokir';
        }
        return $this->status_aktif === '1' ? 'Aktif' : 'Tidak Aktif';
    }

    /**
     * Accessor untuk valid until yang readable
     * 
     * @return string
     */
    public function getValidUntilTextAttribute()
    {
        if (!$this->valid_until) {
            return '-';
        }
        return $this->valid_until->format('d/m/Y');
    }

    /**
     * Accessor untuk tanggal register yang readable
     * 
     * @return string
     */
    public function getTanggalRegisterTextAttribute()
    {
        if (!$this->tanggal_register) {
            return '-';
        }
        return $this->tanggal_register->format('d/m/Y');
    }

    /**
     * Accessor untuk tanggal aktif yang readable
     * 
     * @return string
     */
    public function getTanggalAktifTextAttribute()
    {
        if (!$this->tanggal_aktif) {
            return '-';
        }
        return $this->tanggal_aktif->format('d/m/Y');
    }

    /**
     * Accessor untuk tanggal lahir yang readable
     * 
     * @return string
     */
    public function getTanggalLahirTextAttribute()
    {
        if (!$this->tanggal_lahir) {
            return '-';
        }
        return $this->tanggal_lahir->format('d/m/Y');
    }
} 