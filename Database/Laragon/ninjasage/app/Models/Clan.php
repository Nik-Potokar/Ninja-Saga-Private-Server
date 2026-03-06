<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Clan extends Model
{
    protected $fillable = [
        'season_id', 'name', 'master_id', 'prestige', 'max_members',
        'gold', 'tokens', 'buildings', 'announcement_published', 'announcement_draft',
    ];

    protected $casts = [
        'buildings' => 'array',
    ];

    public function members(): HasMany
    {
        return $this->hasMany(ClanMember::class);
    }

    public function getDefaultBuildings(): array
    {
        // Keys must match what Flash ClanVillage.setDisplay() accesses:
        // clan_data.ramen / clan_data.hot_spring / clan_data.temple / clan_data.training_hall
        return [
            'ramen'         => 0,
            'hot_spring'    => 0,
            'temple'        => 0,
            'training_hall' => 0,
        ];
    }
}