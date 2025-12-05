<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserPin extends Model
{
    protected $table = 'user_pins';
    protected $fillable = [
        'user_id',
        'outlet_id',
        'pin',
        'is_active',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function outlet()
    {
        return $this->belongsTo(Outlet::class, 'outlet_id', 'id_outlet');
    }
} 