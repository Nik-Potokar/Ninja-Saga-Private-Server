<?php

namespace App\Services\Amf;

use App\Services\Amf\SystemLoginService\AccountService;
use App\Services\Amf\SystemLoginService\CharacterDataService;
use App\Services\Amf\SystemLoginService\LoginService;
use App\Services\Amf\SystemLoginService\RegistrationService;
use App\Services\Amf\SystemLoginService\VersionService;

class SystemLoginService
{
    private VersionService $versionService;
    private RegistrationService $registrationService;
    private LoginService $loginService;
    private CharacterDataService $characterDataService;
    private AccountService $accountService;

    public function __construct()
    {
        $this->versionService = new VersionService();
        $this->registrationService = new RegistrationService();
        $this->loginService = new LoginService();
        $this->characterDataService = new CharacterDataService();
        $this->accountService = new AccountService();
    }

    /**
     * checkVersion
     */
    public function checkVersion($buildNum)
    {
        return $this->versionService->checkVersion($buildNum);
    }

    /**
     * registerUser
     */
    public function registerUser($username, $email, $password, $serverString)
    {
        return $this->registrationService->registerUser($username, $email, $password, $serverString);
    }

    /**
     * loginUser
     */
    public function loginUser($username, $encryptedPassword, $char_, $bl, $bt, $char__, $item, $seed, $passLen)
    {
        return $this->loginService->loginUser($username, $encryptedPassword, $char_, $bl, $bt, $char__, $item, $seed, $passLen);
    }

    /**
     * getCharacterData
     */
    public function getCharacterData($charId, $sessionKey)
    {
        return $this->characterDataService->getCharacterData($charId, $sessionKey);
    }

    /**
     * getAllCharacters
     */
    public function getAllCharacters($uid, $sessionkey)
    {
        return $this->accountService->getAllCharacters($uid, $sessionkey);
    }
}
