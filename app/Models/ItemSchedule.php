<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ItemSchedule extends Model
{
    use HasFactory;
    protected $fillable = [
        'item_id', 'arrival_day', 'notes'
    ];
    public function item() {
        return $this->belongsTo(Item::class);
    }
} 