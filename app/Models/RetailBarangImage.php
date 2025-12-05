<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RetailBarangImage extends Model
{
    protected $table = 'retail_barang_images';
    
    protected $fillable = [
        'retail_item_id',
        'file_path',
    ];

    public function retailItem()
    {
        return $this->belongsTo(RetailItem::class);
    }
} 