<?php

namespace App\Services\Amf;

use App\Services\Amf\GameService\LoginService;
use App\Services\Amf\GameService\VersionService;

class GameService
{
    private VersionService $versionService;
    private LoginService $loginService;

    public function __construct()
    {
        $this->versionService = new VersionService();
        $this->loginService = new LoginService();
    }

    /**
     * Example method to test connectivity.
     * Called via AMF target: "Game.getVersion"
     */
    public function getVersion()
    {
        return $this->versionService->getVersion();
    }

    /**
     * Example login method.
     * Called via AMF target: "Game.login"
     */
    public function login($username, $password)
    {
        return $this->loginService->login($username, $password);
    }
}
