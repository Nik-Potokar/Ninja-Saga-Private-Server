<?php

namespace App\Http\Middleware;

use App\Models\ClanAuthToken;
use Closure;
use Illuminate\Http\Request;

class ClanAuth
{
    public function handle(Request $request, Closure $next)
    {
        $authHeader = $request->header('Authorization');

        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return response()->json(['error' => 'Unauthorized', 'code' => 401], 401);
        }

        $token = substr($authHeader, 7);

        $authToken = ClanAuthToken::where('token', $token)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->first();

        if (!$authToken) {
            return response()->json(['error' => 'Unauthorized', 'code' => 401], 401);
        }

        $request->attributes->set('char_id', $authToken->character_id);
        $request->attributes->set('user_id', $authToken->user_id);

        return $next($request);
    }
}
