<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RetailInvoiceImage extends Model
{
    protected $table = 'retail_invoice_images';
    
    protected $fillable = [
        'retail_item_id',
        'file_path',
    ];

    public function retailItem()
    {
        return $this->belongsTo(RetailItem::class);
    }
} 