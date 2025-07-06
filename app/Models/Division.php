<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Division extends Model
{
    protected $table = 'tbl_data_divisi';
    protected $primaryKey = 'id';
    public $timestamps = false;

    public function shifts()
    {
        return $this->hasMany(Shift::class, 'division_id', 'id');
    }
} 