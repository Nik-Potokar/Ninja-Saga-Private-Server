<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CharacterTalent extends Model
{
    use HasFactory;

    protected $table = 'character_talents';

    protected $fillable = [
        'character_id',
        'skill_id',
        'level'
    ];

    public function character()
    {
        return $this->belongsTo(Character::class);
    }
}
