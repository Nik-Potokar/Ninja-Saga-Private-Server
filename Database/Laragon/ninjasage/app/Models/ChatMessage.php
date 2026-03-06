<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatMessage extends Model
{
    protected $table = 'chat_messages';

    // The Node.js chat server manages this table — only created_at exists.
    public    $timestamps  = false;
    const     CREATED_AT   = 'created_at';
    const     UPDATED_AT   = null;

    protected $fillable = [
        'channel',
        'character_id',
        'character_name',
        'character_level',
        'character_rank',
        'character_premium',
        'message',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];
}
