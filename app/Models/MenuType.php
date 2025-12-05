<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MenuType extends Model
{
    protected $table = 'menu_type';
    public $timestamps = false;
    protected $fillable = ['type', 'status'];
} 