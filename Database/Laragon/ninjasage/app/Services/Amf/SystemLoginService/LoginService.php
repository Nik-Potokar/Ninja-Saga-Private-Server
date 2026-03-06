<?php

namespace App\Services\Amf\SystemLoginService;

use App\Models\User;
use App\Models\ClanSeason;
use App\Models\CrewSeason;
use App\Models\ShadowWarSeason;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LoginService
{
    /**
     * loginUser
     */
    public function loginUser($username, $encryptedPassword, $char_, $bl, $bt, $char__, $item, $seed, $passLen)
    {
        Log::info("AMF Login Attempt: $username");

        $user = User::where('username', $username)->first();

        if (!$user) {
            return ['status' => 2];
        }

        $decryptedPassword = $this->decryptPassword($encryptedPassword, $char__, $char_);

        if (!$decryptedPassword || !Hash::check($decryptedPassword, $user->password)) {
            return ['status' => 2];
        }

        $events = [
            'welcome_bonus',
            'mysterious-market',
            'chunin_package',
            'special-deals',
            'monster_hunter_2023',
            'dragon_hunt_2024',
            'justice-badge2024',
            'giveaway-center',
            'leaderboard',
            'tailedbeast',
            'dailygacha',
            'dragongacha',
            'exoticpackage',
            'thanksgiving2025',
            'elementalars',
            'xmass2025',
            'valentine2026',
            'phantom_kyunoki_2026',
        ];

        $banners = [
            // [
            //     'url' => 'https://ns-assets.ninjasage.id/tmp/crew_minotaur.png',
            //     'menu' => 'Crew',
            //     'title' => 'Minotaur is Available',
            //     'action' => 'open:menu'
            // ]
        ];

        // Generate a session key that works across legacy Flash clients
        // (v0.54 / v0.55) and newer ones. Keep the raw key but also
        // pre-compute legacy hashes some builds send back instead of the
        // raw value.
        $sessionKey = bin2hex(random_bytes(16)); // 32 chars, hex-only
        $sessionHashSha256 = hash('sha256', $user->id . $sessionKey);
        $sessionHashMd5 = md5($user->id . $sessionKey);

        $user->session_key = $sessionKey;
        $user->save();

        try {
            $clanSeason = ClanSeason::where('active', true)->latest('number')->first();
            $clanSeasonNumber = $clanSeason ? (string)$clanSeason->number : '1';
        } catch (\Throwable) {
            $clanSeasonNumber = '1';
        }

        try {
            $crewSeason = CrewSeason::where('active', true)->latest('number')->first();
            $crewSeasonNumber = $crewSeason ? (string)$crewSeason->number : '1';
        } catch (\Throwable) {
            $crewSeasonNumber = '1';
        }

        try {
            $swSeason = ShadowWarSeason::where('active', true)->latest('num')->first();
            $swSeasonNumber = $swSeason ? (string)$swSeason->num : '1';
        } catch (\Throwable) {
            $swSeasonNumber = '1';
        }

        return [
            'status' => 1,
            'error' => 0,
            'uid' => $user->id,
            'sessionkey' => $sessionKey,
            'hash' => $sessionHashSha256,           // primary for current clients
            'hash_md5' => $sessionHashMd5,          // legacy helper (pre-0.55 mods)
            'sessionkey_b64' => base64_encode($sessionKey), // extra compatibility
            '__' => $char__,
            'events' => $events,
            'clan_season' => $clanSeasonNumber,
            'crew_season' => $crewSeasonNumber,
            'sw_season' => $swSeasonNumber,
            'banners' => [$banners],
            'system_time' => base64_encode(time())
        ];
    }

    private function decryptPassword($encryptedBase64, $keyString, $ivString)
    {
        try {
            $key = $keyString;
            $iv = $this->pkcs5Pad($ivString, 16);
            $encryptedData = base64_decode($encryptedBase64);
            return openssl_decrypt($encryptedData, 'aes-128-cbc', $key, OPENSSL_RAW_DATA, $iv);
        } catch (\Exception $e) {
            Log::error("Decryption Exception: " . $e->getMessage());
            return false;
        }
    }

    private function pkcs5Pad($text, $blocksize)
    {
        $pad = $blocksize - (strlen($text) % $blocksize);
        return $text . str_repeat(chr($pad), $pad);
    }
}