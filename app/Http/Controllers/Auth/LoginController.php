<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use App\Models\Team;

class LoginController extends Controller
{
 public function showLoginForm(Request $request)
{
    // Nieuwe sessie + token forceren
    $request->session()->flush();
    $request->session()->invalidate();
    $request->session()->regenerate(true);
    $request->session()->regenerateToken();

    $teams = Team::all();
    return response()
        ->view('signin.signin', compact('teams'))
        ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
        ->header('Pragma', 'no-cache')
        ->header('Expires', '0');
}



    public function login(Request $request)
    {
        $credentials = $request->only('name', 'password');

        if (Auth::guard('web')->attempt($credentials)) {
            // Nieuwe sessie starten na inloggen (beveiliging tegen session fixation)
            $request->session()->regenerate();

            $user = Auth::user();

            // Redirect op basis van rol
            if ($user->role === 'admin') {
                return redirect()->route('dashboard.admin');
            } else {
                return redirect()->route('dashboard.user');
            }
        }

        return back()->withErrors([
            'name' => 'De inloggegevens zijn niet correct.',
        ]);
    }

    public function logout(Request $request)
    {
        // Uitloggen
        Auth::guard('web')->logout();

        // Sessie ongeldig maken
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Cookies verwijderen zodat browser geen "oude" sessies meer toont
        Cookie::queue(Cookie::forget('laravel_session'));
        Cookie::queue(Cookie::forget('XSRF-TOKEN'));

        // Redirect terug naar login
        return redirect()->route('login');
    }
}
