<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Npc extends Model
{
    use HasFactory;

    protected $fillable = [
        'npc_id', 'name', 'level', 'rank', 'hp', 'cp', 
        'agility', 'dodge', 'critical', 'accuracy', 'purify', 
        'description', 'attacks', 'price_gold', 'price_tokens', 'premium'
    ];

    protected $casts = [
        'attacks' => 'array',
        'premium' => 'boolean'
    ];
}