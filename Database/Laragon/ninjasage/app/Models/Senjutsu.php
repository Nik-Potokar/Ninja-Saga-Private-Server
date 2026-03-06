<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Senjutsu extends Model
{
    use HasFactory;

    protected $fillable = [
        'senjutsu_id', 'name', 'description', 'effects'
    ];

    protected $casts = [
        'effects' => 'array'
    ];
}