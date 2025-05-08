<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RetailItem extends Model
{
    protected $table = 'retail_items';

    protected $fillable = [
        'retail_id',
        'nama_barang',
        'qty',
        'harga_barang',
        'subtotal',
        'created_by',
    ];

    protected $casts = [
        'qty' => 'integer',
        'harga_barang' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    public function retail(): BelongsTo
    {
        return $this->belongsTo(Retail::class);
    }

    public function invoiceImages(): HasMany
    {
        return $this->hasMany(RetailInvoiceImage::class);
    }

    public function barangImages(): HasMany
    {
        return $this->hasMany(RetailBarangImage::class);
    }
} 