<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Crew extends Model
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
        return $this->hasMany(CrewMember::class);
    }

    public function getDefaultBuildings(): array
    {
        // Keys match crewData fields read by Flash:
        // crewData.kushi_dango / crewData.tea_house / crewData.bath_house / crewData.training_centre
        return [
            'kushi_dango'     => 0,
            'tea_house'       => 0,
            'bath_house'      => 0,
            'training_centre' => 0,
        ];
    }
}
