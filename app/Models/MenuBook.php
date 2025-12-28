<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MenuBook extends Model
{
    use HasFactory;

    protected $table = 'menu_books';

    protected $fillable = [
        'name',
        'description',
        'status',
    ];

    // Relasi dengan Pages (one-to-many)
    public function pages()
    {
        return $this->hasMany(MenuBookPage::class, 'menu_book_id')->orderBy('page_order', 'asc');
    }

    // Relasi dengan Outlets (many-to-many)
    public function outlets()
    {
        return $this->belongsToMany(\App\Models\Outlet::class, 'menu_book_outlets', 'menu_book_id', 'outlet_id', 'id', 'id_outlet')
            ->withTimestamps();
    }
}

