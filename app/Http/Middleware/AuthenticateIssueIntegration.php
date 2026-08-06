<?php

namespace App\Http\Middleware;

use App\Models\IssueIntegrationToken;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateIssueIntegration
{
    public function handle(Request $request, Closure $next): Response
    {
        $plainTextToken = $request->bearerToken();

        if (! $plainTextToken) {
            abort(401, 'A bearer token is required.');
        }

        $token = IssueIntegrationToken::query()
            ->with('user')
            ->where('token_hash', hash('sha256', $plainTextToken))
            ->whereNull('revoked_at')
            ->first();

        if (! $token || ! $token->user) {
            abort(401, 'The integration token is invalid or revoked.');
        }

        Auth::setUser($token->user);
        $request->setUserResolver(fn () => $token->user);
        $token->forceFill(['last_used_at' => now()])->save();

        return $next($request);
    }
}
