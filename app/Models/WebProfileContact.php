<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WebProfileContact extends Model
{
    use HasFactory;

    protected $table = 'web_profile_contacts';

    protected $fillable = [
        'name',
        'email',
        'phone',
        'subject',
        'message',
        'is_read'
    ];

    protected $casts = [
        'is_read' => 'boolean',
    ];
}

