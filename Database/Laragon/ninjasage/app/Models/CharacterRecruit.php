<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CharacterRecruit extends Model
{
    use HasFactory;

    protected $fillable = [
        'character_id',
        'recruit_id'
    ];
}