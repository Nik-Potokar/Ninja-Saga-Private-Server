<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminCommand extends Model
{
    protected $fillable = [
        'name',
        'description',
        'command_type',
        'params',
        'active',
    ];

    protected $casts = [
        'params' => 'array',
        'active' => 'boolean',
    ];

    public static function commandTypes(): array
    {
        return [
        'give_all_skills'        => 'Give All Skills',
        'give_all_category'      => 'Give All Items (by Category)',
        'give_all_hairstyles'    => 'Give All Hairstyles',
        'give_all_pets'          => 'Give All Pets',

        'give_all_weapons'       => 'Give All Weapons',
        'give_all_setitems'      => 'Give All Outfit Sets',
        'give_all_materialitems' => 'Give All Materials',
        'give_all_accessoryitems'=> 'Give All Accessories',
        'give_all_backitems'     => 'Give All Back Items',
        'give_all_shadowwaritems'=> 'Give All Shadow War Items',
        'give_all_packageitems'  => 'Give All Package Items',
        'give_all_eventitems'    => 'Give All Event Items',
        'give_all_leaderboarditems'=> 'Give All Leaderboard Items',
        'give_all_essentialitems'=> 'Give All Essential Items',
        'give_all_dealitems'     => 'Give All Deal Items',
        'give_all_spendingitems' => 'Give All Spending Items',
        'give_all_crewitems'     => 'Give All Crew Items',
        'give_all_clanitems'     => 'Give All Clan Items',

        'give_all_available'     => 'Give All Available Items',

        'add_gold'               => 'Add Gold',
        'add_tokens'             => 'Add Tokens',
        'set_rank'               => 'Set Rank',
        'set_level'              => 'Set Level',
        ];
    }
}
