<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubCategoryAvailability extends Model
{
    protected $table = 'sub_category_availabilities';

    protected $fillable = [
        'sub_category_id',
        'availability_type',
        'region_id',
        'outlet_id'
    ];

    public function subCategory()
    {
        return $this->belongsTo(SubCategory::class);
    }

    public function region()
    {
        return $this->belongsTo(Region::class);
    }

    public function outlet()
    {
        return $this->belongsTo(Outlet::class, 'outlet_id', 'id_outlet');
    }
}