<?php

namespace App\Http\Middleware;

use App\Models\CrewAuthToken;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class CrewAuthMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->input('token')
            ?? $request->query('token')
            ?? $request->bearerToken();

        Log::info('Crew auth attempt', [
            'path'          => $request->path(),
            'has_token'     => !empty($token),
            'token_preview' => $token ? substr($token, 0, 8) : null,
        ]);

        if (!$token) {
            Log::warning('Crew auth: no token provided', ['path' => $request->path()]);
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $authToken = CrewAuthToken::where('token', $token)
            ->where('expires_at', '>', now())
            ->first();

        if (!$authToken) {
            Log::warning('Crew auth: invalid or expired token', [
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
