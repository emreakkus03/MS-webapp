<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class CheckSessionIntegrity
{
    public function handle($request, Closure $next)
    {
        // Controleer of er nog een sessie-ID is, maar geen geldige sessie
        if ($request->hasCookie(config('session.cookie')) && !Session::has('_token')) {
            Auth::logout();
            Session::flush();

            return redirect()->route('login')
                ->withErrors(['message' => 'Je sessie is verlopen. Log opnieuw in.']);
        }

        return $next($request);
    }
}
