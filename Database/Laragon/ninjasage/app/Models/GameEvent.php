<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GameEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'type',
        'image_url',
        'description',
        'active',
        'data',
        'panel',
        'date',
        'icon',
        'inside',
        'sort_order',
        'starts_at',
        'ends_at',
    ];

    protected $casts = [
        'active'    => 'boolean',
        'inside'    => 'boolean',
        'data'      => 'array',
        'starts_at' => 'datetime',
        'ends_at'   => 'datetime',
    ];
}