<?php

namespace App\Services\Amf\SystemLoginService;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class RegistrationService
{
    /**
     * registerUser
     */
    public function registerUser($username, $email, $password, $serverString)
    {
        Log::info("AMF Register Attempt: $username ($email)");

        if (User::where('username', $username)->exists()) {
            return ['status' => 2, 'result' => 'Username already exists!'];
        }

        if (User::where('email', $email)->exists()) {
            return ['status' => 2, 'result' => 'Email already exists!'];
        }

        try {
            User::create([
                'username' => $username,
                'email' => $email,
                'password' => Hash::make($password),
                'name' => $username,
            ]);

            return ['status' => 1, 'result' => 'Registered Successfully!'];
        } catch (\Exception $e) {
            Log::error("Registration Error: " . $e->getMessage());
            return ['status' => 0, 'error' => 'Internal Server Error'];
        }
    }
}
