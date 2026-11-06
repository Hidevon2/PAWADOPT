<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthenticateApiToken
{
    public function handle(Request $request, Closure $next)
    {
        $plainToken = $request->bearerToken();
        $token = $plainToken ? ApiToken::where('token_hash', hash('sha256', $plainToken))
            ->where('expires_at', '>', now())->first() : null;

        if (! $token) {
            return response()->json(['message' => 'Please sign in to continue.'], 401);
        }

        Auth::setUser($token->user);
        $request->setUserResolver(fn () => $token->user);
        $request->attributes->set('api_token', $token);

        return $next($request);
    }
}
