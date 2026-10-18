<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;

class AdminAuthController extends Controller
{
    public function login(Request $r)
    {
        $r->merge(['email' => strtolower(trim((string) $r->input('email')))]);
        $data = $r->validate(['email' => 'required|email|max:254', 'password' => 'required|string|max:1024']);
        $key = 'admin:'.hash('sha256', $data['email'].'|'.$r->ip());
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withErrors(['email' => 'Terlalu banyak percobaan. Coba lagi nanti.']);
        }
        RateLimiter::hit($key, 300);
        if (! Auth::attempt([...$data, 'role' => 'admin', 'active' => true])) {
            return back()->withErrors(['email' => 'Email atau password tidak sesuai.']);
        }
        RateLimiter::clear($key);
        $r->session()->regenerate();

        return redirect('/admin/leads');
    }

    public function logout(Request $r)
    {
        Auth::logout();
        $r->session()->invalidate();
        $r->session()->regenerateToken();

        return redirect('/');
    }
}
