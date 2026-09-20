<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckAccountStatus
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            if (! $user->isActive()) {
                Auth::logout();

                return redirect()->route('login')->withErrors(['username' => 'Your account is no longer active.']);
            }

            if ($user->requires_password_change && ! $request->routeIs('password.change') && ! $request->routeIs('password.update') && ! $request->routeIs('logout')) {
                return redirect()->route('password.change');
            }
        }

        return $next($request);
    }
}
