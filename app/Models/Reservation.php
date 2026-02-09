<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reservation extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'phone',
        'email',
        'outlet_id',
        'reservation_date',
        'reservation_time',
        'number_of_guests',
        'smoking_preference',
        'special_requests',
        'dp',
        'from_sales',
        'sales_user_id',
        'menu',
        'status',
        'created_by',
    ];

    protected $casts = [
        'reservation_date' => 'date',
        'reservation_time' => 'datetime',
        'number_of_guests' => 'integer',
        'dp' => 'decimal:2',
        'from_sales' => 'boolean',
    ];

    public function outlet()
    {
        return $this->belongsTo(Outlet::class, 'outlet_id', 'id_outlet');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function salesUser()
    {
        return $this->belongsTo(User::class, 'sales_user_id');
    }
} 