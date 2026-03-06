<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShadowWarSquad extends Model
{
    use HasFactory;

    protected $fillable = [
        'character_id',
        'season_id',
        'squad',
        'rank',
        'trophy',
    ];
}
