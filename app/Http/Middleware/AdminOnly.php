<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AdminOnly
{
    public function handle(Request $r, Closure $next)
    {
        if (! $r->user()) {
            return redirect('/admin/login');
        } abort_unless($r->user()->role === 'admin' && $r->user()->active, 403);

        return $next($r);
    }
}
