<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CharacterItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'character_id',
        'item_id',
        'quantity',
        'category', // weapon, back, set, hair, material, item
    ];

    public function character()
    {
        return $this->belongsTo(Character::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class, 'item_id', 'item_id');
    }
}