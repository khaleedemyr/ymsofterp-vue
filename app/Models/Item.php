<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Item extends Model
{
    use HasFactory;

    protected $table = 'items';

    protected $fillable = [
        'category_id',
        'sub_category_id',
        'warehouse_division_id',
        'sku',
        'type',
        'name',
        'description',
        'specification',
        'small_unit_id',
        'medium_unit_id',
        'large_unit_id',
        'medium_conversion_qty',
        'small_conversion_qty',
        'min_stock',
        'composition_type',
        'modifier_enabled',
        'status',
        'exp',
    ];

    protected $casts = [
        'medium_conversion_qty' => 'decimal:2',
        'small_conversion_qty' => 'decimal:2',
        'min_stock' => 'integer',
        'modifier_enabled' => 'integer',
    ];

    // Relasi dengan Category
    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    // Relasi dengan SubCategory
    public function subCategory()
    {
        return $this->belongsTo(SubCategory::class, 'sub_category_id');
    }

    // Relasi dengan Unit (small)
    public function smallUnit()
    {
        return $this->belongsTo(\App\Models\Unit::class, 'small_unit_id');
    }

    // Relasi dengan Unit (medium)
    public function mediumUnit()
    {
        return $this->belongsTo(\App\Models\Unit::class, 'medium_unit_id');
    }

    // Relasi dengan Unit (large)
    public function largeUnit()
    {
        return $this->belongsTo(\App\Models\Unit::class, 'large_unit_id');
    }

    // Relasi dengan ItemPrices
    public function prices()
    {
        return $this->hasMany(ItemPrice::class, 'item_id');
    }

    // Relasi dengan ItemAvailabilities
    public function availabilities()
    {
        return $this->hasMany(ItemAvailability::class, 'item_id');
    }

    // Relasi dengan ItemImages
    public function images()
    {
        return $this->hasMany(ItemImage::class, 'item_id');
    }

    // Relasi dengan ModifierOptions melalui pivot table
    public function modifierOptions()
    {
        return $this->belongsToMany(ModifierOption::class, 'item_modifier_options', 'item_id', 'modifier_option_id');
    }

    // Relasi dengan Outlet melalui ItemAvailability
    public function outlets()
    {
        return $this->belongsToMany(Outlet::class, 'item_availabilities', 'item_id', 'outlet_id', 'id', 'id_outlet');
    }

    // Relasi dengan BOM
    public function boms()
    {
        return $this->hasMany(ItemBom::class, 'item_id');
    }

    // Relasi dengan WarehouseDivision
    public function warehouseDivision()
    {
        return $this->belongsTo(\App\Models\WarehouseDivision::class, 'warehouse_division_id');
    }

    // Relasi dengan ItemModifierOptions
    public function itemModifierOptions()
    {
        return $this->hasMany(\App\Models\ItemModifierOption::class, 'item_id', 'id');
    }

    // Relasi dengan ItemBarcode
    public function barcodes()
    {
        return $this->hasMany(\App\Models\ItemBarcode::class, 'item_id', 'id');
    }

    // Relasi dengan Supplier melalui pivot table item_supplier dan item_supplier_outlet
    public function suppliers()
    {
        return $this->belongsToMany(\App\Models\Supplier::class, 'item_supplier', 'item_id', 'supplier_id')
            ->withPivot('id')
            ->withTimestamps();
    }
} 