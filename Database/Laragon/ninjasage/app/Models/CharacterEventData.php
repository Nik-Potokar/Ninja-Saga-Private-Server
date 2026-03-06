<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CharacterEventData extends Model
{
    protected $table = 'character_event_data';

    protected $fillable = [
        'character_id',
        'event_key',
        'energy',
        'battles',
        'milestones_claimed',
        'bought',
        'extra',
    ];

    protected $casts = [
        'energy'             => 'integer',
        'battles'            => 'integer',
        'milestones_claimed' => 'array',
        'bought'             => 'boolean',
        'extra'              => 'array',
    ];

    public function character()
    {
        return $this->belongsTo(Character::class);
    }

    /**
     * Fetch the event data row for a character, creating it with full energy if absent.
     */
    public static function forCharacter(int $characterId, string $eventKey, int $energyMax): self
    {
        return self::firstOrCreate(
            ['character_id' => $characterId, 'event_key' => $eventKey],
            ['energy' => $energyMax, 'battles' => 0, 'milestones_claimed' => [], 'bought' => false]
        );
    }

    public function hasMilestoneClaimed(int $index): bool
    {
        return in_array($index, $this->milestones_claimed ?? [], true);
    }

    public function claimMilestone(int $index): void
    {
        $claimed   = $this->milestones_claimed ?? [];
        $claimed[] = $index;
        $this->milestones_claimed = array_values(array_unique($claimed));
        $this->save();
    }
}
