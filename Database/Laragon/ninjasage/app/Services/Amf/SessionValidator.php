<?php

namespace App\Services\Amf;

use App\Models\Character;
use App\Models\User;

class SessionValidator
{
    public static function validateCharacter(?int $charId, ?string $sessionKey): ?array
    {
        if (self::bypass()) {
            return null;
        }
        return self::validate(
            id: $charId,
            sessionKey: $sessionKey,
            finder: fn() => Character::find($charId)?->user,
            notFound: 'Character not found'
        );
    }

    public static function validateUser(?int $userId, ?string $sessionKey): ?array
    {
        if (self::bypass()) {
            return null;
        }
        return self::validate(
            id: $userId,
            sessionKey: $sessionKey,
            finder: fn() => User::find($userId),
            notFound: 'User not found'
        );
    }

    /**
     * Shared validation helper for Character/User sessions.
     */
    protected static function validate(?int $id, ?string $sessionKey, callable $finder, string $notFound): ?array
    {
        // Normalize common whitespace issues sent by some legacy clients
        $sessionKey = $sessionKey !== null ? trim($sessionKey) : null;

        if (!$id || !$sessionKey) {
            return self::err('Character id or session key is empty');
        }

        $owner = $finder();
        if (!$owner) {
            return self::err($notFound);
        }

        if (!self::matchesAnySessionKey($owner->id, (string) $owner->session_key, (string) $sessionKey)) {
            // Add lightweight diagnostics to help track down legacy client issues.
            logger()->warning('AMF session mismatch', [
                'owner_id' => $owner->id,
                'provided_len' => strlen($sessionKey),
                'stored_len' => strlen((string) $owner->session_key),
                'provided_preview' => substr($sessionKey, 0, 8),
                'stored_preview' => substr((string) $owner->session_key, 0, 8),
            ]);
            return self::err('Invalid session key');
        }

        return null;
    }

    /**
     * Accept multiple legacy representations to keep v0.54 / v0.55 clients working:
     *  - raw session key (current default)
     *  - sha256(id . sessionKey) and md5(id . sessionKey) that some mods send
     *  - base64-encoded session key (occasionally seen on older mobile builds)
     */
    protected static function matchesAnySessionKey(int $ownerId, string $storedKey, string $providedKey): bool
    {
        // Normalize (some clients uppercase the hex)
        $storedKey = trim($storedKey);
        $providedKey = trim($providedKey);

        // Fast path: exact match
        if (hash_equals($storedKey, $providedKey)) {
            return true;
        }

        // Base64 form of the raw key
        $decoded = base64_decode($providedKey, true);
        if ($decoded !== false && hash_equals($storedKey, $decoded)) {
            return true;
        }

        // Legacy hashes
        $sha256 = hash('sha256', $ownerId . $storedKey);
        if (hash_equals($sha256, $providedKey)) {
            return true;
        }

        $md5 = md5($ownerId . $storedKey);
        if (hash_equals($md5, $providedKey)) {
            return true;
        }

        return false;
    }

    protected static function err(string $message): array
    {
        return ['status' => 0, 'error' => $message, 'errorMessage' => $message];
    }

    protected static function bypass(): bool
    {
        return (bool) env('SESSION_VALIDATE_BYPASS', false);
    }
}
