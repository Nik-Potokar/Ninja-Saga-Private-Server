<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class XP extends Model
{
    protected $table = 'x_p_s';

    protected $fillable = [
        'level',
        'character_xp',
        'pet_xp',
    ];
}
