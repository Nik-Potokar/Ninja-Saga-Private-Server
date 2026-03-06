<?php

namespace App\Services\Amf\EventsService;

use App\Models\GameEvent;
use App\Services\Amf\Concerns\ValidatesSession;
use Illuminate\Support\Facades\Log;

class CatalogService
{
    use ValidatesSession;

    /**
     * Supported type values stored in game_events.type:
     *   seasonal  – time-limited seasonal events shown in the top banner area
     *   permanent – always-available event modes (Monster Hunter, Dragon Hunt …)
     *   feature   – utility / gacha features (Leaderboard, Daily Gacha …)
     *   package   – purchasable content packages; content array lives in data->content
     */
    private const TYPE_SEASONAL  = 'seasonal';
    private const TYPE_PERMANENT = 'permanent';
    private const TYPE_FEATURE   = 'feature';
    private const TYPE_PACKAGE   = 'package';

    public function get($params = null)
    {
        if (is_array($params) && isset($params[0], $params[1])) {
            $guard = $this->guardCharacterSession((int) $params[0], $params[1]);
            if ($guard) {
                return $guard;
            }
        }

        Log::info('AMF EventsService.get');

        $events = GameEvent::where('active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $seasonal  = [];
        $permanent = [];
        $features  = [];
        $package   = null;

        foreach ($events as $event) {
            switch ($event->type) {
                case self::TYPE_SEASONAL:
                    $seasonal[] = [
                        'name'  => $event->title,
                        'desc'  => $event->description,
                        'date'  => $event->date,
                        'img'   => $event->image_url,
                        'panel' => $event->panel,
                    ];
                    break;

                case self::TYPE_PERMANENT:
                    $permanent[] = [
                        'name'  => $event->title,
                        'icon'  => $event->icon ?? 1,
                        'panel' => $event->panel,
                    ];
                    break;

                case self::TYPE_FEATURE:
                    $entry = [
                        'name'  => $event->title,
                        'icon'  => $event->icon ?? 1,
                        'panel' => $event->panel,
                    ];
                    if ($event->inside) {
                        $entry['inside'] = true;
                    }
                    $features[] = $entry;
                    break;

                case self::TYPE_PACKAGE:
                    // Only one package block is surfaced at a time.
                    // The first active row (lowest sort_order / id) wins.
                    if ($package === null) {
                        $package = [
                            'name'    => $event->title,
                            'date'    => $event->date,
                            'content' => $event->data['content'] ?? [],
                        ];
                    }
                    break;
            }
        }

        return [
            'status' => 1,
            'error'  => 0,
            'events' => [
                'seasonal'        => $seasonal,
                'event:permanent' => $permanent,
                'features'        => $features,
                'packages'        => $package,
            ],
        ];
    }
}