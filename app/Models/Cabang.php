<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cabang extends Model
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
    protected $table = 'cabangs';

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
        'name',
        'alamat',
        'telepon',
        'email',
        'status',
        'created_at',
        'updated_at'
    ];

    /**
     * Cast attributes
     * 
     * @var array
     */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relationship dengan Point
     * 
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function points()
    {
        return $this->hasMany(Point::class, 'cabang_id', 'id');
    }

    /**
     * Scope untuk cabang aktif
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Accessor untuk nama cabang yang readable
     * 
     * @return string
     */
    public function getNameAttribute($value)
    {
        return $value ?: 'Cabang Tidak Diketahui';
    }
} 