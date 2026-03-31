<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class ExternalUser extends Authenticatable
{
    use HasFactory, Notifiable;

    public const REMEMBER_TOKEN = null;

    protected $table = 'external_report_users';

    protected $fillable = [
        'name',
        'email',
        'password',
        'kode_outlet',
        'nama_outlet',
        'status',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'password' => 'hashed',
    ];
}
