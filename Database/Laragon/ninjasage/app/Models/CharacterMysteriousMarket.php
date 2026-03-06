<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CharacterMysteriousMarket extends Model
{
    use HasFactory;

    protected $fillable = [
        'character_id',
        'market_id',
        'refresh_count',
        'custom_items',
    ];

    protected $casts = [
        'refresh_count' => 'integer',
        'custom_items'  => 'array',
    ];

    public function character()
    {
        return $this->belongsTo(Character::class);
    }

    public function market()
    {
        return $this->belongsTo(MysteriousMarket::class, 'market_id');
    }
}
