<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Game Event Panel Registry
    |--------------------------------------------------------------------------
    |
    | Maps the panel identifiers sent to the Flash client to human-readable
    | labels and groups.  Add new panels here rather than editing the Filament
    | resource.
    |
    | Structure:
    |   'Group label' => [
    |       'PanelId' => 'PanelId',
    |   ]
    |
    */
    'panels' => [
        'Panels with backend service' => [
            'PhantomKyunokiMenu' => 'PhantomKyunokiMenu',
            'MonsterHunter'        => 'MonsterHunter',
            'DragonHunt'           => 'DragonHunt',
            'DragonGacha'          => 'DragonGacha',
            'DailyGacha'           => 'DailyGacha',
            'JusticeBadge'         => 'JusticeBadge',
            'ConfrontingDeathMenu' => 'ConfrontingDeathMenu',
            'FeastOfGratitudeMenu' => 'FeastOfGratitudeMenu',
            'HalloweenMenu'        => 'HalloweenMenu',
            'ChristmasMenu'        => 'ChristmasMenu',
        ],
        'No backend service - NO SWF NOT WORKING (display only)' => [
            'SummerMenu'        => 'SummerMenu',
            'YinYangMenu'       => 'YinYangMenu',
            'IndependenceMenu'  => 'IndependenceMenu',
            'HalloweenTraining' => 'HalloweenTraining',
            'HalloweenGacha'    => 'HalloweenGacha',
            'ChristmasGacha'    => 'ChristmasGacha',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Gacha Panel Identifiers
    |--------------------------------------------------------------------------
    |
    | Panels in this list get the structured gacha pool editor instead of the
    | raw JSON textarea.
    |
    */
    'gacha_panels' => [
        'DragonGacha',
        'ChristmasGacha',
    ],

];
