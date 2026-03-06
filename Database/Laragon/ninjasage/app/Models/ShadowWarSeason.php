<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShadowWarSeason extends Model
{
    use HasFactory;

    protected $fillable = [
        'num',
        'start_at',
        'end_at',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
        'start_at' => 'datetime',
        'end_at' => 'datetime',
    ];
}
