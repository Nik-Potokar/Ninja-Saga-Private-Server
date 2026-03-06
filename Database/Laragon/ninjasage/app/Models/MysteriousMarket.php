<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MysteriousMarket extends Model
{
    use HasFactory;

    protected $fillable = [
        'active',
        'ends_at',
        'discount',
        'refresh_cost',
        'refresh_max',
        'items',
        'all_packages',
    ];

    protected $casts = [
        'active'       => 'boolean',
        'ends_at'      => 'datetime',
        'refresh_cost' => 'integer',
        'refresh_max'  => 'integer',
        'items'        => 'array',
        'all_packages' => 'array',
    ];

    public function characterStates()
    {
        return $this->hasMany(CharacterMysteriousMarket::class, 'market_id');
    }

    /**
     * Seconds remaining until the market closes (0 if already closed).
     */
    public function secondsRemaining(): int
    {
        return max(0, (int) now()->diffInSeconds($this->ends_at, false));
    }
}
