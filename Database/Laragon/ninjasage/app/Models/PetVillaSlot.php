<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PetVillaSlot extends Model
{
    use HasFactory;

    protected $fillable = [
        'character_id',
        'slot_index',
        'status',
        'pet_instance_id',
        'training_ends_at',
        'gold_spent',
    ];

    public function character()
    {
        return $this->belongsTo(Character::class);
    }

    public function petInstance()
    {
        return $this->belongsTo(CharacterPet::class, 'pet_instance_id');
    }
}
