<?php

namespace App\Services\Amf\SystemLoginService;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class VersionService
{
    /**
     * checkVersion
     */
    public function checkVersion($buildNum)
    {
        Log::info("AMF Version Check: Build $buildNum");

        $ivSource = mt_rand(100000, 999999);
        $key = Str::random(16);
        $cdn = rtrim(env('APP_URL', 'https://ninjasage.test/'), '/') . '/';

        return [
            'status' => 1,
            'error' => 0,
            '_' => $ivSource,
            '__' => $key,
            'cdn' => $cdn,
            'pvp_socket' => env('PVP_SOCKET_URL', 'http://127.0.0.1:3000/pvp'),
            'chat_socket' => env('CHAT_SOCKET_URL', 'http://127.0.0.1:3002'),
            //'cdn' => 'https://ns-assets.ninjasage.id/static/releases/cdn/assets/',
            '_rm' => '',
        ];
    }
}
