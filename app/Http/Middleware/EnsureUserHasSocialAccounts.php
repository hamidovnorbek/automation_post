<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasSocialAccounts
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        // Add user's social account data to the request for easy access
        $request->merge([
            'user_social_accounts' => Auth::user()->socialAccounts()->get(),
            'active_connections' => Auth::user()->getActiveConnections(),
        ]);

        return $next($request);
    }
}