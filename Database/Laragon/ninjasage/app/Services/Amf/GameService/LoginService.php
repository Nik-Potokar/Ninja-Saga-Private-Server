<?php

namespace App\Services\Amf\GameService;

class LoginService
{
    /**
     * Example login method.
     * Called via AMF target: "Game.login"
     */
    public function login($username, $password)
    {
        // Add your auth logic here
        return [
            'success' => true,
            'userId' => 123,
            'username' => $username,
            'token' => 'example_token_xyz'
        ];
    }
}
