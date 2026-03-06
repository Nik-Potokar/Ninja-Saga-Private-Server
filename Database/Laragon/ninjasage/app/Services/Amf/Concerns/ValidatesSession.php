<?php

namespace App\Services\Amf\Concerns;

use App\Services\Amf\SessionValidator;

trait ValidatesSession
{
    protected function guardCharacterSession(?int $charId, ?string $sessionKey): ?array
    {
        return SessionValidator::validateCharacter($charId, $sessionKey);
    }

    protected function guardUserSession(?int $userId, ?string $sessionKey): ?array
    {
        return SessionValidator::validateUser($userId, $sessionKey);
    }

    protected function guardCharacterSessionFromParams($charIdOrParams, ?string $sessionKey = null): ?array
    {
        if (is_array($charIdOrParams)) {
            $charId = isset($charIdOrParams[0]) ? (int)$charIdOrParams[0] : null;
            $sessionKey = $charIdOrParams[1] ?? $sessionKey;
        } else {
            $charId = $charIdOrParams !== null ? (int)$charIdOrParams : null;
        }

        return $this->guardCharacterSession($charId, $sessionKey);
    }
}
