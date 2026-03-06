<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClanAuthToken extends Model
{
    protected $fillable = ['user_id', 'character_id', 'token', 'expires_at'];

    protected $casts = [
        'expires_at' => 'datetime',
    ];
}
