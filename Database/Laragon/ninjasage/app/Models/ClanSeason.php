<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClanSeason extends Model
{
    protected $fillable = ['number', 'active', 'started_at', 'ended_at'];

    protected $casts = [
        'active'     => 'boolean',
        'started_at' => 'datetime',
        'ended_at'   => 'datetime',
    ];
}