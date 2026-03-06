<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Talent extends Model
{
    use HasFactory;

    protected $table = 'talents';

    protected $fillable = [
        'talent_id', 'name', 'description', 'skills',
        'price_gold', 'price_tokens', 'is_emblem'
    ];

    protected $casts = [
        'skills' => 'array',
        'is_emblem' => 'boolean'
    ];
}