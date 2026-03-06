<?php

namespace App\Services\Amf\EventsService;

use App\Models\GameEvent;
use App\Services\Amf\Concerns\ValidatesSession;
use Illuminate\Support\Facades\Log;

class ExecuteService
{
    use ValidatesSession;

    private CatalogService $catalogService;

    public function __construct()
    {
        $this->catalogService = new CatalogService();
    }

    /**
     * executeService
     *
     * Handles two cases:
     *   'get'         – returns the events catalog
     *   <panel name>  – returns the gameplay data for that specific event
     *                   e.g. 'ChristmasMenu', 'ValentinesMenu', etc.
     */
    public function executeService($subService, $params)
    {
        Log::info("AMF EventsService.executeService: SubService $subService");

        if ($subService === 'get') {
            return $this->catalogService->get($params);
        }

        // Validate session before serving event data.
        if (is_array($params) && isset($params[0], $params[1])) {
            $guard = $this->guardCharacterSession((int) $params[0], $params[1]);
            if ($guard) {
                return $guard;
            }
        }

        // Look up the event by its panel name.
        $event = GameEvent::where('panel', $subService)
            ->where('active', true)
            ->first();

        if (!$event) {
            Log::error("EventsService: No active event found for panel '$subService'.");
            return ['status' => 0, 'error' => "Event '$subService' not found or inactive"];
        }

        if (empty($event->data)) {
            Log::warning("EventsService: Event '$subService' has no data configured.");
            return ['status' => 0, 'error' => "Event '$subService' has no data configured"];
        }

        Log::info("EventsService: Serving data for panel '$subService'.");

        // Merge event data fields into the response alongside status/error,
        // matching the flat response structure the client expects.
        return array_merge(['status' => 1, 'error' => 0], $event->data);
    }
}
