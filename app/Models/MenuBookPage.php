<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MenuBookPage extends Model
{
    use HasFactory;

    protected $table = 'menu_book_pages';

    protected $fillable = [
        'menu_book_id',
        'image',
        'page_order',
        'status',
    ];

    protected $casts = [
        'menu_book_id' => 'integer',
        'page_order' => 'integer',
    ];

    // Relasi dengan MenuBook (belongs to)
    public function menuBook()
    {
        return $this->belongsTo(MenuBook::class, 'menu_book_id');
    }

    // Relasi dengan Items (many-to-many)
    public function items()
    {
        return $this->belongsToMany(Item::class, 'menu_book_page_items', 'page_id', 'item_id')
            ->withTimestamps();
    }

    // Relasi dengan Categories & SubCategories (many-to-many)
    public function categories()
    {
        return $this->belongsToMany(Category::class, 'menu_book_page_categories', 'page_id', 'category_id')
            ->withPivot('sub_category_id')
            ->withTimestamps();
    }
}

