<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CharacterSkillSet extends Model
{
    protected $fillable = [
        'character_id',
        'preset_index',
        'skills',
    ];

    public function character()
    {
        return $this->belongsTo(Character::class);
    }
}