<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Enemy extends Model
{
    use HasFactory;

    protected $fillable = [
        'enemy_id', 'name', 'level', 'hp', 'cp', 'agility', 'attacks'
    ];

    protected $casts = [
        'attacks' => 'array'
    ];
}