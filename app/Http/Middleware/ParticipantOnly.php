<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ParticipantOnly
{
    public function handle(Request $r, Closure $next)
    {
        if (! $r->user()) {
            return redirect('/login');
        } $user = $r->user()->fresh();
        abort_unless($user && $user->eligible() && (int) $r->session()->get('access_version', -1) === (int) $user->access_version, 403, 'Akses tidak tersedia. Hubungi admin.');

        return $next($r);
    }
}
