<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CharacterMail extends Model
{
    protected $fillable = [
        'character_id',
        'title',
        'sender',
        'body',
        'type',
        'rewards',
        'claimed',
        'viewed',
    ];

    protected $casts = [
        'rewards' => 'array',
        'claimed' => 'boolean',
        'viewed'  => 'boolean',
    ];

    public function character()
    {
        return $this->belongsTo(Character::class);
    }
}
