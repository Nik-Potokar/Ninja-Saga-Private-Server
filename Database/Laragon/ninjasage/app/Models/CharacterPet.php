<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CharacterPet extends Model
{
    use HasFactory;

    protected $fillable = [
        'character_id',
        'pet_id',
        'level',
        'xp',
        'name',
        'maturity_points',
    ];

    public function character()
    {
        return $this->belongsTo(Character::class);
    }

    public function pet()
    {
        return $this->belongsTo(Pet::class, 'pet_id', 'pet_id');
    }
}