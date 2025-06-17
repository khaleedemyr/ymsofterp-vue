<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DataOutlet extends Model
{
    protected $table = 'tbl_data_outlet';
    protected $primaryKey = 'id_outlet';
    public $timestamps = false; // jika tabel tidak ada created_at/updated_at
}
