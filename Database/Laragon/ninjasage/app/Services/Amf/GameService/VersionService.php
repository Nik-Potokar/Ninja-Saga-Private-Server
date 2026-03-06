<?php

namespace App\Services\Amf\GameService;

class VersionService
{
    /**
     * Example method to test connectivity.
     * Called via AMF target: "Game.getVersion"
     */
    public function getVersion()
    {
        return [
            'version' => '1.0.0',
            'server' => 'Laravel Ninja Saga Revived',
            'status' => 'active'
        ];
    }
}
