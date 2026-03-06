<?php

namespace App\Http\Middleware;

use App\Models\ClanAuthToken;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ClanAuthMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        // Accept token from POST body, query string, or Authorization: Bearer header
        $token = $request->input('token')
            ?? $request->query('token')
            ?? $request->bearerToken();

        Log::info('Clan auth attempt', [
            'path'        => $request->path(),
            'method'      => $request->method(),
            'has_token'   => !empty($token),
            'token_preview' => $token ? substr($token, 0, 8) : null,
            'all_input'   => $request->all(),
        ]);

        if (!$token) {
            Log::warning('Clan auth: no token provided', ['path' => $request->path()]);
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $authToken = ClanAuthToken::where('token', $token)
            ->where('expires_at', '>', now())
            ->first();

        if (!$authToken) {
            Log::warning('Clan auth: invalid or expired token', [
                'path'          => $request->path(),
                'token_preview' => substr($token, 0, 8),
            ]);
            return response()->json(['error' => 'Invalid or expired token'], 401);
        }

        $request->attributes->set('char_id', $authToken->character_id);
        $request->attributes->set('user_id', $authToken->user_id);

        return $next($request);
    }
}