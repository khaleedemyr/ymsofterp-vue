<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Reservation extends Model
{
    use HasFactory;

    protected $appends = ['menu_file_url'];

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
        'payment_type_id',
        'from_sales',
        'sales_user_id',
        'menu',
        'menu_file',
        'status',
        'created_by',
    ];

    protected $casts = [
        'reservation_date' => 'date',
        'reservation_time' => 'datetime',
        'number_of_guests' => 'integer',
        'dp' => 'decimal:2',
        'from_sales' => 'boolean',
        'dp_used_at' => 'datetime',
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

    public function paymentType()
    {
        return $this->belongsTo(PaymentType::class, 'payment_type_id');
    }

    public function getMenuFileUrlAttribute()
    {
        if (empty($this->menu_file)) {
            return null;
        }
        return Storage::disk('public')->url($this->menu_file);
    }
} 