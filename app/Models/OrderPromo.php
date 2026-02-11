<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderPromo extends Model
{
    protected $table = 'order_promos';

    protected $fillable = [
        'order_id',
        'promo_id',
        'kode_outlet',
        'status',
    ];

    public $timestamps = true;

    const UPDATED_AT = null;

    public function promo()
    {
        return $this->belongsTo(Promo::class, 'promo_id');
    }
}
